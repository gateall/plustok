import { RefreshCw, Sparkles } from 'lucide-react';
import clsx from 'clsx';
import { Button, Card } from '@/components/admin-ui';
import { AiEmptyState, AiErrorState, AiLoadingState } from '@/components/ai/AiFeatureState';
import { useAiSummary } from '@/hooks/useAiSummary';
import {
  AI_SUMMARY_COPY,
  type AiSummaryApiError,
  type AiSummaryResult,
} from '@/types/aiSummary.types';

export type AiSummaryCardProps = {
  consultId: string;
  /** Existing summary from GET consult detail (aiSummary). */
  initialSummary?: string | null;
  /** Existing timestamp from GET consult detail (aiSummaryAt). */
  initialGeneratedAt?: string | null;
  className?: string;
  compact?: boolean;
};

function formatGeneratedAt(iso: string | null | undefined): string | null {
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

function errorMessage(err: AiSummaryApiError): string {
  if (err.code === 'AI_UNAVAILABLE') return AI_SUMMARY_COPY.errorUnavailable;
  if (err.code === 'RATE_LIMITED') return AI_SUMMARY_COPY.errorRateLimit;
  return err.message;
}

/**
 * Mobile First AI Summary card — AI_SUMMARY.md + AI_WORKFLOW.md §6.
 * States: Empty · Loading · Success · Error(+retry). Mock until Codex M4.
 */
export default function AiSummaryCard({
  consultId,
  initialSummary = null,
  initialGeneratedAt = null,
  className,
  compact = false,
}: AiSummaryCardProps) {
  const { generate, isGenerating, error, result, reset } = useAiSummary(consultId);

  const display: AiSummaryResult | null = result
    ? result
    : initialSummary?.trim()
      ? {
          consultId,
          summary: initialSummary.trim(),
          generatedAt: initialGeneratedAt ?? '',
          provider: '',
        }
      : null;

  const hasSummary = Boolean(display?.summary?.trim());
  const generatedLabel = formatGeneratedAt(display?.generatedAt);

  const onGenerate = async () => {
    reset();
    try {
      await generate();
    } catch {
      /* error surfaced via hook */
    }
  };

  return (
    <Card
      as="section"
      padding={compact ? 'sm' : 'md'}
      className={clsx('ai-summary-card min-w-0', className)}
      aria-label="AI 요약"
    >
      <header className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h3 className="flex items-center gap-1.5 text-sm font-semibold text-slate-900">
            <Sparkles className="h-4 w-4 shrink-0 text-indigo-600" aria-hidden />
            AI 요약
          </h3>
          {generatedLabel ? (
            <p className="mt-0.5 text-xs text-slate-500">{generatedLabel}</p>
          ) : null}
        </div>
        {hasSummary && !isGenerating ? (
          <Button
            type="button"
            variant="ghost"
            className="!min-h-10 shrink-0 px-3 text-xs"
            icon={<RefreshCw className="h-3.5 w-3.5" aria-hidden />}
            onClick={onGenerate}
            disabled={isGenerating}
          >
            {AI_SUMMARY_COPY.regenerateLabel}
          </Button>
        ) : null}
      </header>

      <div className="mt-3">
        {isGenerating ? <AiLoadingState label={AI_SUMMARY_COPY.loadingLabel} /> : null}

        {!isGenerating && error ? (
          <AiErrorState
            title={errorMessage(error)}
            retryLabel={AI_SUMMARY_COPY.retryLabel}
            onRetry={() => {
              void onGenerate();
            }}
          >
            {hasSummary ? (
              <p className="whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-800">
                {display!.summary}
              </p>
            ) : null}
          </AiErrorState>
        ) : null}

        {!isGenerating && !error && !hasSummary ? (
          <AiEmptyState
            title={AI_SUMMARY_COPY.emptyTitle}
            description={AI_SUMMARY_COPY.emptyDescription}
            action={
              <Button
                type="button"
                variant="primary"
                className="!min-h-11 w-full max-w-xs sm:w-auto"
                icon={<Sparkles className="h-4 w-4" aria-hidden />}
                onClick={onGenerate}
              >
                {AI_SUMMARY_COPY.generateLabel}
              </Button>
            }
          />
        ) : null}

        {!isGenerating && !error && hasSummary ? (
          <p className="whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-800">
            {display!.summary}
          </p>
        ) : null}
      </div>
    </Card>
  );
}
