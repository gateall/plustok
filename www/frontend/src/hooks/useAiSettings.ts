import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '../services/api.client';
import type { AiSettings, AiTestResponse } from '../types/aiSettings.types';

const KEYS = {
  all: ['admin', 'settings', 'ai'] as const,
};

export function useAiSettings() {
  return useQuery({
    queryKey: KEYS.all,
    queryFn: async () => {
      const data = await apiFetch<AiSettings>('/admin/settings/ai');
      return data;
    },
  });
}

export function useUpdateAiSettings() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (payload: AiSettings) => {
      const data = await apiFetch<AiSettings>('/admin/settings/ai', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      return data;
    },
    onSuccess: (updatedData) => {
      queryClient.setQueryData(KEYS.all, updatedData);
    },
  });
}

export function useTestAiConnection() {
  return useMutation({
    mutationFn: async (provider: string) => {
      const data = await apiFetch<AiTestResponse>('/admin/settings/ai/test', {
        method: 'POST',
        body: JSON.stringify({ provider }),
      });
      return data;
    },
  });
}

export function useDeleteAiKey() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (provider: string) => {
      const data = await apiFetch<any>(`/admin/settings/ai/providers/${encodeURIComponent(provider)}/key`, {
        method: 'DELETE',
      });
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}
