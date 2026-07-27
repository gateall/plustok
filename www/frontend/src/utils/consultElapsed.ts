import { formatTimeAgo } from './formatTimeAgo';
import { isUnprocessedStatus } from './consultSummary';

export type ElapsedSeverity = 'normal' | 'attention' | 'delayed' | 'long';

export function formatElapsedSince(iso: string): string {
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return '—';
  const diffMin = Math.floor((Date.now() - then) / 60_000);
  if (diffMin < 1) return '방금';
  if (diffMin < 60) return `${diffMin}분 전`;
  const diffHr = Math.floor(diffMin / 60);
  if (diffHr < 24) return `${diffHr}시간 ${diffMin % 60}분 전`;
  return formatTimeAgo(iso);
}

/** Visual-only severity — not an SLA policy. */
export function elapsedSeverity(iso: string, status: string): ElapsedSeverity | null {
  if (!isUnprocessedStatus(status)) return null;
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return null;
  const diffMin = Math.floor((Date.now() - then) / 60_000);
  if (diffMin < 30) return 'normal';
  if (diffMin < 120) return 'attention';
  if (diffMin < 1440) return 'delayed';
  return 'long';
}

export function elapsedSeverityClass(severity: ElapsedSeverity | null): string {
  switch (severity) {
    case 'attention':
      return 'text-amber-700';
    case 'delayed':
      return 'text-orange-700 font-medium';
    case 'long':
      return 'text-red-700 font-semibold';
    default:
      return 'text-slate-500';
  }
}
