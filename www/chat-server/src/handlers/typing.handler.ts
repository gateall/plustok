import type { Server } from 'socket.io';
import type { AuthenticatedSocket } from '../middleware/auth.middleware.js';
import type { TypingPayload } from '../types/socket-events.js';

export function registerTypingHandlers(io: Server): void {
  io.on('connection', (socket: AuthenticatedSocket) => {
    socket.on('typing:start', (payload: TypingPayload) => {
      const roomId = payload?.roomId?.trim();
      if (!roomId) {
        return;
      }
      socket.to(`room:${roomId}`).emit('typing:start', {
        roomId,
        userId: socket.data.userId,
        userName: socket.data.name,
        userType: socket.data.role === 'customer' ? 'customer' : 'agent',
      });
    });

    socket.on('typing:stop', (payload: TypingPayload) => {
      const roomId = payload?.roomId?.trim();
      if (!roomId) {
        return;
      }
      socket.to(`room:${roomId}`).emit('typing:stop', {
        roomId,
        userId: socket.data.userId,
      });
    });
  });
}
