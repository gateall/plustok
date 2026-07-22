import type { Socket } from 'socket.io';
import jwt from 'jsonwebtoken';
import type { JwtPayload } from '../types/socket-events.js';

export interface AuthenticatedSocket extends Socket {
  data: {
    userId: string;
    role: JwtPayload['role'];
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

  const secret = process.env.JWT_SECRET;
  if (!secret) {
    next(new Error('JWT_SECRET not configured'));
    return;
  }

  try {
    const payload = jwt.verify(token, secret) as JwtPayload;
    socket.data = {
      userId: payload.sub,
      role: payload.role,
      name: payload.name ?? '',
      token,
    };
    next();
  } catch {
    next(new Error('UNAUTHORIZED'));
  }
}
