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

  return (
    <FilterBar className="mb-4">
      <div className="flex w-full min-w-0 flex-col gap-2 md:flex-row md:items-center">
        <Select
          value={status}
          onChange={(e) => handleStatusChange(e.target.value)}
          options={CUSTOMER_STATUS_OPTIONS}
          className="w-full md:w-36"
        />
        <SearchBox
          value={q}
          onChange={setQ}
          onSubmit={handleSearchSubmit}
          placeholder="이름, 연락처, 이메일 등 검색"
          className="w-full"
        />
      </div>
    </FilterBar>
  );
}
