import { Lightbulb, RefreshCw } from 'lucide-react';
import clsx from 'clsx';
import { Badge, Button, Card } from '@/components/admin-ui';
import { AiEmptyState, AiErrorState, AiLoadingState } from '@/components/ai/AiFeatureState';
import { useAiRecommendation } from '@/hooks/useAiRecommendation';
import {
  AI_RECOMMENDATION_COPY,
  isLowConfidence,
  labelPriority,
  labelSentiment,
  type AiRecommendationApiError,
  type AiRecommendationResult,
} from '@/types/aiRecommendation.types';

export type AiRecommendationCardProps = {
  consultId: string;
  /** Existing fields from GET consult detail (AI_RECOMMENDATION.md §5). */
  initialSentiment?: string | null;
  initialPriority?: string | null;
  initialTags?: string[] | null;
  initialCategoryAi?: string | null;
  initialContractScore?: number | null;
  initialConfidence?: number | null;
  initialAnalyzedAt?: string | null;
  className?: string;
  compact?: boolean;
};

function formatAnalyzedAt(iso: string | null | undefined): string | null {
  if (!iso) return null;
  try {
    return new Intl.DateTimeFormat('ko-KR', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

function errorMessage(err: AiRecommendationApiError): string {
  if (err.code === 'AI_UNAVAILABLE') return AI_RECOMMENDATION_COPY.errorUnavailable;
  if (err.code === 'AI_ANALYSIS_MALFORMED') return AI_RECOMMENDATION_COPY.errorMalformed;
  if (err.code === 'RATE_LIMITED') return AI_RECOMMENDATION_COPY.errorRateLimit;
  return err.message;
}

function hasAnalyzedData(r: AiRecommendationResult | null): boolean {
  if (!r) return false;
  return Boolean(
    r.sentiment ||
      r.priority ||
      r.categoryAi ||
      (r.tags && r.tags.length > 0) ||
      r.analyzedAt,
  );
}

function fromInitial(props: AiRecommendationCardProps): AiRecommendationResult | null {
  const {
    consultId,
    initialSentiment,
    initialPriority,
    initialTags,
    initialCategoryAi,
    initialContractScore,
    initialConfidence,
    initialAnalyzedAt,
  } = props;

  const tags = initialTags ?? [];
  const hasAny =
    Boolean(initialSentiment) ||
    Boolean(initialPriority) ||
    Boolean(initialCategoryAi) ||
    tags.length > 0 ||
    Boolean(initialAnalyzedAt);

  if (!hasAny) return null;

  return {
    consultId,
    sentiment: initialSentiment ?? null,
    priority: initialPriority ?? null,
    tags,
    categoryAi: initialCategoryAi ?? null,
    contractScore: initialContractScore ?? null,
    confidence: initialConfidence ?? null,
    analyzedAt: initialAnalyzedAt ?? '',
  };
}

function ResultBody({ data, compact }: { data: AiRecommendationResult; compact?: boolean }) {
  const low = isLowConfidence(data.confidence);
  const confidencePct =
    typeof data.confidence === 'number' ? `${Math.round(data.confidence * 100)}%` : null;

  return (
    <div className={clsx('space-y-3', compact && 'space-y-2')}>
      <div className="flex flex-wrap items-center gap-1.5">
        {low ? <Badge label={AI_RECOMMENDATION_COPY.lowConfidenceBadge} tone="warning" /> : null}
        {confidencePct ? (
          <Badge label={`신뢰도 ${confidencePct}`} tone={low ? 'warning' : 'success'} />
        ) : null}
        {data.priority ? (
          <Badge
            label={`긴급도 ${labelPriority(data.priority)}`}
            tone={
              data.priority.toLowerCase() === 'urgent' || data.priority.toLowerCase() === 'high'
                ? 'danger'
                : 'info'
            }
          />
        ) : null}
      </div>

      <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-xs text-slate-500">감정</dt>
          <dd className="mt-0.5 font-medium text-slate-900">{labelSentiment(data.sentiment)}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">긴급도</dt>
          <dd className="mt-0.5 font-medium text-slate-900">{labelPriority(data.priority)}</dd>
        </div>
        <div className="col-span-2">
          <dt className="text-xs text-slate-500">카테고리</dt>
          <dd className="mt-0.5 break-words font-medium text-slate-900">
            {data.categoryAi ?? '—'}
          </dd>
        </div>
        <div className="col-span-2">
          <dt className="text-xs text-slate-500">계약확률</dt>
          <dd className="mt-0.5 font-medium text-slate-900">
            {data.contractScore != null
              ? `${Math.round(data.contractScore)}%`
              : AI_RECOMMENDATION_COPY.contractScorePending}
          </dd>
        </div>
      </dl>

      {data.tags.length > 0 ? (
        <div>
          <p className="text-xs text-slate-500">추천 태그</p>
          <ul className="mt-1.5 flex flex-wrap gap-1.5" aria-label="AI 추천 태그">
            {data.tags.map((tag) => (
              <li key={tag}>
                <Badge label={tag} tone="neutral" />
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}

/**
 * Mobile First AI Recommendation card — AI_RECOMMENDATION.md + AI_WORKFLOW.md §6.
 * States: Empty · Loading · Success · Error(+retry). Mock until Codex M4.
 * Low confidence: show badge, still display tags/sentiment (SSOT §4).
 */
export default function AiRecommendationCard({
  consultId,
  initialSentiment = null,
  initialPriority = null,
  initialTags = null,
  initialCategoryAi = null,
  initialContractScore = null,
  initialConfidence = null,
  initialAnalyzedAt = null,
  className,
  compact = false,
}: AiRecommendationCardProps) {
  const { analyze, isAnalyzing, error, result, reset } = useAiRecommendation(consultId);

  const display: AiRecommendationResult | null =
    result ??
    fromInitial({
      consultId,
      initialSentiment,
      initialPriority,
      initialTags,
      initialCategoryAi,
      initialContractScore,
      initialConfidence,
      initialAnalyzedAt,
    });

  const hasData = hasAnalyzedData(display);
  const analyzedLabel = formatAnalyzedAt(display?.analyzedAt);

  const onAnalyze = async () => {
    reset();
    try {
      await analyze();
    } catch {
      /* error surfaced via hook */
    }
  };

  return (
    <Card
      as="section"
      padding={compact ? 'sm' : 'md'}
      className={clsx('ai-recommendation-card min-w-0', className)}
      aria-label="AI 추천 분석"
    >
      <header className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h3 className="flex items-center gap-1.5 text-sm font-semibold text-slate-900">
            <Lightbulb className="h-4 w-4 shrink-0 text-indigo-600" aria-hidden />
            {AI_RECOMMENDATION_COPY.title}
          </h3>
          {analyzedLabel ? (
            <p className="mt-0.5 text-xs text-slate-500">{analyzedLabel}</p>
          ) : null}
        </div>
        {hasData && !isAnalyzing ? (
          <Button
            type="button"
            variant="ghost"
            className="!min-h-10 shrink-0 px-3 text-xs"
            icon={<RefreshCw className="h-3.5 w-3.5" aria-hidden />}
            onClick={onAnalyze}
            disabled={isAnalyzing}
          >
            {AI_RECOMMENDATION_COPY.regenerateLabel}
          </Button>
        ) : null}
      </header>

      <div className="mt-3">
        {isAnalyzing ? <AiLoadingState label={AI_RECOMMENDATION_COPY.loadingLabel} /> : null}

        {!isAnalyzing && error ? (
          <AiErrorState
            title={errorMessage(error)}
            retryLabel={AI_RECOMMENDATION_COPY.retryLabel}
            onRetry={() => {
              void onAnalyze();
            }}
          >
            {hasData && display ? <ResultBody data={display} compact={compact} /> : null}
          </AiErrorState>
        ) : null}

        {!isAnalyzing && !error && !hasData ? (
          <AiEmptyState
            title={AI_RECOMMENDATION_COPY.emptyTitle}
            description={AI_RECOMMENDATION_COPY.emptyDescription}
            action={
              <Button
                type="button"
                variant="primary"
                className="!min-h-11 w-full max-w-xs sm:w-auto"
                icon={<Lightbulb className="h-4 w-4" aria-hidden />}
                onClick={onAnalyze}
              >
                {AI_RECOMMENDATION_COPY.generateLabel}
              </Button>
            }
          />
        ) : null}

        {!isAnalyzing && !error && hasData && display ? (
          <ResultBody data={display} compact={compact} />
        ) : null}
      </div>
    </Card>
  );
}
