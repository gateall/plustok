import { describe, expect, it } from 'vitest';

import { parseSiteFilters } from '@/components/sites/SiteFilters';
import type { SiteItem } from '@/types/site.types';
import {
  computeSiteSummary,
  filterSitesByIntegration,
  getSiteIntegrationStatus,
  hasActiveSiteFilters,
  sortSites,
} from '@/utils/siteDisplay';

const baseSite = (overrides: Partial<SiteItem> = {}): SiteItem => ({
  id: 1,
  siteCode: 'demo',
  siteName: 'Demo',
  domain: 'demo.example.com',
  brand: 'Brand',
  division: '',
  persona: null,
  status: true,
  createdAt: '2026-01-01T00:00:00Z',
  updatedAt: null,
  lastHealthCheck: { checkedAt: null, isHealthy: true, responseMs: 1, statusCode: 200, errorMessage: null, targetUrl: null },
  totalConsultCount: 3,
  ...overrides,
});

describe('siteDisplay', () => {
  it('classifies integration status', () => {
    expect(getSiteIntegrationStatus(baseSite())).toBe('healthy');
    expect(
      getSiteIntegrationStatus(
        baseSite({ lastHealthCheck: { checkedAt: null, isHealthy: false, responseMs: null, statusCode: 500, errorMessage: 'fail', targetUrl: null } }),
      ),
    ).toBe('needs_check');
    expect(getSiteIntegrationStatus(baseSite({ status: false }))).toBe('inactive');
  });

  it('computes summary from full list', () => {
    const sites = [
      baseSite({ id: 1 }),
      baseSite({ id: 2, lastHealthCheck: { checkedAt: null, isHealthy: false, responseMs: null, statusCode: 500, errorMessage: null, targetUrl: null } }),
      baseSite({ id: 3, status: false }),
    ];
    expect(computeSiteSummary(sites)).toEqual({
      total: 3,
      healthy: 1,
      needsCheck: 1,
      inactive: 1,
    });
  });

  it('filters by integration card selection', () => {
    const sites = [
      baseSite({ id: 1 }),
      baseSite({ id: 2, status: false }),
    ];
    expect(filterSitesByIntegration(sites, 'inactive')).toHaveLength(1);
    expect(filterSitesByIntegration(sites, '')).toHaveLength(2);
  });

  it('sorts sites by name and consult count', () => {
    const sites = [
      baseSite({ id: 1, siteName: 'Zeta', totalConsultCount: 1 }),
      baseSite({ id: 2, siteName: 'Alpha', totalConsultCount: 9 }),
    ];
    expect(sortSites(sites, 'name').map((s) => s.siteName)).toEqual(['Alpha', 'Zeta']);
    expect(sortSites(sites, 'consult').map((s) => s.id)).toEqual([2, 1]);
  });

  it('detects active filters', () => {
    expect(hasActiveSiteFilters({ q: '', integration: '' })).toBe(false);
    expect(hasActiveSiteFilters({ q: 'demo', integration: '' })).toBe(true);
    expect(hasActiveSiteFilters({ integration: 'healthy' })).toBe(true);
  });
});

describe('parseSiteFilters', () => {
  it('parses integration and sort from search params', () => {
    const params = new URLSearchParams('q=demo&integration=healthy&sort=consult');
    expect(parseSiteFilters(params)).toEqual({
      q: 'demo',
      integration: 'healthy',
      sort: 'consult',
    });
  });

  it('ignores invalid integration values', () => {
    const params = new URLSearchParams('integration=unknown');
    expect(parseSiteFilters(params).integration).toBeUndefined();
  });
});
