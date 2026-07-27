import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import SearchBox from '@/components/admin-ui/SearchBox';
import type { SiteListFilters } from '@/types/site.types';

export function parseSiteFilters(params: URLSearchParams): SiteListFilters {
  const q = params.get('q')?.trim();
  const status = params.get('status')?.trim();
  return {
    ...(q ? { q } : {}),
    ...(status ? { status } : {}),
  };
}

export default function SiteFilters() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [q, setQ] = useState(searchParams.get('q') ?? '');
  const [status, setStatus] = useState(searchParams.get('status') ?? '');

  useEffect(() => {
    setQ(searchParams.get('q') ?? '');
    setStatus(searchParams.get('status') ?? '');
  }, [searchParams]);

  const applyFilters = (nextQ: string, nextStatus: string) => {
    const params = new URLSearchParams(searchParams);
    if (nextQ.trim()) params.set('q', nextQ.trim());
    else params.delete('q');
    if (nextStatus) params.set('status', nextStatus);
    else params.delete('status');
    setSearchParams(params, { replace: true });
  };

  return (
    <div className="mb-4 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
      <SearchBox
        value={q}
        placeholder="사이트 코드, 이름, 도메인 검색"
        onChange={setQ}
        onSubmit={() => applyFilters(q, status)}
        className="min-w-0 flex-1"
      />
      <select
        aria-label="활성 상태 필터"
        value={status}
        onChange={(e) => {
          const next = e.target.value;
          setStatus(next);
          applyFilters(q, next);
        }}
        className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700"
      >
        <option value="">전체 상태</option>
        <option value="1">활성</option>
        <option value="0">중지</option>
      </select>
    </div>
  );
}
