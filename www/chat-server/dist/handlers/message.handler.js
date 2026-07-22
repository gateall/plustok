import { sendMessageViaRest } from '../services/backend.client.js';
import { publishRoomEvent } from '../services/redis.pubsub.js';
export function registerMessageHandlers(io) {
    io.on('connection', (socket) => {
        socket.on('message:send', async (payload) => {
            const roomId = payload?.roomId?.trim();
            const content = payload?.content?.trim();
            if (!roomId || !content) {
                socket.emit('error', {
                    code: 'VALIDATION_ERROR',
                    message: 'roomId and content required',
                    tempId: payload?.tempId,
                });
                return;
            }
            const saved = await sendMessageViaRest(roomId, socket.data.token, {
                content,
                source: 'manual',
            });
            if (!saved) {
                socket.emit('error', {
                    code: 'MSG_SEND_FAILED',
                    message: 'Message save failed',
                    tempId: payload.tempId,
                });
                return;
            }
            const broadcast = {
                messageId: saved.messageId,
                roomId,
                content,
                senderType: 'agent',
                senderId: socket.data.userId,
                tempId: payload.tempId,
                createdAt: saved.createdAt,
            };
            io.to(`room:${roomId}`).emit('message:receive', broadcast);
            await publishRoomEvent(roomId, 'message:receive', broadcast);
        });
    });
}
