import type { AiUpdateStatus } from './socket-events';

export interface CustomerSummary {
  id: string;
  name: string;
  phoneMasked: string;
  tags: string[];
}

export interface ChatRoomItem {
  id: string;
  customer: CustomerSummary;
  inquiryType: string;
  status: 'new' | 'active' | 'closed';
  unreadCount: number;
  contractProbability: number;
  updatedAt: string;
}

export interface ChatRoomsResponse {
  rooms: ChatRoomItem[];
  pagination: { page: number; limit: number; total: number };
}

export type MessageClientStatus = 'pending' | 'sent' | 'failed';

export interface ChatMessageItem {
  id: string;
  senderType: 'agent' | 'customer';
  senderId: string;
  content: string;
  attachmentUrl?: string | null;
  attachmentType?: string | null;
  source?: string;
  createdAt: string;
  readStatus?: { delivered: boolean; read: boolean };
  tempId?: string;
  clientStatus?: MessageClientStatus;
}

export interface MessagesResponse {
  messages: ChatMessageItem[];
  hasMore: boolean;
}

export interface AiRecommendationItem {
  roomId: string;
  contractProbability: number | null;
  contractLabel: string | null;
  sentiment: string | null;
  intent: string | null;
  customerTags: string[];
  recommendations: Array<{
    id: string;
    text: string;
    confidence: number;
    approach?: string;
  }>;
  faq: Array<{ question: string; answer: string }>;
  aiModel: string | null;
  status: AiUpdateStatus;
  updatedAt: string;
}
