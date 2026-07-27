import StatusBadge from '@/components/admin-ui/StatusBadge';
import { Button } from '@/components/admin-ui';
import type { SiteItem } from '@/types/site.types';

type SiteListActions = {
  onToggle: (site: SiteItem) => void;
  onRegenKey: (site: SiteItem) => void;
  onDelete: (site: SiteItem) => void;
  togglingId?: number | null;
};

function healthLabel(site: SiteItem): { label: string; tone: 'success' | 'danger' | 'neutral' } {
  const health = site.lastHealthCheck;
  if (!health) return { label: '미검사', tone: 'neutral' };
  return health.isHealthy
    ? { label: '정상', tone: 'success' }
    : { label: '점검 필요', tone: 'danger' };
}

export default function SiteTable({
  sites,
  onToggle,
  onRegenKey,
  onDelete,
  togglingId,
}: { sites: SiteItem[] } & SiteListActions) {
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
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-right">관리</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--pt-color-border)]">
            {sites.map((site) => {
              const health = healthLabel(site);
              return (
                <tr key={site.id} className="transition-colors hover:bg-slate-50">
                  <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                    <Button variant="ghost" to={`/admin/sites/${site.id}`} className="!min-h-0 !px-0 !py-0 !bg-transparent hover:!bg-transparent">
                      {site.siteName}
                    </Button>
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 font-mono text-xs">{site.siteCode}</td>
                  <td className="max-w-[200px] truncate px-4 py-3" title={site.domain}>
                    {site.domain || '-'}
                  </td>
                  <td className="max-w-[120px] truncate px-4 py-3">{site.brand || '-'}</td>
                  <td className="whitespace-nowrap px-4 py-3 text-center">
                    <StatusBadge label={site.status ? '활성' : '중지'} tone={site.status ? 'success' : 'neutral'} />
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-center">
                    <StatusBadge label={health.label} tone={health.tone} />
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-center tabular-nums">
                    {site.totalConsultCount ?? 0}
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-right">
                    <div className="flex justify-end gap-1">
                      <Button variant="ghost" to={`/admin/sites/${site.id}`} className="admin-touch-target">
                        보기
                      </Button>
                      <Button variant="ghost" to={`/admin/sites/${site.id}/edit`} className="admin-touch-target">
                        수정
                      </Button>
                      <Button
                        variant="secondary"
                        disabled={togglingId === site.id}
                        onClick={() => onToggle(site)}
                        className="admin-touch-target"
                      >
                        {site.status ? '중지' : '활성'}
                      </Button>
                      <Button variant="secondary" onClick={() => onRegenKey(site)} className="admin-touch-target">
                        Key
                      </Button>
                      <Button variant="danger" onClick={() => onDelete(site)} className="admin-touch-target">
                        삭제
                      </Button>
                    </div>
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
