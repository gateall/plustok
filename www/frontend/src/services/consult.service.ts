import { apiFetch, API_BASE, getAccessToken } from './api.client';
import type {
  BulkActionResult,
  ConsultAttachment,
  ConsultDetail,
  ConsultListFilters,
  ConsultListResponse,
  ConsultTag,
  ConsultTagColorKey,
  ConsultTimelineResponse,
  ServiceResult,
  TimelineEntry,
  TimelineEntryKind,
  TimelineSection,
} from '../types/consult.types';
import { CONSULT_STATUS_LABELS, DEFAULT_CONSULT_TAGS } from '../types/consult.types';

function isNotFound(err: unknown): boolean {
  return err instanceof Error && /404|not found|endpoint/i.test(err.message);
}

async function withApiFallback<T>(
  apiCall: () => Promise<T>,
  emptyFallback: T,
): Promise<ServiceResult<T>> {
  try {
    const data = await apiCall();
    return { data, pending: false };
  } catch (err) {
    if (isNotFound(err)) {
      return { data: emptyFallback, pending: true };
    }
    throw err;
  }
}

type ApiTimelineItem = {
  id: string;
  type: string;
  createdAt: string;
  fromStatus?: string | null;
  toStatus?: string;
  managerId?: number | null;
  note?: string;
  senderType?: string;
  content?: string;
};

type ApiAttachmentItem = {
  id: string | number;
  source?: string;
  fileName?: string;
  origName?: string;
  mimeType?: string;
  fileExt?: string | null;
  fileSize?: number;
  sizeBytes?: number;
  publicUrl?: string;
  savedPath?: string;
  downloadUrl?: string;
  createdAt?: string;
  uploaderId?: string | number;
};

type ApiTagItem = { name: string; count?: number };

const TAG_COLOR_CYCLE: ConsultTagColorKey[] = ['indigo', 'sky', 'emerald', 'amber', 'rose', 'slate'];

function tagColorFor(label: string): ConsultTagColorKey {
  let hash = 0;
  for (let i = 0; i < label.length; i++) hash = (hash + label.charCodeAt(i)) % TAG_COLOR_CYCLE.length;
  return TAG_COLOR_CYCLE[hash] ?? 'indigo';
}

function mapApiTag(name: string, index = 0): ConsultTag {
  return {
    id: `tag-${name}`,
    label: name,
    color: TAG_COLOR_CYCLE[index % TAG_COLOR_CYCLE.length] ?? tagColorFor(name),
  };
}

function statusLabel(code?: string | null): string {
  if (!code) return '—';
  return CONSULT_STATUS_LABELS[code] ?? code;
}

function mapTimelineItem(item: ApiTimelineItem): TimelineEntry {
  const at = item.createdAt;
  switch (item.type) {
    case 'status':
      return {
        id: item.id,
        at,
        kind: 'status',
        section: 'changes',
        title: `상태 변경: ${statusLabel(item.fromStatus)} → ${statusLabel(item.toStatus)}`,
        detail: item.note?.trim() || undefined,
        actor: item.managerId ? `담당 #${item.managerId}` : '시스템',
      };
    case 'assign':
      return {
        id: item.id,
        at,
        kind: 'assign',
        section: 'changes',
        title: '담당자 변경',
        detail: item.note,
        actor: item.managerId ? `담당 #${item.managerId}` : undefined,
      };
    case 'memo':
    case 'note':
      return {
        id: item.id,
        at,
        kind: 'memo',
        section: 'activity',
        title: '메모 작성',
        detail: item.content ?? item.note,
        actor: item.managerId ? `담당 #${item.managerId}` : undefined,
      };
    case 'message':
      return {
        id: item.id,
        at,
        kind: 'message',
        section: 'activity',
        title: item.senderType === 'agent' ? '상담원 메시지' : '고객 메시지',
        detail: item.content,
        actor: item.senderType ?? '알 수 없음',
      };
    case 'file':
    case 'attachment':
      return {
        id: item.id,
        at,
        kind: 'attachment',
        section: 'activity',
        title: '첨부파일 업로드',
        detail: item.content ?? item.note,
      };
    case 'mail':
    case 'email':
      return {
        id: item.id,
        at,
        kind: 'email',
        section: 'activity',
        title: '이메일 발송',
        detail: item.content ?? item.note,
      };
    case 'ai':
    case 'ai_summary':
      return {
        id: item.id,
        at,
        kind: 'ai_summary',
        section: 'activity',
        title: 'AI 요약',
        detail: item.content ?? item.note,
        actor: 'AI',
      };
    case 'ai_reply':
      return {
        id: item.id,
        at,
        kind: 'ai_reply',
        section: 'activity',
        title: 'AI 답변',
        detail: item.content ?? item.note,
        actor: 'AI',
      };
    case 'contract':
      return {
        id: item.id,
        at,
        kind: 'contract',
        section: 'activity',
        title: '계약서 발송',
        detail: item.content ?? item.note,
      };
    case 'completed':
      return {
        id: item.id,
        at,
        kind: 'completed',
        section: 'history',
        title: '상담 완료',
        detail: item.content ?? item.note,
      };
    case 'created':
    case 'consult_created':
      return {
        id: item.id,
        at,
        kind: 'consult_created',
        section: 'history',
        title: '상담 생성',
        detail: item.content ?? item.note ?? 'CRM 상담이 생성되었습니다.',
        actor: '시스템',
      };
    case 'tag':
      return {
        id: item.id,
        at,
        kind: 'tag',
        section: 'changes',
        title: '태그 변경',
        detail: item.content ?? item.note,
      };
    default:
      return {
        id: item.id,
        at,
        kind: 'system',
        section: 'history',
        title: item.type,
        detail: item.content ?? item.note,
      };
  }
}

