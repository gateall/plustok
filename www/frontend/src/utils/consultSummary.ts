import type { ConsultListItem } from '@/types/consult.types';
import { dateKey } from './consultDisplay';

const NEW_STATUSES = new Set(['new', 'open']);
const IN_PROGRESS_STATUSES = new Set(['progress', 'consulting', 'active', 'in_progress']);
const WAITING_STATUSES = new Set(['hold', 'pending', 'waiting_customer']);
const COMPLETED_STATUSES = new Set(['installed', 'completed', 'closed', 'contracted']);

export type ConsultSummaryStats = {
  total: number;
  today: number;
  newCount: number;
  inProgress: number;
  waiting: number;
  completed: number;
};

export function computeConsultSummary(consults: ConsultListItem[]): ConsultSummaryStats {
  const todayKey = dateKey(new Date().toISOString());
  let today = 0;
  let newCount = 0;
  let inProgress = 0;
  let waiting = 0;
  let completed = 0;

  for (const consult of consults) {
    const status = consult.status.toLowerCase();
    if (dateKey(consult.createdAt) === todayKey) today += 1;
    if (NEW_STATUSES.has(status) || (consult.unreadCount ?? 0) > 0) newCount += 1;
    if (IN_PROGRESS_STATUSES.has(status)) inProgress += 1;
    if (WAITING_STATUSES.has(status)) waiting += 1;
    if (COMPLETED_STATUSES.has(status)) completed += 1;
  }

  return {
    total: consults.length,
    today,
    newCount,
    inProgress,
    waiting,
    completed,
  };
}

export function isNewOrUnread(consult: ConsultListItem): boolean {
  const status = consult.status.toLowerCase();
  return NEW_STATUSES.has(status) || (consult.unreadCount ?? 0) > 0;
}

export function isUnprocessedStatus(status: string): boolean {
  const normalized = status.toLowerCase();
  return NEW_STATUSES.has(normalized) || IN_PROGRESS_STATUSES.has(normalized) || WAITING_STATUSES.has(normalized);
}
