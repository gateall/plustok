import jwt from 'jsonwebtoken';
import type { JwtPayload } from './types/socket-events.js';

export function getJwtSecret(): string {
  const secret = process.env.JWT_SECRET?.trim();
  if (!secret) {
    throw new Error('JWT_SECRET not configured');
  }
  return secret;
}

export function isJwtConfigured(): boolean {
  return Boolean(process.env.JWT_SECRET?.trim());
}

export function verifyJwtToken(token: string): JwtPayload | null {
  try {
    return jwt.verify(token, getJwtSecret()) as JwtPayload;
  } catch {
    return null;
  }
}

export function decodeJwtPayload(token: string): JwtPayload | null {
  const payload = verifyJwtToken(token);
  if (!payload?.sub || !payload.role) {
    return null;
  }
  return payload;
}
