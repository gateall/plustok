import { AiSummaryApiError, type AiSummaryResult } from '@/types/aiSummary.types';
import type { AiSummaryRepository } from './AiSummaryRepository';

const MOCK_LATENCY_MS = 650;

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

const MOCK_SUMMARIES: Record<string, string> = {
  default:
    '고객은 인터넷 설치 문의로 시작해 요금제 비교를 요청했으며 KT 기준 설치를 희망함.',
};

/**
 * Mock AI Summary — SSOT response shape; ready to swap for live API (Codex M4).
 * Set `sessionStorage.plustok_ai_summary_fail=1` to simulate AI_UNAVAILABLE in UI tests.
 */
export class MockAiSummaryRepository implements AiSummaryRepository {
  async generateSummary(consultId: string): Promise<AiSummaryResult> {
    await delay(MOCK_LATENCY_MS);

    if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem('plustok_ai_summary_fail') === '1') {
      throw new AiSummaryApiError('AI_UNAVAILABLE', 'AI 분석을 완료할 수 없습니다', 503);
    }

    const summary = MOCK_SUMMARIES[consultId] ?? MOCK_SUMMARIES.default;
    return {
      consultId,
      summary,
      generatedAt: new Date().toISOString(),
      provider: 'mock',
    };
  }
}

export const mockAiSummaryRepository = new MockAiSummaryRepository();
