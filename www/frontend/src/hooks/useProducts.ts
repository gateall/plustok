import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { productService } from '@/services/product.service';
import type {
  ProductCreatePayload,
  ProductListFilters,
  ProductUpdatePayload,
} from '@/types/product.types';

export function useProducts(filters: ProductListFilters) {
  return useQuery({
    queryKey: ['admin', 'products', filters],
    queryFn: () => productService.list(filters),
  });
}

export function useProduct(id: number | undefined) {
  return useQuery({
    queryKey: ['admin', 'products', id],
    queryFn: () => productService.get(id!),
    enabled: id != null && id > 0,
  });
}

export function useProductCreate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ProductCreatePayload) => productService.create(payload),
    onSuccess: (created) => {
      void qc.invalidateQueries({ queryKey: ['admin', 'products'] });
      void qc.invalidateQueries({ queryKey: ['admin', 'products', created.id] });
    },
  });
}

export function useProductUpdate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ProductUpdatePayload }) =>
      productService.update(id, payload),
    onSuccess: (_data, { id }) => {
      void qc.invalidateQueries({ queryKey: ['admin', 'products'] });
      void qc.invalidateQueries({ queryKey: ['admin', 'products', id] });
    },
  });
}

export function useProductDelete() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => productService.delete(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'products'] });
    },
  });
}

export function useProductToggle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => productService.toggle(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'products'] });
    },
  });
}
