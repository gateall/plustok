import { AiReplyApiError, type AiReplyDraftResult } from '@/types/aiReply.types';
import type { AiReplyRepository } from './AiReplyRepository';

const MOCK_LATENCY_MS = 700;

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Mock AI Reply — SSOT response shape (AI_REPLY.md §5).
 * sessionStorage `plustok_ai_reply_fail=1` → AI_UNAVAILABLE.
 */
export class MockAiReplyRepository implements AiReplyRepository {
  async generateReplyDraft(consultId: string): Promise<AiReplyDraftResult> {
    await delay(MOCK_LATENCY_MS);

    if (
      typeof sessionStorage !== 'undefined' &&
      sessionStorage.getItem('plustok_ai_reply_fail') === '1'
    ) {
      throw new AiReplyApiError('AI_UNAVAILABLE', 'AI 분석을 완료할 수 없습니다', 503);
    }

    return {
      consultId,
      draft:
        '안녕하세요, 문의주신 인터넷 설치 관련하여 안내드립니다. 원하시는 요금제와 설치 가능 일정을 확인한 뒤 담당자가 상세히 안내드리겠습니다.',
      toneNote: '브랜드 페르소나 반영됨(mock)',
      generatedAt: new Date().toISOString(),
    };
  }
}

export const mockAiReplyRepository = new MockAiReplyRepository();
