import { useMutation, useQueryClient } from '@tanstack/react-query';

import { aiRecommendationService } from '@/services/aiRecommendation.service';

import {

  mapAiRecommendationError,

  type AiRecommendationResult,

} from '@/types/aiRecommendation.types';

import { consultDetailQueryKey } from '@/hooks/useConsultDetail';



export const aiRecommendationQueryKey = (consultId: string) =>

  ['admin', 'consults', 'ai-analyze', consultId] as const;



/**

 * Run AI recommendation analysis for a consult (POST ai-analyze).

 * On success, patches consult detail cache with recommendation fields.

 */

export function useAiRecommendation(consultId: string | undefined) {

  const queryClient = useQueryClient();



  const mutation = useMutation({

    mutationFn: async (): Promise<AiRecommendationResult> => {

      if (!consultId) throw new Error('상담 ID가 없습니다.');

      try {

        return await aiRecommendationService.analyze(consultId);

      } catch (err) {

        throw mapAiRecommendationError(err);

      }

    },

    onSuccess: (data) => {

      if (!consultId) return;

      queryClient.setQueryData(consultDetailQueryKey(consultId), (old: unknown) => {

        if (!old || typeof old !== 'object') return old;

        return {

          ...old,

          sentiment: data.sentiment,

          priority: data.priority ?? (old as { priority?: string }).priority,

          categoryAi: data.categoryAi,

          contractScore: data.contractScore,

          aiConfidence: data.confidence,

          aiAnalyzedAt: data.analyzedAt,

          aiTags: data.tags,

        };

      });

      queryClient.setQueryData(aiRecommendationQueryKey(consultId), data);

    },

  });



  return {

    analyze: mutation.mutateAsync,

    isAnalyzing: mutation.isPending,

    error: mutation.error ? mapAiRecommendationError(mutation.error) : null,

    result: mutation.data ?? null,

    reset: mutation.reset,

  };

}


