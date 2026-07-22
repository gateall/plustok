import { useQuery, useQueryClient } from '@tanstack/react-query';
import { adminService } from '../services/admin.service';

export function useAdminOverview() {
  return useQuery({
    queryKey: ['admin', 'overview'],
    queryFn: () => adminService.overview(),
    refetchInterval: 60_000,
  });
}

export function useAdminSentiment() {
  return useQuery({
    queryKey: ['admin', 'sentiment'],
    queryFn: () => adminService.sentiment(),
    refetchInterval: 60_000,
  });
}

export function useAdminFunnel() {
  return useQuery({
    queryKey: ['admin', 'funnel'],
    queryFn: () => adminService.funnel(),
    refetchInterval: 60_000,
  });
}

export function useAdminAgents() {
  return useQuery({
    queryKey: ['admin', 'agents'],
    queryFn: () => adminService.agents(),
    refetchInterval: 300_000,
  });
}

export function useAdminTrends() {
  return useQuery({
    queryKey: ['admin', 'trends'],
    queryFn: () => adminService.trends(),
    refetchInterval: 300_000,
  });
}

export function useAdminMonitor() {
  return useQuery({
    queryKey: ['admin', 'monitor'],
    queryFn: () => adminService.monitorRooms(),
    refetchInterval: 30_000,
  });
}

export function useAdminRealtimeInvalidation() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: ['admin', 'overview'] });
    void qc.invalidateQueries({ queryKey: ['admin', 'monitor'] });
    void qc.invalidateQueries({ queryKey: ['admin', 'agents'] });
  };
}
