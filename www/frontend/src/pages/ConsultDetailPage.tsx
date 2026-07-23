import { useRef } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import PageHeader from '@/components/common/PageHeader';
import ConsultDetailSummary from '@/components/consults/ConsultDetailSummary';
import ConsultQuickActions from '@/components/consults/ConsultQuickActions';
import ConsultChatPanel from '@/components/consults/ConsultChatPanel';
import { useConsultDetail } from '@/hooks/useConsultDetail';
import { formatDateTime } from '@/utils/formatTimeAgo';

export default function ConsultDetailPage() {
  const { id } = useParams<{ id: string }>();
  const chatRef = useRef<HTMLElement | null>(null);
  const { data: consult, isLoading, error } = useConsultDetail(id);

  const scrollToChat = () => {
    chatRef.current?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
  };

  if (isLoading) {
    return (
      <div className="consult-detail-page min-w-0 py-4">
        <PageHeader title="상담 상세" description="불러오는 중…" />
        <div className="consult-detail-card animate-pulse rounded-xl border border-slate-200 bg-white p-6">
          <div className="h-4 w-1/3 rounded bg-slate-200" />
          <div className="mt-4 h-6 w-2/3 rounded bg-slate-200" />
        </div>
      </div>
    );
  }

  if (error || !consult) {
    return (
      <div className="consult-detail-page min-w-0 py-4">
        <Link
          to="/admin/consults"
          className="mb-4 inline-flex h-11 items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden />
          목록으로
        </Link>
        <PageHeader title="상담 상세" />
        <div className="consult-detail-card rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">
          {error instanceof Error ? error.message : '상담을 찾을 수 없습니다.'}
        </div>
      </div>
    );
  }

  return (
    <div className="consult-detail-page min-w-0 space-y-4 pb-4 md:space-y-5 md:pb-6">
      <Link
        to="/admin/consults"
        className="inline-flex h-11 items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden />
        목록으로
      </Link>

      <PageHeader
        title={consult.consultNo}
        description={`${consult.source === 'crm' ? 'CRM' : 'ACEP'} · ${formatDateTime(consult.createdAt)}`}
      />

      <ConsultDetailSummary consult={consult} />

      <section className="consult-detail-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 className="text-sm font-semibold text-slate-900">고객 정보</h3>
        <dl className="mt-3 grid gap-2 text-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-slate-500">이름</dt>
            <dd className="font-medium text-slate-900">{consult.customerNameMasked}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-slate-500">연락처</dt>
            <dd className="font-mono text-slate-900">{consult.phoneMasked ?? '—'}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-slate-500">이메일</dt>
            <dd className="break-all text-slate-900">{consult.email ?? '—'}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-slate-500">사이트</dt>
            <dd className="text-slate-900">{consult.siteName ?? '—'}</dd>
          </div>
        </dl>
      </section>

      <ConsultQuickActions consult={consult} onScrollToChat={scrollToChat} />

      {consult.roomId ? (
        <div ref={(el) => {
          chatRef.current = el;
        }}>
          <ConsultChatPanel roomId={consult.roomId} roomStatus={consult.status} />
        </div>
      ) : (
        <section className="consult-detail-card rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
          연결된 채팅방이 없습니다. CRM 상담은 ACEP 채팅방 연동 후 메시지를 주고받을 수 있습니다.
        </section>
      )}

      <section className="consult-detail-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 className="text-sm font-semibold text-slate-900">고객 요청 내용</h3>
        <div className="mt-3 whitespace-pre-wrap break-words-safe rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm leading-relaxed text-slate-800">
          {consult.memo?.trim() ? consult.memo : '내용 없음'}
        </div>
        {consult.aiSummary && (
          <div className="mt-4">
            <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">AI 요약</h4>
            <p className="mt-2 whitespace-pre-wrap break-words-safe text-sm text-slate-700">
              {consult.aiSummary}
            </p>
          </div>
        )}
      </section>

      <section className="consult-detail-card rounded-xl border border-dashed border-slate-300 bg-white p-4 text-center shadow-sm">
        <h3 className="text-sm font-semibold text-slate-700">첨부 파일</h3>
        <p className="mt-2 text-sm text-slate-500">Phase 5 — PHP view.php 첨부 탭 parity</p>
      </section>

      <section className="consult-detail-card rounded-xl border border-dashed border-slate-300 bg-white p-4 text-center shadow-sm">
        <h3 className="text-sm font-semibold text-slate-700">상태 이력</h3>
        <p className="mt-2 text-sm text-slate-500">Phase 5 — consult_history 연동</p>
      </section>
    </div>
  );
}
