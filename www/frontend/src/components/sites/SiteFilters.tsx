import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import { Button } from '@/components/admin-ui';
import SearchBox from '@/components/admin-ui/SearchBox';
import type { SiteIntegrationFilter, SiteListFilters, SiteSortKey } from '@/types/site.types';

const INTEGRATION_OPTIONS: { value: SiteIntegrationFilter; label: string }[] = [
  { value: '', label: '전체' },
  { value: 'healthy', label: '정상연동' },
  { value: 'needs_check', label: '점검필요' },
  { value: 'inactive', label: '비활성' },
];

const SORT_OPTIONS: { value: SiteSortKey; label: string }[] = [
  { value: 'name', label: '이름순' },
  { value: 'created', label: '최근 등록순' },
  { value: 'consult', label: '상담 많은 순' },
  { value: 'last_consult', label: '최근 상담순 (상담 수 대체)' },
];

const SORT_VALUES = new Set<string>(SORT_OPTIONS.map((o) => o.value));

export function parseSiteFilters(params: URLSearchParams): SiteListFilters {
  const q = params.get('q')?.trim();
  const integrationRaw = params.get('integration') ?? '';
  const integration = (
    ['healthy', 'needs_check', 'inactive'].includes(integrationRaw)
      ? integrationRaw
      : ''
  ) as SiteIntegrationFilter;
  const sortRaw = params.get('sort') ?? 'name';
  const sort = (SORT_VALUES.has(sortRaw) ? sortRaw : 'name') as SiteSortKey;

  return {
    ...(q ? { q } : {}),
    ...(integration ? { integration } : {}),
    sort,
  };
}

type SiteFiltersProps = {
  onResetSelection?: () => void;
};

export default function SiteFilters({ onResetSelection }: SiteFiltersProps) {
  const [searchParams, setSearchParams] = useSearchParams();
  const [q, setQ] = useState(searchParams.get('q') ?? '');
  const [integration, setIntegration] = useState(searchParams.get('integration') ?? '');
  const [sort, setSort] = useState(searchParams.get('sort') ?? 'name');

  useEffect(() => {
    setQ(searchParams.get('q') ?? '');
    setIntegration(searchParams.get('integration') ?? '');
    setSort(searchParams.get('sort') ?? 'name');
  }, [searchParams]);

  const applyFilters = (nextQ: string, nextIntegration: string, nextSort: string) => {
    const params = new URLSearchParams(searchParams);
    params.delete('page');

    if (nextQ.trim()) params.set('q', nextQ.trim());
    else params.delete('q');

    if (nextIntegration) params.set('integration', nextIntegration);
    else params.delete('integration');

    if (nextSort && nextSort !== 'name') params.set('sort', nextSort);
    else params.delete('sort');

    setSearchParams(params, { replace: true });
  };

  const handleSearch = () => {
    applyFilters(q, integration, sort);
  };

  const handleReset = () => {
    setQ('');
    setIntegration('');
    setSort('name');
    const params = new URLSearchParams(searchParams);
    params.delete('q');
    params.delete('integration');
    params.delete('sort');
    params.delete('page');
    setSearchParams(params, { replace: true });
    onResetSelection?.();
  };

  return (
    <div className="admin-sites-filters">
      <SearchBox
        value={q}
        placeholder="사이트명·도메인·코드·브랜드 검색"
        onChange={setQ}
        onSubmit={handleSearch}
        className="min-w-0 w-full"
      />

      <div className="admin-sites-filters__row-2 md:contents">
        <select
          aria-label="연동 상태 필터"
          value={integration}
          onChange={(e) => {
            const next = e.target.value;
            setIntegration(next);
            applyFilters(q, next, sort);
          }}
          className="admin-touch-target h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 md:w-auto"
        >
          {INTEGRATION_OPTIONS.map((opt) => (
            <option key={opt.value || 'all'} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <select
          aria-label="정렬"
          value={sort}
          onChange={(e) => {
            const next = e.target.value;
            setSort(next);
            applyFilters(q, integration, next);
          }}
          className="admin-touch-target h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 md:w-auto"
        >
          {SORT_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      <div className="admin-sites-filters__actions md:contents">
        <Button variant="primary" onClick={handleSearch} className="admin-touch-target w-full md:w-auto">
          검색
        </Button>
        <Button variant="secondary" onClick={handleReset} className="admin-touch-target w-full md:w-auto">
          초기화
        </Button>
      </div>
    </div>
  );
}
