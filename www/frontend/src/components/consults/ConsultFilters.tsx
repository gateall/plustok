import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import clsx from 'clsx';
import { Filter } from 'lucide-react';
import { Drawer, SearchBox, TagFilter } from '@/components/admin-ui';
import { CONSULT_STATUS_OPTIONS, CONSULT_SORT_OPTIONS } from '@/types/consult.types';
import type { ConsultListFilters } from '@/types/consult.types';
import { useConsultListSearch } from '@/features/admin/ConsultListSearchContext';
import { useAgents } from '@/hooks/useAgents';
import { dateKey } from '@/utils/consultDisplay';

const DEFAULT_LIMIT = 20;

const SORT_VALUES = ['newest', 'oldest', 'status', 'customer', 'recent_response', 'unprocessed'] as const;

function parseFilters(params: URLSearchParams): ConsultListFilters {
  const page = Math.max(1, parseInt(params.get('page') ?? '1', 10) || 1);
  const status = params.get('status') ?? '';
  const q = params.get('q') ?? '';
  const from = params.get('from') ?? '';
  const to = params.get('to') ?? '';
  const site = params.get('site') ?? '';
  const metaKey = params.get('meta_key') ?? '';
  const metaValue = params.get('meta_value') ?? '';
  const tag = params.get('tag') ?? '';
  const assignee = params.get('assignee') ?? '';
  const sortRaw = params.get('sort') ?? 'newest';
  const sort = (SORT_VALUES as readonly string[]).includes(sortRaw)
    ? (sortRaw as ConsultListFilters['sort'])
    : 'newest';

  return {
    page,
    limit: DEFAULT_LIMIT,
    sort,
    ...(status ? { status } : {}),
    ...(q ? { q } : {}),
    ...(from ? { from } : {}),
    ...(to ? { to } : {}),
    ...(site ? { site } : {}),
    ...(metaKey ? { metaKey } : {}),
    ...(metaValue ? { metaValue } : {}),
    ...(tag ? { tag } : {}),
    ...(assignee ? { assignee } : {}),
  };
}

function filtersToParams(filters: ConsultListFilters): URLSearchParams {
  const params = new URLSearchParams();
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  if (filters.status) params.set('status', filters.status);
  if (filters.q) params.set('q', filters.q);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.site) params.set('site', filters.site);
  if (filters.metaKey) params.set('meta_key', filters.metaKey);
  if (filters.metaValue) params.set('meta_value', filters.metaValue);
  if (filters.tag) params.set('tag', filters.tag);
  if (filters.assignee) params.set('assignee', filters.assignee);
  if (filters.sort && filters.sort !== 'newest') params.set('sort', filters.sort);
  return params;
}

function isoDateOffset(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return dateKey(d.toISOString());
}

type QuickDateKey = 'today' | 'yesterday' | '7d' | '30d';

function quickDateRange(key: QuickDateKey): { from: string; to: string } {
  const today = dateKey(new Date().toISOString());
  switch (key) {
    case 'today':
      return { from: today, to: today };
    case 'yesterday': {
      const y = isoDateOffset(-1);
      return { from: y, to: y };
    }
    case '7d':
      return { from: isoDateOffset(-6), to: today };
    case '30d':
      return { from: isoDateOffset(-29), to: today };
  }
}

function activeQuickDate(filters: ConsultListFilters): QuickDateKey | null {
  if (!filters.from && !filters.to) return null;
  const today = dateKey(new Date().toISOString());
  const yesterday = isoDateOffset(-1);
  if (filters.from === today && filters.to === today) return 'today';
  if (filters.from === yesterday && filters.to === yesterday) return 'yesterday';
  if (filters.from === isoDateOffset(-6) && filters.to === today) return '7d';
  if (filters.from === isoDateOffset(-29) && filters.to === today) return '30d';
  return null;
}

type ConsultFiltersProps = {
  variant?: 'page' | 'panel';
  siteOptions?: string[];
};

