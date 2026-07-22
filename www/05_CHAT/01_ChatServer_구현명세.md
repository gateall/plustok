# ACEP (PlusTok Enterprise) — Chat Server 구현명세

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 4 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Real-time Platform Team  
**Audience:** Node.js Developers, DevOps, Backend Developers  

**적용 위치:** `www/chat-server/` 또는 `www/docker/chat-server/`  
**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 4, [03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md)  
**API 설계:** [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) §12  
**WebSocket 프로토콜:** [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md)  
**UI/UX:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §6.2

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 런타임 | Node.js 20 LTS + TypeScript 5.x |
| Real-time | Socket.io 4.x (Engine.IO v4) |
| 목표 규모 | **1,000+ 동시 WebSocket 연결** |
| MVP V1.0 | Chat Server는 **신규 컴포넌트** — PHP Backend와 Redis Pub/Sub로 분리 |
| Source of Truth | MariaDB (Backend PHP) — Chat Server는 **relay·알림 전용** |

본 문서는 ACEP 상담채팅 플랫폼의 **Chat Server (Node.js + Socket.io)** 구현 명세이다. DB 직접 접근·AI 호출·비즈니스 로직은 Backend(PHP)에 두고, Chat Server는 실시간 메시지 라우팅·Typing·Redis 브릿지만 담당한다.

---

## 1. 아키텍처 개요

### 1.1 Chat Server vs Backend 책임 분리

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         RESPONSIBILITY MATRIX                            │
├──────────────────────┬──────────────────────┬───────────────────────────┤
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

**핵심 원칙 (MASTER §4.4):**

1. Chat Server는 **SQL 실행 금지** — 모든 영속 데이터는 Backend REST API 또는 Backend가 PUBLISH한 Redis 이벤트를 통해 전달
2. Client의 `message:send`는 **optimistic UI 보조** — 최종 저장은 `POST /api/v1/chats/{id}/messages`
3. Chat Server 인스턴스 간 동기화는 **Redis Pub/Sub** (`acep:room:{roomId}:events`, `acep:events:broadcast`)

### 1.2 V1.0 MVP 배치

| 환경 | 구성 |
|------|------|
| Development | `docker-compose.dev.yml` — chat-server 1 replica |
| Alpha | chat-server 1 + backend 1 + redis + mariadb |
| Production (1K WS) | chat-server 4 replicas + Nginx `ip_hash` |

PLUS톡 V2.0 기존 `www/` PHP 코드는 Backend로 유지. Chat Server만 `www/chat-server/` 신규 추가.

### 1.3 데이터 흐름 (메시지 전송)

```
Agent Client
    │
    ├─ POST /api/v1/chats/{id}/messages  ──► Backend (PHP)
    │       │                                    │
    │       │                                    ├─ INSERT chat_messages
    │       │                                    ├─ PUBLISH acep:room:{id}:events
    │       │                                    └─ trigger AI (async)
    │       │
    │       └─ 201 { messageId }
    │
    └─ socket.emit('message:send', { tempId })  ──► Chat Server
            (optional optimistic — V1.0 권장: REST only, WS receive only)

Backend PUBLISH ──► Redis ──► Chat Server SUBSCRIBE ──► io.to(roomId).emit('message:receive')
```

> **V1.0 권장:** 메시지 **저장은 REST 단일 경로**. `message:send` WS 이벤트는 고객 위젯(V1.5) 또는 optimistic UI 활성화 시에만 사용.

---

## 2. 폴더 구조

### 2.1 권장 디렉터리 (`www/chat-server/`)

