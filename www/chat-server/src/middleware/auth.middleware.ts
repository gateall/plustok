import type { Socket } from 'socket.io';
import { decodeJwtPayload } from '../auth.js';

export interface AuthenticatedSocket extends Socket {
  data: {
    userId: string;
    role: 'agent' | 'admin' | 'operator' | 'customer';
    name: string;
    token: string;
    activeRoomId?: string;
  };
}

export function authMiddleware(
  socket: AuthenticatedSocket,
  next: (err?: Error) => void,
): void {
  const token = socket.handshake.auth?.token as string | undefined;
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