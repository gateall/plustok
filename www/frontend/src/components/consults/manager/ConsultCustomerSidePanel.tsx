import { Link } from 'react-router-dom';
import {
  AdminCard,
  AdminCardBody,
  AdminCardHeader,
  InfoRow,
  StatusBadge,
  consultStatusBadgeProps,
} from '@/components/admin-ui';
import AiSummaryCard from '@/components/ai/AiSummaryCard';
import AiReplyCard from '@/components/ai/AiReplyCard';
import AiRecommendationCard from '@/components/ai/AiRecommendationCard';
import { ConsultTagChip } from './ConsultTags';
import type { ConsultDetail } from '@/types/consult.types';
import { displayConsultNo, formatReceiptTime } from '@/utils/consultDisplay';
import { formatTimeAgo } from '@/utils/formatTimeAgo';

type ConsultCustomerSidePanelProps = {
  consult: ConsultDetail | null;
  className?: string;
};

export default function ConsultCustomerSidePanel({ consult, className }: ConsultCustomerSidePanelProps) {
  if (!consult) {
    return (
      <aside className={`flex min-h-0 flex-col bg-white ${className ?? ''}`} aria-label="고객 정보 패널">
        <header className="shrink-0 border-b border-slate-200 px-4 py-3">
          <h2 className="text-sm font-semibold text-slate-900">고객 정보</h2>
          <p className="mt-0.5 text-xs text-slate-500">상담을 선택하세요</p>
        </header>
        <p className="p-4 text-center text-sm text-slate-500">좌측 목록에서 상담을 선택하면 고객 정보가 표시됩니다.</p>
      </aside>
    );
  }

  const tags = consult.tags ?? [];
  const contractScore =
    consult.contractProbability != null ? `${Math.round(consult.contractProbability)}%` : '미집계';

  return (
    <aside className={`consult-customer-side flex min-h-0 flex-col bg-white ${className ?? ''}`} aria-label="고객 정보 패널">
      <header className="shrink-0 border-b border-slate-200 px-4 py-3">
        <h2 className="text-sm font-semibold text-slate-900">
          {consult.companyName ? `${consult.companyName} (${consult.customerNameMasked})` : consult.customerNameMasked}
        </h2>
        <p className="mt-0.5 font-mono text-xs text-slate-500">{consult.phoneMasked ?? '연락처 미등록'}</p>
      </header>

      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
        <AdminCard>
          <AdminCardHeader title="고객 정보" subtitle={consult.customerType ?? '일반'} />
          <AdminCardBody className="pt-2">
            <InfoRow label="사이트" value={consult.siteName ?? '—'} valueClassName="font-medium text-slate-700" />
            <InfoRow label="이메일" value={consult.email ?? '—'} valueClassName="font-medium text-slate-700" />
            <InfoRow label="담당" value={consult.agent?.displayName ?? '미배정'} valueClassName="font-medium text-slate-700" />
            <InfoRow
              label="최근 접속"
              value={consult.recentActivity ?? formatTimeAgo(consult.updatedAt)}
              valueClassName="font-medium text-slate-700"
            />
          </AdminCardBody>
        </AdminCard>

        <AdminCard>
          <AdminCardHeader
            title="계약 정보"
            subtitle={displayConsultNo(consult)}
            aside={<StatusBadge {...consultStatusBadgeProps(consult.status)} />}
          />
          <AdminCardBody className="pt-2">
            <InfoRow label="계약확률" value={contractScore} valueClassName="font-semibold text-indigo-700" />
            <InfoRow label="상품" value={consult.productName ?? '—'} valueClassName="font-medium text-slate-700" />
            <InfoRow label="접수" value={formatReceiptTime(consult.createdAt)} valueClassName="font-medium text-slate-700" />
            {consult.status === 'contracted' || consult.status === 'installed' ? (
              <div className="pt-2">
                <Link
                  to="/admin/contracts"
                  className="inline-flex min-h-9 items-center text-sm font-medium text-indigo-600 hover:text-indigo-700"
                >
                  계약 관리에서 보기
                </Link>
              </div>
            ) : null}
          </AdminCardBody>
        </AdminCard>

        {tags.length > 0 ? (
          <AdminCard>
            <AdminCardHeader title="태그" />
            <AdminCardBody className="flex flex-wrap gap-1.5 pt-2">
              {tags.map((tag) => (
                <ConsultTagChip key={tag.id} tag={tag} compact />
              ))}
            </AdminCardBody>
          </AdminCard>
        ) : null}

        <AdminCard>
          <AdminCardHeader title="상담 이력" subtitle="고객별 이력 API" />
          <AdminCardBody className="pt-2">
            <p className="text-sm text-slate-500">
              고객별 상담 이력은 <code className="text-xs">GET /admin/customers/:id/consults</code> 연동 후 표시됩니다.
            </p>
            <p className="mt-2 text-xs text-slate-400">Mock 데이터를 표시하지 않습니다.</p>
          </AdminCardBody>
        </AdminCard>

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
      </div>
    </aside>
  );
}