export default function ConsultFilters({ variant = 'page', siteOptions = [] }: ConsultFiltersProps) {
  const [searchParams, setSearchParams] = useSearchParams();
  const { registerSearchInput, focusSearch } = useConsultListSearch();
  const searchRef = useRef<HTMLInputElement>(null);
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);

  const filters = parseFilters(searchParams);
  const { data: agents = [] } = useAgents();
  const [qDraft, setQDraft] = useState(filters.q ?? '');
  const [tagDraft, setTagDraft] = useState(filters.tag ?? '');

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

  useEffect(() => {
    setTagDraft(filters.tag ?? '');
  }, [filters.tag]);

  const applyParams = useCallback(
    (next: ConsultListFilters) => {
      setSearchParams(filtersToParams(next), { replace: true });
    },
    [setSearchParams],
  );

  const handleStatusChange = (status: string) => {
    applyParams({ ...filters, status: status || undefined, page: 1 });
  };

  const handleSortChange = (sort: string) => {
    applyParams({
      ...filters,
      sort: (sort || 'newest') as ConsultListFilters['sort'],
      page: 1,
    });
  };

  const handleAssigneeChange = (assignee: string) => {
    applyParams({ ...filters, assignee: assignee || undefined, page: 1 });
  };

  const handleSiteChange = (site: string) => {
    applyParams({ ...filters, site: site || undefined, page: 1 });
  };

  const handleQuickDate = (key: QuickDateKey) => {
    const active = activeQuickDate(filters);
    if (active === key) {
      applyParams({ ...filters, from: undefined, to: undefined, page: 1 });
      return;
    }
    const range = quickDateRange(key);
    applyParams({ ...filters, ...range, page: 1 });
  };

  const appliedExtraCount = [filters.from, filters.to, filters.metaKey, filters.metaValue, filters.tag, filters.assignee, filters.site].filter(
    Boolean,
  ).length;

  const quickActive = activeQuickDate(filters);

  const quickDateButtons = (
    <div className="hidden flex-wrap gap-1.5 min-[769px]:flex">
      {(
        [
          ['today', '오늘'],
          ['yesterday', '어제'],
          ['7d', '최근 7일'],
          ['30d', '최근 30일'],
        ] as const
      ).map(([key, label]) => (
        <button
          key={key}
          type="button"
          className={clsx('consult-quick-date-btn', quickActive === key && 'consult-quick-date-btn--active')}
          onClick={() => handleQuickDate(key)}
        >
          {label}
        </button>
      ))}
    </div>
  );

  const extraFilters = (
    <>
      <input
        type="date"
        value={filters.from ?? ''}
        onChange={(e) => applyParams({ ...filters, from: e.target.value || undefined, page: 1 })}
        className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"
        aria-label="시작일"
      />
      <input
        type="date"
        value={filters.to ?? ''}
        onChange={(e) => applyParams({ ...filters, to: e.target.value || undefined, page: 1 })}
        className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"
        aria-label="종료일"
      />
      {siteOptions.length > 0 ? (
        <select
          value={filters.site ?? ''}
          onChange={(e) => handleSiteChange(e.target.value)}
          className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 min-[769px]:max-w-[10rem]"
          aria-label="사이트 필터"
        >
          <option value="">전체 사이트</option>
          {siteOptions.map((site) => (
            <option key={site} value={site}>
              {site}
            </option>
          ))}
        </select>
      ) : null}
      <TagFilter
        value={tagDraft}
        onChange={(tag) => {
          setTagDraft(tag ?? '');
          applyParams({ ...filters, tag: tag || undefined, page: 1 });
        }}
        showSearch={false}
        className="w-full min-[769px]:max-w-[10rem]"
      />
    </>
  );

  const selectControls = (
    <>
      <select
        value={filters.status ?? ''}
        onChange={(e) => handleStatusChange(e.target.value)}
        className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        aria-label="상태 필터"
      >
        {CONSULT_STATUS_OPTIONS.map((opt) => (
          <option key={opt.value || 'all'} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      <select
        value={filters.assignee ?? ''}
        onChange={(e) => handleAssigneeChange(e.target.value)}
        className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        aria-label="담당자 필터"
      >
        <option value="">전체 담당</option>
        {agents.map((agent) => (
          <option key={agent.id} value={agent.id}>
            {agent.displayName}
          </option>
        ))}
      </select>
      <select
        value={filters.sort ?? 'newest'}
        onChange={(e) => handleSortChange(e.target.value)}
        className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        aria-label="정렬"
      >
        {CONSULT_SORT_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </>
  );

  return (
    <div className={`admin-filters min-w-0 ${variant === 'page' ? 'mb-4 space-y-3' : 'space-y-2'}`}>
      <div className="flex min-w-0 flex-col gap-2 min-[769px]:flex-row min-[769px]:flex-wrap min-[769px]:items-center">
        <SearchBox
          ref={searchRef}
          value={qDraft}
          onChange={setQDraft}
          onSubmit={() => applyParams({ ...filters, q: qDraft.trim(), page: 1 })}
          placeholder="상담번호, 고객명, 연락처, 문의내용 검색"
          label="상담 검색"
          className="min-w-0 flex-1 min-[769px]:min-w-[20rem] min-[769px]:max-w-[30rem]"
        />
        <button
          type="button"
          onClick={() => applyParams({ ...filters, q: qDraft.trim(), page: 1 })}
          className="hidden h-11 shrink-0 rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white transition-colors hover:bg-indigo-700 min-[769px]:inline-flex min-[769px]:items-center"
        >
          검색
        </button>
        <div className="hidden min-[769px]:flex min-[769px]:flex-wrap min-[769px]:items-center min-[769px]:gap-2">
          {selectControls}
        </div>
        <button
          type="button"
          onClick={() => setMobileDrawerOpen(true)}
          className="relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors hover:bg-slate-50 min-[769px]:hidden"
          aria-label="필터"
        >
          <Filter className="h-5 w-5" />
          {appliedExtraCount > 0 ? (
            <span className="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-indigo-600 px-1 text-xs font-bold text-white">
              {appliedExtraCount}
            </span>
          ) : null}
        </button>
      </div>

      {quickDateButtons}

      <div className="hidden min-w-0 flex-wrap items-center gap-2 min-[769px]:flex">{extraFilters}</div>

      <div className="flex min-w-0 flex-wrap items-center gap-2 min-[769px]:hidden">{selectControls}</div>

      <Drawer open={mobileDrawerOpen} title="필터" onClose={() => setMobileDrawerOpen(false)} className="min-[769px]:hidden">
        <div className="space-y-3 p-1">
          <div className="flex flex-wrap gap-2">
            {(
              [
                ['today', '오늘'],
                ['yesterday', '어제'],
                ['7d', '7일'],
                ['30d', '30일'],
              ] as const
            ).map(([key, label]) => (
              <button
                key={key}
                type="button"
                className={clsx('consult-quick-date-btn', quickActive === key && 'consult-quick-date-btn--active')}
                onClick={() => handleQuickDate(key)}
              >
                {label}
              </button>
            ))}
          </div>
          {extraFilters}
        </div>
      </Drawer>
    </div>
  );
}

export { parseFilters, DEFAULT_LIMIT };
