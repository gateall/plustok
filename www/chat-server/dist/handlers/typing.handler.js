export function registerTypingHandlers(io) {
    io.on('connection', (socket) => {
        socket.on('typing:start', (payload) => {
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
        socket.on('typing:stop', (payload) => {
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
