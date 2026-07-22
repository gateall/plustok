import { createAdapter } from '@socket.io/redis-adapter';
import { createClient } from 'redis';
let pubClient = null;
export async function attachRedisAdapter(io) {
    const url = process.env.REDIS_URL?.trim();
    if (!url) {
        console.warn('[redis] REDIS_URL not set — Socket.io in-memory adapter');
        return false;
    }
    try {
        pubClient = createClient({ url });
        const subClient = pubClient.duplicate();
        pubClient.on('error', (e) => console.error('[redis adapter pub]', e));
        subClient.on('error', (e) => console.error('[redis adapter sub]', e));
        await Promise.all([pubClient.connect(), subClient.connect()]);
        io.adapter(createAdapter(pubClient, subClient));
        console.log('[redis] Socket.io Redis adapter connected');
        return true;
    }
    catch (e) {
        console.error('[redis] adapter failed', e);
        return false;
    }
}
export function isRedisAdapterActive() {
    return pubClient?.isOpen ?? false;
}
