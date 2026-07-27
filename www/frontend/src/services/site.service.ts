import { apiFetch } from './api.client';
import type {
  SiteCreatePayload,
  SiteCreateResponse,
  SiteDeleteResponse,
  SiteHealthCheck,
  SiteHealthHistoryResponse,
  SiteItem,
  SiteListFilters,
  SiteListResponse,
  SiteRegenKeyResponse,
  SiteStats,
  SiteToggleResponse,
  SiteUpdatePayload,
} from '../types/site.types';

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

  get: (id: number) => apiFetch<SiteItem>(`/admin/sites/${id}`),

  create: (payload: SiteCreatePayload) =>
    apiFetch<SiteCreateResponse>('/admin/sites', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  update: (id: number, payload: SiteUpdatePayload) =>
    apiFetch<SiteItem>(`/admin/sites/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),

  delete: (id: number) =>
    apiFetch<SiteDeleteResponse>(`/admin/sites/${id}`, {
      method: 'DELETE',
    }),

  toggle: (id: number) =>
    apiFetch<SiteToggleResponse>(`/admin/sites/${id}/toggle`, {
      method: 'POST',
    }),

  regenerateKey: (id: number) =>
    apiFetch<SiteRegenKeyResponse>(`/admin/sites/${id}/regen-key`, {
      method: 'POST',
    }),

  getStats: (id: number) => apiFetch<SiteStats>(`/admin/sites/${id}/stats`),

  getHealth: (id: number) =>
    apiFetch<SiteHealthHistoryResponse>(`/admin/sites/${id}/health`),

  runHealthCheck: (id: number) =>
    apiFetch<SiteHealthCheck>(`/admin/sites/${id}/health-check`, {
      method: 'POST',
    }),
};

/** Guard helper — ensures sites list is always an array */
export function normalizeSiteList(data: SiteListResponse | unknown | undefined): SiteItem[] {
  if (data == null || typeof data !== 'object') return [];
  const envelope = data as Record<string, unknown>;
  if (Array.isArray(envelope.items)) return envelope.items as SiteItem[];
  if (Array.isArray(envelope.data)) return envelope.data as SiteItem[];
  return [];
}
