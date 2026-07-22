# ACEP (PlusTok Enterprise) — WebSocket 설계

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 4 — 2차 작업 SSOT)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Real-time Platform Team  
**Audience:** Frontend Developers, Chat Server Developers, Backend Developers, DevOps, QA  

**적용 위치:** Chat Server (`www/chat-server/`) + React Client (`src/hooks/useSocket.ts`)  
**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 4, §6.5  
**REST API:** [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §12  
**실시간 동기화:** [02_실시간동기화.md](02_실시간동기화.md)  
**UI/UX:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §6.2, §7, §9  
**Frontend Hooks:** [06_FRONTEND/03_Hooks_및_상태관리.md](../06_FRONTEND/03_Hooks_및_상태관리.md) §5  
**AI 통합:** [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md) §4  

> **SSOT 안내:** 본 문서가 STEP 4 WebSocket 프로토콜·연결·이벤트의 **단일 기준 문서**이다.  
> 레거시 참고: [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md), [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| Protocol | Socket.io 4.x (Engine.IO v4) |
| Encoding | JSON UTF-8 |
| Auth | JWT in handshake `auth.token` |
| Client Events (C→S) | 5개: room:join/leave, message:send, typing:start/stop |
| Server Events (S→C) | 8개: room:joined/update, message:receive, typing:*, ai:update, read:update, error, pong |
| Timezone | ISO 8601 with offset (`+09:00`) |
| Scale Target | 1,000+ 동시 WebSocket 연결 |

본 문서는 ACEP 상담채팅 **WebSocket 전체 설계**를 정의한다. REST API([02_API설계.md](../03_SYSTEM/02_API설계.md))와 **이중 전송 패턴**으로 동작하며, Chat Server는 **relay·알림 전용**, Backend(PHP)는 **영속화·비즈니스 로직** Source of Truth이다.

---

## 1. 설계 원칙 및 ADR

### 1.1 핵심 원칙

| ID | 원칙 | 설명 |
|----|------|------|
| WS-P01 | REST = Source of Truth | 메시지·읽음·AI 결과는 MariaDB에 REST로 저장 |
| WS-P02 | WS = Real-time Notify | Chat Server는 Redis PUBLISH → Socket.io broadcast |
| WS-P03 | Chat Server No SQL | Node.js는 SQL 실행 금지 — Backend API 또는 Redis만 |
| WS-P04 | JWT Everywhere | handshake + REST 동일 secret, 동일 payload schema |
| WS-P05 | Room Scoped | 모든 이벤트는 `room:{roomId}` Socket.io room 단위 |
| WS-P06 | Backward Compatible | 신규 event 추가 시 unknown event 무시 (Client) |

### 1.2 Architecture Decision Records

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ADR-WS-001: Socket.io 4.x 선택                                          │
├─────────────────────────────────────────────────────────────────────────┤
│ Context:  React SPA + Nginx TLS + 1K concurrent connections             │
│ Decision: Socket.io 4.7.x (Engine.IO v4)                                │
│ Rationale: 자동 재연결, polling fallback, Redis adapter, TS typings       │
│ Alternatives: raw ws (재연결/폴백 직접 구현), SSE (단방향 부적합)         │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ ADR-WS-002: 메시지 저장 REST 단일 경로 (V1.0)                            │
├─────────────────────────────────────────────────────────────────────────┤
│ Context:  message:send WS vs POST /messages                             │
│ Decision: V1.0 MVP — POST /api/v1/chats/{id}/messages only              │
│ Rationale: Source of Truth 단일화, Chat Server 비즈니스 로직 배제         │
│ V1.5:     message:send optional (optimistic path, customer widget)        │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ ADR-WS-003: Backend ↔ Chat Server = Redis Pub/Sub                       │
├─────────────────────────────────────────────────────────────────────────┤
│ Context:  PHP Backend와 Node Chat Server 프로세스 분리                   │
│ Decision: acep:room:{roomId}:events + acep:events:broadcast             │
│ Rationale: 언어 독립, scale-out, Cafe24/Docker 배포 호환                  │
└─────────────────────────────────────────────────────────────────────────┘
```

### 1.3 책임 분리 매트릭스

```
┌──────────────────────┬──────────────────────┬───────────────────────────┐
│ 영역                 │ Chat Server (Node)   │ Backend (PHP 8.4)         │
├──────────────────────┼──────────────────────┼───────────────────────────┤
│ JWT 발급             │ ✗                    │ ✓ AuthService             │
│ JWT handshake 검증   │ ✓ (공유 secret)      │ ✓ REST middleware         │
│ 메시지 영속화        │ ✗                    │ ✓ MessageService → MariaDB│
│ AI Router            │ ✗                    │ ✓ AiRecommendationService │
│ room:join 권한       │ ✓ (Backend API 호출) │ ✓ ChatRoomService RBAC    │
│ message:receive      │ ✓ broadcast          │ ✓ Redis PUBLISH 트리거    │
│ typing relay         │ ✓                    │ ✗                         │
│ read:update relay    │ ✓                    │ ✓ ReadStatusService       │
│ ai:update relay      │ ✓                    │ ✓ AI worker PUBLISH       │
│ room:update relay    │ ✓                    │ ✓ status/assign 변경 시   │
│ CRM / 파일 업로드    │ ✗                    │ ✓                         │
└──────────────────────┴──────────────────────┴───────────────────────────┘
```

---

## 2. 연결 (Connection)

### 2.1 Endpoint

| Environment | URL | Port |
|-------------|-----|------|
| Production | `wss://{host}/socket.io/?EIO=4&transport=websocket` | 443 (Nginx) |
| Development | `ws://localhost:3001/socket.io/` | 3001 |
| Docker internal | `http://acep-chat-server:3001/socket.io/` | 3001 |

### 2.2 Client Connection Options

```typescript
// src/services/socket.client.ts
import { io, Socket } from 'socket.io-client';
import type { ClientToServerEvents, ServerToClientEvents } from '../types/socket-events';

type AppSocket = Socket<ServerToClientEvents, ClientToServerEvents>;

let socketInstance: AppSocket | null = null;

export function connectSocket(accessToken: string): AppSocket {
  if (socketInstance?.connected) return socketInstance;

  socketInstance = io(import.meta.env.VITE_WS_URL ?? window.location.origin, {
    path: '/socket.io',
    transports: ['websocket', 'polling'],
    auth: { token: accessToken },
    reconnection: true,
    reconnectionAttempts: Infinity,
    reconnectionDelay: 1000,
    reconnectionDelayMax: 30000,
    randomizationFactor: 0.5,
    timeout: 20000,
  }) as AppSocket;

  return socketInstance;
}

export function getSocket(): AppSocket | null {
  return socketInstance;
}

export function disconnectSocket(): void {
  socketInstance?.disconnect();
  socketInstance = null;
}
```

### 2.3 Connection Lifecycle State Machine

```
                    ┌─────────────┐
                    │ disconnected│
                    └──────┬──────┘
                           │ io.connect({ auth: { token } })
                           ▼
                    ┌─────────────┐
         connect_error│ connecting  │
              ┌───────┤             ├─────── connect
              │       └─────────────┘
              ▼              │
       ┌─────────────┐       │
       │ auth_failed │       ▼
       └─────────────┘  ┌─────────────┐
                        │  connected  │  (JWT ok, no active room)
                        └──────┬──────┘
                               │ room:join
                               ▼
                        ┌─────────────┐
                        │   in_room   │  (joined room:{roomId})
                        └──────┬──────┘
                               │ room:leave / disconnect / room switch
                               ▼
                        ┌─────────────┐
                        │  connected  │
                        └─────────────┘
```

### 2.4 Handshake Sequence Diagram

```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  React   │     │  Nginx   │     │   Chat   │     │ Backend  │
│  Client  │     │  (TLS)   │     │  Server  │     │  (PHP)   │
└────┬─────┘     └────┬─────┘     └────┬─────┘     └────┬─────┘
     │ POST /auth/login                │                │
     │─────────────────────────────────────────────────>│
     │<──────────────── accessToken ────────────────────│
     │                                   │                │
     │ io.connect({ auth: { token }})    │                │
     │──────────────────────────────────>│                │
     │         Upgrade: websocket        │ jwt.verify()   │
     │<──────────────── connect OK ──────│                │
     │                                   │                │
     │ emit room:join { roomId }         │                │
     │──────────────────────────────────>│ GET /internal/ │
     │                                   │──access───────>│
     │<──────────── room:joined ─────────│<─── allowed ───│
     │                                   │                │
     │  ... real-time events ...         │                │
```

### 2.5 connect_error Codes

| Error message | Cause | Client action |
|---------------|-------|---------------|
| `UNAUTHORIZED` | JWT invalid/expired/missing | POST `/auth/refresh` → reconnect |
| `FORBIDDEN` | role blocked / account locked | logout → login redirect |
| `TRANSPORT_ERROR` | network / proxy failure | exponential backoff retry |
| `TIMEOUT` | handshake timeout 20s | retry with backoff |

```typescript
socket.on('connect_error', async (err: Error) => {
  if (err.message === 'UNAUTHORIZED') {
    try {
      const newToken = await refreshAccessToken();
      socket.auth = { token: newToken };
      socket.connect();
    } catch {
      window.location.href = '/login';
    }
  }
});
```

---

## 3. JWT 인증 (Authentication)

### 3.1 Token 전달 규칙

| 항목 | 규칙 |
|------|------|
| 위치 | Socket.io v4 `auth.token` (handshake) |
| 형식 | JWT raw string (**Bearer prefix 없음**) |
| Secret | `JWT_SECRET` env — Backend와 **동일** |
| Payload | `{ sub, role, name, iat, exp }` |
| Access TTL | 24h (MASTER §9.1) |
| Refresh | HttpOnly Cookie → POST `/api/v1/auth/refresh` |

### 3.2 JWT Payload Schema

```typescript
// types/jwt-payload.ts
export interface JwtPayload {
  sub: string;           // userId (agent UUID or customer UUID)
  role: 'agent' | 'admin' | 'operator' | 'customer';
  name?: string;
  iat: number;
  exp: number;
}
```

### 3.3 Server Auth Middleware

```typescript
// chat-server/src/middleware/auth.middleware.ts
import { Socket } from 'socket.io';
import jwt from 'jsonwebtoken';
import type { JwtPayload } from '../types/jwt-payload';

export interface AuthenticatedSocket extends Socket {
  data: {
    userId: string;
    role: JwtPayload['role'];
    name: string;
    activeRoomId?: string;
  };
}

export function authMiddleware(
  socket: AuthenticatedSocket,
  next: (err?: Error) => void
): void {
  const token = socket.handshake.auth?.token as string | undefined;
  if (!token) return next(new Error('UNAUTHORIZED'));

  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET!) as JwtPayload;
    socket.data = {
      userId: payload.sub,
      role: payload.role,
      name: payload.name ?? '',
    };
    next();
  } catch {
    next(new Error('UNAUTHORIZED'));
  }
}
```

### 3.4 Mid-Session Token Refresh

| 시나리오 | 동작 |
|----------|------|
| Access token 만료 (24h) | `connect_error UNAUTHORIZED` → refresh → reconnect |
| Refresh token 만료 (7d) | refresh 실패 → login redirect |
| Multi-tab | 각 tab 독립 socket — 동일 userId, 동일 events 수신 |
| Logout | `disconnectSocket()` + queryClient.clear() |

### 3.5 Internal Backend RBAC (room:join)

JWT만으로 room 접근을 허용하지 **않는다**. `room:join` 시 Backend internal API:

```
GET {BACKEND_INTERNAL_URL}/internal/v1/chats/{roomId}/access
Headers:
  X-Internal-Secret: {INTERNAL_API_SECRET}
  X-User-Id: {socket.data.userId}
  X-User-Role: {socket.data.role}
Response 200:
  { "success": true, "data": { "allowed": true } }
Response 403:
  { "success": false, "data": { "allowed": false } }
```

---

## 4. Room Lifecycle

### 4.1 Socket.io Room Naming

| Room Key | 형식 | 용도 | V1.0 |
|----------|------|------|:----:|
| 상담방 | `room:{roomId}` | message/typing/ai/read broadcast | ✓ |
| Agent presence | `agent:{agentId}` | 개인 알림 | V1.5 |
| Admin monitor | `admin:monitor` | 전체 모니터링 | V1.5 |

### 4.2 room:join

**Direction:** Client → Server

**Payload:**

```json
{
  "roomId": "room-uuid-1"
}
```

| Field | Type | Required | Description |
|-------|------|:--------:|-------------|
| roomId | string (UUID) | Y | chat_rooms.id |

**Success Response:** `room:joined`

```json
{
  "roomId": "room-uuid-1",
  "timestamp": "2026-07-21T14:30:00+09:00"
}
```

**Errors:** `error` event — `FORBIDDEN`, `ROOM_NOT_FOUND`, `VALIDATION_ERROR`

**Server Handler Logic:**

1. Validate `roomId` present
2. Call `backendClient.assertRoomAccess(roomId, userId, role)`
3. Leave previous active room (Agent UI single-room model)
4. `socket.join('room:' + roomId)`
5. Set `socket.data.activeRoomId = roomId`
6. Emit `room:joined`

### 4.3 room:leave

**Direction:** Client → Server

```json
{ "roomId": "room-uuid-1" }
```

- Server: `socket.leave('room:{roomId}')`
- No ack required (V1.0)
- Trigger: room switch, component unmount, logout

### 4.4 room:update (Server → Client)

**Trigger:** Backend — status change, agent assign, unreadCount, contractProbability

```json
{
  "roomId": "room-uuid-1",
  "status": "active",
  "agentId": "agent-uuid-1",
  "unreadCount": 0,
  "contractProbability": 87,
  "updatedAt": "2026-07-21T14:35:00+09:00"
}
```

**Broadcast:** `acep:events:broadcast` channel → `io.emit('room:update')` (global ChatList)

**UI:** `ChatList` item refresh, `StatusBadge` update, unread badge

### 4.5 Room Business Rules

| Rule ID | 설명 |
|---------|------|
| BR-ROOM-001 | Agent는 ChatList room 선택 시 `room:join` |
| BR-ROOM-002 | room 전환 시 이전 room 자동 `leave` |
| BR-ROOM-003 | disconnect 시 모든 room에서 제거 (Socket.io default) |
| BR-ROOM-004 | closed room join → Backend 403 → `error FORBIDDEN` |
| BR-ROOM-005 | Admin은 모든 active room join 가능 |
| BR-ROOM-006 | Customer는 본인 room만 join |

---

## 5. Client → Server Events

### 5.1 Event Summary

| Event | Payload Schema | Trigger (UI) | V1.0 |
|-------|----------------|--------------|:----:|
| `room:join` | `{ roomId }` | ChatList room select | ✓ |
| `room:leave` | `{ roomId }` | room switch / unmount | ✓ |
| `message:send` | see §5.2 | InputField send (optional) | optional |
| `typing:start` | `{ roomId }` | InputField onInput | ✓ |
| `typing:stop` | `{ roomId }` | 3s idle / send / blur | ✓ |

### 5.2 message:send (Optional — V1.0 REST primary)

```json
{
  "roomId": "room-uuid-1",
  "content": "안녕하세요.",
  "tempId": "temp-client-id-123",
  "attachmentId": null
}
```

| Field | Type | Required | Notes |
|-------|------|:--------:|-------|
| roomId | string | Y | chat_rooms.id |
| content | string | Y* | *or attachmentId |
| tempId | string | N | optimistic UI correlation |
| attachmentId | string | N | pre-uploaded file UUID |

> **V1.0:** Client는 `POST /api/v1/chats/{id}/messages` 사용. `message:send`는 V1.5 optimistic path.

**Server (if enabled):**

```typescript
socket.on('message:send', async (payload) => {
  const result = await backendClient.postMessage(payload.roomId, {
    content: payload.content,
    tempId: payload.tempId,
    senderType: socket.data.role === 'customer' ? 'customer' : 'agent',
    senderId: socket.data.userId,
  });
  if (!result.ok) {
    socket.emit('error', {
      code: 'MSG_SEND_FAILED',
      message: '메시지 전송에 실패했습니다.',
      tempId: payload.tempId,
    });
  }
});
```

### 5.3 typing:start

```json
{ "roomId": "room-uuid-1" }
```

Server enriches and relays to peers (sender excluded):

```json
{
  "roomId": "room-uuid-1",
  "userId": "cust-1",
  "userName": "홍길동",
  "userType": "customer"
}
```

**UI Rules:** BR-TYPE-001~004 — [01_상담채팅화면 §7.2](../02_UIUX/01_상담채팅화면.fig.md)

### 5.4 typing:stop

```json
{ "roomId": "room-uuid-1" }
```

Relay:

```json
{ "roomId": "room-uuid-1", "userId": "cust-1" }
```

**Auto-stop triggers:** 3s idle, message send, input blur, disconnect

---

## 6. Server → Client Events

### 6.1 Event Summary

| Event | Source | UI Component | Recoverable |
|-------|--------|--------------|:-----------:|
| `room:joined` | Chat Server | internal state | N/A |
| `message:receive` | Backend Redis | MessageBubble | ✓ REST |
| `typing:start` | Chat Server relay | TypingIndicator | ✗ ephemeral |
| `typing:stop` | Chat Server relay | TypingIndicator | ✗ ephemeral |
| `ai:update` | Backend Redis | AIPanelCard | ✓ REST |
| `read:update` | Backend Redis | MessageBubble ✓✓ | ✓ REST |
| `room:update` | Backend Redis | ChatList | ✓ REST |
| `error` | Chat Server | toast | — |
| `pong` | Chat Server | health (optional) | — |

### 6.2 message:receive

```json
{
  "messageId": "msg-uuid-new",
  "roomId": "room-uuid-1",
  "content": "설치비는 무료입니다.",
  "senderType": "agent",
  "senderId": "agent-1",
  "attachmentUrl": null,
  "attachmentType": null,
  "timestamp": "2026-07-21T14:32:00+09:00",
  "tempId": "temp-client-id-123"
}
```

| Field | Type | Description |
|-------|------|-------------|
| messageId | string | chat_messages.id |
| senderType | enum | customer, agent, system |
| tempId | string? | optimistic replace key |

**Client logic (useSocket):**

```typescript
socket.on('message:receive', (msg) => {
  queryClient.setQueryData(queryKeys.messages(msg.roomId), (old = []) => {
    if (msg.tempId) {
      const idx = old.findIndex((m) => m.tempId === msg.tempId);
      if (idx >= 0) {
        const next = [...old];
        next[idx] = mapWsMessageToChatMessage(msg);
        return next;
      }
    }
    if (old.some((m) => m.id === msg.messageId)) return old;
    return [...old, mapWsMessageToChatMessage(msg)];
  });
});
```

### 6.3 ai:update

```json
{
  "roomId": "room-uuid-1",
  "recommendationId": "rec-uuid-1",
  "status": "completed",
  "contractProbability": 87,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

| status | Client action |
|--------|---------------|
| pending | AIPanelCard skeleton |
| processing | "분석 중..." spinner |
| completed | invalidate → GET `/api/v1/ai/recommendations/{roomId}` |
| failed | error state + "다시 시도" button |

**AI Pipeline:** [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md) §2, §4

### 6.4 read:update

```json
{
  "roomId": "room-uuid-1",
  "messageId": "msg-uuid-1",
  "readerType": "agent",
  "readAt": "2026-07-21T14:36:00+09:00"
}
```

**UI:** MessageBubble read indicator → ✓✓ (blue checkmarks)

### 6.5 error

```json
{
  "code": "FORBIDDEN",
  "message": "상담방 접근 권한이 없습니다.",
  "tempId": "temp-client-id-123",
  "details": {}
}
```

| code | HTTP equiv | UI |
|------|------------|-----|
| VALIDATION_ERROR | 400 | toast |
| UNAUTHORIZED | 401 | login redirect |
| FORBIDDEN | 403 | toast + room leave |
| ROOM_NOT_FOUND | 404 | toast |
| MSG_SEND_FAILED | 500 | optimistic rollback |
| INTERNAL_ERROR | 500 | toast + retry |

---

## 7. REST ↔ WebSocket 이중 전송 패턴

### 7.1 Message Send (V1.0 권장)

```
Agent: ActionButton(send)
    │
    ├─ POST /api/v1/chats/{id}/messages     ← Source of Truth
    │       └─ 201 { messageId }
    │
    └─ (optional V1.5) socket.emit message:send  ← optimistic only

Backend:
    INSERT chat_messages
    INSERT chat_read_status (delivered)
    PUBLISH Redis message:receive

Chat Server:
    SUBSCRIBE acep:room:{id}:events
    io.to('room:{id}').emit('message:receive', payload)

All Clients:
    MessageBubble append / optimistic replace
```

### 7.2 AI Update Flow

```
Customer message → Backend INSERT
    ↓
AiRecommendationService: status=pending
    ↓
Async worker: ai_call() → Failover chain
    ↓
UPDATE ai_recommendations status=completed
PUBLISH Redis ai:update
    ↓
Chat Server → Agent Client ai:update
    ↓
GET /api/v1/ai/recommendations/{roomId}
    ↓
AIPanelCard + RecommendationCard ×3
```

### 7.3 Read Receipt Flow

```
Agent: room focus / scroll visible
    PUT /api/v1/chats/{id}/read { messageIds, readerType: "agent" }
    ↓
Backend: UPDATE chat_read_status.read_at
PUBLISH Redis read:update
    ↓
Peer Client: MessageBubble ✓✓
```

---

## 8. Chat Server 구현 (Node.js)

### 8.1 폴더 구조

```
www/chat-server/
├── package.json
├── tsconfig.json
├── Dockerfile
├── .env.example
└── src/
    ├── server.ts
    ├── app.config.ts
    ├── types/
    │   ├── socket-events.ts
    │   ├── jwt-payload.ts
    │   └── redis-events.ts
    ├── middleware/
    │   ├── auth.middleware.ts
    │   ├── rateLimit.middleware.ts
    │   └── error.middleware.ts
    ├── handlers/
    │   ├── connection.handler.ts
    │   ├── room.handler.ts
    │   ├── message.handler.ts
    │   ├── typing.handler.ts
    │   └── index.ts
    ├── services/
    │   ├── redis.pubsub.ts
    │   ├── backend.client.ts
    │   ├── room.session.ts
    │   └── logger.service.ts
    └── health/
        └── health.controller.ts
```

### 8.2 server.ts Bootstrap

```typescript
import http from 'http';
import { Server } from 'socket.io';
import { authMiddleware } from './middleware/auth.middleware';
import { registerRoomHandlers } from './handlers/room.handler';
import { registerTypingHandlers } from './handlers/typing.handler';
import { startRedisSubscriber } from './services/redis.pubsub';

const PORT = Number(process.env.CHAT_SERVER_PORT ?? 3001);
const httpServer = http.createServer((req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'healthy', uptimeSec: process.uptime() }));
    return;
  }
  res.writeHead(404);
  res.end();
});

