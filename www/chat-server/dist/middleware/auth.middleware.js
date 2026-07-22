import { decodeJwtPayload } from '../auth.js';
export function authMiddleware(socket, next) {
    const token = socket.handshake.auth?.token;
    if (!token) {
        next(new Error('UNAUTHORIZED'));
        return;
    }
    if (!process.env.JWT_SECRET?.trim()) {
        next(new Error('JWT_SECRET not configured'));
        return;
    }
    const payload = decodeJwtPayload(token);
    if (!payload) {
        next(new Error('UNAUTHORIZED'));
        return;
    }
    socket.data = {
        userId: payload.sub,
        role: payload.role,
        name: payload.name ?? '',
        token,
    };
    next();
}
