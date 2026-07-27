import StatusBadge from '@/components/admin-ui/StatusBadge';
import type { SiteItem } from '@/types/site.types';

function healthLabel(site: SiteItem): { label: string; tone: 'success' | 'danger' | 'neutral' } {
  const health = site.lastHealthCheck;
  if (!health) return { label: '미검사', tone: 'neutral' };
  return health.isHealthy
    ? { label: '정상', tone: 'success' }
    : { label: '점검 필요', tone: 'danger' };
}

export default function SiteTable({ sites }: { sites: SiteItem[] }) {
  return (
    <div className="admin-desktop-table overflow-hidden rounded-xl border border-[var(--pt-color-border)] bg-white shadow-sm">
      <div className="table-scroll w-full overflow-x-auto">
        <table className="w-full text-left text-sm text-slate-600">
          <thead className="bg-slate-50 text-slate-700">
            <tr>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">사이트명</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">코드</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">도메인</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">브랜드</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">상태</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">헬스</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">상담</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--pt-color-border)]">
            {sites.map((site) => {
              const health = healthLabel(site);
              return (
                <tr key={site.id} className="hover:bg-slate-50 transition-colors">
                  <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">{site.siteName}</td>
                  <td className="whitespace-nowrap px-4 py-3 font-mono text-xs">{site.siteCode}</td>
                  <td className="px-4 py-3 max-w-[200px] truncate" title={site.domain}>
                    {site.domain || '-'}
                  </td>
                  <td className="px-4 py-3 max-w-[120px] truncate">{site.brand || '-'}</td>
                  <td className="whitespace-nowrap px-4 py-3 text-center">
                    <StatusBadge label={site.status ? '활성' : '중지'} tone={site.status ? 'success' : 'neutral'} />
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-center">
                    <StatusBadge label={health.label} tone={health.tone} />
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-center tabular-nums">
                    {site.totalConsultCount ?? 0}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
