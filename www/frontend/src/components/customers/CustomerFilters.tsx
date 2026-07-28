import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import FilterBar from '../common/FilterBar';
import SearchBox from '../admin-ui/SearchBox';
import Select from '../admin-ui/Select';
import { CUSTOMER_STATUS_OPTIONS } from '../../types/customer.types';
import type { CustomerListFilters } from '../../types/customer.types';

export function parseCustomerFilters(params: URLSearchParams): CustomerListFilters {
  const page = parseInt(params.get('page') || '1', 10);
  const limit = parseInt(params.get('limit') || '20', 10);
  const status = params.get('status') || undefined;
  const q = params.get('q') || undefined;
  
  return {
    page: isNaN(page) ? 1 : page,
    limit: isNaN(limit) ? 20 : limit,
    status,
    q,
  };
}

export default function CustomerFilters() {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialQ = searchParams.get('q') ?? '';
  const initialStatus = searchParams.get('status') ?? '';

  const [q, setQ] = useState(initialQ);
  const [status, setStatus] = useState(initialStatus);

  useEffect(() => {
    setQ(searchParams.get('q') ?? '');
    setStatus(searchParams.get('status') ?? '');
  }, [searchParams]);

  const applyFilters = (newQ: string, newStatus: string) => {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.delete('page');

    if (newQ.trim()) nextParams.set('q', newQ.trim());
    else nextParams.delete('q');

    if (newStatus) nextParams.set('status', newStatus);
    else nextParams.delete('status');

    setSearchParams(nextParams, { replace: true });
  };

  const handleSearchSubmit = () => {
    applyFilters(q, status);
  };

  const handleStatusChange = (val: string) => {
    setStatus(val);
    applyFilters(q, val);
  };

  const handleReset = () => {
    setQ('');
    setStatus('');
    applyFilters('', '');
  };

  return (
    <FilterBar className="mb-4">
      <div className="flex w-full flex-col gap-3 md:flex-row md:items-center">
        <SearchBox
          value={q}
          onChange={setQ}
          onSubmit={handleSearchSubmit}
          placeholder="고객명, 연락처, 이메일 검색"
          className="w-full flex-1 [&_input]:min-h-[44px]"
        />
        <div className="flex w-full gap-2 md:w-auto">
          <Select
            value={status}
            onChange={(e) => handleStatusChange(e.target.value)}
            options={CUSTOMER_STATUS_OPTIONS}
            className="w-full flex-1 md:w-36 min-h-[44px]"
          />
          <button
            type="button"
            onClick={handleReset}
            className="shrink-0 h-[44px] px-4 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            초기화
          </button>
        </div>
      </div>
    </FilterBar>
  );
}
