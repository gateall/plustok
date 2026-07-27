import { apiFetch } from './api.client';
import type {
  AdminAgentListResponse,
  AdminAgentCreatePayload,
  AdminAgentUpdatePayload,
} from '../types/adminAgent.types';

export const adminAgentService = {
  list: () => apiFetch<AdminAgentListResponse>('/admin/agents'),
  
  create: (payload: AdminAgentCreatePayload) => 
    apiFetch<{ id: string; displayName: string; role: string }>('/admin/agents', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
    
  update: (id: string, payload: AdminAgentUpdatePayload) =>
    apiFetch<{ id: string; updated: boolean }>(`/admin/agents/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
};
