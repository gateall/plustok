import { Sparkles, UserRound, Languages } from 'lucide-react';

import AiSummaryCard from '@/components/ai/AiSummaryCard';
import AiReplyCard from '@/components/ai/AiReplyCard';
import AiRecommendationCard from '@/components/ai/AiRecommendationCard';
import type { ConsultDetail } from '@/types/consult.types';

type AiSection = {
  id: string;
  label: string;
  icon: typeof Sparkles;
  content: string;
};

function buildRemainingStubs(): AiSection[] {
  return [
    {
      id: 'persona',
      label: '성향',
      icon: UserRound,
      content: '고객 성향 프로필 — Customer 360(#/admin/customers/:id)에서 확인',
    },
    {
      id: 'translate',
      label: '번역',
      icon: Languages,
      content: '다국어 번역 stub',
    },
  ];
}

type ConsultAiPanelProps = {
  consult: ConsultDetail | null;
  className?: string;
};

/** AI assist panel — Summary / Reply / Recommendation stubs. */
export default function ConsultAiPanel({ consult, className }: ConsultAiPanelProps) {
  const stubs = buildRemainingStubs();

  const statusText = consult
    ? consult.aiEnabled
      ? 'AI 사용 중'
      : 'AI 꺼짐'
    : '상담을 선택하세요';

  return (
    <aside className={`consult-ai-panel flex min-h-0 flex-col bg-white ${className ?? ''}`} aria-label="AI 패널">
      <header className="shrink-0 border-b border-slate-200 px-4 py-3">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
          <Sparkles className="h-4 w-4 text-indigo-500" aria-hidden />
          AI 어시스트
        </h2>
        <p className="mt-0.5 text-xs text-slate-500" role="status" aria-live="polite">
          {statusText}
        </p>
      </header>

      <div
        className="min-h-0 flex-1 overflow-y-auto p-3"
        role="region"
        aria-label="AI 인사이트"
      >
        {consult ? (
          <div className="space-y-3">
            <AiSummaryCard
              consultId={consult.id}
              initialSummary={consult.aiSummary}
              initialGeneratedAt={consult.aiSummaryAt}
              compact
            />
            <AiRecommendationCard
              consultId={consult.id}
              initialSentiment={consult.sentiment}
              initialPriority={consult.priority}
              initialTags={consult.aiTags ?? null}
              initialCategoryAi={consult.categoryAi}
              initialContractScore={consult.contractScore ?? null}
              initialConfidence={consult.aiConfidence}
              initialAnalyzedAt={consult.aiAnalyzedAt}
              compact
            />
            <AiReplyCard consultId={consult.id} compact />
            {stubs.map((section) => {
              const headingId = `consult-ai-stub-${section.id}`;
              return (
                <section
                  key={section.id}
                  className="rounded-xl border border-slate-200 bg-slate-50/80 p-3"
                  aria-labelledby={headingId}
                >
                  <h3
                    id={headingId}
                    className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-500"
                  >
                    <section.icon className="h-3.5 w-3.5" aria-hidden />
                    {section.label}
                  </h3>
                  <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-800">
                    {section.content}
                  </p>
                </section>
              );
            })}
          </div>
        ) : (
          <div
            className="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-3 py-8 text-center"
            role="status"
          >
            <p className="text-sm font-medium text-slate-800">상담을 선택하세요</p>
            <p className="mt-1 text-xs leading-relaxed text-slate-500">
              좌측에서 상담을 선택하면 AI 인사이트가 표시됩니다.
            </p>
          </div>
        )}
      </div>
    </aside>
  );
}
