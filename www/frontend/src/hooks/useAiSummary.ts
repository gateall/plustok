import { useMutation, useQueryClient } from '@tanstack/react-query';
import { aiSummaryService } from '@/services/aiSummary.service';
import { mapAiSummaryError, type AiSummaryResult } from '@/types/aiSummary.types';
import { consultDetailQueryKey } from '@/hooks/useConsultDetail';

export const aiSummaryQueryKey = (consultId: string) =>
  ['admin', 'consults', 'ai-summary', consultId] as const;

/**
 * Generate AI summary for a consult (POST ai-summary).
 * On success, patches consult detail cache with aiSummary / aiSummaryAt.
 */
export function useAiSummary(consultId: string | undefined) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: async (): Promise<AiSummaryResult> => {
      if (!consultId) throw new Error('상담 ID가 없습니다.');
      try {
        return await aiSummaryService.generateSummary(consultId);
      } catch (err) {
        throw mapAiSummaryError(err);
      }
    },
    onSuccess: (data) => {
      if (!consultId) return;
      queryClient.setQueryData(consultDetailQueryKey(consultId), (old: unknown) => {
        if (!old || typeof old !== 'object') return old;
        return {
          ...old,
          aiSummary: data.summary,
          aiSummaryAt: data.generatedAt,
        };
      });
      queryClient.setQueryData(aiSummaryQueryKey(consultId), data);
    },
  });

  return {
    generate: mutation.mutateAsync,
    isGenerating: mutation.isPending,
    error: mutation.error ? mapAiSummaryError(mutation.error) : null,
    result: mutation.data ?? null,
    reset: mutation.reset,
  };
}
