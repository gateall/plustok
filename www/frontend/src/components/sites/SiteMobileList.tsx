import { Globe, Activity } from 'lucide-react';

import StatusBadge from '@/components/admin-ui/StatusBadge';
import type { SiteItem } from '@/types/site.types';

function healthLabel(site: SiteItem): { label: string; tone: 'success' | 'danger' | 'neutral' } {
  const health = site.lastHealthCheck;
  if (!health) return { label: '미검사', tone: 'neutral' };
  return health.isHealthy
    ? { label: '정상', tone: 'success' }
    : { label: '점검 필요', tone: 'danger' };
}

function SiteCard({ site }: { site: SiteItem }) {
  const health = healthLabel(site);
  return (
    <div className="flex flex-col gap-3 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="font-bold text-slate-900 truncate">{site.siteName}</p>
          <p className="mt-0.5 text-xs font-mono text-slate-500 truncate">{site.siteCode}</p>
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
    </div>
  );
}

export default function SiteMobileList({ sites }: { sites: SiteItem[] }) {
  return (
    <div className="admin-mobile-list flex flex-col gap-3">
      {sites.map((site) => (
        <SiteCard key={site.id} site={site} />
      ))}
    </div>
  );
}
