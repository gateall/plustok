import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Filter, Search } from 'lucide-react';
import { CONSULT_STATUS_OPTIONS } from '@/types/consult.types';
import type { ConsultListFilters } from '@/types/consult.types';
import { useConsultListSearch } from '@/features/admin/ConsultListSearchContext';

const DEFAULT_LIMIT = 20;

function parseFilters(params: URLSearchParams): ConsultListFilters {
  const page = Math.max(1, parseInt(params.get('page') ?? '1', 10) || 1);
  const status = params.get('status') ?? '';
  const q = params.get('q') ?? '';
  const from = params.get('from') ?? '';
  const to = params.get('to') ?? '';

  return {
    page,
    limit: DEFAULT_LIMIT,
    ...(status ? { status } : {}),
    ...(q ? { q } : {}),
    ...(from ? { from } : {}),
    ...(to ? { to } : {}),
  };
}

function filtersToParams(filters: ConsultListFilters): URLSearchParams {
  const params = new URLSearchParams();
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  if (filters.status) params.set('status', filters.status);
  if (filters.q) params.set('q', filters.q);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  return params;
}

export default function ConsultFilters() {
  const [searchParams, setSearchParams] = useSearchParams();
  const { registerSearchInput, focusSearch } = useConsultListSearch();
  const searchRef = useRef<HTMLInputElement>(null);
  const [showExtra, setShowExtra] = useState(false);

  const filters = parseFilters(searchParams);
  const [qDraft, setQDraft] = useState(filters.q ?? '');

  useEffect(() => {
    registerSearchInput(searchRef);
  }, [registerSearchInput]);

  useEffect(() => {
    if (searchParams.get('focus') === 'search') {
      focusSearch();
      const next = new URLSearchParams(searchParams);
      next.delete('focus');
      setSearchParams(next, { replace: true });
    }
  }, [searchParams, setSearchParams, focusSearch]);

  useEffect(() => {
    setQDraft(filters.q ?? '');
  }, [filters.q]);

  const applyParams = useCallback(
    (next: ConsultListFilters) => {
      setSearchParams(filtersToParams(next), { replace: true });
    },
    [setSearchParams],
  );

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault();
    applyParams({ ...filters, q: qDraft.trim(), page: 1 });
  };

  const handleStatusChange = (status: string) => {
    applyParams({ ...filters, status: status || undefined, page: 1 });
  };

  const appliedExtraCount = [filters.from, filters.to].filter(Boolean).length;

  return (
    <div className="mb-4 min-w-0 space-y-3">
      <form onSubmit={handleSearchSubmit} className="flex min-w-0 gap-2">
        <div className="relative min-w-0 flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            ref={searchRef}
            type="search"
            name="q"
            value={qDraft}
            onChange={(e) => setQDraft(e.target.value)}
            placeholder="이름·전화·상담번호 검색"
            className="h-11 w-full min-w-0 rounded-lg border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            aria-label="상담 검색"
          />
        </div>
        <button
          type="submit"
          className="hidden h-11 shrink-0 rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700 sm:inline-flex sm:items-center"
        >
          검색
        </button>
        <button
          type="button"
          onClick={() => setShowExtra((v) => !v)}
          className="relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 md:hidden"
          aria-label="필터"
          aria-expanded={showExtra}
        >
          <Filter className="h-5 w-5" />
          {appliedExtraCount > 0 && (
            <span className="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">
              {appliedExtraCount}
            </span>
          )}
        </button>
      </form>

      <div className="flex min-w-0 flex-wrap items-center gap-2">
        <select
          value={filters.status ?? ''}
          onChange={(e) => handleStatusChange(e.target.value)}
          className="h-10 min-w-0 max-w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          aria-label="상태 필터"
        >
          {CONSULT_STATUS_OPTIONS.map((opt) => (
            <option key={opt.value || 'all'} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <div className={`${showExtra ? 'flex' : 'hidden'} w-full flex-wrap gap-2 md:flex`}>
          <input
            type="date"
            value={filters.from ?? ''}
            onChange={(e) =>
              applyParams({ ...filters, from: e.target.value || undefined, page: 1 })
            }
            className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"
            aria-label="시작일"
          />
          <input
            type="date"
            value={filters.to ?? ''}
            onChange={(e) => applyParams({ ...filters, to: e.target.value || undefined, page: 1 })}
            className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"
            aria-label="종료일"
          />
        </div>
      </div>
    </div>
  );
}

export { parseFilters, DEFAULT_LIMIT };