function mapAttachmentItem(item: ApiAttachmentItem): ConsultAttachment {
  const name = item.fileName ?? item.origName ?? 'file';
  const ext = item.fileExt?.toLowerCase() ?? '';
  const mimeType =
    item.mimeType ??
    (ext === 'pdf'
      ? 'application/pdf'
      : ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
        ? `image/${ext === 'jpg' ? 'jpeg' : ext}`
        : 'application/octet-stream');

  return {
    id: String(item.id),
    name,
    mimeType,
    sizeBytes: item.fileSize ?? item.sizeBytes ?? 0,
    url: item.publicUrl ?? item.downloadUrl ?? item.savedPath,
    uploadedAt: item.createdAt ?? new Date().toISOString(),
    uploadedBy: item.uploaderId !== undefined ? String(item.uploaderId) : undefined,
  };
}

function buildQuery(filters: ConsultListFilters): string {
  const params = new URLSearchParams();
  if (filters.page) params.set('page', String(filters.page));
  if (filters.limit) params.set('limit', String(filters.limit));
  if (filters.status) params.set('status', filters.status);
  if (filters.source && filters.source !== 'all') params.set('source', filters.source);
  if (filters.q) params.set('q', filters.q);
  if (filters.site) params.set('site', filters.site);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.metaKey) params.set('meta_key', filters.metaKey);
  if (filters.metaValue) params.set('meta_value', filters.metaValue);
  if (filters.tag) params.set('tag', filters.tag);
  const qs = params.toString();
  return qs ? `?${qs}` : '';
}

async function apiUpload<T>(path: string, formData: FormData): Promise<T> {
  const headers = new Headers();
  const token = getAccessToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const res = await fetch(`${API_BASE}${path}`, {
    method: 'POST',
    headers,
    body: formData,
    credentials: 'include',
  });

  const json = (await res.json()) as {
    success: boolean;
    data: T;
    error?: { code: string; message: string };
  };
  if (!res.ok || !json.success) {
    throw new Error(json.error?.message ?? `HTTP ${res.status}`);
  }
  return json.data;
}

