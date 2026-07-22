import clsx from 'clsx';
import type { AiRecommendationItem } from '@/types/chat.types';

interface AIRecommendationProps {
  recommendation: AiRecommendationItem | null;
  isLoading: boolean;
  isProcessing: boolean;
}

export function AIRecommendationPanel({
  recommendation,
  isLoading,
  isProcessing,
}: AIRecommendationProps) {
  return (
    <div className="flex h-full flex-col p-4">
      <h3 className="mb-3 text-sm font-bold text-slate-900">AI 추천</h3>

      {isLoading && !recommendation && (
        <p className="text-sm text-slate-500">불러오는 중…</p>
      )}

      {!isLoading && !recommendation && (
        <p className="text-sm text-slate-500">상담방을 선택하세요.</p>
      )}

      {isProcessing && (
        <div className="mb-4 flex items-center gap-2 text-sm text-acep-primary">
          <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-acep-primary border-t-transparent" />
          분석 중…
        </div>
      )}

      {recommendation?.status === 'failed' && (
        <p className="text-sm text-red-600">AI 분석에 실패했습니다.</p>
      )}

      {recommendation && recommendation.status === 'completed' && (
        <div className="space-y-4">
          {recommendation.contractProbability != null && (
            <div>
              <span className="text-xs text-slate-500">계약확률</span>
              <div className="text-2xl font-bold text-acep-primary">
                {recommendation.contractProbability}%
              </div>
              {recommendation.contractLabel && (
                <p className="text-xs text-slate-500">{recommendation.contractLabel}</p>
              )}
            </div>
          )}

          {recommendation.sentiment && (
            <div>
              <span className="text-xs text-slate-500">감정</span>
              <p className="text-sm capitalize">{recommendation.sentiment}</p>
            </div>
          )}

          {recommendation.intent && (
            <div>
              <span className="text-xs text-slate-500">의도</span>
              <p className="text-sm">{recommendation.intent}</p>
            </div>
          )}

          <div className="space-y-2">
            <span className="text-xs text-slate-500">추천 답변</span>
            {recommendation.recommendations.length === 0 && (
              <p className="text-sm text-slate-500">추천 없음</p>
            )}
            {recommendation.recommendations.map((rec) => (
              <div
                key={rec.id}
                className={clsx(
                  'rounded-lg border border-acep-border bg-blue-50 p-2 text-sm',
                  rec.confidence >= 0.9 && 'border-acep-primary',
                )}
              >
                <p>{rec.text}</p>
                <p className="mt-1 text-[10px] text-slate-500">
                  신뢰도 {(rec.confidence * 100).toFixed(0)}%
                </p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
