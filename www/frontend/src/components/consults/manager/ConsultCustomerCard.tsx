import clsx from 'clsx';
import { Crown } from 'lucide-react';
import { ConsultTagChip } from './ConsultTags';
import type { ConsultDetail, ConsultTag } from '@/types/consult.types';
import { StatusBadge, consultStatusBadgeProps } from '@/components/admin-ui';
import { displayConsultNo, formatReceiptTime } from '@/utils/consultDisplay';
import { formatTimeAgo } from '@/utils/formatTimeAgo';

type ConsultCustomerCardProps = {
  consult: ConsultDetail;
  className?: string;
};

function normalizeTags(raw: ConsultTag[] | string[] | undefined): ConsultTag[] {
  if (!raw?.length) return [];
  return raw.map((t, i) =>
    typeof t === 'string'
      ? { id: `tag-${t}-${i}`, label: t, color: 'indigo' as const }
      : t,
  );
}

export default function ConsultCustomerCard({ consult, className }: ConsultCustomerCardProps) {
  const siteLabel = consult.siteName ?? (consult.source === 'crm' ? 'CRM' : 'ACEP');
  const typeLabel = consult.customerType ?? '일반';
  const tags = normalizeTags(consult.tags as ConsultTag[] | string[] | undefined);

  return (
    <aside
      className={clsx(
        'consult-customer-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm',
        className,
      )}
      aria-label="고객 정보"
    >
      <div className="flex min-w-0 items-start justify-between gap-2">
        <StatusBadge {...consultStatusBadgeProps(consult.status)} />
        {consult.isVip ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
            <Crown className="h-3.5 w-3.5" aria-hidden />
            VIP
          </span>
        ) : null}
      </div>

      <h2 className="mt-3 break-text text-lg font-semibold text-slate-900">{consult.customerNameMasked}</h2>

      <p className="mt-1 break-mono font-mono text-sm text-slate-600">{consult.phoneMasked ?? '연락처 미등록'}</p>
      {consult.email ? (
        <p className="mt-1 break-all text-sm text-slate-600">{consult.email}</p>
      ) : null}

      <dl className="mt-3 grid gap-2 text-sm">
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">상담번호</dt>
          <dd className="break-mono font-mono font-medium text-slate-900">{displayConsultNo(consult)}</dd>
        </div>
        {consult.productName?.trim() ? (
          <div className="flex justify-between gap-3">
            <dt className="text-slate-500">상품/제목</dt>
            <dd className="text-right font-medium text-slate-900">{consult.productName}</dd>
          </div>
        ) : null}
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">사이트</dt>
          <dd className="font-medium text-slate-900">{siteLabel}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">출처</dt>
          <dd className="font-medium uppercase text-slate-900">{consult.source}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">유형</dt>
          <dd className="font-medium text-slate-900">{typeLabel}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">담당</dt>
          <dd className="font-medium text-slate-900">{consult.agent?.displayName ?? '미배정'}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">접수</dt>
          <dd className="text-right text-slate-900">{formatReceiptTime(consult.createdAt)}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">수정</dt>
          <dd className="text-right text-slate-900">{formatTimeAgo(consult.updatedAt)}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-slate-500">최근</dt>
          <dd className="text-slate-900">{consult.recentActivity ?? formatTimeAgo(consult.updatedAt)}</dd>
        </div>
      </dl>

      {consult.memo?.trim() ? (
        <div className="mt-3 rounded-lg bg-slate-50 p-3">
          <p className="text-xs font-semibold text-slate-500">메모</p>
          <p className="mt-1 whitespace-pre-wrap break-text text-sm text-slate-800">{consult.memo}</p>
        </div>
      ) : null}

      {tags.length > 0 ? (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {tags.map((tag) => (
            <ConsultTagChip key={tag.id} tag={tag} compact />
          ))}
        </div>
      ) : null}
    </aside>
  );
}
