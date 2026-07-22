export interface JwtPayload {
  sub: string;
  role: 'agent' | 'admin' | 'operator' | 'customer';
  name?: string;
  iat: number;
  exp: number;
}

export interface RoomJoinPayload {
  roomId: string;
}

export interface RoomLeavePayload {
  roomId: string;
}

export interface MessageSendPayload {
  roomId: string;
  content: string;
  tempId?: string;
}

export interface TypingPayload {
  roomId: string;
}

export interface ReadUpdatePayload {
  roomId: string;
  messageId: string;
  readerType: 'agent' | 'customer';
  readAt: string;
}

export interface RedisEnvelope {
  event: string;
  roomId: string;
  payload: Record<string, unknown>;
  timestamp: string;
  source: 'backend' | 'chat-server';
  traceId?: string;
}
