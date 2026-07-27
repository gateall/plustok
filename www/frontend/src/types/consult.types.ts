export type ConsultSource = 'acep' | 'crm';

export interface ConsultAgent {
  id: string;
  displayName: string;
}

/** Row from consult_meta table — indexed detail_json fields. */
export interface ConsultMetaEntry {
  metaKey: string;
  metaValue: string;
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
  consultNo?: string;
  phoneMasked?: string;
  siteName?: string;
  productName?: string;
  lastMessagePreview?: string;
  unreadCount?: number;
  priority?: string;
  tags?: ConsultTag[];
  customerType?: string;
  isVip?: boolean;
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
  metaKey?: string;
  metaValue?: string;
  tag?: string;
  sort?: 'newest' | 'oldest' | 'status' | 'customer' | 'recent_response' | 'unprocessed';
  assignee?: string;
}

export const CONSULT_SORT_OPTIONS = [
  { value: 'newest', label: '최신 접수순' },
  { value: 'oldest', label: '오래된 접수순' },
  { value: 'recent_response', label: '최근 응답순' },
  { value: 'unprocessed', label: '미처리 우선' },
  { value: 'status', label: '상태순' },
  { value: 'customer', label: '고객명순' },
] as const;

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
  aiSummaryAt?: string | null;
  sentiment?: string | null;
  categoryAi?: string | null;
  contractScore?: number | null;
  aiConfidence?: number | null;
  aiAnalyzedAt?: string | null;
  aiTags?: string[] | null;
  detailJson?: Record<string, unknown> | null;
  consultMeta?: ConsultMetaEntry[];
  createdAt: string;
  updatedAt: string;
  priority?: string;
  tags?: ConsultTag[];
  customerType?: string;
  isVip?: boolean;
  recentActivity?: string;
}

/** Filter dropdown — list page */
export const CONSULT_STATUS_OPTIONS = [
  { value: '', label: '전체 상태' },
  { value: 'new', label: '접수' },
  { value: 'progress', label: '진행' },
  { value: 'consulting', label: '상담중' },
  { value: 'contracted', label: '계약' },
  { value: 'installed', label: '완료' },
  { value: 'hold', label: '보류' },
  { value: 'canceled', label: '취소' },
] as const;

/** Detail header status dropdown — immediate save UI */
export const CONSULT_STATUS_DROPDOWN = [
  { value: 'new', label: '접수' },
  { value: 'progress', label: '진행' },
  { value: 'consulting', label: '상담중' },
  { value: 'contracted', label: '계약' },
  { value: 'installed', label: '완료' },
  { value: 'hold', label: '보류' },
  { value: 'canceled', label: '취소' },
] as const;

export const CONSULT_STATUS_LABELS: Record<string, string> = {
  new: '접수',
  progress: '진행',
  consulting: '상담중',
  contracted: '계약',
  installed: '완료',
  hold: '보류',
  canceled: '취소',
  open: '대기',
  active: '상담중',
  closed: '완료',
  pending: '대기',
  in_progress: '진행',
  completed: '완료',
  cancelled: '취소',
  quoted: '견적발송',
};

export type ConsultDetailTab = 'chat' | 'memo' | 'history' | 'files';

export const CONSULT_TAG_COLORS = [
  { key: 'indigo', label: '인디고', chipClass: 'bg-indigo-100 text-indigo-800 ring-indigo-200' },
  { key: 'sky', label: '스카이', chipClass: 'bg-sky-100 text-sky-800 ring-sky-200' },
  { key: 'emerald', label: '에메랄드', chipClass: 'bg-emerald-100 text-emerald-800 ring-emerald-200' },
  { key: 'amber', label: '앰버', chipClass: 'bg-amber-100 text-amber-800 ring-amber-200' },
  { key: 'rose', label: '로즈', chipClass: 'bg-rose-100 text-rose-800 ring-rose-200' },
  { key: 'slate', label: '슬레이트', chipClass: 'bg-slate-100 text-slate-800 ring-slate-200' },
] as const;

export type ConsultTagColorKey = (typeof CONSULT_TAG_COLORS)[number]['key'];

export interface ConsultTag {
  id: string;
  label: string;
  color: ConsultTagColorKey;
}

export type TimelineSection = 'history' | 'changes' | 'activity';

export type TimelineEntryKind =
  | 'consult_created'
  | 'status'
  | 'assign'
  | 'memo'
  | 'ai_summary'
  | 'ai_reply'
  | 'email'
  | 'attachment'
  | 'contract'
  | 'completed'
  | 'message'
  | 'note'
  | 'system'
  | 'file'
  | 'tag';

export const DEFAULT_CONSULT_TAGS: readonly Omit<ConsultTag, 'id'>[] = [
  { label: '신규', color: 'sky' },
  { label: '진행중', color: 'indigo' },
  { label: '대기', color: 'amber' },
  { label: '완료', color: 'emerald' },
  { label: '긴급', color: 'rose' },
  { label: 'VIP', color: 'amber' },
  { label: 'AI', color: 'indigo' },
  { label: '계약', color: 'emerald' },
] as const;

export type TimelineEntry = {
  id: string;
  at: string;
  kind: TimelineEntryKind;
  section: TimelineSection;
  title: string;
  detail?: string;
  actor?: string;
};

export interface ConsultTimelineResponse {
  entries: TimelineEntry[];
  pending?: boolean;
}

export interface ConsultAttachment {
  id: string;
  name: string;
  mimeType: string;
  sizeBytes: number;
  url?: string;
  thumbnailUrl?: string;
  uploadedAt: string;
  uploadedBy?: string;
}

export interface BulkActionResult {
  affected: number;
  pending?: boolean;
}

export interface ServiceResult<T> {
  data: T;
  pending: boolean;
}
