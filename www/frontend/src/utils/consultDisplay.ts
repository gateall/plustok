import { formatDateTime, formatTimeAgo } from './formatTimeAgo';

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const CONSULT_NO_REGEX = /^C\d{12}$/;

export function isUuid(value: string): boolean {
  return UUID_REGEX.test(value);
}

function hashCode(value: string): number {
  let hash = 0;
  for (let i = 0; i < value.length; i += 1) {
    hash = (hash << 5) - hash + value.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

/** CRM consult number — never expose raw UUID in UI. */
export function displayConsultNo(item: {
  consultNo?: string;
  id: string;
  createdAt?: string;
}): string {
  const candidate = item.consultNo?.trim();
  if (candidate && CONSULT_NO_REGEX.test(candidate)) {
    return candidate;
  }
  if (candidate && !isUuid(candidate)) {
    return candidate;
  }

  const base = item.createdAt ? new Date(item.createdAt) : new Date();
  if (!Number.isNaN(base.getTime())) {
    const y = base.getFullYear();
    const m = String(base.getMonth() + 1).padStart(2, '0');
    const d = String(base.getDate()).padStart(2, '0');
    const seq = String(hashCode(item.id) % 10000).padStart(4, '0');
    return `C${y}${m}${d}${seq}`;
  }

  return 'C—';
}

export function formatReceiptTime(iso: string, mode: 'compact' | 'relative' = 'compact'): string {
  if (mode === 'relative') {
    return formatTimeAgo(iso);
  }
  const formatted = formatDateTime(iso);
  if (formatted === '—') return formatted;
  return `접수 ${formatted}`;
}

export function formatRowTime(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });
}

export type DateGroupLabel = {
  primary: string;
  secondary: string;
};

function formatShortDate(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}.${m}.${day}`;
}

export function formatDateGroupLabel(iso: string): DateGroupLabel {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return { primary: '—', secondary: '' };
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);

  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

  const secondary = formatShortDate(d);
  if (sameDay(d, today)) return { primary: '오늘', secondary };
  if (sameDay(d, yesterday)) return { primary: '어제', secondary };
  return { primary: secondary, secondary: '' };
}

export function formatDateHeader(iso: string): string {
  const { primary, secondary } = formatDateGroupLabel(iso);
  if (secondary && (primary === '오늘' || primary === '어제')) {
    return `${primary} · ${secondary}`;
  }
  return primary;
}

export function displaySiteName(consult: { siteName?: string; source: string }): string {
  if (consult.siteName) return consult.siteName;
  return consult.source === 'crm' ? 'CRM' : 'ACEP';
}

export function inquiryPreviewParts(consult: {
  productName?: string;
  lastMessagePreview?: string;
}): { title: string | null; body: string | null } {
  const body = consult.lastMessagePreview?.trim() || null;
  const title = consult.productName?.trim() || null;
  if (title && body && title === body) return { title, body: null };
  return { title, body };
}

export function dateKey(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return 'unknown';
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
