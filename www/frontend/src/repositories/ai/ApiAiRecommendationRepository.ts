import { apiFetch } from '@/services/api.client';

import {

  AiRecommendationApiError,

  mapAiRecommendationError,

  type AiRecommendationResult,

} from '@/types/aiRecommendation.types';

import type { AiRecommendationRepository } from './AiRecommendationRepository';

import { mockAiRecommendationRepository } from './MockAiRecommendationRepository';



function isNotFound(err: unknown): boolean {

  const msg = err instanceof Error ? err.message : String(err);

  return /404|not found|경로를 찾을 수 없/i.test(msg);

}



/**

 * Live POST /api/v1/admin/consults/{id}/ai-analyze — AI_RECOMMENDATION.md §5.

 * 404 (endpoint not yet shipped) → mock fallback (Sites pattern KEEP).

 * 503 / 429 / malformed / other errors surface to UI (no silent mock on provider failure).

 */

export class ApiAiRecommendationRepository implements AiRecommendationRepository {

  async analyze(consultId: string): Promise<AiRecommendationResult> {

    try {

      return await apiFetch<AiRecommendationResult>(

        `/admin/consults/${encodeURIComponent(consultId)}/ai-analyze`,

        { method: 'POST' },

      );

    } catch (err) {

      if (isNotFound(err)) {

        return mockAiRecommendationRepository.analyze(consultId);

      }

      throw mapAiRecommendationError(err);

    }

  }

}



export const apiAiRecommendationRepository = new ApiAiRecommendationRepository();



export { AiRecommendationApiError };


