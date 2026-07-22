# ACEP Chat Server (Phase 2)

SSOT: `05_CHAT/01_WebSocket설계.md`

## Setup

```bash
cd chat-server
npm install
cp .env.example .env
# JWT_SECRET = config/acep.local.php ACEP_JWT_SECRET 와 동일
npm run dev
```

## Events (SSOT 부록 A)

| Direction | Event |
|-----------|-------|
| C→S | `room:join`, `room:leave`, `message:send`, `typing:start`, `typing:stop` |
| S→C | `room:joined`, `message:receive`, `typing:*`, `read:update`, `ai:update`, `room:update`, `error` |

## Architecture

- JWT auth (`auth.token` — no Bearer prefix)
- Room access via PHP REST `GET /api/v1/chats/{id}`
- Message persist via PHP REST `POST /api/v1/chats/{id}/messages`
- Redis pub/sub bridge (optional) for PHP → WS broadcast

## Health

`GET http://localhost:3001/health`

## Render (Docker)

| Render setting | Value |
|----------------|-------|
| Root Directory | `www/chat-server` (or `chat-server` if repo root is `www/`) |
| Dockerfile Path | `Dockerfile` |
| Environment | `JWT_SECRET`, `BACKEND_URL`, `CORS_ALLOWED_ORIGINS` |

Render injects `PORT` — do not hardcode 3001 in production.

```env
JWT_SECRET=<same as acep.local.php>
BACKEND_URL=https://plustok.mycafe24.com/api/v1
CORS_ALLOWED_ORIGINS=https://plustok.mycafe24.com
```

Frontend after deploy: `VITE_WS_URL=https://<your-render-service>.onrender.com`