const io = new Server(httpServer, {
  path: '/socket.io',
  pingInterval: 25000,
  pingTimeout: 20000,
  cors: {
    origin: (process.env.CORS_ALLOWED_ORIGINS ?? '').split(',').filter(Boolean),
    credentials: true,
  },
});

io.use(authMiddleware);
registerRoomHandlers(io);
registerTypingHandlers(io);
startRedisSubscriber(io);

httpServer.listen(PORT, () => {
  console.log(`ACEP Chat Server listening on ${PORT}`);
});
```

### 8.3 Redis Pub/Sub Bridge

| Channel | Publisher | Subscriber | Events |
|---------|-----------|------------|--------|
| `acep:room:{roomId}:events` | Backend PHP | Chat Server (psubscribe) | message:receive, ai:update, read:update |
| `acep:events:broadcast` | Backend PHP | Chat Server (subscribe) | room:update |

**Envelope:**

```json
{
  "event": "message:receive",
  "roomId": "room-uuid-1",
  "payload": { "...event-specific..." },
  "timestamp": "2026-07-21T14:32:00+09:00",
  "source": "backend",
  "traceId": "req-uuid-optional"
}
```

**Subscriber:**

```typescript
sub.on('pmessage', (_pattern, channel, message) => {
  const envelope = JSON.parse(message);
  io.to(`room:${envelope.roomId}`).emit(envelope.event, envelope.payload);
});
```

### 8.4 Multi-Instance: Redis Adapter

```typescript
import { createAdapter } from '@socket.io/redis-adapter';
import { createClient } from 'redis';