```
www/
├── chat-server/                          # Node.js 20 + Socket.io 4
│   ├── package.json
│   ├── package-lock.json
│   ├── tsconfig.json
│   ├── .env.example
│   ├── Dockerfile
│   ├── README.md
│   └── src/
│       ├── server.ts                     # HTTP + Socket.io bootstrap
│       ├── app.config.ts                 # env validation (zod)
│       ├── types/
│       │   ├── socket-events.ts          # Client/Server event typings
│       │   ├── jwt-payload.ts
│       │   └── redis-events.ts
│       ├── middleware/
│       │   ├── auth.middleware.ts        # JWT handshake
│       │   ├── rateLimit.middleware.ts   # WS connection rate limit
│       │   └── error.middleware.ts
│       ├── handlers/
│       │   ├── connection.handler.ts     # connect/disconnect
│       │   ├── room.handler.ts           # room:join, room:leave
│       │   ├── message.handler.ts        # message:send (optional)
│       │   ├── typing.handler.ts         # typing:start/stop
│       │   └── index.ts
│       ├── services/
│       │   ├── redis.pubsub.ts           # SUBSCRIBE/PUBLISH bridge
│       │   ├── backend.client.ts         # internal REST to PHP
│       │   ├── room.session.ts           # in-memory room → socket map
│       │   ├── heartbeat.service.ts
│       │   └── logger.service.ts         # pino/winston
│       ├── utils/
│       │   ├── jwt.verify.ts
│       │   └── backoff.ts
│       └── health/
│           └── health.controller.ts      # GET /health
├── docker/
│   └── chat-server/
│       └── Dockerfile                    # multi-stage build (선택)
└── docker-compose.yml                    # acep-chat-server service
```

### 2.2 Docker 배치 대안

`docker/chat-server/Dockerfile`만 두고 소스는 `chat-server/`에 유지하는 패턴:

```
www/docker/chat-server/Dockerfile   → COPY ../chat-server
www/docker-compose.yml              → build: ./docker/chat-server
```

