import { Globe, Activity } from 'lucide-react';

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

function SiteCard({
  site,
  onToggle,
  onRegenKey,
  onDelete,
  togglingId,
}: { site: SiteItem } & SiteListActions) {
  const health = healthLabel(site);
  return (
    <div className="flex flex-col gap-3 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <Button variant="ghost" to={`/admin/sites/${site.id}`} className="!min-h-0 !px-0 !py-0 !bg-transparent hover:!bg-transparent">
            <p className="truncate font-bold text-slate-900">{site.siteName}</p>
          </Button>
          <p className="mt-0.5 truncate font-mono text-xs text-slate-500">{site.siteCode}</p>
        </div>
        <StatusBadge label={site.status ? '활성' : '중지'} tone={site.status ? 'success' : 'neutral'} />
      </div>
      <div className="flex flex-col gap-1.5 text-sm text-slate-600">
        <div className="flex items-center gap-2 truncate">
          <Globe size={14} className="shrink-0 text-slate-400" />
          <span className="truncate">{site.domain || '-'}</span>
        </div>
        <div className="flex items-center gap-2 truncate">
          <Activity size={14} className="shrink-0 text-slate-400" />
          <StatusBadge label={health.label} tone={health.tone} />
        </div>
        <p className="text-xs text-slate-500">
          상담 {site.totalConsultCount ?? 0}건 · 오늘 {site.todayConsultCount ?? 0}건
        </p>
      </div>
      <div className="flex flex-wrap gap-2">
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
    </div>
  );
}

export default function SiteMobileList({
  sites,
  ...actions
}: { sites: SiteItem[] } & SiteListActions) {
  return (
    <div className="admin-mobile-list flex flex-col gap-3">
      {sites.map((site) => (
        <SiteCard key={site.id} site={site} {...actions} />
      ))}
    </div>
  );
}
