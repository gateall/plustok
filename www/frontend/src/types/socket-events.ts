/**
 * Socket.io event types — 100% compatible with chat-server/src/types/socket-events.ts
 * SSOT: 05_CHAT/01_WebSocket설계.md
 */

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

export interface TypingEmitPayload {
  roomId: string;
}

export interface RoomJoinedPayload {
  roomId: string;
  timestamp: string;
}

export interface MessageReceivePayload {
  messageId: string;
  roomId: string;
  content: string;
  senderType: 'agent' | 'customer';
  senderId: string;
  tempId?: string;
  createdAt: string;
  attachmentUrl?: string | null;
}

export interface TypingStartPayload {
  roomId: string;
  userId: string;
  userName: string;
  userType: 'agent' | 'customer';
}

export interface TypingStopPayload {
  roomId: string;
  userId: string;
}

export type AiUpdateStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface AiUpdatePayload {
  roomId: string;
  recommendationId: string;
  status: AiUpdateStatus;
  contractProbability?: number;
  timestamp: string;
}

export interface ReadUpdatePayload {
  roomId: string;
  messageId: string;
  readerType: 'agent' | 'customer';
  readAt: string;
}

export interface RoomUpdatePayload {
  roomId: string;
  status?: string;
  agentId?: string | null;
  customerName?: string;
  inquiryType?: string;
  lastMessage?: string | null;
  unreadCount?: number;
  contractProbability?: number;
  updatedAt?: string;
}

export interface SocketErrorPayload {
  code: string;
  message: string;
  tempId?: string;
  details?: Record<string, unknown>;
}

export interface ClientToServerEvents {
  'room:join': (payload: RoomJoinPayload) => void;
  'room:leave': (payload: RoomLeavePayload) => void;
  'message:send': (payload: MessageSendPayload) => void;
  'typing:start': (payload: TypingEmitPayload) => void;
  'typing:stop': (payload: TypingEmitPayload) => void;
}

export interface ServerToClientEvents {
  'room:joined': (payload: RoomJoinedPayload) => void;
  'message:receive': (payload: MessageReceivePayload) => void;
  'typing:start': (payload: TypingStartPayload) => void;
  'typing:stop': (payload: TypingStopPayload) => void;
  'ai:update': (payload: AiUpdatePayload) => void;
  'read:update': (payload: ReadUpdatePayload) => void;
  'room:update': (payload: RoomUpdatePayload) => void;
  error: (payload: SocketErrorPayload) => void;
  pong: (payload: { ts: number }) => void;
}

export type ServerEventName = keyof ServerToClientEvents;
export type ClientEventName = keyof ClientToServerEvents;
