import type { AiReplyDraftResult } from '@/types/aiReply.types';

/** AI Reply draft repository — mock or live via resolveDataSource(). */
export interface AiReplyRepository {
  /** POST /admin/consults/{id}/ai-reply-draft */
  generateReplyDraft(consultId: string): Promise<AiReplyDraftResult>;
}
