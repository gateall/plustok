import type { AiRecommendationResult } from '@/types/aiRecommendation.types';



/** Data access for AI Recommendation — mock until Codex M4 ships REST. */

export interface AiRecommendationRepository {

  /** POST /admin/consults/{id}/ai-analyze */

  analyze(consultId: string): Promise<AiRecommendationResult>;

}


