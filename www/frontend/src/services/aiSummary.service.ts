import { createAiSummaryRepository } from '@/repositories/ai';
import type { AiSummaryResult } from '@/types/aiSummary.types';

const repo = createAiSummaryRepository();

/** Facade — mock or live API via resolveDataSource() (Sites pattern). */
export const aiSummaryService = {
  generateSummary(consultId: string): Promise<AiSummaryResult> {
    return repo.generateSummary(consultId);
  },
};
