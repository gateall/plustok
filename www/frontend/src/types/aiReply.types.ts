/**
 * AI Reply draft contracts — SSOT: CRM_Enterprise_PM/03_AI_SYSTEM/AI_REPLY.md
 * POST /api/v1/admin/consults/{id}/ai-reply-draft
 */

export type AiReplyDraftResult = {
  consultId: string;
  draft: string;
  toneNote?: string | null;
  generatedAt: string;
};

export type AiReplyErrorCode = 'AI_UNAVAILABLE' | 'RATE_LIMITED' | 'FORBIDDEN' | 'UNKNOWN';

export class AiReplyApiError extends Error {
  readonly code: AiReplyErrorCode;
  readonly httpStatus?: number;

  constructor(code: AiReplyErrorCode, message: string, httpStatus?: number) {
    super(message);
    this.name = 'AiReplyApiError';
    this.code = code;
    this.httpStatus = httpStatus;
  }
}

export function mapAiReplyError(err: unknown): AiReplyApiError {
  if (err instanceof AiReplyApiError) return err;
  const message = err instanceof Error ? err.message : String(err);
  const lower = message.toLowerCase();
  if (lower.includes('unavailable') || lower.includes('503')) {
    return new AiReplyApiError('AI_UNAVAILABLE', AI_REPLY_COPY.errorUnavailable, 503);
  }
  if (lower.includes('429') || lower.includes('rate')) {
    return new AiReplyApiError('RATE_LIMITED', '잠시 후 다시 시도해주세요', 429);
  }
  if (lower.includes('403') || lower.includes('forbidden')) {
    return new AiReplyApiError('FORBIDDEN', '이 상담에 대한 AI 답변 권한이 없습니다', 403);
  }
  return new AiReplyApiError('UNKNOWN', message || AI_REPLY_COPY.errorUnavailable);
}

export const AI_REPLY_COPY = {
  title: 'AI 답변 초안',
  generateLabel: 'AI 답변초안',
  regenerateLabel: '다시 생성',
  loadingLabel: '답변 초안 생성 중…',
  emptyTitle: '아직 답변 초안이 없습니다',
  emptyHint: '브랜드 페르소나를 반영한 답변 초안을 생성합니다.',
  editLabel: '편집',
  acceptLabel: '수락',
  rejectLabel: '폐기',
  errorUnavailable: 'AI 분석을 완료할 수 없습니다',
  retryLabel: '재시도',
} as const;
