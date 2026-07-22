# 작업지시서: Render Node.js Chat Server 통합 (ACEP SSOT)

> **버전:** 2026-07-22  
> **대상:** `chat-server/` (Render) ↔ `api/v1/` (Cafe24 PHP) ↔ `frontend/` (React)

---

## 아키텍처 (ACEP 확정)

```
Browser (React + useSocket)
    ↓ wss://plustok.onrender.com/socket.io
Render Node.js (chat-server)
    ↓ JWT 검증 (JWT_SECRET = ACEP_JWT_SECRET)
    ↓ REST SoT — 메시지 저장/조회는 PHP만
PHP Backend (Cafe24)
    ↓
MariaDB
```

**중요:** chat-server는 **MySQL에 직접 접속하지 않습니다.**  
메시지 영속화는 `backend.client.ts` → `POST /api/v1/chats/{roomId}/messages` (PHP SSOT).

---

## 작업지시서 원본과의 차이

| 원본 작업지시서 | ACEP SSOT (본 구현) |
|----------------|---------------------|
| `database.ts` → MySQL 직접 INSERT | ❌ 사용 안 함 — REST SoT |
| `chat:join_room`, `chat:send_message` | ✅ `room:join`, `message:send` (`05_CHAT/01_WebSocket설계.md`) |
| JWT `user_id` (number) | ✅ JWT `sub` (UUID), `role`, `name` |
| `acep_chat_messages` 신규 테이블 | ❌ 기존 PHP 스키마/라우터 사용 |

---

## Phase 체크리스트

### Phase 1 — JWT 검증 통합 ✅

| 파일 | 역할 |
|------|------|
| `chat-server/src/auth.ts` | `verifyJwtToken`, `decodeJwtPayload`, `isJwtConfigured` |
| `chat-server/src/middleware/auth.middleware.ts` | Socket.io handshake JWT 검증 |

Render 환경 변수: `JWT_SECRET` = `config/acep.local.php`의 `ACEP_JWT_SECRET`

### Phase 2 — Socket.io 이벤트 (SSOT) ✅

| 파일 | 역할 |
|------|------|
| `chat-server/src/types/socket-events.ts` | 이벤트 타입 정의 |
| `frontend/src/types/socket-events.ts` | 클라이언트 동일 타입 |

이벤트: `room:join`, `room:leave`, `message:send`, `message:receive`, `typing:start`, `typing:stop`

### Phase 3 — Socket.io 서버 (Render) ✅

| 파일 | 역할 |
|------|------|
| `chat-server/src/server.ts` | `0.0.0.0:PORT` listen, enhanced `/health` |
| `chat-server/src/handlers/*.ts` | room / message / typing |
| `chat-server/Dockerfile` | Render Docker 배포 |

### Phase 4 — Backend REST 연동 ✅

| 파일 | 역할 |
|------|------|
| `chat-server/src/services/backend.client.ts` | `assertRoomAccess`, `sendMessageViaRest`, `pingBackend` |
| `api/v1/router.php` | `/chats/{id}/messages`, `/health` |

### Phase 5 — Redis (선택) ✅

| 파일 | 역할 |
|------|------|
| `chat-server/src/services/redis.adapter.ts` | Socket.io multi-instance (`@socket.io/redis-adapter`) |
| `chat-server/src/services/redis.pubsub.ts` | PHP → WS 브릿지 (`acep:room:*:events`) |

Render: `REDIS_URL` 설정 시 활성화 (미설정 시 in-memory adapter)

### Phase 6 — Frontend Socket.io ✅

| 파일 | 역할 |
|------|------|
| `frontend/src/hooks/useSocket.tsx` | `VITE_WS_URL`, path `/socket.io` |
| `frontend/.env.production` | `VITE_WS_URL=wss://plustok.onrender.com` |

### Phase 7 — 통합 테스트

```bash
# 1. Render health
curl -s https://plustok.onrender.com/health

# 2. Frontend 로그인 → JWT 발급
# 3. WS connect (브라우저 Network → WS)
# 4. room:join → message:send → message:receive
# 5. DB/REST에서 메시지 확인 GET /api/v1/chats/{roomId}/messages
```

---

## Render 환경 변수

| 변수 | 값 | 필수 |
|------|-----|------|
| `JWT_SECRET` | PHP `ACEP_JWT_SECRET`과 동일 | ✅ |
| `BACKEND_URL` | `https://plustok.mycafe24.com/api/v1` | ✅ |
| `CORS_ALLOWED_ORIGINS` | `https://plustok.mycafe24.com` | ✅ |
| `PORT` | Render 자동 설정 | ✅ (자동) |
| `REDIS_URL` | Redis Cloud URL | 선택 |

---

## `/health` 응답 예시

```json
{
  "status": "healthy",
  "uptimeSec": 120.5,
  "backend": {
    "url": "https://plustok.mycafe24.com/api/v1",
    "reachable": true,
    "latencyMs": 180
  },
  "jwt": { "configured": true },
  "redis": { "adapter": false, "pubsub": false }
}
```

`status: degraded` — Backend `/health` unreachable (JWT/WS는 동작하나 메시지 REST 실패 가능)

---

## 배포 순서

1. `git push origin main` → Render 자동 배포 (Dockerfile)
2. Render Dashboard → Environment Variables 확인
3. `frontend` 재빌드 + Cafe24 FTP (`dist/` → `/frontend/`)
4. 통합 테스트 (Phase 7)

---

## 관련 문서

- `chat-server/DEPLOY.md`
- `05_CHAT/01_WebSocket설계.md`
- `_BUG003_WebSocket_진단보고서.md`
