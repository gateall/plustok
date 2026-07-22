import jwt from 'jsonwebtoken';
export function authMiddleware(socket, next) {
    const token = socket.handshake.auth?.token;
    if (!token) {
        next(new Error('UNAUTHORIZED'));
        return;
    }
    const secret = process.env.JWT_SECRET;
    if (!secret) {
        next(new Error('JWT_SECRET not configured'));
        return;
    }
    try {
        const payload = jwt.verify(token, secret);
        socket.data = {
            userId: payload.sub,
            role: payload.role,
            name: payload.name ?? '',
            token,
        };
        next();
    }
    catch {
        next(new Error('UNAUTHORIZED'));
    }
}
