import type { SiteIntegrationFilter, SiteItem, SiteListSummary, SiteSortKey } from '@/types/site.types';

export type SiteIntegrationStatus = 'healthy' | 'needs_check' | 'inactive';

export function getSiteIntegrationStatus(site: SiteItem): SiteIntegrationStatus {
  if (!site.status) return 'inactive';
  if (site.lastHealthCheck?.isHealthy === true) return 'healthy';
  return 'needs_check';
}

export function integrationStatusLabel(status: SiteIntegrationStatus): string {
  switch (status) {
    case 'healthy':
      return '정상연동';
    case 'needs_check':
      return '점검필요';
    case 'inactive':
      return '비활성';
  }
}

export function integrationFilterToStatus(
  integration: SiteIntegrationFilter | undefined,
): SiteIntegrationStatus | null {
  if (!integration) return null;
  return integration;
}

export function computeSiteSummary(sites: SiteItem[]): SiteListSummary {
  let healthy = 0;
  let needsCheck = 0;
  let inactive = 0;

  for (const site of sites) {
    const status = getSiteIntegrationStatus(site);
    if (status === 'healthy') healthy += 1;
    else if (status === 'needs_check') needsCheck += 1;
    else inactive += 1;
  }

  return {
    total: sites.length,
    healthy,
    needsCheck,
    inactive,
  };
}

export function filterSitesByIntegration(
  sites: SiteItem[],
  integration: SiteIntegrationFilter | undefined,
): SiteItem[] {
  if (!integration) return sites;
  return sites.filter((site) => getSiteIntegrationStatus(site) === integration);
}

export function sortSites(sites: SiteItem[], sort: SiteSortKey | undefined): SiteItem[] {
  const key = sort ?? 'name';
  const copy = [...sites];

  switch (key) {
    case 'created':
      copy.sort((a, b) => {
        const aTime = a.createdAt ? Date.parse(a.createdAt) : 0;
        const bTime = b.createdAt ? Date.parse(b.createdAt) : 0;
        return bTime - aTime;
      });
      break;
    case 'consult':
      copy.sort(
        (a, b) => (b.totalConsultCount ?? 0) - (a.totalConsultCount ?? 0),
      );
      break;
    case 'last_consult':
      copy.sort(
        (a, b) => (b.totalConsultCount ?? 0) - (a.totalConsultCount ?? 0),
      );
      break;
    case 'name':
    default:
      copy.sort((a, b) => a.siteName.localeCompare(b.siteName, 'ko'));
      break;
  }

  return copy;
}

export function hasActiveSiteFilters(params: {
  q?: string;
  integration?: SiteIntegrationFilter;
}): boolean {
  return Boolean(params.q?.trim() || params.integration);
}
