import { apiFetch } from '@/services/api.client';
import { AiSummaryApiError, mapAiSummaryError, type AiSummaryResult } from '@/types/aiSummary.types';
import type { AiSummaryRepository } from './AiSummaryRepository';
import { mockAiSummaryRepository } from './MockAiSummaryRepository';

function isNotFound(err: unknown): boolean {
  const msg = err instanceof Error ? err.message : String(err);
  return /404|not found|경로를 찾을 수 없/i.test(msg);
}

/**
 * Live POST /api/v1/admin/consults/{id}/ai-summary — AI_SUMMARY.md §5.
 * 404 (endpoint not yet shipped) → mock fallback (Sites pattern KEEP).
 * 503 / 429 / other errors surface to UI (no silent mock on provider failure).
 */
export class ApiAiSummaryRepository implements AiSummaryRepository {
  async generateSummary(consultId: string): Promise<AiSummaryResult> {
    try {
      return await apiFetch<AiSummaryResult>(
        `/admin/consults/${encodeURIComponent(consultId)}/ai-summary`,
        { method: 'POST' },
      );
    } catch (err) {
      if (isNotFound(err)) {
        return mockAiSummaryRepository.generateSummary(consultId);
      }
      throw mapAiSummaryError(err);
    }
  }
}

export const apiAiSummaryRepository = new ApiAiSummaryRepository();

export { AiSummaryApiError };
