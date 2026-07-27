import { describe, expect, it } from 'vitest';
import { normalizeSiteList } from '../services/site.service';
import type { SiteItem, SiteListResponse } from '../types/site.types';

describe('site.service', () => {
  const sampleSite: SiteItem = {
    id: 1,
    siteCode: 'demo',
    siteName: 'Demo Site',
    domain: 'demo.example.com',
    brand: 'Demo',
    division: 'Sales',
    persona: null,
    status: true,
    createdAt: null,
    updatedAt: null,
    lastHealthCheck: null,
  };

  describe('normalizeSiteList', () => {
    it('returns data array when present', () => {
      const response: SiteListResponse = { data: [sampleSite] };
      expect(normalizeSiteList(response)).toEqual([sampleSite]);
    });

    it('returns items array when present', () => {
      const response = { items: [sampleSite] };
      expect(normalizeSiteList(response)).toEqual([sampleSite]);
    });

    it('returns empty array when data is undefined', () => {
      expect(normalizeSiteList(undefined)).toEqual([]);
    });

    it('returns empty array when data is not an array', () => {
      expect(normalizeSiteList({ data: null as unknown as SiteItem[] })).toEqual([]);
      expect(normalizeSiteList({ data: 'invalid' as unknown as SiteItem[] })).toEqual([]);
      expect(normalizeSiteList({ items: {} as unknown as SiteItem[] })).toEqual([]);
    });
  });
});

describe('Array.isArray guard pattern', () => {
  it('guards sites list from malformed API response', () => {
    const data: { data?: unknown; items?: unknown } = { data: [{ id: 1 }] };
    const sites = Array.isArray(data?.data)
      ? data.data
      : Array.isArray(data?.items)
        ? data.items
        : [];
    expect(sites).toHaveLength(1);

    const bad: { data?: unknown; items?: unknown } = { data: null };
    const empty = Array.isArray(bad?.data)
      ? bad.data
      : Array.isArray(bad?.items)
        ? bad.items
        : [];
    expect(empty).toEqual([]);
  });
});
