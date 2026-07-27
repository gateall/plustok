import { createAiReplyRepository } from '@/repositories/ai';
import type { AiReplyDraftResult } from '@/types/aiReply.types';

const repo = createAiReplyRepository();

/** Facade — mock or live API via resolveDataSource() (Sites pattern). */
export const aiReplyService = {
  generateReplyDraft(consultId: string): Promise<AiReplyDraftResult> {
    return repo.generateReplyDraft(consultId);
  },
};
