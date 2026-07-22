import { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchAiRecommendations } from '@/services/chat.service';
import { useSocket } from '@/hooks/useSocket';
import type { AiRecommendationItem } from '@/types/chat.types';

export const aiQueryKey = (roomId: string) => ['ai', 'recommendations', roomId] as const;

export function useAiRecommendations(roomId: string | null) {
  const queryClient = useQueryClient();
  const { on } = useSocket();

  const query = useQuery({
    queryKey: roomId ? aiQueryKey(roomId) : ['ai', 'none'],
    queryFn: () => fetchAiRecommendations(roomId!),
    enabled: !!roomId,
    staleTime: 10_000,
    refetchInterval: (q) => {
      const status = q.state.data?.status;
      return status === 'pending' || status === 'processing' ? 5000 : false;
    },
  });

  useEffect(() => {
    if (!roomId) return undefined;
    return on('ai:update', (payload) => {
      if (payload.roomId !== roomId) return;
      if (payload.status === 'completed' || payload.status === 'failed') {
        queryClient.invalidateQueries({ queryKey: aiQueryKey(roomId) });
        return;
      }
      queryClient.setQueryData<AiRecommendationItem>(aiQueryKey(roomId), (old) => {
        if (!old) return old;
        return {
          ...old,
          status: payload.status,
          contractProbability: payload.contractProbability ?? old.contractProbability,
          updatedAt: payload.timestamp,
        };
      });
    });
  }, [roomId, on, queryClient]);

  return {
    recommendation: query.data ?? null,
    isLoading: query.isLoading,
    isProcessing: query.data?.status === 'processing' || query.data?.status === 'pending',
    refetch: query.refetch,
  };
}
