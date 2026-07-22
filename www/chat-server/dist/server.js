import http from 'node:http';
import { Server } from 'socket.io';
import { authMiddleware } from './middleware/auth.middleware.js';
import { registerRoomHandlers } from './handlers/room.handler.js';
import { registerMessageHandlers } from './handlers/message.handler.js';
import { registerTypingHandlers } from './handlers/typing.handler.js';
import { startRedisSubscriber } from './services/redis.pubsub.js';
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
registerMessageHandlers(io);
registerTypingHandlers(io);
startRedisSubscriber(io).catch((e) => {
    console.error('[redis] startup failed', e);
});
httpServer.listen(PORT, () => {
    console.log(`ACEP Chat Server listening on ${PORT}`);
});
