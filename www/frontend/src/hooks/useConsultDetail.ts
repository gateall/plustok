import { useQuery } from '@tanstack/react-query';
import { consultService } from '../services/consult.service';

export const consultDetailQueryKey = (id: string) => ['admin', 'consults', 'detail', id] as const;

export function useConsultDetail(id: string | undefined) {
  return useQuery({
    queryKey: id ? consultDetailQueryKey(id) : ['admin', 'consults', 'detail', 'none'],
    queryFn: () => consultService.getConsult(id!),
    enabled: !!id,
    staleTime: 30_000,
  });
}
