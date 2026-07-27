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
    const items = Array.isArray(raw.data) ? raw.data : [];
    return items.map((agent) => ({
      id: agent.id,
      displayName: agent.displayName,
    }));
  },
};
