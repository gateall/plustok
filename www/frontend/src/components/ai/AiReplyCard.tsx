import { useEffect, useState } from 'react';
import { MessageSquareText, Sparkles } from 'lucide-react';
import clsx from 'clsx';
import { Button, Card, Textarea } from '@/components/admin-ui';
import { AiEmptyState, AiErrorState, AiLoadingState } from '@/components/ai/AiFeatureState';
import { useAiReply } from '@/hooks/useAiReply';
import { AI_REPLY_COPY } from '@/types/aiReply.types';

/**
 * AI Reply card — AI_REPLY.md Edit/Accept/Reject UX.
 * Live POST via aiReplyService when resolveDataSource() === 'api'.
 */
export type AiReplyCardProps = {
  consultId: string;
  className?: string;
  compact?: boolean;
};

export default function AiReplyCard({ consultId, className, compact = false }: AiReplyCardProps) {
  const { generate, isGenerating, error, result, reset } = useAiReply(consultId);
  const [draft, setDraft] = useState<string | null>(null);
  const [toneNote, setToneNote] = useState<string | null>(null);
  const [isEditing, setIsEditing] = useState(false);

  useEffect(() => {
    if (result) {
      setDraft(result.draft);
      setToneNote(result.toneNote ?? null);
      setIsEditing(false);
    }
  }, [result]);

  const onGenerate = async () => {
    reset();
    try {
      await generate();
    } catch {
      /* error surfaced via hook */
    }
  };

  const onReject = () => {
    setDraft(null);
    setToneNote(null);
    setIsEditing(false);
    reset();
  };

  const errorMessage = error?.message ?? null;

  return (
    <Card
      as="section"
      padding={compact ? 'sm' : 'md'}
      className={clsx('ai-reply-card min-w-0', className)}
      aria-label="AI 답변 초안"
    >
      <header className="flex flex-wrap items-start justify-between gap-2">
        <h3 className="flex items-center gap-1.5 text-sm font-semibold text-slate-900">
          <MessageSquareText className="h-4 w-4 shrink-0 text-indigo-600" aria-hidden />
          {AI_REPLY_COPY.title}
        </h3>
        {draft && !isGenerating ? (
          <Button
            type="button"
            variant="ghost"
            className="!min-h-10 shrink-0 px-3 text-xs"
            onClick={onGenerate}
          >
            {AI_REPLY_COPY.regenerateLabel}
          </Button>
        ) : null}
      </header>

      <div className="mt-3 space-y-3">
        {isGenerating ? <AiLoadingState label={AI_REPLY_COPY.loadingLabel} /> : null}

        {!isGenerating && errorMessage ? (
          <AiErrorState
            title={errorMessage}
            retryLabel={AI_REPLY_COPY.retryLabel}
            onRetry={() => {
              void onGenerate();
            }}
          />
        ) : null}

        {!isGenerating && !errorMessage && !draft ? (
          <AiEmptyState
            title={AI_REPLY_COPY.emptyTitle}
            description={AI_REPLY_COPY.emptyHint}
            action={
              <Button
                type="button"
                variant="primary"
                className="!min-h-11 w-full max-w-xs sm:w-auto"
                icon={<Sparkles className="h-4 w-4" aria-hidden />}
                onClick={onGenerate}
              >
                {AI_REPLY_COPY.generateLabel}
              </Button>
            }
          />
        ) : null}

        {!isGenerating && draft ? (
          <>
            {isEditing ? (
              <Textarea
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                rows={compact ? 4 : 6}
                aria-label="답변 초안 편집"
              />
            ) : (
              <p className="whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-800">
                {draft}
              </p>
            )}
            {toneNote ? <p className="text-xs text-slate-400">{toneNote}</p> : null}
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="secondary"
                className="!min-h-10 px-3 text-xs"
                onClick={() => setIsEditing((v) => !v)}
              >
                {isEditing ? '편집 완료' : AI_REPLY_COPY.editLabel}
              </Button>
              <Button
                type="button"
                variant="primary"
                className="!min-h-10 px-3 text-xs"
                onClick={() => {
                  /* Accept → existing send flow (out of AI_REPLY scope) */
                  setIsEditing(false);
                }}
              >
                {AI_REPLY_COPY.acceptLabel}
              </Button>
              <Button
                type="button"
                variant="ghost"
                className="!min-h-10 px-3 text-xs"
                onClick={onReject}
              >
                {AI_REPLY_COPY.rejectLabel}
              </Button>
            </div>
          </>
        ) : null}
      </div>
    </Card>
  );
}
