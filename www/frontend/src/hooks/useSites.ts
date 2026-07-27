import { useQuery } from '@tanstack/react-query';

import { siteService } from '@/services/site.service';
import type { SiteListFilters } from '@/types/site.types';

export function useSites(filters: SiteListFilters = {}) {
  return useQuery({
    queryKey: ['admin-sites', filters],
    queryFn: () => siteService.list(filters),
  });
}
