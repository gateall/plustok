import { apiFetch } from './api.client';
import type {
  ContractItem,
  ContractsResponse,
  ContractListFilters,
  CreateContractPayload,
  UpdateContractPayload,
} from '../types/contract.types';

function pick<T>(raw: Record<string, unknown>, camel: string, snake: string): T | undefined {
  if (raw[camel] !== undefined) return raw[camel] as T;
  if (raw[snake] !== undefined) return raw[snake] as T;
  return undefined;
}

function mapPermissions(raw: Record<string, unknown> | undefined): ContractItem['permissions'] {
  if (!raw) return undefined;
  return {
    canEdit: Boolean(raw.canEdit ?? raw.can_edit),
    canCancel: Boolean(raw.canCancel ?? raw.can_cancel),
    canArchive: Boolean(raw.canArchive ?? raw.can_archive),
    canDelete: Boolean(raw.canDelete ?? raw.can_delete),
  };
}

export function mapContractItem(raw: Record<string, unknown>): ContractItem {
  const totalAmount = Number(pick(raw, 'totalAmount', 'total_amount') ?? 0);
  const paidAmount = Number(pick(raw, 'paidAmount', 'paid_amount') ?? 0);
  const outstandingRaw = pick<number>(raw, 'outstandingAmount', 'outstanding_amount');
  const outstandingAmount =
    outstandingRaw !== undefined ? Number(outstandingRaw) : Math.max(0, totalAmount - paidAmount);

  return {
    id: String(raw.id ?? ''),
    contractNo: String(pick(raw, 'contractNo', 'contract_no') ?? ''),
    title: String(raw.title ?? ''),
    customerId: String(pick(raw, 'customerId', 'customer_id') ?? ''),
    customerName: (pick<string | null>(raw, 'customerName', 'customer_name') ?? null) as string | null,
    siteId: pick<number | null>(raw, 'siteId', 'site_id') ?? null,
    productName: (pick<string | null>(raw, 'productName', 'product_name') ?? null) as string | null,
    managerId: (pick<string | null>(raw, 'managerId', 'manager_id') ?? null) as string | null,
    totalAmount,
    paidAmount,
    outstandingAmount,
    status: String(raw.status ?? 'draft'),
    documentStatus: String(pick(raw, 'documentStatus', 'document_status') ?? 'none'),
    startDate: (pick<string | null>(raw, 'startDate', 'start_date') ?? null) as string | null,
    endDate: (pick<string | null>(raw, 'endDate', 'end_date') ?? null) as string | null,
    signedAt: (pick<string | null>(raw, 'signedAt', 'signed_at') ?? null) as string | null,
    signerName: (pick<string | null>(raw, 'signerName', 'signer_name') ?? null) as string | null,
    cancelReasonCode: (pick<string | null>(raw, 'cancelReasonCode', 'cancel_reason_code') ?? null) as string | null,
    cancelReason: (pick<string | null>(raw, 'cancelReason', 'cancel_reason') ?? null) as string | null,
    cancelledAt: (pick<string | null>(raw, 'cancelledAt', 'cancelled_at') ?? null) as string | null,
    archivedAt: (pick<string | null>(raw, 'archivedAt', 'archived_at') ?? null) as string | null,
    notes: (raw.notes as string | null) ?? null,
    contractedAt: (pick<string | null>(raw, 'contractedAt', 'contracted_at') ?? null) as string | null,
    createdAt: (pick<string | null>(raw, 'createdAt', 'created_at') ?? null) as string | null,
    updatedAt: (pick<string | null>(raw, 'updatedAt', 'updated_at') ?? null) as string | null,
    permissions: mapPermissions(raw.permissions as Record<string, unknown> | undefined),
    deleteBlockReason: (pick<string | null>(raw, 'deleteBlockReason', 'delete_block_reason') ?? null) as string | null,
  };
}

function mapContractsResponse(raw: Record<string, unknown>): ContractsResponse {
  const itemsRaw = (raw.items as Record<string, unknown>[] | undefined) ?? [];
  return {
    items: itemsRaw.map(mapContractItem),
    total: Number(raw.total ?? 0),
    page: Number(raw.page ?? 1),
    limit: Number(raw.limit ?? 20),
    sort: raw.sort as string | undefined,
    order: (raw.order as 'asc' | 'desc' | undefined) ?? 'desc',
  };
}

function buildQuery(filters: ContractListFilters): string {
  const q = new URLSearchParams();
  if (filters.page) q.set('page', String(filters.page));
  if (filters.limit) q.set('limit', String(filters.limit));
  if (filters.q) q.set('q', filters.q);
  if (filters.status) q.set('status', filters.status);
  if (filters.site_id) q.set('site_id', String(filters.site_id));
  if (filters.sort) q.set('sort', filters.sort);
  if (filters.order) q.set('order', filters.order);
  const str = q.toString();
  return str ? `?${str}` : '';
}

export const contractService = {
  list: async (filters: ContractListFilters = {}): Promise<ContractsResponse> => {
    const { payment_status: _clientOnly, ...apiFilters } = filters;
    const data = await apiFetch<Record<string, unknown>>(`/admin/contracts${buildQuery(apiFilters)}`);
    return mapContractsResponse(data);
  },

  get: async (id: string): Promise<ContractItem> => {
    const data = await apiFetch<Record<string, unknown>>(`/admin/contracts/${id}`);
    return mapContractItem(data);
  },

  create: async (payload: CreateContractPayload): Promise<ContractItem> => {
    const data = await apiFetch<Record<string, unknown>>('/admin/contracts', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    return mapContractItem(data);
  },

  update: async (id: string, payload: UpdateContractPayload): Promise<ContractItem> => {
    const data = await apiFetch<Record<string, unknown>>(`/admin/contracts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
    return mapContractItem(data);
  },

  updateStatus: async (id: string, status: string, signerName?: string): Promise<ContractItem> => {
    const body: Record<string, string> = { status };
    if (signerName) body.signerName = signerName;
    const data = await apiFetch<Record<string, unknown>>(`/admin/contracts/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(body),
    });
    return mapContractItem(data);
  },
};
