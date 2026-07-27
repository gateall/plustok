import { apiFetch } from './api.client';
import type { SiteListFilters, SiteListResponse } from '../types/site.types';

function buildQuery(filters: SiteListFilters): string {
  const q = new URLSearchParams();
  if (filters.q) q.set('q', filters.q);
  if (filters.status) q.set('status', filters.status);
  if (filters.brand) q.set('brand', filters.brand);
  if (filters.division) q.set('division', filters.division);
  const str = q.toString();
  return str ? `?${str}` : '';
}

export const siteService = {
  list: (filters: SiteListFilters = {}) =>
    apiFetch<SiteListResponse>(`/admin/sites${buildQuery(filters)}`),
};
