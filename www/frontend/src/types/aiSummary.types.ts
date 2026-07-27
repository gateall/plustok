/**
 * AI Summary contracts — SSOT: CRM_Enterprise_PM/03_AI_SYSTEM/AI_SUMMARY.md
 * POST /api/v1/admin/consults/{id}/ai-summary
 */

/** Successful generate / cached summary payload (API `data`). */
export type AiSummaryResult = {
  consultId: string;
  summary: string;
  generatedAt: string;
  provider: string;
};

/** Known error codes from AI_SUMMARY / AI_WORKFLOW UX contract. */
export type AiSummaryErrorCode = 'AI_UNAVAILABLE' | 'RATE_LIMITED' | 'FORBIDDEN' | 'UNKNOWN';

export class AiSummaryApiError extends Error {
  readonly code: AiSummaryErrorCode;
  readonly httpStatus?: number;

  constructor(code: AiSummaryErrorCode, message: string, httpStatus?: number) {
    super(message);
    this.name = 'AiSummaryApiError';
    this.code = code;
    this.httpStatus = httpStatus;
  }
}

export function mapAiSummaryError(err: unknown): AiSummaryApiError {
  if (err instanceof AiSummaryApiError) return err;
  const message = err instanceof Error ? err.message : String(err);
  const lower = message.toLowerCase();
  if (lower.includes('unavailable') || lower.includes('503')) {
    return new AiSummaryApiError('AI_UNAVAILABLE', 'AI 분석을 완료할 수 없습니다', 503);
  }
  if (lower.includes('429') || lower.includes('rate')) {
    return new AiSummaryApiError('RATE_LIMITED', '잠시 후 다시 시도해주세요', 429);
  }
  if (lower.includes('403') || lower.includes('forbidden')) {
    return new AiSummaryApiError('FORBIDDEN', '이 상담에 대한 AI 요약 권한이 없습니다', 403);
  }
  return new AiSummaryApiError('UNKNOWN', message || 'AI 요약 요청에 실패했습니다');
}

/** UX copy — AI_WORKFLOW.md §6 */
export const AI_SUMMARY_COPY = {
  emptyTitle: '아직 분석되지 않았습니다',
  emptyDescription: '상담 내용과 이력을 바탕으로 AI 요약을 생성할 수 있습니다.',
  generateLabel: 'AI 요약 생성',
  regenerateLabel: '다시 생성',
  loadingLabel: 'AI 요약 생성 중…',
  errorUnavailable: 'AI 분석을 완료할 수 없습니다',
  errorRateLimit: '잠시 후 다시 시도해주세요',
  retryLabel: '재시도',
} as const;
