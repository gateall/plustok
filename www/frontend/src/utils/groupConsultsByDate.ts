import type { ConsultListItem } from '@/types/consult.types';
import { dateKey, formatDateGroupLabel, formatDateHeader } from './consultDisplay';
import { isNewOrUnread } from './consultSummary';

export type ConsultDateGroup = {
  key: string;
  label: string;
  primary: string;
  secondary: string;
  items: ConsultListItem[];
  totalCount: number;
  newCount: number;
};

export function groupConsultsByDate(consults: ConsultListItem[]): ConsultDateGroup[] {
  const map = new Map<string, ConsultListItem[]>();

  for (const consult of consults) {
    const key = dateKey(consult.createdAt);
    const bucket = map.get(key);
    if (bucket) bucket.push(consult);
    else map.set(key, [consult]);
  }

  return Array.from(map.entries())
    .sort(([a], [b]) => b.localeCompare(a))
    .map(([key, items]) => {
      const { primary, secondary } = formatDateGroupLabel(items[0]?.createdAt ?? key);
      return {
        key,
        label: formatDateHeader(items[0]?.createdAt ?? key),
        primary,
        secondary,
        items,
        totalCount: items.length,
        newCount: items.filter(isNewOrUnread).length,
      };
    });
}

export function consultRowKey(consult: ConsultListItem): string {
  return `${consult.source}-${consult.id}`;
}
