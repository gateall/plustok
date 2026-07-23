import { apiFetch } from './api.client';
import type { ConsultDetail, ConsultListFilters, ConsultListResponse } from '../types/consult.types';

/**
 * GET /api/v1/admin/consults
 * Supported query params (AdminConsultService): page, limit, status, source.
 * PHP parity filters (site, q, from, to) are forwarded for future API support.
 */
function buildQuery(filters: ConsultListFilters): string {
  const params = new URLSearchParams();
  if (filters.page) params.set('page', String(filters.page));
  if (filters.limit) params.set('limit', String(filters.limit));
  if (filters.status) params.set('status', filters.status);
  if (filters.source && filters.source !== 'all') params.set('source', filters.source);
  if (filters.q) params.set('q', filters.q);
  if (filters.site) params.set('site', filters.site);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  const qs = params.toString();
  return qs ? `?${qs}` : '';
}

export const consultService = {
  list: (filters: ConsultListFilters = {}) =>
    apiFetch<ConsultListResponse>(`/admin/consults${buildQuery(filters)}`),

  /** GET /api/v1/admin/consults/{id} — ACEP room UUID or CRM consult_no */
  getConsult: (id: string) =>
    apiFetch<ConsultDetail>(`/admin/consults/${encodeURIComponent(id)}`),

  /** roomId is included on ConsultDetail; helper for chat panel wiring */
  getChatRoomId: async (consultId: string): Promise<string | null> => {
    const detail = await consultService.getConsult(consultId);
    return detail.roomId;
  },
};