[03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §10과 동일 구조.

### 2.3 package.json (핵심 의존성)

```json
{
  "name": "@acep/chat-server",
  "version": "1.0.0",
  "engines": { "node": ">=20.0.0" },
  "scripts": {
    "dev": "tsx watch src/server.ts",
    "build": "tsc",
    "start": "node dist/server.js",
    "lint": "eslint src/"
  },
  "dependencies": {
    "socket.io": "^4.7.5",
    "@socket.io/redis-adapter": "^8.3.0",
    "ioredis": "^5.4.1",
    "jsonwebtoken": "^9.0.2",
    "pino": "^9.0.0",
    "zod": "^3.23.0",
    "dotenv": "^16.4.0"
  },
  "devDependencies": {
    "typescript": "^5.5.0",
    "tsx": "^4.16.0",
    "@types/node": "^20.14.0"
  }
}
```

---

## 3. 연결 및 인증 (JWT Handshake)

### 3.1 연결 URL

| 항목 | 값 |
|------|-----|
| Production | `wss://{host}/socket.io` |
| Path | `/socket.io/` (Socket.io default) |
| Transport | `websocket` preferred, `polling` fallback |
| CORS | `CORS_ALLOWED_ORIGINS` whitelist |

### 3.2 Handshake 인증

Client는 Socket.io v4 `auth` 옵션으로 Access Token 전달:

```typescript
// Client (React useSocket.ts)
import { io } from 'socket.io-client';

const socket = io(APP_URL, {
  path: '/socket.io',
  transports: ['websocket', 'polling'],
  auth: { token: accessToken },
  reconnection: true,
  reconnectionDelay: 1000,
  reconnectionDelayMax: 30000,
});
```

**Server middleware (`auth.middleware.ts`):**

```typescript
import { Socket } from 'socket.io';
import jwt from 'jsonwebtoken';
import type { JwtPayload } from '../types/jwt-payload';

export interface AuthenticatedSocket extends Socket {
  data: {
    userId: string;
    role: 'agent' | 'admin' | 'operator' | 'customer';
    name: string;
  };
}

export function authMiddleware(socket: AuthenticatedSocket, next: (err?: Error) => void): void {
  const token = socket.handshake.auth?.token as string | undefined;
  if (!token) {
    return next(new Error('UNAUTHORIZED'));
  }
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET!) as JwtPayload;
    socket.data = {
      userId: payload.sub,
      role: payload.role,
      name: payload.name ?? '',
    };
    next();
  } catch (err) {
    next(new Error('UNAUTHORIZED'));
  }
}
```

### 3.3 인증 실패 처리

| 조건 | Server 동작 | Client 동작 |
|------|-------------|-------------|
| token 없음 | `connect_error` UNAUTHORIZED | 로그인 페이지 redirect |
| token 만료 | connect_error | POST `/auth/refresh` → 재연결 |
| role 불일치 (room) | `error` event `{ code: 'FORBIDDEN' }` | toast + room leave |

### 3.4 Internal Backend 검증 (room:join)

JWT만으로 room 접근을 허용하지 않는다. `room:join` 시 Backend internal API 호출:

```
GET {BACKEND_INTERNAL_URL}/internal/v1/chats/{roomId}/access
Header: X-Internal-Secret, X-User-Id, X-User-Role
Response: { "allowed": true }
```

MVP V1.0: Chat Server가 JWT decode 후 `backend.client.assertRoomAccess(roomId, userId, role)` 호출.

---

## 4. Room 관리 (join / leave)

### 4.1 Socket.io Room 네이밍

| Room Key | 형식 | 용도 |
|----------|------|------|
| 상담방 | `room:{roomId}` | message/typing/ai/read broadcast |
| Agent presence | `agent:{agentId}` | (V1.5) 개인 알림 |
| Admin monitor | `admin:monitor` | (V1.5) 전체 모니터링 |

### 4.2 room:join Handler

```typescript
// handlers/room.handler.ts
import { Server } from 'socket.io';
import { AuthenticatedSocket } from '../middleware/auth.middleware';
import { backendClient } from '../services/backend.client';
import { logger } from '../services/logger.service';

export function registerRoomHandlers(io: Server): void {
  io.on('connection', (socket: AuthenticatedSocket) => {
    socket.on('room:join', async (payload: { roomId: string }) => {
      const { roomId } = payload;
      if (!roomId) {
        socket.emit('error', { code: 'VALIDATION_ERROR', message: 'roomId required' });
        return;
      }

      const allowed = await backendClient.assertRoomAccess(
        roomId,
        socket.data.userId,
        socket.data.role
      );
      if (!allowed) {
        socket.emit('error', { code: 'FORBIDDEN', message: 'Room access denied' });
        return;
      }

      // 이전 room leave (단일 active room — Agent UI)
      const prevRoom = socket.data.activeRoomId as string | undefined;
      if (prevRoom && prevRoom !== roomId) {
        socket.leave(`room:${prevRoom}`);
      }

      socket.join(`room:${roomId}`);
      socket.data.activeRoomId = roomId;

      logger.info({ userId: socket.data.userId, roomId }, 'room:join');
      socket.emit('room:joined', { roomId, timestamp: new Date().toISOString() });
    });

    socket.on('room:leave', (payload: { roomId: string }) => {
      const { roomId } = payload;
      socket.leave(`room:${roomId}`);
      if (socket.data.activeRoomId === roomId) {
        delete socket.data.activeRoomId;
      }
      logger.info({ userId: socket.data.userId, roomId }, 'room:leave');
    });
  });
}
```

### 4.3 Room Lifecycle 규칙

| 규칙 ID | 설명 |
|---------|------|
| BR-ROOM-001 | Agent는 ChatList room 선택 시 `room:join` |
| BR-ROOM-002 | room 전환 시 이전 room 자동 `leave` |
| BR-ROOM-003 | disconnect 시 모든 room에서 제거 (Socket.io 기본) |
| BR-ROOM-004 | closed room join → Backend 403 → `error` FORBIDDEN |
| BR-ROOM-005 | Admin은 모든 active room join 가능 |

### 4.4 In-Memory Session Map

```typescript
// services/room.session.ts
/** roomId → Set<socketId> — 로컬 인스턴스 디버깅용 (Redis adapter가 cross-node 담당) */
const localRoomSockets = new Map<string, Set<string>>();

export function trackJoin(roomId: string, socketId: string): void {
  if (!localRoomSockets.has(roomId)) {
    localRoomSockets.set(roomId, new Set());
  }
  localRoomSockets.get(roomId)!.add(socketId);
}

export function getLocalConnectionCount(roomId: string): number {
  return localRoomSockets.get(roomId)?.size ?? 0;
}
```

---

## 5. 이벤트 명세 (Handler 구현)

### 5.1 이벤트 목록 요약

| Event | 방향 | Chat Server 역할 |
|-------|------|------------------|
| `room:join` | C→S | join + Backend RBAC |
| `room:leave` | C→S | leave |
| `room:joined` | S→C | join 확인 (신규) |
| `message:send` | C→S | (optional) forward to Backend |
| `message:receive` | S→C | Redis/Backend → broadcast |
| `typing:start` | C→S→C | room 내 relay (sender 제외) |
| `typing:stop` | C→S→C | room 내 relay |
| `ai:update` | S→C | Redis subscribe → emit |
| `read:update` | S→C | Redis subscribe → emit |
| `room:update` | S→C | status/assign/unread 변경 |
| `error` | S→C | validation/auth errors |
| `pong` | S→C | heartbeat response |

상세 payload: [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md)

### 5.2 message:receive (Redis → Broadcast)

Backend가 메시지 INSERT 후 Redis PUBLISH:

```json
{
  "event": "message:receive",
  "roomId": "room-uuid-1",
  "payload": {
    "messageId": "msg-uuid-new",
    "roomId": "room-uuid-1",
    "content": "설치비는 무료입니다.",
    "senderType": "agent",
    "senderId": "agent-1",
    "attachmentUrl": null,
    "timestamp": "2026-07-21T14:32:00+09:00",
    "tempId": "temp-client-id-123"
  }
}
```

**Redis subscriber (`redis.pubsub.ts`):**

```typescript
import { Server } from 'socket.io';
import Redis from 'ioredis';

const REDIS_PREFIX = process.env.REDIS_PREFIX ?? 'acep:';

export function startRedisSubscriber(io: Server): void {
  const sub = new Redis({
    host: process.env.REDIS_HOST,
    port: Number(process.env.REDIS_PORT ?? 6379),
    password: process.env.REDIS_PASSWORD || undefined,
  });

  // Pattern subscribe: acep:room:*:events
  sub.psubscribe(`${REDIS_PREFIX}room:*:events`, (err) => {
    if (err) console.error('Redis psubscribe failed', err);
  });

  sub.on('pmessage', (_pattern, channel, message) => {
    try {
      const envelope = JSON.parse(message) as {
        event: string;
        roomId: string;
        payload: Record<string, unknown>;
      };
      const roomKey = `room:${envelope.roomId}`;
      io.to(roomKey).emit(envelope.event, envelope.payload);
    } catch (e) {
      console.error('Invalid Redis message', e);
    }
  });

  // Global broadcast (ChatList room:update)
  sub.subscribe(`${REDIS_PREFIX}events:broadcast`, () => {});
  sub.on('message', (channel, message) => {
    if (!channel.endsWith('events:broadcast')) return;
    const envelope = JSON.parse(message);
    if (envelope.event === 'room:update') {
      io.emit('room:update', envelope.payload);
    }
  });
}
```

### 5.3 typing:start / typing:stop

```typescript
// handlers/typing.handler.ts
export function registerTypingHandlers(io: Server): void {
  io.on('connection', (socket: AuthenticatedSocket) => {
    let typingTimer: NodeJS.Timeout | null = null;

    socket.on('typing:start', (payload: { roomId: string }) => {
      const { roomId } = payload;
      socket.to(`room:${roomId}`).emit('typing:start', {
        roomId,
        userId: socket.data.userId,
        userName: socket.data.name,
        userType: socket.data.role === 'customer' ? 'customer' : 'agent',
      });
    });

    socket.on('typing:stop', (payload: { roomId: string }) => {
      const { roomId } = payload;
      socket.to(`room:${roomId}`).emit('typing:stop', {
        roomId,
        userId: socket.data.userId,
      });
    });

    socket.on('disconnect', () => {
      if (typingTimer) clearTimeout(typingTimer);
      const roomId = socket.data.activeRoomId as string | undefined;
      if (roomId) {
        socket.to(`room:${roomId}`).emit('typing:stop', {
          roomId,
          userId: socket.data.userId,
        });
      }
    });
  });
}
```

**UI 규칙 (BR-TYPE-001~004):** [01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §7.2

### 5.4 ai:update / read:update / room:update

| Event | Redis trigger | Backend source |
|-------|---------------|----------------|
| `ai:update` | AI worker complete | AiRecommendationService |
| `read:update` | PUT /chats/{id}/read | ReadStatusService |
| `room:update` | room status/assign/unread | ChatRoomService |

Chat Server는 **변환 없이 relay** (payload 표준화는 Backend 책임).

### 5.5 message:send (Optional — Optimistic Path)

V1.0 MVP에서는 **비활성화 권장**. 활성화 시:

```typescript
socket.on('message:send', async (payload) => {
  // 1. Backend REST proxy (internal)
  const result = await backendClient.postMessage(payload.roomId, {
    content: payload.content,
    tempId: payload.tempId,
    senderType: socket.data.role === 'customer' ? 'customer' : 'agent',
    senderId: socket.data.userId,
  });
  // 2. 실제 broadcast는 Backend Redis PUBLISH → subscriber
  // 3. 실패 시 sender에게만 error
  if (!result.ok) {
    socket.emit('error', { code: 'MSG_SEND_FAILED', tempId: payload.tempId });
  }
});
```

---

## 6. Redis Pub/Sub Bridge

### 6.1 Channel 설계

| Channel | Publisher | Subscriber | 용도 |
|---------|-----------|------------|------|
| `acep:room:{roomId}:events` | Backend PHP | Chat Server (psubscribe) | room scoped events |
| `acep:events:broadcast` | Backend PHP | Chat Server | ChatList global |
| `acep:chat:presence` | Chat Server | (V1.5) | online count |

### 6.2 Envelope 표준 형식

```typescript
interface RedisEventEnvelope {
  event: 'message:receive' | 'ai:update' | 'read:update' | 'room:update';
  roomId: string;
  payload: Record<string, unknown>;
  timestamp: string;       // ISO8601
  source: 'backend' | 'chat-server';
  traceId?: string;        // X-Request-Id
}
```

### 6.3 Socket.io Redis Adapter (Multi-Instance)

2+ Chat Server replica 시 `@socket.io/redis-adapter` 필수:

```typescript
import { createAdapter } from '@socket.io/redis-adapter';
import { createClient } from 'redis';

const pubClient = createClient({ url: process.env.REDIS_URL });
const subClient = pubClient.duplicate();
await Promise.all([pubClient.connect(), subClient.connect()]);
io.adapter(createAdapter(pubClient, subClient));
```

이 adapter는 **Socket.io room broadcast**를 cross-node 동기화. Backend Redis channel과 **별도** 운용.

### 6.4 PHP Backend PUBLISH 예시

```php
// includes/services/RedisEventPublisher.php
function redis_publish_room_event(string $roomId, string $event, array $payload): void
{
    $redis = redis_client();
    $envelope = [
        'event'     => $event,
        'roomId'    => $roomId,
        'payload'   => $payload,
        'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('c'),
        'source'    => 'backend',
    ];
    $channel = (getenv('REDIS_PREFIX') ?: 'acep:') . "room:{$roomId}:events";
    $redis->publish($channel, json_encode($envelope, JSON_UNESCAPED_UNICODE));
}
```

---

## 7. 재연결 · Heartbeat · Session Affinity

### 7.1 Client Reconnection (MASTER §6.5)

| 단계 | 지연 | 최대 |
|------|------|------|
| 1차 | 1s | - |
| 2차 | 2s | - |
| 3차 | 4s | - |
| n차 | min(2^n, 30s) | 30s |

재연결 후 Client **필수 시퀀스:**

```
1. socket.connect (auth.token = refreshed accessToken)
2. socket.emit('room:join', { roomId: activeRoomId })
3. GET /api/v1/chats/{id}/messages?before=... (gap fill)
4. GET /api/v1/ai/recommendations/{id} (AI state sync)
```

### 7.2 Server Heartbeat (Ping/Pong)

Socket.io Engine.IO 기본 pingInterval/pingTimeout + 커스텀 health:

```typescript
// server.ts
const io = new Server(httpServer, {
  pingInterval: 25000,   // 25s
  pingTimeout: 20000,    // 20s — MASTER 연결 끊김 0.1% 목표
  cors: { origin: process.env.CORS_ALLOWED_ORIGINS?.split(',') },
});

// Optional application-level pong
setInterval(() => {
  io.fetchSockets().then((sockets) => {
    sockets.forEach((s) => s.emit('pong', { ts: Date.now() }));
  });
}, 60000);
```

### 7.3 Session Affinity (Nginx)

WebSocket은 **동일 Chat Server 인스턴스** sticky 권장:

```nginx
upstream acep_chat {
    ip_hash;   # 또는 sticky cookie
    server acep-chat-server-1:3001;
    server acep-chat-server-2:3001;
}

location /socket.io/ {
    proxy_pass http://acep_chat;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 86400;
    proxy_send_timeout 86400;
}
```

Redis adapter 사용 시 sticky는 **권장**이나 필수는 아님 — reconnect 시 다른 node로 붙어도 room broadcast 정상.

### 7.4 Disconnect Grace Period

| 시나리오 | 처리 |
|----------|------|
| 네트워크 일시 끊김 | Client auto reconnect |
| 20s ping timeout | disconnect → typing:stop broadcast |
| Server restart | Client reconnect → room:join |
| JWT 만료 mid-session | connect_error → refresh token |

---

## 8. Nginx 라우팅

### 8.1 Full Location Block

```nginx
# /socket.io/* → Chat Server
location /socket.io/ {
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
}

# /api/v1/* → PHP Backend
location /api/v1/ {
    proxy_pass http://acep_backend;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Request-Id $request_id;
}

# React static
location / {
    root /var/www/acep/static;
    try_files $uri $uri/ /index.html;
}
```

### 8.2 TLS / WSS

- Production: TLS 1.3 termination at Nginx
- Client connects `wss://` — plain ws 금지 (MASTER §9.4)
- HSTS: `Strict-Transport-Security: max-age=31536000`

### 8.3 Rate Limit (Edge)

```nginx
limit_req_zone $binary_remote_addr zone=ws_conn:10m rate=10r/s;

location /socket.io/ {
    limit_req zone=ws_conn burst=20 nodelay;
    # ... proxy ...
}
```

---

## 9. 에러 처리 및 로깅

### 9.1 Error Event Payload

```typescript
socket.emit('error', {
  code: 'FORBIDDEN' | 'VALIDATION_ERROR' | 'ROOM_NOT_FOUND' | 'INTERNAL_ERROR',
  message: 'Human readable (ko-KR)',
  details?: Record<string, unknown>,
  tempId?: string,  // message:send optimistic rollback용
});
```

### 9.2 로깅 (Pino)

| Level | 이벤트 |
|-------|--------|
| info | connect, disconnect, room:join, room:leave |
| warn | auth failure, room access denied |
| error | Redis disconnect, Backend timeout |
| debug | typing events (sample 1%) |

**PII 금지:** content, phone, email 로그 출력 금지 — `roomId`, `userId`, `messageId` only.

```typescript
import pino from 'pino';

export const logger = pino({
  level: process.env.LOG_LEVEL ?? 'info',
  formatters: {
    level: (label) => ({ level: label }),
  },
  timestamp: pino.stdTimeFunctions.isoTime,
});
```

### 9.3 Graceful Shutdown

```typescript
process.on('SIGTERM', async () => {
  logger.info('SIGTERM received, closing server');
  io.close();
  await redisSub.quit();
  httpServer.close(() => process.exit(0));
});
```

### 9.4 Health Check

```
GET http://acep-chat-server:3001/health

Response 200:
{
  "status": "healthy",
  "connections": 342,
  "uptimeSec": 86400,
  "redis": "connected"
}
```

`GET /api/v1/system/health` (API-030)의 `chatServer` 컴포넌트가 이 endpoint polling.

---

## 10. server.ts Bootstrap (전체 골격)

```typescript
// src/server.ts
import http from 'http';
import { Server } from 'socket.io';
import dotenv from 'dotenv';
import { authMiddleware } from './middleware/auth.middleware';
import { registerRoomHandlers } from './handlers/room.handler';
import { registerTypingHandlers } from './handlers/typing.handler';
import { startRedisSubscriber } from './services/redis.pubsub';
import { logger } from './services/logger.service';

dotenv.config();

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
  logger.info({ port: PORT }, 'ACEP Chat Server started');
});
```

---

## 11. Docker 및 환경 변수

### 11.1 Dockerfile

```dockerfile
# chat-server/Dockerfile
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:20-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY package.json ./
EXPOSE 3001
USER node
CMD ["node", "dist/server.js"]
```

### 11.2 docker-compose.yml (발췌)

```yaml
services:
  acep-chat-server:
    build: ./chat-server
    container_name: acep-chat-server
    restart: unless-stopped
    ports:
      - "3001:3001"
    environment:
      - CHAT_SERVER_PORT=3001
      - JWT_SECRET=${JWT_SECRET}
      - REDIS_HOST=acep-redis
      - REDIS_PORT=6379
      - REDIS_PREFIX=acep:
      - BACKEND_INTERNAL_URL=http://acep-backend:8081
      - CORS_ALLOWED_ORIGINS=${CORS_ALLOWED_ORIGINS}
      - LOG_LEVEL=info
    depends_on:
      - acep-redis
    networks:
      - acep-network-app
```

### 11.3 환경 변수 목록

| Variable | Required | Default | Description |
|----------|:--------:|---------|-------------|
| `CHAT_SERVER_PORT` | Y | 3001 | HTTP + Socket.io port |
| `JWT_SECRET` | Y | — | Backend와 **동일** secret |
| `REDIS_HOST` | Y | localhost | Pub/Sub + adapter |
| `REDIS_PORT` | N | 6379 | |
| `REDIS_PASSWORD` | N | — | Production 권장 |
| `REDIS_PREFIX` | N | acep: | Channel prefix |
| `REDIS_URL` | N | — | adapter용 full URL |
| `BACKEND_INTERNAL_URL` | Y | — | room access API |
| `INTERNAL_API_SECRET` | Y | — | Backend internal auth |
| `CORS_ALLOWED_ORIGINS` | Y | — | comma-separated |
| `LOG_LEVEL` | N | info | pino level |
| `NODE_ENV` | N | production | |

[03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §9.5 참조.

---

## 12. 성능: 1,000 동시 연결

### 12.1 Capacity Model

| Metric | 값 | 근거 |
|--------|-----|------|
| Connection memory | ~10 KB/conn | Socket.io benchmark |
| 1,000 conn RAM | ~10 MB | negligible vs 512MB container |
| Messages/min (peak) | 10,000 | MASTER PART 8 |
| Typing events/sec | ~200 | debounced client-side |
| Redis pub/sub latency | <5ms | local network |

### 12.2 Scale-out Plan

| Connections | chat-server replicas | Nginx | Redis |
|-------------|---------------------|-------|-------|
| ≤250 | 1 | single | 1 |
| ≤500 | 2 | ip_hash | 1 |
| ≤1,000 | 4 | ip_hash + LB | 1 (+ adapter) |
| >1,000 | 4+ | add replicas | Sentinel (V2.0) |

### 12.3 OS Tuning

```bash
# /etc/sysctl.conf (host)
net.core.somaxconn = 65535
net.ipv4.tcp_max_syn_backlog = 65535

# container ulimit
ulimit -n 65535
```

### 12.4 Monitoring Metrics

| Metric | Alert Threshold |
|--------|-----------------|
| `connections_active` | >900/instance |
| `redis_pubsub_lag_ms` | >100ms |
| `auth_failure_rate` | >5%/min |
| `disconnect_rate` | >0.1% (MASTER SLA) |

---

## 13. Backend Client (Internal REST)

```typescript
// services/backend.client.ts
const BASE = process.env.BACKEND_INTERNAL_URL;
const SECRET = process.env.INTERNAL_API_SECRET;

export const backendClient = {
  async assertRoomAccess(roomId: string, userId: string, role: string): Promise<boolean> {
    const res = await fetch(`${BASE}/internal/v1/chats/${roomId}/access`, {
      headers: {
        'X-Internal-Secret': SECRET!,
        'X-User-Id': userId,
        'X-User-Role': role,
      },
    });
    if (!res.ok) return false;
    const json = await res.json();
    return json.data?.allowed === true;
  },

  async postMessage(roomId: string, body: Record<string, unknown>) {
    const res = await fetch(`${BASE}/internal/v1/chats/${roomId}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Secret': SECRET!,
      },
      body: JSON.stringify(body),
    });
    return { ok: res.ok, status: res.status };
  },
};
```

Internal API는 STEP 4 Backend 명세 [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md) §14 참조.

---

## 14. V1.0 구현 체크리스트

| Priority | Task | Status |
|----------|------|:------:|
| P0 | JWT handshake middleware | 📋 |
| P0 | room:join + Backend RBAC | 📋 |
| P0 | Redis subscriber → message:receive | 📋 |
| P0 | typing:start/stop relay | 📋 |
| P0 | ai:update / read:update relay | 📋 |
| P0 | Nginx /socket.io/ proxy | 📋 |
| P0 | Docker + env vars | 📋 |
| P1 | @socket.io/redis-adapter | 📋 |
| P1 | room:update broadcast | 📋 |
| P1 | /health endpoint | 📋 |
| P2 | message:send proxy | 📋 |
| P2 | presence tracking | 📋 |

---

## 15. 테스트 시나리오

| ID | 시나리오 | Expected |
|----|----------|----------|
| TC-WS-001 | Valid JWT connect | connection success |
| TC-WS-002 | Invalid JWT | connect_error UNAUTHORIZED |
| TC-WS-003 | room:join authorized | room:joined |
| TC-WS-004 | room:join forbidden | error FORBIDDEN |
| TC-WS-005 | Backend PUBLISH msg | message:receive to room |
| TC-WS-006 | typing:start | TypingIndicator (peer only) |
| TC-WS-007 | disconnect + reconnect | room:join + history fetch |
| TC-WS-008 | 2 replicas + redis adapter | cross-node broadcast |
| TC-WS-009 | 1000 concurrent connections | no OOM, p99 latency <100ms |

---

## 부록 A. UI Component ↔ Event 매핑

| UI Component | WebSocket Event | REST Fallback |
|--------------|-----------------|---------------|
| MessageBubble | message:receive | GET messages |
| TypingIndicator | typing:start/stop | — |
| AIPanelCard | ai:update | GET ai/recommendations |
| MessageBubble ✓✓ | read:update | PUT read |
| ChatList | room:update | GET rooms (poll 30s) |

## 부록 B. 관련 문서

- [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md)
- [03_AI_Router_Service_구현명세.md](03_AI_Router_Service_구현명세.md)
- [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md)
- [03_SYSTEM/03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md)
- [_CHAT_INDEX.md](_CHAT_INDEX.md)

## 부록 C. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 4 — Chat Server 구현명세 초안 |

---

**문서 끝 — 구현 시 [04_WebSocket_프로토콜_명세.md](04_WebSocket_프로토콜_명세.md) 및 Backend 명세와 함께 사용한다.**
