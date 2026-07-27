import { useQuery } from '@tanstack/react-query';
import { agentService } from '@/services/agent.service';

export const agentsQueryKey = ['admin', 'agents'] as const;

export function useAgents() {
  return useQuery({
    queryKey: agentsQueryKey,
    queryFn: () => agentService.list(),
    staleTime: 60_000,
  });
}
