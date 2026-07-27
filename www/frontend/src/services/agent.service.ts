import { apiFetch } from './api.client';
import type { ConsultAgent } from '../types/consult.types';

type AgentListResponse = {
  data: Array<{
    id: string;
    displayName: string;
    loginId?: string;
    role?: string;
    status?: string;
    activeAssignments?: number;
  }>;
};

export const agentService = {
  /** GET /api/v1/admin/agents */
  list: async (): Promise<ConsultAgent[]> => {
    const raw = await apiFetch<AgentListResponse>('/admin/agents');
    return (raw.data ?? []).map((agent) => ({
      id: agent.id,
      displayName: agent.displayName,
    }));
  },
};
