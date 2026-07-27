export interface ContractPermissions {
  canEdit: boolean;
  canCancel: boolean;
  canArchive: boolean;
  canDelete: boolean;
}

export interface ContractItem {
  id: string;
  contractNo: string;
  title: string;
  customerId: string;
  customerName: string | null;
  siteId: number | null;
  productName: string | null;
  managerId: string | null;
  totalAmount: number;
  paidAmount: number;
  outstandingAmount: number;
  status: string;
  documentStatus: string;
  startDate: string | null;
  endDate: string | null;
  signedAt: string | null;
  signerName: string | null;
  cancelReasonCode: string | null;
  cancelReason: string | null;
  cancelledAt: string | null;
  archivedAt: string | null;
  notes: string | null;
  contractedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  permissions?: ContractPermissions;
  deleteBlockReason?: string | null;
}

export interface ContractsResponse {
  items: ContractItem[];
  total: number;
  page: number;
  limit: number;
  sort?: string;
  order?: 'asc' | 'desc';
}

export interface ContractListFilters {
  page?: number;
  limit?: number;
  q?: string;
  status?: string;
  site_id?: number;
  sort?: string;
  order?: 'asc' | 'desc';
  /** 클라이언트 전용 — API 미지원, 목록 표시 필터 */
  payment_status?: PaymentStatusFilter;
}

export type PaymentStatusFilter = '' | 'paid' | 'partial' | 'unpaid' | 'overdue';

export interface ContractSummary {
  total: number;
  inProgress: number;
  expiringSoon: number;
  outstanding: number;
  /** 페이지 기준 집계 여부 */
  pageScoped: boolean;
}

export interface CreateContractPayload {
  title: string;
  customerId: string;
  totalAmount: number;
  siteId?: number | null;
  productName?: string | null;
  managerId?: string | null;
  startDate?: string | null;
  endDate?: string | null;
  notes?: string | null;
}

export interface UpdateContractPayload {
  title?: string;
  siteId?: number | null;
  productName?: string | null;
  managerId?: string | null;
  totalAmount?: number;
  startDate?: string | null;
  endDate?: string | null;
  notes?: string | null;
}

export const CONTRACT_STATUS_OPTIONS = [
  { value: '', label: '전체 상태' },
  { value: 'draft', label: '초안' },
  { value: 'review', label: '검토' },
  { value: 'sent', label: '발송' },
  { value: 'signature_pending', label: '서명 대기' },
  { value: 'signed', label: '서명 완료' },
  { value: 'active', label: '진행중' },
  { value: 'on_hold', label: '보류' },
  { value: 'completed', label: '완료' },
  { value: 'cancelled', label: '취소' },
  { value: 'expired', label: '만료' },
  { value: 'archived', label: '보관' },
] as const;

export const CONTRACT_STATUS_LABELS: Record<string, string> = {
  draft: '초안',
  review: '검토',
  sent: '발송',
  signature_pending: '서명 대기',
  signed: '서명 완료',
  active: '진행중',
  on_hold: '보류',
  completed: '완료',
  cancelled: '취소',
  expired: '만료',
  archived: '보관',
};

export const PAYMENT_STATUS_OPTIONS = [
  { value: '', label: '전체 결제' },
  { value: 'paid', label: '완납' },
  { value: 'partial', label: '부분납' },
  { value: 'unpaid', label: '미납' },
  { value: 'overdue', label: '연체' },
] as const;

export const CONTRACT_SORT_OPTIONS = [
  { value: 'contracted_at', label: '계약일' },
  { value: 'total_amount', label: '금액' },
  { value: 'end_date', label: '종료일' },
  { value: 'status', label: '상태' },
  { value: 'customer_name', label: '고객명' },
] as const;

export const CONTRACT_LIMIT_OPTIONS = [20, 50, 100] as const;

/** 진행중으로 집계할 상태 */
export const IN_PROGRESS_STATUSES = new Set([
  'review',
  'sent',
  'signature_pending',
  'signed',
  'active',
  'on_hold',
]);
