import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import FilterBar from '../common/FilterBar';
import SearchBox from '../admin-ui/SearchBox';
import Select from '../admin-ui/Select';
import {
  CONTRACT_STATUS_OPTIONS,
  CONTRACT_SORT_OPTIONS,
  CONTRACT_LIMIT_OPTIONS,
  PAYMENT_STATUS_OPTIONS,
  type ContractListFilters,
  type PaymentStatusFilter,
} from '../../types/contract.types';

export function parseContractFilters(params: URLSearchParams): ContractListFilters {
  const page = parseInt(params.get('page') || '1', 10);
  const limit = parseInt(params.get('limit') || '20', 10);
  const siteRaw = params.get('site_id');
  const siteId = siteRaw ? parseInt(siteRaw, 10) : undefined;

  return {
    page: Number.isNaN(page) ? 1 : page,
    limit: Number.isNaN(limit) ? 20 : limit,
    q: params.get('q') || undefined,
    status: params.get('status') || undefined,
    site_id: siteId && !Number.isNaN(siteId) ? siteId : undefined,
    sort: params.get('sort') || 'contracted_at',
    order: (params.get('order') as 'asc' | 'desc') || 'desc',
    payment_status: (params.get('payment_status') as PaymentStatusFilter) || undefined,
  };
}

export default function ContractFilters() {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialQ = searchParams.get('q') ?? '';
  const initialStatus = searchParams.get('status') ?? '';
  const initialPayment = searchParams.get('payment_status') ?? '';
  const initialSort = searchParams.get('sort') ?? 'contracted_at';
  const initialOrder = searchParams.get('order') ?? 'desc';
  const initialLimit = searchParams.get('limit') ?? '20';
  const initialSiteId = searchParams.get('site_id') ?? '';

  const [q, setQ] = useState(initialQ);
  const [status, setStatus] = useState(initialStatus);
  const [paymentStatus, setPaymentStatus] = useState(initialPayment);
  const [sort, setSort] = useState(initialSort);
  const [order, setOrder] = useState(initialOrder);
  const [limit, setLimit] = useState(initialLimit);
  const [siteId, setSiteId] = useState(initialSiteId);

  useEffect(() => {
    setQ(searchParams.get('q') ?? '');
    setStatus(searchParams.get('status') ?? '');
    setPaymentStatus(searchParams.get('payment_status') ?? '');
    setSort(searchParams.get('sort') ?? 'contracted_at');
    setOrder(searchParams.get('order') ?? 'desc');
    setLimit(searchParams.get('limit') ?? '20');
    setSiteId(searchParams.get('site_id') ?? '');
  }, [searchParams]);

  const applyParams = (patch: Record<string, string | undefined>, resetPage = true) => {
    const next = new URLSearchParams(searchParams);
    if (resetPage) next.delete('page');

    Object.entries(patch).forEach(([key, val]) => {
      if (val && val.trim() !== '') next.set(key, val.trim());
      else next.delete(key);
    });

    setSearchParams(next, { replace: true });
  };

  // 300ms debounce for search
  useEffect(() => {
    const timer = window.setTimeout(() => {
      const currentQ = searchParams.get('q') ?? '';
      if (q.trim() === currentQ.trim()) return;
      applyParams({ q: q.trim() || undefined });
    }, 300);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q]);

  const handleStatusChange = (val: string) => {
    setStatus(val);
    applyParams({ status: val || undefined });
  };

  const handlePaymentChange = (val: string) => {
    setPaymentStatus(val);
    applyParams({ payment_status: val || undefined });
  };

  const handleSortChange = (val: string) => {
    setSort(val);
    applyParams({ sort: val || 'contracted_at' });
  };

  const handleOrderChange = (val: string) => {
    setOrder(val);
    applyParams({ order: val || 'desc' });
  };

  const handleLimitChange = (val: string) => {
    setLimit(val);
    applyParams({ limit: val || '20' });
  };

  const handleSiteIdChange = (val: string) => {
    setSiteId(val);
    applyParams({ site_id: val || undefined });
  };

  return (
    <FilterBar className="admin-contracts-filters mb-4">
      <div className="flex w-full min-w-0 flex-col gap-2">
        <SearchBox
          value={q}
          onChange={setQ}
          placeholder="계약번호, 제목, 고객명, 상품명 검색"
          className="w-full"
        />
        <div className="grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
          <Select
            value={status}
            onChange={(e) => handleStatusChange(e.target.value)}
            options={[...CONTRACT_STATUS_OPTIONS]}
            className="w-full"
            aria-label="계약 상태"
          />
          <Select
            value={paymentStatus}
            onChange={(e) => handlePaymentChange(e.target.value)}
            options={[...PAYMENT_STATUS_OPTIONS]}
            className="w-full"
            aria-label="결제 상태"
          />
          <input
            type="number"
            min={1}
            value={siteId}
            onChange={(e) => handleSiteIdChange(e.target.value)}
            placeholder="사이트 ID"
            className="h-12 w-full min-w-0 rounded-[var(--pt-radius-md)] border border-[var(--pt-border-color)] bg-[var(--pt-color-surface)] px-3 text-sm"
            aria-label="사이트 ID"
          />
          <Select
            value={sort}
            onChange={(e) => handleSortChange(e.target.value)}
            options={[...CONTRACT_SORT_OPTIONS]}
            className="w-full"
            aria-label="정렬"
          />
          <Select
            value={order}
            onChange={(e) => handleOrderChange(e.target.value)}
            options={[
              { value: 'desc', label: '내림차순' },
              { value: 'asc', label: '오름차순' },
            ]}
            className="w-full"
            aria-label="정렬 방향"
          />
          <Select
            value={limit}
            onChange={(e) => handleLimitChange(e.target.value)}
            options={CONTRACT_LIMIT_OPTIONS.map((n) => ({ value: String(n), label: `${n}건` }))}
            className="w-full"
            aria-label="페이지당 건수"
          />
        </div>
      </div>
    </FilterBar>
  );
}
