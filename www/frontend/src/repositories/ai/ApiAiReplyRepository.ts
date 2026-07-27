import { apiFetch } from '@/services/api.client';
import { AiReplyApiError, mapAiReplyError, type AiReplyDraftResult } from '@/types/aiReply.types';
import type { AiReplyRepository } from './AiReplyRepository';
import { mockAiReplyRepository } from './MockAiReplyRepository';

function isNotFound(err: unknown): boolean {
  const msg = err instanceof Error ? err.message : String(err);
  return /404|not found|경로를 찾을 수 없/i.test(msg);
}

/**
 * Live POST /api/v1/admin/consults/{id}/ai-reply-draft — AI_REPLY.md §5.
 * 404 → mock fallback (Sites KEEP). 503 / 429 / other errors surface to UI.
 */
export class ApiAiReplyRepository implements AiReplyRepository {
  async generateReplyDraft(consultId: string): Promise<AiReplyDraftResult> {
    try {
      return await apiFetch<AiReplyDraftResult>(
        `/admin/consults/${encodeURIComponent(consultId)}/ai-reply-draft`,
        { method: 'POST' },
      );
    } catch (err) {
      if (isNotFound(err)) {
        return mockAiReplyRepository.generateReplyDraft(consultId);
      }
      throw mapAiReplyError(err);
    }
  }
}

export const apiAiReplyRepository = new ApiAiReplyRepository();

export { AiReplyApiError };
