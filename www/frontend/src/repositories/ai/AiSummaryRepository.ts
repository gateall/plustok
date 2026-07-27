import type { AiSummaryResult } from '@/types/aiSummary.types';

/** Data access for AI Summary — mock until Codex M4 ships REST. */
export interface AiSummaryRepository {
  /** POST /admin/consults/{id}/ai-summary */
  generateSummary(consultId: string): Promise<AiSummaryResult>;
}
