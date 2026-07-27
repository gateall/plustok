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

export function useProductCreate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ProductCreatePayload) => productService.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'products'] });
    },
  });
}

export function useProductUpdate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ProductUpdatePayload }) =>
      productService.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'products'] });
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