const pubClient = createClient({ url: process.env.REDIS_URL });
const subClient = pubClient.duplicate();
await Promise.all([pubClient.connect(), subClient.connect()]);
io.adapter(createAdapter(pubClient, subClient));
```

| Connections | chat-server replicas | Nginx |
|-------------|---------------------|-------|
| ≤250 | 1 | single |
| ≤500 | 2 | ip_hash |
| ≤1,000 | 4 | ip_hash + LB |

---

## 9. Nginx WSS 라우팅

### 9.1 Upstream & Location

```nginx
upstream acep_chat {
    ip_hash;
    server acep-chat-server-1:3001;
    server acep-chat-server-2:3001;
}

limit_req_zone $binary_remote_addr zone=ws_conn:10m rate=10r/s;

server {
    listen 443 ssl http2;
    server_name chat.example.com;

    ssl_certificate     /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    add_header Strict-Transport-Security "max-age=31536000" always;

    location /socket.io/ {
        limit_req zone=ws_conn burst=20 nodelay;
        proxy_pass http://acep_chat;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }

    location /api/v1/ {
        proxy_pass http://acep_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Request-Id $request_id;
    }

    location / {
        root /var/www/acep/static;
        try_files $uri $uri/ /index.html;
    }
}
```

### 9.2 TLS / WSS 규칙

| Rule | Value |
|------|-------|
| Production | `wss://` only — plain `ws://` 금지 |
| TLS | 1.3 termination at Nginx |
| Sticky | `ip_hash` 권장 (Redis adapter 있으면 optional) |
| Timeout | `proxy_read_timeout 86400` (24h) |

---

## 10. React Client 책임

### 10.1 useSocket Hook

**파일:** `src/hooks/useSocket.ts`  
**역할:** connect, reconnect, event subscriptions, room switch, cleanup

| Responsibility | Implementation |
|----------------|----------------|
| Connect on mount | `connectSocket(token)` |
| Join active room | `room:join` on connect + room change |
| Leave on unmount | `room:leave` cleanup |
| Message cache update | `queryClient.setQueryData` on message:receive |
| AI invalidation | `invalidateQueries` on ai:update completed |
| Read status | `setQueryData` on read:update |
| ChatList refresh | `invalidateQueries` on room:update |
| Auth refresh | connect_error UNAUTHORIZED handler |

### 10.2 UI Component ↔ Event Mapping

| UI Component | Inbound WS | Outbound WS | REST |
|--------------|------------|-------------|------|
| ChatList | room:update | room:join | GET /chats/rooms |
| MessageBubble | message:receive, read:update | — | GET/POST messages |
| InputField | — | typing:*, (message:send) | POST messages |
| TypingIndicator | typing:start/stop | typing:* | — |
| AIPanelCard | ai:update | — | GET ai/recommendations |
| RecommendationCard | ai:update | — | GET ai/recommendations |
| ConnectionBanner | connect/disconnect | — | — |
| FileUpload | message:receive | — | POST files/upload |

---

## 11. 에러 처리 및 재연결

### 11.1 Reconnection Protocol

**Client Reconnect Checklist:**

1. Refresh JWT if expired (`POST /auth/refresh`)
2. `socket.connect()` with new token
3. `room:join` active roomId
4. `GET /chats/{id}/messages?before={lastKnownTimestamp}` — gap fill
5. `GET /ai/recommendations/{id}` — AI state sync
6. `GET /chats/rooms` — ChatList sync

### 11.2 Backoff Schedule

| Attempt | Delay |
|---------|-------|
| 1 | 1s |
| 2 | 2s |
| 3 | 4s |
| 4 | 8s |
| 5+ | min(2^n, 30s) |

### 11.3 Missed Events Recovery

| Event | Recovery Method |
|-------|-----------------|
| message:receive | REST messages cursor (`?before=`) |
| ai:update | GET ai/recommendations |
| read:update | GET messages (readStatus field) |
| room:update | GET /chats/rooms |
| typing:* | not recoverable (ephemeral) |

### 11.4 UI Exception Handling

| 상황 | UI 표시 | Action |
|------|---------|--------|
| WS 끊김 | Header 배너 "재연결 중..." | auto backoff |
| WS 30s fail | "연결 끊김. [새로고침]" | manual refresh |
| UNAUTHORIZED | login redirect | re-auth |
| MSG_SEND_FAILED | MessageBubble ⚠️ retry | manual retry |

---

## 12. TypeScript Type Definitions

```typescript
// src/types/socket-events.ts

export interface RoomJoinPayload {
  roomId: string;
}

export interface RoomUpdatePayload {
  roomId: string;
  status?: 'new' | 'active' | 'closed';
  agentId?: string | null;
  unreadCount?: number;
  contractProbability?: number | null;
  updatedAt: string;
}

export interface MessageSendPayload {
  roomId: string;
  content?: string;
  tempId?: string;
  attachmentId?: string | null;
}

export interface MessageReceivePayload {
  messageId: string;
  roomId: string;
  content: string;
  senderType: 'customer' | 'agent' | 'system';
  senderId: string;
  attachmentUrl?: string | null;
  attachmentType?: string | null;
  timestamp: string;
  tempId?: string;
}

export interface AiUpdatePayload {
  roomId: string;
  recommendationId: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  contractProbability?: number | null;
  timestamp: string;
}

export interface ReadUpdatePayload {
  roomId: string;
  messageId: string;
  readerType: 'customer' | 'agent';
  readAt: string;
}

export interface TypingPayload {
  roomId: string;
  userId: string;
  userName?: string;
  userType?: 'customer' | 'agent';
}

export interface SocketErrorPayload {
  code: string;
  message: string;
  tempId?: string;
  details?: Record<string, unknown>;
}

export interface ClientToServerEvents {
  'room:join': (payload: RoomJoinPayload) => void;
  'room:leave': (payload: RoomJoinPayload) => void;
  'message:send': (payload: MessageSendPayload) => void;
  'typing:start': (payload: { roomId: string }) => void;
  'typing:stop': (payload: { roomId: string }) => void;
}

export interface ServerToClientEvents {
  'room:joined': (payload: RoomJoinPayload & { timestamp: string }) => void;
  'message:receive': (payload: MessageReceivePayload) => void;
  'typing:start': (payload: TypingPayload) => void;
  'typing:stop': (payload: Pick<TypingPayload, 'roomId' | 'userId'>) => void;
  'ai:update': (payload: AiUpdatePayload) => void;
  'read:update': (payload: ReadUpdatePayload) => void;
  'room:update': (payload: RoomUpdatePayload) => void;
  error: (payload: SocketErrorPayload) => void;
  pong: (payload: { ts: number }) => void;
}
```

---

## 13. Sequence Diagrams

### 13.1 New Message (Agent → Customer)

```
Agent UI          REST API         Backend          Redis         Chat Server       Customer UI
    │                 │                │               │                │                │
    │ POST messages   │                │               │                │                │
    │────────────────>│                │               │                │                │
    │                 │ INSERT msg     │               │                │                │
    │                 │───────────────>│               │                │                │
    │                 │                │ PUBLISH       │                │                │
    │                 │                │──────────────>│                │                │
    │                 │                │               │ SUBSCRIBE      │                │
    │                 │                │               │───────────────>│                │
    │ 201 messageId   │                │               │                │ message:receive│
    │<────────────────│                │               │                │───────────────>│
    │ message:receive │                │               │                │                │
    │<─────────────────────────────────────────────────────────────────│                │
```

### 13.2 Typing Indicator

```
Agent A           Chat Server           Agent B (peer)
    │                    │                    │
    │ typing:start       │                    │
    │───────────────────>│                    │
    │                    │ typing:start       │
    │                    │───────────────────>│
    │                    │                    │ TypingIndicator show
    │ (3s idle)          │                    │
    │ typing:stop        │                    │
    │───────────────────>│                    │
    │                    │ typing:stop        │
    │                    │───────────────────>│
```

### 13.3 AI Update

```
Customer         Backend Worker        Redis          Chat Server        Agent UI
    │                  │                  │                 │                │
    │ POST message     │                  │                 │                │
    │─────────────────>│                  │                 │                │
    │                  │ ai_call()        │                 │                │
    │                  │ PUBLISH ai:update│                 │                │
    │                  │─────────────────>│                 │                │
    │                  │                  │ SUBSCRIBE       │                │
    │                  │                  │────────────────>│                │
    │                  │                  │                 │ ai:update      │
    │                  │                  │                 │───────────────>│
    │                  │                  │                 │                │ GET ai/rec
```

### 13.4 Read Receipt

```
Agent UI           REST API          Backend           Redis         Chat Server      Customer UI
    │                  │                 │                │                │                │
    │ PUT /read        │                 │                │                │                │
    │─────────────────>│                 │                │                │                │
    │                  │ UPDATE read_at  │                │                │                │
    │                  │────────────────>│                │                │                │
    │                  │                 │ PUBLISH        │                │                │
    │                  │                 │───────────────>│                │                │
    │                  │                 │                │ read:update    │                │
    │                  │                 │                │───────────────>│                │
    │                  │                 │                │                │ read:update    │
    │                  │                 │                │                │───────────────>│
```

### 13.5 Reconnect Gap Fill

```
Client (disconnect 10s)                    Backend              Chat Server
    │                                         │                      │
    │ ─── network loss ───                    │                      │
    │                                         │                      │
    │ socket.connect() + room:join            │                      │
    │──────────────────────────────────────────────────────────────>│
    │<──────────────────── room:joined ─────────────────────────────│
    │                                         │                      │
    │ GET /messages?before=lastTimestamp      │                      │
    │────────────────────────────────────────>│                      │
    │<─────────────── missed messages ────────│                      │
    │ merge into React Query cache            │                      │
```

---

## 14. 환경 변수

| Variable | Required | Default | Description |
|----------|:--------:|---------|-------------|
| `CHAT_SERVER_PORT` | Y | 3001 | HTTP + Socket.io port |
| `JWT_SECRET` | Y | — | Backend와 동일 |
| `REDIS_HOST` | Y | localhost | Pub/Sub + adapter |
| `REDIS_PORT` | N | 6379 | |
| `REDIS_PASSWORD` | N | — | Production 권장 |
| `REDIS_PREFIX` | N | acep: | Channel prefix |
| `REDIS_URL` | N | — | adapter full URL |
| `BACKEND_INTERNAL_URL` | Y | — | room access API |
| `INTERNAL_API_SECRET` | Y | — | Backend internal auth |
| `CORS_ALLOWED_ORIGINS` | Y | — | comma-separated |
| `LOG_LEVEL` | N | info | pino level |

**Client (.env):**

| Variable | Example |
|----------|---------|
| `VITE_WS_URL` | `https://chat.example.com` |
| `VITE_API_URL` | `https://chat.example.com/api/v1` |

---

## 15. QA Test Matrix

| ID | Test | Steps | Expected |
|----|------|-------|----------|
| TC-WS-001 | Auth connect | valid JWT handshake | connected |
| TC-WS-002 | Auth fail | expired JWT | connect_error UNAUTHORIZED |
| TC-WS-003 | Join room | room:join authorized | room:joined |
| TC-WS-004 | Join forbidden | wrong agent room | error FORBIDDEN |
| TC-WS-005 | Receive message | Backend PUBLISH | message:receive all peers |
| TC-WS-006 | Typing | typing:start | peer TypingIndicator |
| TC-WS-007 | AI update | worker complete | ai:update status=completed |
| TC-WS-008 | Read update | PUT read | read:update peer ✓✓ |
| TC-WS-009 | Reconnect | disconnect 5s | gap fill via REST |
| TC-WS-010 | Multi-tab | 2 sockets same user | both receive events |
| TC-WS-011 | Nginx WSS | wss:// production | TLS handshake OK |
| TC-WS-012 | Scale | 1000 concurrent | p99 < 100ms, no OOM |

---

## 16. Versioning

| Protocol Version | Socket.io | Breaking Changes |
|------------------|-----------|------------------|
| 1.0 (V1.0 MVP) | 4.7.x | initial release |
| 1.1 (V1.5) | 4.x | message:send standard path |
| 2.0 (V2.0) | 4.x | namespace `/agent`, `/customer` |

Event 추가는 **backward compatible** — Client는 unknown events 무시.

---

## 부록 A. Event Quick Reference Card

```
CONNECT  wss://host/socket.io  auth: { token: JWT }

C→S  room:join        { roomId }
C→S  room:leave       { roomId }
C→S  message:send     { roomId, content, tempId? }     [optional V1.0]
C→S  typing:start     { roomId }
C→S  typing:stop      { roomId }

S→C  room:joined      { roomId, timestamp }
S→C  message:receive  { messageId, roomId, content, senderType, tempId?, ... }
S→C  typing:start     { roomId, userId, userName, userType }
S→C  typing:stop      { roomId, userId }
S→C  ai:update        { roomId, recommendationId, status, contractProbability? }
S→C  read:update      { roomId, messageId, readerType, readAt }
S→C  room:update      { roomId, status, unreadCount?, contractProbability?, ... }
S→C  error            { code, message, tempId? }
S→C  pong             { ts: number }
```

## 부록 B. 관련 문서

| 문서 | 용도 |
|------|------|
| [02_실시간동기화.md](02_실시간동기화.md) | 동기화·optimistic UI·read receipts |
| [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md) | 레거시 — Chat Server 상세 구현 |
| [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md) | 레거시 — 프로토콜 초안 |
| [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) | REST API §12 |
| [06_FRONTEND/03_Hooks_및_상태관리.md](../06_FRONTEND/03_Hooks_및_상태관리.md) | useSocket 구현 |
| [_CHAT_INDEX.md](_CHAT_INDEX.md) | Chat 도메인 인덱스 |

## 부록 C. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 4 2차 — WebSocket 설계 SSOT (01_WebSocket설계.md) |

---

**문서 끝 — Frontend·Chat Server 구현 시 본 문서와 `src/types/socket-events.ts`를 SSOT로 사용한다.**
