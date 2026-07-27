/**

 * AI Recommendation (analyze) contracts — SSOT: CRM_Enterprise_PM/03_AI_SYSTEM/AI_RECOMMENDATION.md

 * POST /api/v1/admin/consults/{id}/ai-analyze

 */



/** Successful analyze payload (API `data`). */

export type AiRecommendationResult = {

  consultId: string;

  sentiment: string | null;

  priority: string | null;

  tags: string[];

  categoryAi: string | null;

  /** Always null until 12_CONTRACT — SSOT §4. */

  contractScore: number | null;

  confidence: number | null;

  analyzedAt: string;

};



/** Known error codes from AI_RECOMMENDATION / AI_WORKFLOW. */

export type AiRecommendationErrorCode =

  | 'AI_UNAVAILABLE'

  | 'AI_ANALYSIS_MALFORMED'

  | 'RATE_LIMITED'

  | 'FORBIDDEN'

  | 'UNKNOWN';



export class AiRecommendationApiError extends Error {

  readonly code: AiRecommendationErrorCode;

  readonly httpStatus?: number;



  constructor(code: AiRecommendationErrorCode, message: string, httpStatus?: number) {

    super(message);

    this.name = 'AiRecommendationApiError';

    this.code = code;

    this.httpStatus = httpStatus;

  }

}



export function mapAiRecommendationError(err: unknown): AiRecommendationApiError {

  if (err instanceof AiRecommendationApiError) return err;

  const message = err instanceof Error ? err.message : String(err);

  const lower = message.toLowerCase();

  if (lower.includes('malformed') || lower.includes('ai_analysis_malformed')) {

    return new AiRecommendationApiError(

      'AI_ANALYSIS_MALFORMED',

      'AI 분석 결과를 해석할 수 없습니다',

      503,

    );

  }

  if (lower.includes('unavailable') || lower.includes('503')) {

    return new AiRecommendationApiError('AI_UNAVAILABLE', 'AI 분석을 완료할 수 없습니다', 503);

  }

  if (lower.includes('429') || lower.includes('rate')) {

    return new AiRecommendationApiError('RATE_LIMITED', '잠시 후 다시 시도해주세요', 429);

  }

  if (lower.includes('403') || lower.includes('forbidden')) {

    return new AiRecommendationApiError('FORBIDDEN', '이 상담에 대한 AI 분석 권한이 없습니다', 403);

  }

  return new AiRecommendationApiError('UNKNOWN', message || 'AI 추천 분석 요청에 실패했습니다');

}



/** Cursor UX: confidence below this shows "낮은 신뢰도" badge (SSOT §4). */

export const AI_RECOMMENDATION_LOW_CONFIDENCE = 0.7;



export const SENTIMENT_LABELS: Record<string, string> = {

  positive: '긍정',

  neutral: '중립',

  negative: '부정',

  mixed: '혼합',

};



export const PRIORITY_LABELS: Record<string, string> = {

  urgent: '긴급',

  high: '높음',

  normal: '보통',

  medium: '보통',

  low: '낮음',

};



export function labelSentiment(value: string | null | undefined): string {

  if (!value) return '—';

  return SENTIMENT_LABELS[value.toLowerCase()] ?? value;

}



export function labelPriority(value: string | null | undefined): string {

  if (!value) return '—';

  return PRIORITY_LABELS[value.toLowerCase()] ?? value;

}



export function isLowConfidence(confidence: number | null | undefined): boolean {

  return typeof confidence === 'number' && confidence < AI_RECOMMENDATION_LOW_CONFIDENCE;

}



/** UX copy — AI_WORKFLOW.md §6 + AI_RECOMMENDATION.md */

export const AI_RECOMMENDATION_COPY = {

  title: 'AI 추천 분석',

  emptyTitle: '아직 추천 분석이 없습니다',

  emptyDescription: '감정·긴급도·태그·카테고리를 AI가 추천합니다.',

  generateLabel: 'AI 분석 실행',

  regenerateLabel: '다시 분석',

  loadingLabel: 'AI 분석 중…',

  errorUnavailable: 'AI 분석을 완료할 수 없습니다',

  errorMalformed: 'AI 분석 결과를 해석할 수 없습니다',

  errorRateLimit: '잠시 후 다시 시도해주세요',

  retryLabel: '재시도',

  lowConfidenceBadge: '낮은 신뢰도',

  contractScorePending: '계약 연동 전',

} as const;


