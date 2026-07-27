import { apiFetch } from './api.client';
import type { CustomersResponse, CustomerListFilters } from '../types/customer.types';

function buildQuery(filters: CustomerListFilters): string {
  const q = new URLSearchParams();
  if (filters.page) q.set('page', filters.page.toString());
  if (filters.limit) q.set('limit', filters.limit.toString());
  if (filters.status) q.set('status', filters.status);
  if (filters.q) q.set('q', filters.q);
  if (filters.site_id) q.set('site_id', filters.site_id.toString());
  if (filters.sort) q.set('sort', filters.sort);
  if (filters.order) q.set('order', filters.order);

  const str = q.toString();
  return str ? `?${str}` : '';
}

export const customerService = {
  list: (filters: CustomerListFilters = {}) =>
    apiFetch<CustomersResponse>(`/admin/customers${buildQuery(filters)}`),
};
