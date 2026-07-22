import type { Server } from 'socket.io';
import type { AuthenticatedSocket } from '../middleware/auth.middleware.js';
import { sendMessageViaRest } from '../services/backend.client.js';
import { publishRoomEvent } from '../services/redis.pubsub.js';
import type { MessageSendPayload } from '../types/socket-events.js';

export function registerMessageHandlers(io: Server): void {
  io.on('connection', (socket: AuthenticatedSocket) => {
    socket.on('message:send', async (payload: MessageSendPayload) => {
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

      const senderType = socket.data.role === 'customer' ? 'customer' : 'agent';

      const broadcast = {
        messageId: saved.messageId,
        roomId,
        content,
        senderType,
        senderId: socket.data.userId,
        tempId: payload.tempId,
        createdAt: saved.createdAt,
      };

      io.to(`room:${roomId}`).emit('message:receive', broadcast);
      await publishRoomEvent(roomId, 'message:receive', broadcast);
    });
  });
}
