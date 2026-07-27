import { apiFetch } from './api.client';
import type {
  ProductCreatePayload,
  ProductItem,
  ProductListFilters,
  ProductsResponse,
  ProductUpdatePayload,
} from '../types/product.types';

function buildQuery(filters: ProductListFilters): string {
  const q = new URLSearchParams();
  if (filters.page) q.set('page', filters.page.toString());
  if (filters.limit) q.set('limit', filters.limit.toString());
  if (filters.q) q.set('q', filters.q);
  if (filters.brand) q.set('brand', filters.brand);
  if (filters.use_yn) q.set('use_yn', filters.use_yn);
  const str = q.toString();
  return str ? `?${str}` : '';
}

export const productService = {
  list: (filters: ProductListFilters = {}) =>
    apiFetch<ProductsResponse>(`/admin/products${buildQuery(filters)}`),

  get: (id: number) => apiFetch<ProductItem>(`/admin/products/${id}`),

  create: (payload: ProductCreatePayload) =>
    apiFetch<ProductItem>('/admin/products', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  update: (id: number, payload: ProductUpdatePayload) =>
    apiFetch<ProductItem>(`/admin/products/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),

  delete: (id: number) =>
    apiFetch<{ deleted: boolean; id: number }>(`/admin/products/${id}`, {
      method: 'DELETE',
    }),

  toggle: (id: number) =>
    apiFetch<ProductItem>(`/admin/products/${id}/toggle`, {
      method: 'POST',
    }),
};
