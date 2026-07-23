export type ConsultSource = 'acep' | 'crm';

export interface ConsultAgent {
  id: string;
  displayName: string;
}

/** Row from GET /api/v1/admin/consults — extended optional fields for future API parity. */
export interface ConsultListItem {
  id: string;
  source: ConsultSource;
  customerNameMasked: string;
  agent: ConsultAgent | null;
  status: string;
  aiEnabled: boolean;
  aiAdoptionRate?: number;
  contractProbability: number | null;
  createdAt: string;
  updatedAt: string;
  /** Not yet returned by AdminConsultService — reserved for PHP parity. */
  consultNo?: string;
  phoneMasked?: string;
  siteName?: string;
  productName?: string;
  lastMessagePreview?: string;
}

export interface ConsultListMeta {
  page: number;
  limit: number;
  total: number;
}

export interface ConsultListResponse {
  data: ConsultListItem[];
  meta: ConsultListMeta;
}

export interface ConsultListFilters {
  page?: number;
  limit?: number;
  status?: string;
  source?: 'all' | 'acep' | 'crm';
  q?: string;
  site?: string;
  from?: string;
  to?: string;
}

/** Row from GET /api/v1/admin/consults/{id} */
export interface ConsultDetail {
  id: string;
  source: ConsultSource;
  consultNo: string;
  status: string;
  customerNameMasked: string;
  phoneMasked?: string | null;
  email?: string | null;
  siteName?: string | null;
  productName?: string | null;
  memo?: string;
  agent: ConsultAgent | null;
  roomId: string | null;
  aiEnabled: boolean;
  contractProbability: number | null;
  aiSummary?: string | null;
  createdAt: string;
  updatedAt: string;
}

export const CONSULT_STATUS_OPTIONS = [
  { value: '', label: '전체 상태' },
  { value: 'new', label: '신규' },
  { value: 'open', label: '대기' },
  { value: 'active', label: '진행중' },
  { value: 'closed', label: '완료' },
] as const;

export const CONSULT_STATUS_LABELS: Record<string, string> = {
  new: '신규',
  open: '대기',
  active: '진행중',
  closed: '완료',
  pending: '대기',
  in_progress: '진행중',
  completed: '완료',
  cancelled: '취소',
};
