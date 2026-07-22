import { apiFetch } from './api.client';
import type {
  AiRecommendationItem,
  ChatRoomsResponse,
  MessagesResponse,
} from '@/types/chat.types';

export function fetchChatRooms(params?: { status?: string; search?: string }): Promise<ChatRoomsResponse> {
  const qs = new URLSearchParams();
  if (params?.status) qs.set('status', params.status);
  if (params?.search) qs.set('search', params.search);
  const q = qs.toString();
  return apiFetch<ChatRoomsResponse>(`/chats/rooms${q ? `?${q}` : ''}`);
}

export function fetchMessages(roomId: string, limit = 50): Promise<MessagesResponse> {
  return apiFetch<MessagesResponse>(`/chats/${roomId}/messages?limit=${limit}`);
}

export function postMessage(
  roomId: string,
  content: string,
  source: 'manual' | 'ai_recommendation' = 'manual',
): Promise<{ messageId: string; createdAt: string }> {
  return apiFetch<{ messageId: string; createdAt: string }>(`/chats/${roomId}/messages`, {
    method: 'POST',
    body: JSON.stringify({ content, source }),
  });
}

export function fetchAiRecommendations(roomId: string): Promise<AiRecommendationItem> {
  return apiFetch<AiRecommendationItem>(`/ai/recommendations/${roomId}`);
}
