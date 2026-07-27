import { useRef, useState, type RefObject } from 'react';
import { ArrowLeft, List, UserRound } from 'lucide-react';
import { Link } from 'react-router-dom';
import { EmptyState } from '@/components/admin-ui';
import AiSummaryCard from '@/components/ai/AiSummaryCard';
import AiReplyCard from '@/components/ai/AiReplyCard';
import AiRecommendationCard from '@/components/ai/AiRecommendationCard';
import ConsultChatPanel from '@/components/consults/ConsultChatPanel';
import { useConsultDetail } from '@/hooks/useConsultDetail';
import type { ConsultDetailTab } from '@/types/consult.types';
import { displayConsultNo } from '@/utils/consultDisplay';
import ConsultCustomerCard from './ConsultCustomerCard';
import ConsultHeaderControls from './ConsultHeaderControls';
import ConsultDetailTabs, { tabId, tabPanelId } from './ConsultDetailTabs';
import ConsultTimeline from './ConsultTimeline';
import ConsultAttachments from './ConsultAttachments';
import ConsultTags from './ConsultTags';
import ConsultResponseComposer from './ConsultResponseComposer';
import ConsultCrmActions from './ConsultCrmActions';

type ConsultDetailPanelProps = {
  consultId?: string;
  onOpenList?: () => void;
  onOpenAi?: () => void;
  showMobileChrome?: boolean;
  listTriggerRef?: RefObject<HTMLButtonElement>;
  aiTriggerRef?: RefObject<HTMLButtonElement>;
  /** Mobile side-panel trigger label (default: 고객) */
  customerPanelLabel?: string;
};

export default function ConsultDetailPanel({
  consultId,
  onOpenList,
  onOpenAi,
  showMobileChrome,
  listTriggerRef,
  aiTriggerRef,
  customerPanelLabel = '고객',
}: ConsultDetailPanelProps) {
  const chatRef = useRef<HTMLDivElement | null>(null);
  const [activeTab, setActiveTab] = useState<ConsultDetailTab>('chat');
  const { data: consult, isLoading, error } = useConsultDetail(consultId);

  const scrollToChat = () => {
    setActiveTab('chat');
    chatRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  if (!consultId) {
    return (
      <div className="consult-detail-panel flex min-h-[320px] flex-1 items-center justify-center bg-slate-50 p-6">
        <EmptyState
          title="상담을 선택하세요"
          description="좌측 목록에서 상담을 선택하면 상세 CRM 카드와 채팅이 표시됩니다."
        />
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="consult-detail-panel flex flex-1 flex-col bg-white p-4">
        <div className="animate-pulse space-y-4">
          <div className="h-6 w-1/3 rounded bg-slate-200" />
          <div className="h-32 rounded-xl bg-slate-100" />
          <div className="h-48 rounded-xl bg-slate-100" />
        </div>
      </div>
    );
  }

  if (error || !consult) {
    return (
      <div className="consult-detail-panel flex flex-1 flex-col bg-white p-4">
        {showMobileChrome ? (
          <button
            type="button"
            onClick={onOpenList}
            className="mb-3 inline-flex h-11 items-center gap-1 text-sm font-medium text-indigo-600"
          >
            <ArrowLeft className="h-4 w-4" />
            목록
          </button>
        ) : null}
        <EmptyState
          title="상담을 불러오지 못했습니다"
          description={error instanceof Error ? error.message : '상담을 찾을 수 없습니다.'}
        />
      </div>
    );
  }

  return (
    <div className="consult-detail-panel flex min-h-0 flex-1 flex-col bg-slate-50">
      <header className="consult-detail-panel__header shrink-0 border-b border-slate-200 bg-white px-3 py-3 lg:px-4">
        <div className="flex min-w-0 items-start justify-between gap-2">
          <div className="min-w-0">
            {showMobileChrome ? (
              <div className="mb-2 flex items-center gap-2">
                <button
                  ref={listTriggerRef}
                  type="button"
                  onClick={onOpenList}
                  className="inline-flex h-10 items-center gap-1 rounded-lg px-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                >
                  <List className="h-4 w-4" />
                  목록
                </button>
                <button
                  ref={aiTriggerRef}
                  type="button"
                  onClick={onOpenAi}
                  className="inline-flex h-10 items-center gap-1 rounded-lg px-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 min-[992px]:hidden"
                >
                  <UserRound className="h-4 w-4" />
                  {customerPanelLabel}
                </button>
              </div>
            ) : (
              <Link
                to="/admin/consults"
                className="mb-2 hidden text-sm font-medium text-indigo-600 hover:text-indigo-700 md:inline-flex md:items-center md:gap-1"
              >
                <ArrowLeft className="h-4 w-4" />
                전체 목록
              </Link>
            )}
            <h1 className="break-mono truncate font-mono text-lg font-bold text-slate-900">
              {displayConsultNo(consult)}
            </h1>
          </div>
        </div>

        <div className="mt-3">
          <ConsultHeaderControls
            consultId={consult.id}
            status={consult.status}
            assigneeId={consult.agent?.id}
          />
        </div>

        <div className="mt-3">
          <ConsultTags
            consultId={consult.id}
            tags={consult.tags}
            editable
            compact
          />
        </div>

        <div className="mt-3">
          <ConsultCrmActions consult={consult} onScrollToChat={scrollToChat} />
        </div>
      </header>

      <div className="consult-detail-panel__body min-h-0 flex-1 overflow-y-auto">
        <div className="grid gap-3 p-3 lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] lg:p-4">
          <ConsultCustomerCard consult={consult} className="lg:sticky lg:top-0 lg:self-start" />

          <div className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <ConsultDetailTabs active={activeTab} onChange={setActiveTab} />

            {activeTab === 'chat' && (
              <div
                ref={chatRef}
                id={tabPanelId('chat')}
                role="tabpanel"
                aria-labelledby={tabId('chat')}
              >
                {consult.roomId ? (
                  <ConsultChatPanel roomId={consult.roomId} roomStatus={consult.status} />
                ) : (
                  <div className="p-6 text-center text-sm text-slate-500">
                    연결된 채팅방이 없습니다.
                  </div>
                )}
              </div>
            )}

            {activeTab === 'memo' && (
              <div
                id={tabPanelId('memo')}
                role="tabpanel"
                aria-labelledby={tabId('memo')}
                className="space-y-3 p-4"
              >
                <section>
                  <h3 className="text-sm font-semibold text-slate-900">고객 요청</h3>
                  <p className="mt-2 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm text-slate-800">
                    {consult.memo?.trim() || '내용 없음'}
                  </p>
                </section>
                <AiSummaryCard
                  consultId={consult.id}
                  initialSummary={consult.aiSummary}
                  initialGeneratedAt={consult.aiSummaryAt}
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
                />
                <AiReplyCard consultId={consult.id} />
              </div>
            )}

            {activeTab === 'history' && (
              <div id={tabPanelId('history')} role="tabpanel" aria-labelledby={tabId('history')}>
                <ConsultTimeline
                  consultId={consult.id}
                  consultCreatedAt={consult.createdAt}
                  status={consult.status}
                />
              </div>
            )}

            {activeTab === 'files' && (
              <div id={tabPanelId('files')} role="tabpanel" aria-labelledby={tabId('files')}>
                <ConsultAttachments consultId={consult.id} />
              </div>
            )}
          </div>
        </div>
      </div>

      <ConsultResponseComposer consultId={consult.id} />
    </div>
  );
}
