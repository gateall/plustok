import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { contractService } from '../services/contract.service';
import type { ContractListFilters, CreateContractPayload, UpdateContractPayload } from '../types/contract.types';

export function useContracts(filters: ContractListFilters) {
  return useQuery({
    queryKey: ['contracts', filters],
    queryFn: () => contractService.list(filters),
    refetchInterval: 60_000,
  });
}

export function useContract(id: string | undefined) {
  return useQuery({
    queryKey: ['contract', id],
    queryFn: () => contractService.get(id!),
    enabled: Boolean(id),
  });
}

export function useCreateContract() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateContractPayload) => contractService.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['contracts'] });
    },
  });
}

export function useUpdateContract(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: UpdateContractPayload) => contractService.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['contracts'] });
      void qc.invalidateQueries({ queryKey: ['contract', id] });
    },
  });
}

export function useUpdateContractStatus(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ status, signerName }: { status: string; signerName?: string }) =>
      contractService.updateStatus(id, status, signerName),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['contracts'] });
      void qc.invalidateQueries({ queryKey: ['contract', id] });
    },
  });
}
