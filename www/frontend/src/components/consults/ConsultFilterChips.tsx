import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CONSULT_STATUS_LABELS } from '@/types/consult.types';
import type { ConsultListFilters } from '@/types/consult.types';

type Chip = { key: string; label: string; clear: () => void };

type ConsultFilterChipsProps = {
  filters: ConsultListFilters;
  onClearAll: () => void;
};

function formatDateChip(value: string): string {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' });
}

export default function ConsultFilterChips({ filters, onClearAll }: ConsultFilterChipsProps) {
  const [, setSearchParams] = useSearchParams();

  const chips = useMemo(() => {
    const list: Chip[] = [];
    const apply = (next: ConsultListFilters) => {
      const params = new URLSearchParams();
      if (next.page && next.page > 1) params.set('page', String(next.page));
      if (next.status) params.set('status', next.status);
      if (next.q) params.set('q', next.q);
      if (next.from) params.set('from', next.from);
      if (next.to) params.set('to', next.to);
      if (next.site) params.set('site', next.site);
      if (next.assignee) params.set('assignee', next.assignee);
      if (next.tag) params.set('tag', next.tag);
      if (next.sort && next.sort !== 'newest') params.set('sort', next.sort);
      setSearchParams(params, { replace: true });
    };

    const base = { ...filters, page: 1 as const };

    if (filters.q) {
      list.push({
        key: 'q',
        label: `검색: ${filters.q}`,
        clear: () => apply({ ...base, q: undefined }),
      });
    }
    if (filters.status) {
      list.push({
        key: 'status',
        label: CONSULT_STATUS_LABELS[filters.status] ?? filters.status,
        clear: () => apply({ ...base, status: undefined }),
      });
    }
    if (filters.from) {
      list.push({
        key: 'from',
        label: `시작 ${formatDateChip(filters.from)}`,
        clear: () => apply({ ...base, from: undefined }),
      });
    }
    if (filters.to) {
      list.push({
        key: 'to',
        label: `종료 ${formatDateChip(filters.to)}`,
        clear: () => apply({ ...base, to: undefined }),
      });
    }
    if (filters.site) {
      list.push({
        key: 'site',
        label: `사이트: ${filters.site}`,
        clear: () => apply({ ...base, site: undefined }),
      });
    }
    if (filters.assignee) {
      list.push({
        key: 'assignee',
        label: '담당자 필터',
        clear: () => apply({ ...base, assignee: undefined }),
      });
    }
    if (filters.tag) {
      list.push({
        key: 'tag',
        label: `태그: ${filters.tag}`,
        clear: () => apply({ ...base, tag: undefined }),
      });
    }

    return list;
  }, [filters, setSearchParams]);

  if (chips.length === 0) return null;

  return (
    <div className="consult-filter-chips mt-2" aria-label="적용된 필터">
      <span className="mr-1 text-xs text-slate-500">적용 필터:</span>
      {chips.map((chip) => (
        <span key={chip.key} className="consult-filter-chip">
          {chip.label}
          <button type="button" onClick={chip.clear} aria-label={`${chip.label} 필터 제거`}>
            ×
          </button>
        </span>
      ))}
      {chips.length > 1 ? (
        <button type="button" className="text-xs font-medium text-indigo-600 hover:text-indigo-800" onClick={onClearAll}>
          전체 해제
        </button>
      ) : null}
    </div>
  );
}
