import StatusBadge from '@/components/admin-ui/StatusBadge';
import { Button } from '@/components/admin-ui';
import type { SiteItem } from '@/types/site.types';
import {
  getSiteIntegrationStatus,
  integrationStatusLabel,
} from '@/utils/siteDisplay';

type SiteListActions = {
  onToggle: (site: SiteItem) => void;
  onRegenKey: (site: SiteItem) => void;
  selectedIds: Set<number>;
  onToggleSelect: (id: number) => void;
  onToggleSelectAll: (ids: number[]) => void;
  togglingId?: number | null;
};

function integrationTone(site: SiteItem): 'info' | 'warning' | 'neutral' {
  const status = getSiteIntegrationStatus(site);
  if (status === 'healthy') return 'info';
  if (status === 'needs_check') return 'warning';
  return 'neutral';
}

export default function SiteTable({
  sites,
  onToggle,
  onRegenKey,
  selectedIds,
  onToggleSelect,
  onToggleSelectAll,
  togglingId,
}: { sites: SiteItem[] } & SiteListActions) {
  const pageIds = sites.map((s) => s.id);
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id));

  return (
    <div className="admin-desktop-table overflow-hidden rounded-xl border border-[var(--pt-color-border)] bg-white shadow-sm">
      <table className="w-full table-fixed text-left text-sm text-slate-600">
        <thead className="bg-slate-50 text-slate-700">
          <tr>
            <th className="w-10 px-3 py-3">
              <input
                type="checkbox"
                aria-label="전체 선택"
                checked={allSelected}
                onChange={() => onToggleSelectAll(pageIds)}
                className="h-4 w-4 rounded border-slate-300"
              />
            </th>
            <th className="px-3 py-3 font-semibold">사이트명</th>
            <th className="px-3 py-3 font-semibold">도메인</th>
            <th className="px-3 py-3 font-semibold">site_code</th>
            <th className="w-16 px-3 py-3 text-center font-semibold">상담 수</th>
            <th className="w-24 px-3 py-3 text-center font-semibold">연동 상태</th>
            <th className="w-16 px-3 py-3 text-center font-semibold">사용</th>
            <th className="w-28 px-3 py-3 font-semibold">최근 상담</th>
            <th className="w-[280px] px-3 py-3 text-right font-semibold">작업</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-[var(--pt-color-border)]">
          {sites.map((site) => {
            const integration = getSiteIntegrationStatus(site);
            return (
              <tr key={site.id} className="transition-colors hover:bg-slate-50">
                <td className="px-3 py-3">
                  <input
                    type="checkbox"
                    aria-label={`${site.siteName} 선택`}
                    checked={selectedIds.has(site.id)}
                    onChange={() => onToggleSelect(site.id)}
                    className="h-4 w-4 rounded border-slate-300"
                  />
                </td>
                <td className="truncate px-3 py-3 font-medium text-slate-900" title={site.siteName}>
                  {site.siteName}
                </td>
                <td className="truncate px-3 py-3" title={site.domain}>
                  {site.domain || '-'}
                </td>
                <td className="truncate px-3 py-3 font-mono text-xs">{site.siteCode}</td>
                <td className="px-3 py-3 text-center tabular-nums">{site.totalConsultCount ?? 0}</td>
                <td className="px-3 py-3 text-center">
                  <StatusBadge
                    label={integrationStatusLabel(integration)}
                    tone={integrationTone(site)}
                  />
                </td>
                <td className="px-3 py-3 text-center">
                  <StatusBadge
                    label={site.status ? '사용' : '중지'}
                    tone={site.status ? 'success' : 'neutral'}
                  />
                </td>
                <td className="truncate px-3 py-3 text-xs text-slate-500">
                  {site.lastConsultedAt ? site.lastConsultedAt.slice(0, 16).replace('T', ' ') : '—'}
                </td>
                <td className="px-3 py-3">
                  <div className="admin-sites-table-actions">
                    <Button variant="ghost" to={`/admin/sites/${site.id}`} className="admin-touch-target">
                      상세
                    </Button>
                    <Button variant="ghost" to={`/admin/sites/${site.id}/edit`} className="admin-touch-target">
                      수정
                    </Button>
                    <Button
                      variant="ghost"
                      to={`/admin/consults?site=${encodeURIComponent(site.siteCode)}`}
                      className="admin-touch-target"
                    >
                      상담
                    </Button>
                    <Button variant="secondary" onClick={() => onRegenKey(site)} className="admin-touch-target">
                      키 재발급
                    </Button>
                    <Button
                      variant={site.status ? 'danger' : 'primary'}
                      disabled={togglingId === site.id}
                      onClick={() => onToggle(site)}
                      className="admin-touch-target"
                    >
                      {site.status ? '중지' : '활성화'}
                    </Button>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
