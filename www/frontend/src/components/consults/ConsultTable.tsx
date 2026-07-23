import { Link } from 'react-router-dom';
import type { ConsultListItem } from '@/types/consult.types';
import { formatDateTime } from '@/utils/formatTimeAgo';
import ConsultStatusBadge from './ConsultStatusBadge';

type ConsultTableProps = {
  consults: ConsultListItem[];
};

function displayConsultNo(consult: ConsultListItem): string {
  return consult.consultNo ?? consult.id;
}

function displaySite(consult: ConsultListItem): string {
  if (consult.siteName) return consult.siteName;
  return consult.source === 'crm' ? 'CRM' : 'ACEP';
}

function displayProduct(consult: ConsultListItem): string {
  if (consult.productName) return consult.productName;
  if (consult.contractProbability != null) {
    return `score ${Math.round(consult.contractProbability)}`;
  }
  return '—';
}

export default function ConsultTable({ consults }: ConsultTableProps) {
  return (
    <div className="table-scroll hidden min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
      <table className="w-full min-w-[720px] border-collapse text-sm">
        <thead>
          <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <th className="px-4 py-3">상담번호</th>
            <th className="px-4 py-3">고객</th>
            <th className="px-4 py-3">사이트</th>
            <th className="px-4 py-3">문의상품</th>
            <th className="px-4 py-3">상태</th>
            <th className="px-4 py-3">담당자</th>
            <th className="px-4 py-3">접수시간</th>
            <th className="px-4 py-3">관리</th>
          </tr>
        </thead>
        <tbody>
          {consults.map((consult) => (
            <tr
              key={`${consult.source}-${consult.id}`}
              className="border-b border-slate-100 last:border-0 hover:bg-slate-50/80"
            >
              <td className="px-4 py-3 font-mono text-xs font-medium text-slate-900">
                {displayConsultNo(consult)}
              </td>
              <td className="max-w-[8rem] px-4 py-3">
                <span className="text-overflow-truncate block">{consult.customerNameMasked}</span>
              </td>
              <td className="max-w-[6rem] px-4 py-3">
                <span className="text-overflow-truncate block">{displaySite(consult)}</span>
              </td>
              <td className="max-w-[8rem] px-4 py-3">
                <span className="text-overflow-truncate block">{displayProduct(consult)}</span>
              </td>
              <td className="px-4 py-3">
                <ConsultStatusBadge status={consult.status} />
              </td>
              <td className="max-w-[6rem] px-4 py-3 text-slate-600">
                <span className="text-overflow-truncate block">
                  {consult.agent?.displayName ?? '—'}
                </span>
              </td>
              <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                {formatDateTime(consult.createdAt)}
              </td>
              <td className="px-4 py-3">
                <Link
                  to={`/admin/consults/${encodeURIComponent(consult.id)}`}
                  className="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                >
                  상세
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
