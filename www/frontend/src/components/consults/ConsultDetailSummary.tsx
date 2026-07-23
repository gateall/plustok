import type { ConsultDetail } from '@/types/consult.types';
import { formatTimeAgo } from '@/utils/formatTimeAgo';
import ConsultStatusBadge from './ConsultStatusBadge';

type ConsultDetailSummaryProps = {
  consult: ConsultDetail;
};

export default function ConsultDetailSummary({ consult }: ConsultDetailSummaryProps) {
  const siteLabel = consult.siteName ?? (consult.source === 'crm' ? 'CRM' : 'ACEP');
  const productLabel =
    consult.productName ||
    (consult.contractProbability != null
      ? `score ${Math.round(consult.contractProbability)}`
      : '—');

  return (
    <section className="consult-detail-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex min-w-0 items-start justify-between gap-2">
        <ConsultStatusBadge status={consult.status} />
        <span className="shrink-0 text-xs text-slate-500">{formatTimeAgo(consult.updatedAt)}</span>
      </div>

      <h2 className="mt-3 break-words-safe text-lg font-semibold text-slate-900">
        {consult.customerNameMasked}
      </h2>

      {consult.phoneMasked && (
        <p className="mt-1 font-mono text-sm text-slate-600">{consult.phoneMasked}</p>
      )}

      <dl className="mt-3 grid gap-2 text-sm">
        <div className="flex min-w-0 justify-between gap-3">
          <dt className="shrink-0 text-slate-500">사이트</dt>
          <dd className="min-w-0 break-words-safe text-right font-medium text-slate-900">
            {siteLabel}
          </dd>
        </div>
        <div className="flex min-w-0 justify-between gap-3">
          <dt className="shrink-0 text-slate-500">상품</dt>
          <dd className="min-w-0 break-words-safe text-right font-medium text-slate-900">
            {productLabel}
          </dd>
        </div>
        <div className="flex min-w-0 justify-between gap-3">
          <dt className="shrink-0 text-slate-500">담당</dt>
          <dd className="min-w-0 break-words-safe text-right font-medium text-slate-900">
            {consult.agent?.displayName ?? '미배정'}
          </dd>
        </div>
      </dl>
    </section>
  );
}
