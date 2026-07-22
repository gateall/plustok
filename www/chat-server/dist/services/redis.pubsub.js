import { createClient } from 'redis';
let pub = null;
let sub = null;
export async function startRedisSubscriber(io) {
    const url = process.env.REDIS_URL;
    if (!url) {
        console.warn('[redis] REDIS_URL not set — pub/sub bridge disabled');
        return;
    }
    pub = createClient({ url });
    sub = pub.duplicate();
    pub.on('error', (e) => console.error('[redis pub]', e));
    sub.on('error', (e) => console.error('[redis sub]', e));
    await Promise.all([pub.connect(), sub.connect()]);
    await sub.pSubscribe('acep:room:*:events', (message, channel) => {
        try {
            const envelope = JSON.parse(message);
            io.to(`room:${envelope.roomId}`).emit(envelope.event, envelope.payload);
        }
        catch (e) {
            console.error('[redis] invalid envelope on', channel, e);
        }
    });
    await sub.subscribe('acep:events:broadcast', (message) => {
        try {
            const envelope = JSON.parse(message);
            io.emit(envelope.event, envelope.payload);
        }
        catch (e) {
            console.error('[redis] broadcast parse error', e);
        }
    });
    console.log('[redis] subscriber connected');
}
export function isRedisSubscriberActive() {
    return sub?.isOpen ?? false;
}
export async function publishRoomEvent(roomId, event, payload) {
    if (!pub?.isOpen) {
        return;
    }
    const envelope = {
        event,
        roomId,
        payload,
        timestamp: new Date().toISOString(),
        source: 'chat-server',
    };
    await pub.publish(`acep:room:${roomId}:events`, JSON.stringify(envelope));
}
