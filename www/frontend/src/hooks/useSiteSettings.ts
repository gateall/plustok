import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '../services/api.client';
import type { SiteSettings } from '../types/siteSettings.types';

const KEYS = {
  all: ['admin', 'settings', 'site'] as const,
};

export function useSiteSettings() {
  return useQuery({
    queryKey: KEYS.all,
    queryFn: async () => apiFetch<SiteSettings>('/admin/settings/site'),
  });
}

export function useUpdateSiteSettings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: SiteSettings) =>
      apiFetch<SiteSettings>('/admin/settings/site', {
        method: 'PUT',
        body: JSON.stringify(payload),
      }),
    onSuccess: (updatedData) => {
      queryClient.setQueryData(KEYS.all, updatedData);
    },
  });
}
