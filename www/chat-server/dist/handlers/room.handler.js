import { assertRoomAccess } from '../services/backend.client.js';
export function registerRoomHandlers(io) {
    io.on('connection', (socket) => {
        socket.on('room:join', async (payload) => {
            const roomId = payload?.roomId?.trim();
            if (!roomId) {
                socket.emit('error', { code: 'VALIDATION_ERROR', message: 'roomId required' });
                return;
            }
            const allowed = await assertRoomAccess(roomId, socket.data.token);
            if (!allowed) {
                socket.emit('error', { code: 'FORBIDDEN', message: 'Room access denied' });
                return;
            }
            if (socket.data.activeRoomId) {
                socket.leave(`room:${socket.data.activeRoomId}`);
            }
            await socket.join(`room:${roomId}`);
            socket.data.activeRoomId = roomId;
            socket.emit('room:joined', { roomId, timestamp: new Date().toISOString() });
        });
        socket.on('room:leave', (payload) => {
            const roomId = payload?.roomId?.trim();
            if (!roomId) {
                return;
            }
            socket.leave(`room:${roomId}`);
            if (socket.data.activeRoomId === roomId) {
                socket.data.activeRoomId = undefined;
            }
        });
        socket.on('disconnect', () => {
            socket.data.activeRoomId = undefined;
        });
    });
}
