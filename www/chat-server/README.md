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
