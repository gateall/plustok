import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminAgentService } from '../services/adminAgent.service';
import type { AdminAgentCreatePayload, AdminAgentUpdatePayload } from '../types/adminAgent.types';

export function useAdminUsers() {
  return useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => adminAgentService.list(),
  });
}

export function useAdminUserCreate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: AdminAgentCreatePayload) => adminAgentService.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
}

export function useAdminUserUpdate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: AdminAgentUpdatePayload }) => 
      adminAgentService.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
}
