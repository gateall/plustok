import http from 'node:http';
import { Server } from 'socket.io';
import { isJwtConfigured } from './auth.js';
import { authMiddleware } from './middleware/auth.middleware.js';
import { registerRoomHandlers } from './handlers/room.handler.js';
import { registerMessageHandlers } from './handlers/message.handler.js';
import { registerTypingHandlers } from './handlers/typing.handler.js';
import { pingBackend } from './services/backend.client.js';
import { attachRedisAdapter, isRedisAdapterActive } from './services/redis.adapter.js';
import { startRedisSubscriber, isRedisSubscriberActive } from './services/redis.pubsub.js';
const PORT = Number(process.env.PORT ?? process.env.CHAT_SERVER_PORT ?? 3001);
const BACKEND_URL = (process.env.BACKEND_URL ?? 'http://localhost/api/v1').replace(/\/$/, '');
let backendReachable = false;
const httpServer = http.createServer(async (req, res) => {
    if (req.url === '/health' || req.url === '/health/') {
        const backend = await pingBackend();
        backendReachable = backend.ok;
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: backend.ok ? 'healthy' : 'degraded',
            uptimeSec: process.uptime(),
            backend: {
                url: BACKEND_URL,
                reachable: backend.ok,
                latencyMs: backend.latencyMs,
                error: backend.error,
            },
            jwt: { configured: isJwtConfigured() },
            redis: {
                adapter: isRedisAdapterActive(),
                pubsub: isRedisSubscriberActive(),
            },
        }));
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
registerMessageHandlers(io);
registerTypingHandlers(io);
async function start() {
    await attachRedisAdapter(io);
    startRedisSubscriber(io).catch((e) => {
        console.error('[redis] pubsub startup failed', e);
    });
    const backend = await pingBackend();
    backendReachable = backend.ok;
    if (!backend.ok) {
        console.warn('[startup] Backend unreachable:', backend.error, '→', BACKEND_URL);
    }
    else {
        console.log('[startup] Backend OK', backend.latencyMs, 'ms');
    }
    if (!isJwtConfigured()) {
        console.warn('[startup] JWT_SECRET not set — all socket connections will fail');
    }
    httpServer.listen(PORT, '0.0.0.0', () => {
        console.log(`ACEP Chat Server listening on 0.0.0.0:${PORT}`);
        console.log(`[info] BACKEND_URL=${BACKEND_URL}`);
        console.log(`[info] backendReachable=${backendReachable}`);
    });
}
start().catch((e) => {
    console.error('[fatal]', e);
    process.exit(1);
});
