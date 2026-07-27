import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { siteService } from '@/services/site.service';
import type { SiteCreatePayload, SiteListFilters, SiteUpdatePayload } from '@/types/site.types';

export function useSites(filters: SiteListFilters = {}) {
  return useQuery({
    queryKey: ['admin-sites', filters],
    queryFn: () => siteService.list(filters),
  });
}

export function useSite(id: number | undefined) {
  return useQuery({
    queryKey: ['admin-sites', id],
    queryFn: () => siteService.get(id!),
    enabled: id != null && id > 0,
  });
}

export function useSiteStats(id: number | undefined) {
  return useQuery({
    queryKey: ['admin-sites', id, 'stats'],
    queryFn: () => siteService.getStats(id!),
    enabled: id != null && id > 0,
  });
}

export function useSiteHealth(id: number | undefined) {
  return useQuery({
    queryKey: ['admin-sites', id, 'health'],
    queryFn: () => siteService.getHealth(id!),
    enabled: id != null && id > 0,
  });
}

export function useSiteCreate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: SiteCreatePayload) => siteService.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin-sites'] });
    },
  });
}

export function useSiteUpdate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: SiteUpdatePayload }) =>
      siteService.update(id, payload),
    onSuccess: (_data, { id }) => {
      void qc.invalidateQueries({ queryKey: ['admin-sites'] });
      void qc.invalidateQueries({ queryKey: ['admin-sites', id] });
    },
  });
}

export function useSiteDelete() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => siteService.delete(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin-sites'] });
    },
  });
}

export function useSiteToggle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => siteService.toggle(id),
    onSuccess: (_data, id) => {
      void qc.invalidateQueries({ queryKey: ['admin-sites'] });
      void qc.invalidateQueries({ queryKey: ['admin-sites', id] });
    },
  });
}

export function useSiteRegenKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => siteService.regenerateKey(id),
    onSuccess: (_data, id) => {
      void qc.invalidateQueries({ queryKey: ['admin-sites', id] });
    },
  });
}

export function useSiteHealthCheck() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => siteService.runHealthCheck(id),
    onSuccess: (_data, id) => {
      void qc.invalidateQueries({ queryKey: ['admin-sites', id] });
      void qc.invalidateQueries({ queryKey: ['admin-sites', id, 'health'] });
    },
  });
}
