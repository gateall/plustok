import { useQuery } from '@tanstack/react-query';
import { customerService } from '../services/customer.service';
import type { CustomerListFilters } from '../types/customer.types';

export function useCustomers(filters: CustomerListFilters) {
  return useQuery({
    queryKey: ['customers', filters],
    queryFn: () => customerService.list(filters),
    refetchInterval: 60_000,
  });
}