export const consultService = {
  list: (filters: ConsultListFilters = {}) =>
    apiFetch<ConsultListResponse>(`/admin/consults${buildQuery(filters)}`),

  getConsult: (id: string) =>
    apiFetch<ConsultDetail>(`/admin/consults/${encodeURIComponent(id)}`),

  getChatRoomId: async (consultId: string): Promise<string | null> => {
    const detail = await consultService.getConsult(consultId);
    return detail.roomId;
  },

  bulkDelete: async (ids: string[]): Promise<ServiceResult<BulkActionResult>> => {
    const result = await withApiFallback(
      () => apiFetch<{ deleted?: number; requested?: number }>('/admin/consults/bulk-delete', {
        method: 'POST',
        body: JSON.stringify({ ids }),
      }),
      { deleted: 0 },
    );
    return {
      pending: result.pending,
      data: { affected: result.data.deleted ?? 0 },
    };
  },

  updateStatus: async (id: string, status: string): Promise<ServiceResult<void>> =>
    withApiFallback(
      () =>
        apiFetch<void>(`/admin/consults/${encodeURIComponent(id)}/status`, {
          method: 'PATCH',
          body: JSON.stringify({ status }),
        }),
      undefined,
    ),

  updateAssignee: async (id: string, agentId: string | null): Promise<ServiceResult<void>> =>
    withApiFallback(
      () =>
        apiFetch<void>(`/admin/consults/${encodeURIComponent(id)}/assign`, {
          method: 'PATCH',
          body: JSON.stringify({ agentId }),
        }),
      undefined,
    ),

  bulkStatus: async (ids: string[], status: string): Promise<ServiceResult<BulkActionResult>> => {
    const result = await withApiFallback(
      () =>
        apiFetch<{ updated?: number }>('/admin/consults/bulk-status', {
          method: 'POST',
          body: JSON.stringify({ ids, status }),
        }),
      { updated: 0 },
    );
    return {
      pending: result.pending,
      data: { affected: result.data.updated ?? ids.length },
    };
  },

  bulkUpdateStatus: async (ids: string[], status: string): Promise<void> => {
    await consultService.bulkStatus(ids, status);
  },

  bulkAssign: async (ids: string[], agentId: string | null): Promise<ServiceResult<BulkActionResult>> => {
    const result = await withApiFallback(
      () =>
        apiFetch<{ updated?: number }>('/admin/consults/bulk-assign', {
          method: 'POST',
          body: JSON.stringify({ ids, agentId }),
        }),
      { updated: 0 },
    );
    return {
      pending: result.pending,
      data: { affected: result.data.updated ?? ids.length },
    };
  },

  bulkUpdateAssignee: async (ids: string[], agentId: string | null): Promise<void> => {
    await consultService.bulkAssign(ids, agentId);
  },

  bulkTags: async (
    ids: string[],
    tags: ConsultTag[],
    mode: 'add' | 'remove' | 'set' = 'set',
  ): Promise<ServiceResult<BulkActionResult>> => {
    const tagNames = tags.map((t) => t.label);
    const result = await withApiFallback(
      () =>
        apiFetch<{ updated?: number }>('/admin/consults/bulk-tags', {
          method: 'POST',
          body: JSON.stringify({ ids, tags: tagNames, mode }),
        }),
      { updated: 0 },
    );
    return {
      pending: result.pending,
      data: { affected: result.data.updated ?? ids.length },
    };
  },

  getTimeline: async (consultId: string): Promise<ServiceResult<ConsultTimelineResponse>> => {
    try {
      const raw = await apiFetch<{ id: string; data: ApiTimelineItem[] }>(
        `/admin/consults/${encodeURIComponent(consultId)}/timeline`,
      );
      return {
        data: { entries: (raw.data ?? []).map(mapTimelineItem), pending: false },
        pending: false,
      };
    } catch (err) {
      if (isNotFound(err)) {
        return { data: { entries: [], pending: true }, pending: true };
      }
      throw err;
    }
  },

  /** POST /api/v1/admin/consults/{id}/timeline */
  createTimelineMemo: (consultId: string, note: string) =>
    apiFetch<{ id: number | string; created?: boolean }>(
      `/admin/consults/${encodeURIComponent(consultId)}/timeline`,
      {
        method: 'POST',
        body: JSON.stringify({ note }),
      },
    ),

  getTags: async (q?: string): Promise<ServiceResult<ConsultTag[]>> => {
    const qs = q ? `?q=${encodeURIComponent(q)}` : '';
    const result = await withApiFallback(
      async () => {
        const raw = await apiFetch<{ data: ApiTagItem[] }>(`/admin/consult-tags${qs}`);
        return (raw.data ?? []).map((t, i) => mapApiTag(t.name, i));
      },
      DEFAULT_CONSULT_TAGS.map((t, i) => mapApiTag(t.label, i)),
    );
    return result;
  },

  setTags: async (consultId: string, tags: ConsultTag[]): Promise<ServiceResult<ConsultTag[]>> => {
    const { pending } = await consultService.bulkTags([consultId], tags, 'set');
    return { data: tags, pending };
  },

  searchByTag: (tag: string, filters: ConsultListFilters = {}) =>
    consultService.list({ ...filters, q: tag, tag }),

  uploadAttachment: async (consultId: string, file: File): Promise<ServiceResult<ConsultAttachment>> => {
    const form = new FormData();
    form.append('file', file);
    form.append('category', 'etc');
    const raw = await apiUpload<ApiAttachmentItem>(
      `/admin/consults/${encodeURIComponent(consultId)}/attachments`,
      form,
    );
    return { data: mapAttachmentItem(raw), pending: false };
  },

  listAttachments: async (consultId: string): Promise<ServiceResult<ConsultAttachment[]>> => {
    const result = await withApiFallback(
      async () => {
        const raw = await apiFetch<{ data: ApiAttachmentItem[] }>(
          `/admin/consults/${encodeURIComponent(consultId)}/attachments`,
        );
        return (raw.data ?? []).map(mapAttachmentItem);
      },
      [],
    );
    return result;
  },

  deleteAttachment: async (consultId: string, attachmentId: string): Promise<ServiceResult<void>> =>
    withApiFallback(
      () =>
        apiFetch<void>(
          `/admin/consults/${encodeURIComponent(consultId)}/attachments/${encodeURIComponent(attachmentId)}`,
          { method: 'DELETE' },
        ),
      undefined,
    ),

  getAttachment: (consultId: string, attachmentId: string) =>
    apiFetch<ApiAttachmentItem>(
      `/admin/consults/${encodeURIComponent(consultId)}/attachments/${encodeURIComponent(attachmentId)}`,
    ),

  downloadUrl: (consultId: string, attachmentId: string): string =>
    `${API_BASE}/admin/consults/${encodeURIComponent(consultId)}/attachments/${encodeURIComponent(attachmentId)}`,
};

export type { TimelineEntryKind, TimelineSection };
