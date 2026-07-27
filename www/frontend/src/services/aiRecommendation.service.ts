import { createAiRecommendationRepository } from '@/repositories/ai';

import type { AiRecommendationResult } from '@/types/aiRecommendation.types';



const repo = createAiRecommendationRepository();



/** Facade — mock or live API via resolveDataSource() (Sites pattern). */

export const aiRecommendationService = {

  analyze(consultId: string): Promise<AiRecommendationResult> {

    return repo.analyze(consultId);

  },

};


