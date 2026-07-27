import { useMutation } from '@tanstack/react-query';
import { aiReplyService } from '@/services/aiReply.service';
import { mapAiReplyError, type AiReplyDraftResult } from '@/types/aiReply.types';

export const aiReplyQueryKey = (consultId: string) =>
  ['admin', 'consults', 'ai-reply', consultId] as const;

/**
 * Generate AI reply draft for a consult (POST ai-reply-draft).
 * No DB persist on accept — out of AI_REPLY Tier 1 scope.
 */
export function useAiReply(consultId: string | undefined) {
  const mutation = useMutation({
    mutationFn: async (): Promise<AiReplyDraftResult> => {
      if (!consultId) throw new Error('상담 ID가 없습니다.');
      try {
        return await aiReplyService.generateReplyDraft(consultId);
      } catch (err) {
        throw mapAiReplyError(err);
      }
    },
  });

  return {
    generate: mutation.mutateAsync,
    isGenerating: mutation.isPending,
    error: mutation.error ? mapAiReplyError(mutation.error) : null,
    result: mutation.data ?? null,
    reset: mutation.reset,
  };
}
