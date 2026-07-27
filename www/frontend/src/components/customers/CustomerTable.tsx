import type { CustomerItem } from '../../types/customer.types';
import { CUSTOMER_STATUS_LABELS } from '../../types/customer.types';
import StatusBadge from '../admin-ui/StatusBadge';

function formatStandardDate(dateStr: string | null) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

export default function CustomerTable({ customers }: { customers: CustomerItem[] }) {
  return (
    <div className="admin-desktop-table overflow-hidden rounded-xl border border-[var(--pt-color-border)] bg-white shadow-sm">
      <div className="table-scroll w-full overflow-x-auto">
        <table className="w-full text-left text-sm text-slate-600">
          <thead className="bg-slate-50 text-slate-700">
            <tr>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">고객명</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">연락처</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">이메일</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">소속</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">사이트</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">상태</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">상담 건수</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">최근 상담일</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--pt-color-border)]">
            {customers.map((c) => (
              <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                  {c.name}
                </td>
                <td className="whitespace-nowrap px-4 py-3">
                  {c.phone || '-'}
                </td>
                <td className="px-4 py-3 max-w-[200px] truncate" title={c.emailMasked || ''}>
                  {c.emailMasked || '-'}
                </td>
                <td className="px-4 py-3 max-w-[150px] truncate" title={c.companyName || ''}>
                  {c.companyName || '-'}
                </td>
                <td className="px-4 py-3 max-w-[120px] truncate" title={c.siteName || ''}>
                  {c.siteName || '-'}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-center">
                  <StatusBadge label={CUSTOMER_STATUS_LABELS[c.status] || c.status} tone={c.status === 'new' ? 'info' : c.status === 'active' ? 'success' : 'neutral'} />
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-center">
                  {c.consultCount}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-slate-500">
                  {formatStandardDate(c.lastConsultAt)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
