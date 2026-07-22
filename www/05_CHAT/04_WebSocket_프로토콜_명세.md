# ACEP (PlusTok Enterprise) — WebSocket 프로토콜 명세

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 4 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Real-time Platform Team  
**Audience:** Frontend, Chat Server, Backend Developers, QA  

**적용 위치:** Chat Server + React Client  
**상위 API:** [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §12  
**Chat Server:** [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md)  
**UI/UX:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §6.2, §7

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| Protocol | Socket.io 4.x (Engine.IO v4) |
| Encoding | JSON UTF-8 |
| Auth | JWT in handshake `auth.token` |
| Events | 12 Client↔Server + 2 system |
| Timezone | ISO 8601 with offset (`+09:00`) |

본 문서는 ACEP 상담채팅 **WebSocket 프로토콜 전체 참조**이다. REST API([02_API설계.md](../03_SYSTEM/02_API설계.md))와 **이중 전송 패턴**으로 동작한다.

---

## 1. 연결 (Connection)

### 1.1 Endpoint

| Environment | URL |
|-------------|-----|
| Production | `wss://{host}/socket.io/?EIO=4&transport=websocket` |
| Development | `ws://localhost:3001/socket.io/` |

### 1.2 Handshake Parameters

```typescript
const socket = io(BASE_URL, {
  path: '/socket.io',
  transports: ['websocket', 'polling'],
  auth: {
    token: accessToken,  // JWT Bearer (without "Bearer " prefix)
  },
  reconnection: true,
  reconnectionDelay: 1000,
  reconnectionDelayMax: 30000,
});
```

### 1.3 Connection Lifecycle

```
Client                          Chat Server
  │                                  │
  │──── connect (auth.token) ───────>│
  │                                  │ verify JWT
  │<─── connect / connect_error ────│
  │                                  │
  │──── room:join ──────────────────>│
  │                                  │ Backend RBAC check
  │<─── room:joined ─────────────────│
  │                                  │
  │  ... events ...                  │
  │                                  │
  │──── disconnect ─────────────────>│
  │                                  │ typing:stop broadcast
```

### 1.4 connect_error Codes

| Error message | Cause | Client action |
|---------------|-------|---------------|
| `UNAUTHORIZED` | JWT invalid/expired | refresh → reconnect |
| `FORBIDDEN` | role blocked | logout |
| `TRANSPORT_ERROR` | network | retry backoff |

---

## 2. 인증 흐름 (Authentication Flow)

### 2.1 Sequence

```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  React   │     │  Nginx   │     │   Chat   │     │ Backend  │
│  Client  │     │          │     │  Server  │     │  (PHP)   │
└────┬─────┘     └────┬─────┘     └────┬─────┘     └────┬─────┘
     │ POST /auth/login                │                │
     │─────────────────────────────────────────────────>│
     │<──────────────── accessToken ────────────────────│
     │                                   │                │
     │ io.connect({ auth: { token }})    │                │
     │──────────────────────────────────>│                │
     │                    jwt.verify()   │                │
     │<──────────────── connect OK ──────│                │
     │                                   │                │
     │ emit room:join                    │ GET /internal/ │
     │──────────────────────────────────>│──access───────>│
     │<──────────── room:joined ─────────│<─── allowed ───│
```

### 2.2 Token Refresh Mid-Session

```typescript
socket.on('connect_error', async (err) => {
  if (err.message === 'UNAUTHORIZED') {
    const newToken = await api.refreshToken();
    socket.auth = { token: newToken };
    socket.connect();
  }
});
```

---

## 3. Room Lifecycle

### 3.1 States

| State | Description |
|-------|-------------|
| disconnected | socket not connected |
| connected | JWT ok, no active room |
| in_room | joined `room:{roomId}` |

### 3.2 room:join

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

**Error:** `error` event — FORBIDDEN, ROOM_NOT_FOUND

### 3.3 room:leave

**Direction:** Client → Server

```json
{
  "roomId": "room-uuid-1"
}
```

Server: socket.leave(`room:{roomId}`), no ack required (V1.0).

### 3.4 room:update (Server → Client)

**Trigger:** Backend — status change, assign, unreadCount change

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

**UI:** `ChatList` item refresh, `StatusBadge` update

---

## 4. Client → Server Events

### 4.1 Event Summary

| Event | Payload Schema | Trigger (UI) |
|-------|----------------|--------------|
| `room:join` | `{ roomId }` | ChatList room select |
| `room:leave` | `{ roomId }` | room switch / unmount |
| `message:send` | see §4.2 | InputField send (optional) |
| `typing:start` | `{ roomId }` | InputField onInput |
| `typing:stop` | `{ roomId }` | 3s idle / send / blur |

### 4.2 message:send (Optional — V1.0 REST primary)

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
| roomId | string | Y | |
| content | string | Y* | *or attachmentId |
| tempId | string | N | optimistic UI correlation |
| attachmentId | string | N | pre-uploaded file |

> **V1.0:** Client는 `POST /api/v1/chats/{id}/messages` 사용. `message:send`는 V1.5 optimistic path.

### 4.3 typing:start

```json
{
  "roomId": "room-uuid-1"
}
```

Server adds: `userId`, `userName`, `userType` on relay to peers.

### 4.4 typing:stop

```json
{
  "roomId": "room-uuid-1"
}
```

**BR-TYPE-001~004:** [01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §7.2

---

## 5. Server → Client Events

### 5.1 Event Summary

| Event | Source | UI Component |
|-------|--------|--------------|
| `room:joined` | Chat Server | internal state |
| `message:receive` | Backend Redis | MessageBubble |
| `typing:start` | Chat Server relay | TypingIndicator |
| `typing:stop` | Chat Server relay | TypingIndicator |
| `ai:update` | Backend Redis | AIPanelCard |
| `read:update` | Backend Redis | MessageBubble ✓✓ |
| `room:update` | Backend Redis | ChatList |
| `error` | Chat Server | toast |
| `pong` | Chat Server | health (optional) |

### 5.2 message:receive

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

**Client logic:**

```typescript
socket.on('message:receive', (msg) => {
  if (msg.tempId) {
    replaceOptimisticMessage(msg.tempId, msg);
  } else {
    appendMessage(msg);
  }
});
```

### 5.3 typing:start (relay)

```json
{
  "roomId": "room-uuid-1",
  "userId": "cust-1",
  "userName": "홍길동",
  "userType": "customer"
}
```

**UI:** `TypingIndicator` — `{userName}님이 입력 중...`

### 5.4 typing:stop (relay)

```json
{
  "roomId": "room-uuid-1",
  "userId": "cust-1"
}
```

### 5.5 ai:update

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
| processing | "분석 중..." |
| completed | GET `/api/v1/ai/recommendations/{roomId}` |
| failed | error state + retry button |

### 5.6 read:update

```json
{
  "roomId": "room-uuid-1",
  "messageId": "msg-uuid-1",
  "readerType": "agent",
  "readAt": "2026-07-21T14:36:00+09:00"
}
```

**UI:** MessageBubble read indicator → ✓✓

### 5.7 error

```json
{
  "code": "FORBIDDEN",
  "message": "상담방 접근 권한이 없습니다.",
  "tempId": "temp-client-id-123",
  "details": {}
}
```

| code | HTTP equiv |
|------|------------|
| VALIDATION_ERROR | 400 |
| UNAUTHORIZED | 401 |
| FORBIDDEN | 403 |
| ROOM_NOT_FOUND | 404 |
| MSG_SEND_FAILED | 500 |
| INTERNAL_ERROR | 500 |

---

## 6. REST ↔ WebSocket 이중 전송 패턴

### 6.1 Message Send (권장 V1.0)

```
Agent: ActionButton(send)
    │
    ├─ POST /api/v1/chats/{id}/messages     ← Source of Truth
    │       └─ 201 { messageId }
    │
    └─ (optional) socket.emit message:send  ← optimistic only

Backend:
    INSERT chat_messages
    PUBLISH Redis message:receive

Chat Server:
    SUBSCRIBE → io.to(room).emit('message:receive')

All Clients:
    MessageBubble append
```

### 6.2 AI Update

```
Backend AI Worker:
    UPDATE ai_recommendations
    PUBLISH Redis ai:update

Chat Server → Agent Client:
    ai:update { status: completed }

Agent Client:
    GET /api/v1/ai/recommendations/{id}
    → RecommendationCard ×3
```

### 6.3 Read Receipt

```
Agent: room focus / scroll visible
    PUT /api/v1/chats/{id}/read { messageIds }

Backend:
    UPDATE chat_read_status.read_at
    PUBLISH read:update

Peer Client:
    MessageBubble ✓✓
```

---

## 7. UI Component 매핑

| UI Component | Inbound WS | Outbound WS | REST |
|--------------|------------|-------------|------|
| ChatList | room:update | room:join | GET /chats/rooms |
| MessageBubble | message:receive, read:update | — | GET/POST messages |
| InputField | — | typing:*, (message:send) | POST messages |
| TypingIndicator | typing:start/stop | typing:* | — |
| AIPanelCard | ai:update | — | GET ai/recommendations |
| RecommendationCard | ai:update | — | GET ai/recommendations |
| FileUpload | message:receive | — | POST files/upload |
| ActionButton (close) | room:update | — | PUT close |

---

## 8. Sequence Diagrams

### 8.1 New Message (상담원 → 고객)

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
    │ MessageBubble   │                │               │                │ MessageBubble  │
```

### 8.2 Typing Indicator

```
Agent A           Chat Server           Agent B (peer)
    │                    │                    │
    │ typing:start       │                    │
    │───────────────────>│                    │
    │                    │ typing:start       │
    │                    │───────────────────>│
    │                    │                    │ TypingIndicator show
    │                    │                    │
    │ (3s idle)          │                    │
    │ typing:stop        │                    │
    │───────────────────>│                    │
    │                    │ typing:stop        │
    │                    │───────────────────>│
    │                    │                    │ TypingIndicator hide
```

### 8.3 AI Update

```
Customer         Backend Worker        Redis          Chat Server        Agent UI
    │                  │                  │                 │                │
    │ POST message     │                  │                 │                │
    │─────────────────>│                  │                 │                │
    │                  │ ai_call()        │                 │                │
    │                  │ INSERT ai_rec    │                 │                │
    │                  │ PUBLISH ai:update│                 │                │
    │                  │─────────────────>│                 │                │
    │                  │                  │ SUBSCRIBE       │                │
    │                  │                  │────────────────>│                │
    │                  │                  │                 │ ai:update      │
    │                  │                  │                 │───────────────>│
    │                  │                  │                 │                │ GET ai/rec
    │                  │                  │                 │                │ AIPanelCard
```

### 8.4 Read Receipt

```
Agent UI           REST API          Backend           Redis         Chat Server      Customer UI
    │                  │                 │                │                │                │
    │ room focus       │                 │                │                │                │
    │ PUT /read        │                 │                │                │                │
    │─────────────────>│                 │                │                │                │
    │                  │ UPDATE read_at  │                │                │                │
    │                  │────────────────>│                │                │                │
    │                  │                 │ PUBLISH        │                │                │
    │                  │                 │───────────────>│                │                │
    │                  │                 │                │ read:update    │                │
    │                  │                 │                │───────────────>│                │
    │ 200 updatedCount │                 │                │                │ read:update    │
    │<─────────────────│                 │                │                │───────────────>│
    │ ✓✓ local         │                 │                │                │ ✓✓ on bubble   │
```

---

## 9. Reconnection Protocol

### 9.1 Client Reconnect Checklist

1. Refresh JWT if expired (`POST /auth/refresh`)
2. `socket.connect()` with new token
3. `room:join` active roomId
4. `GET /chats/{id}/messages?before={lastKnownTimestamp}` — gap fill
5. `GET /ai/recommendations/{id}` — AI state sync

### 9.2 Missed Events

| Event | Recovery |
|-------|----------|
| message:receive | REST messages cursor |
| ai:update | GET ai/recommendations |
| read:update | GET messages (readStatus field) |
| room:update | GET /chats/rooms |
| typing:* | not recoverable (ephemeral) |

### 9.3 Backoff Schedule

| Attempt | Delay |
|---------|-------|
| 1 | 1s |
| 2 | 2s |
| 3 | 4s |
| 4+ | min(2^n, 30s) |

---

## 10. Redis Envelope (Internal)

Chat Server는 Backend PUBLISH 메시지를 파싱:

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

Chat Server emit: `io.to('room:{roomId}').emit(envelope.event, envelope.payload)`

---

## 11. TypeScript Type Definitions

```typescript
// types/socket-events.ts

export interface RoomJoinPayload {
  roomId: string;
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

## 12. QA Test Matrix

| ID | Test | Steps | Expected |
|----|------|-------|----------|
| TC-WS-P01 | Auth connect | valid JWT | connected |
| TC-WS-P02 | Auth fail | expired JWT | connect_error |
| TC-WS-P03 | Join room | room:join | room:joined |
| TC-WS-P04 | Join forbidden | wrong agent | error FORBIDDEN |
| TC-WS-P05 | Receive message | Backend publish | message:receive |
| TC-WS-P06 | Typing | typing:start | peer indicator |
| TC-WS-P07 | AI update | worker complete | ai:update completed |
| TC-WS-P08 | Read update | PUT read | read:update |
| TC-WS-P09 | Reconnect | disconnect 5s | gap fill via REST |
| TC-WS-P10 | Multi-tab | 2 sockets same user | both receive events |

---

## 13. Versioning

| Protocol Version | Socket.io | Breaking Changes |
|------------------|-----------|------------------|
| 1.0 (V1.0 MVP) | 4.7.x | initial |
| 1.1 (V1.5) | 4.x | message:send standard |
| 2.0 (V2.0) | 4.x | namespace `/agent`, `/customer` |

Event 추가는 **backward compatible** — unknown events ignored by client.

---

## 부록 A. Event Quick Reference Card

```
CONNECT  wss://host/socket.io  auth: { token }

C→S  room:join        { roomId }
C→S  room:leave       { roomId }
C→S  message:send     { roomId, content, tempId? }     [optional V1.0]
C→S  typing:start     { roomId }
C→S  typing:stop      { roomId }

S→C  room:joined      { roomId, timestamp }
S→C  message:receive  { messageId, roomId, content, senderType, ... }
S→C  typing:start     { roomId, userId, userName, userType }
S→C  typing:stop      { roomId, userId }
S→C  ai:update        { roomId, recommendationId, status, contractProbability? }
S→C  read:update      { roomId, messageId, readerType, readAt }
S→C  room:update      { roomId, status, unreadCount?, ... }
S→C  error            { code, message, tempId? }
```

## 부록 B. 관련 문서

- [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md)
- [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §12
- [_CHAT_INDEX.md](_CHAT_INDEX.md)

## 부록 C. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 4 — WebSocket 프로토콜 명세 |

---

**문서 끝 — Frontend STEP 5 구현 시 본 문서와 TypeScript types를 SSOT로 사용한다.**
