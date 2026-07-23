import { useEffect } from 'react';
import toast from 'react-hot-toast';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchChatRooms } from '@/services/chat.service';
import { useAuth } from '@/features/auth/AuthProvider';
import { useSocket } from '@/hooks/useSocket';
import type { ChatRoomItem } from '@/types/chat.types';
import type { RoomUpdatePayload } from '@/types/socket-events';

export const chatRoomsQueryKey = ['chats', 'rooms'] as const;

const AGENT_ROLES = new Set(['agent', 'admin', 'operator']);

function isAgentRole(role: string | undefined): boolean {
  return role !== undefined && AGENT_ROLES.has(role);
}

function notifyNewChatQueueItem(payload: RoomUpdatePayload): void {
  if (payload.status !== 'new') {
    return;
  }
  if (payload.agentId) {
    return;
  }

  const customer = payload.customerName ?? '고객';
  const inquiry = payload.inquiryType ?? '문의';
  toast(`새 상담: ${customer} — ${inquiry}`, {
    icon: '💬',
    duration: 6000,
  });
}

export function useChatRooms(search?: string) {
  const queryClient = useQueryClient();
  const { on } = useSocket();
  const { user } = useAuth();

  const query = useQuery({
    queryKey: [...chatRoomsQueryKey, search ?? ''],
    queryFn: () => fetchChatRooms(search ? { search } : undefined),
    staleTime: 15_000,
  });

  useEffect(() => {
    return on('room:update', (payload: RoomUpdatePayload) => {
      if (isAgentRole(user?.role)) {
        notifyNewChatQueueItem(payload);
      }
      queryClient.invalidateQueries({ queryKey: chatRoomsQueryKey });
    });
  }, [on, queryClient, user?.role]);

  return {
    rooms: (query.data?.rooms ?? []) as ChatRoomItem[],
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
  };
}
