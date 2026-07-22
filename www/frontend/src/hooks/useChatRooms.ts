import { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchChatRooms } from '@/services/chat.service';
import { useSocket } from '@/hooks/useSocket';
import type { ChatRoomItem } from '@/types/chat.types';

export const chatRoomsQueryKey = ['chats', 'rooms'] as const;

export function useChatRooms(search?: string) {
  const queryClient = useQueryClient();
  const { on } = useSocket();

  const query = useQuery({
    queryKey: [...chatRoomsQueryKey, search ?? ''],
    queryFn: () => fetchChatRooms(search ? { search } : undefined),
    staleTime: 15_000,
  });

  useEffect(() => {
    return on('room:update', () => {
      queryClient.invalidateQueries({ queryKey: chatRoomsQueryKey });
    });
  }, [on, queryClient]);

  return {
    rooms: (query.data?.rooms ?? []) as ChatRoomItem[],
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
  };
}
