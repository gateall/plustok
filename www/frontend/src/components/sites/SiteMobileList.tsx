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
  togglingId?: number | null;
};

function integrationTone(site: SiteItem): 'info' | 'warning' | 'neutral' {
  const status = getSiteIntegrationStatus(site);
  if (status === 'healthy') return 'info';
  if (status === 'needs_check') return 'warning';
  return 'neutral';
}

function SiteCard({
  site,
  onToggle,
  onRegenKey,
  selectedIds,
  onToggleSelect,
  togglingId,
}: { site: SiteItem } & SiteListActions) {
  const integration = getSiteIntegrationStatus(site);

  return (
    <div className="flex min-w-0 flex-col gap-3 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
      <div className="flex items-start gap-3">
        <input
          type="checkbox"
          aria-label={`${site.siteName} 선택`}
          checked={selectedIds.has(site.id)}
          onChange={() => onToggleSelect(site.id)}
          className="mt-1 h-5 w-5 shrink-0 rounded border-slate-300"
        />
        <div className="min-w-0 flex-1">
          <p className="truncate font-bold text-slate-900">{site.siteName}</p>
          <p className="mt-0.5 truncate text-sm text-slate-600">{site.domain || '-'}</p>
          <p className="mt-0.5 truncate font-mono text-xs text-slate-500">{site.siteCode}</p>
        </div>
      </div>

      <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
        <div>
          <dt className="text-xs text-slate-500">연동 상태</dt>
          <dd className="mt-0.5">
            <StatusBadge label={integrationStatusLabel(integration)} tone={integrationTone(site)} />
          </dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">사용 상태</dt>
          <dd className="mt-0.5">
            <StatusBadge label={site.status ? '사용' : '중지'} tone={site.status ? 'success' : 'neutral'} />
          </dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">상담 수</dt>
          <dd className="mt-0.5 font-medium tabular-nums text-slate-800">{site.totalConsultCount ?? 0}</dd>
        </div>
        <div>
          <dt className="text-xs text-slate-500">최근 상담</dt>
          <dd className="mt-0.5 text-xs text-slate-600">
            {site.lastConsultedAt ? site.lastConsultedAt.slice(0, 16).replace('T', ' ') : '—'}
          </dd>
        </div>
      </dl>

      <div className="admin-sites-mobile-actions">
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
          className="admin-touch-target col-span-2 sm:col-span-1"
        >
          {site.status ? '중지' : '활성화'}
        </Button>
      </div>
    </div>
  );
}

export default function SiteMobileList({
  sites,
  ...actions
}: { sites: SiteItem[] } & SiteListActions) {
  return (
    <div className="admin-mobile-list flex min-w-0 flex-col gap-3">
      {sites.map((site) => (
        <SiteCard key={site.id} site={site} {...actions} />
      ))}
    </div>
  );
}
