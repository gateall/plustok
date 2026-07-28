import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { AdminErrorState } from '@/components/admin-ui';
import CustomerFilters, { parseCustomerFilters } from '@/components/customers/CustomerFilters';
import CustomerMobileList from '@/components/customers/CustomerMobileList';
import CustomerTable from '@/components/customers/CustomerTable';
import { useCustomers } from '@/hooks/useCustomers';
import { adminErrorFromUnknown } from '@/utils/adminErrorState';

function CustomerListSkeleton() {
  return (
    <div className="space-y-3" aria-label="고객 목록 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <LoadingSkeleton key={i} className="h-32 w-full rounded-xl" />
      ))}
    </div>
  );
}

export default function AdminCustomersPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseCustomerFilters(searchParams), [searchParams]);
  const { data, isLoading, isError, error, refetch } = useCustomers(filters);

  const customers = Array.isArray(data?.items) ? data.items : [];
  const total = data?.total ?? 0;
  const page = data?.page ?? filters.page ?? 1;
  const limit = data?.limit ?? filters.limit ?? 20;
  const totalPages = Math.max(1, Math.ceil(total / limit));

  const goPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 w-full">
      <PageHeader
        title="고객관리"
        description="등록 고객과 상담 현황을 관리합니다."
      />

      <CustomerFilters />

      {isLoading && <CustomerListSkeleton />}

      {isError && (
        <AdminErrorState
          {...adminErrorFromUnknown(error, 'list')}
          onRetry={() => void refetch()}
        />
      )}

      {!isLoading && !isError && customers.length === 0 && (
        <EmptyState
          title="등록된 고객이 없습니다"
          description="필터를 변경하거나 새로운 고객이 등록되기를 기다려 주세요."
        />
      )}

      {!isLoading && !isError && customers.length > 0 && (
        <>
          <CustomerMobileList customers={customers} />
          <CustomerTable customers={customers} />

          {totalPages > 1 && (
            <nav
              className="mt-6 flex min-w-0 items-center justify-center gap-3 pb-8"
              aria-label="페이지 네비게이션"
            >
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => goPage(page - 1)}
                className="h-11 md:h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50 min-w-[64px]"
              >
                이전
              </button>
              <span className="text-sm text-slate-600 font-medium">
                {page} / {totalPages}
              </span>
              <button
                type="button"
                disabled={page >= totalPages}
                onClick={() => goPage(page + 1)}
                className="h-11 md:h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50 min-w-[64px]"
              >
                다음
              </button>
            </nav>
          )}
        </>
      )}
    </div>
  );
}
