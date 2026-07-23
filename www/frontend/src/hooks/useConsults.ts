import { useQuery } from '@tanstack/react-query';
import { consultService } from '../services/consult.service';
import type { ConsultListFilters } from '../types/consult.types';

export const consultsQueryKey = (filters: ConsultListFilters) =>
  ['admin', 'consults', filters] as const;

export function useConsults(filters: ConsultListFilters) {
  return useQuery({
    queryKey: consultsQueryKey(filters),
    queryFn: () => consultService.list(filters),
    refetchInterval: 60_000,
  });
}
