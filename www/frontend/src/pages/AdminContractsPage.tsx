import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Plus } from 'lucide-react';
import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { AdminErrorState } from '@/components/admin-ui';
import ContractFilters, { parseContractFilters } from '@/components/contracts/ContractFilters';
import ContractCardList from '@/components/contracts/ContractCardList';
import ContractTable from '@/components/contracts/ContractTable';
import ContractSummaryCards, { computeContractSummary } from '@/components/contracts/ContractSummaryCards';
import { filterByPaymentStatus } from '@/components/contracts/PaymentStatusBadge';
import { useContracts } from '@/hooks/useContracts';
import { adminErrorFromUnknown } from '@/utils/adminErrorState';

function ContractListSkeleton() {
  return (
    <div className="space-y-3" aria-label="계약 목록 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <LoadingSkeleton key={i} className="h-36 w-full rounded-xl" />
      ))}
    </div>
  );
}

export default function AdminContractsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseContractFilters(searchParams), [searchParams]);
  const { data, isLoading, isError, error, refetch } = useContracts(filters);

  const rawItems = Array.isArray(data?.items) ? data.items : [];
  const contracts = useMemo(
    () => filterByPaymentStatus(rawItems, filters.payment_status),
    [rawItems, filters.payment_status],
  );

  const total = data?.total ?? 0;
  const page = data?.page ?? filters.page ?? 1;
  const limit = data?.limit ?? filters.limit ?? 20;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const pageScoped = totalPages > 1 || contracts.length < total;

  const summary = useMemo(
    () => computeContractSummary(contracts, total, pageScoped),
    [contracts, total, pageScoped],
  );
  const outstandingSum = useMemo(
    () => contracts.reduce((sum, c) => sum + c.outstandingAmount, 0),
    [contracts],
  );

  const goPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  const errorState = isError ? adminErrorFromUnknown(error, 'list') : null;

  return (
    <div className="admin-page-shell admin-contracts-page min-w-0 py-4 md:py-6 w-full">
      <PageHeader
        title="계약 관리"
        description={`총 ${total}건${total > 0 ? ` (페이지 ${page}/${totalPages})` : ''}`}
        actions={
          <Link
            to="/admin/contracts/new"
            className="inline-flex h-11 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700 lg:h-10"
          >
            <Plus size={16} aria-hidden />
            새 계약
          </Link>
        }
      />

      {!isLoading && !isError && <ContractSummaryCards summary={summary} outstandingSum={outstandingSum} />}

      <ContractFilters />

      {isLoading && <ContractListSkeleton />}

      {errorState && (
        <AdminErrorState
          {...errorState}
          onRetry={() => void refetch()}
        />
      )}

      {!isLoading && !isError && contracts.length === 0 && (
        <EmptyState
          title="등록된 계약이 없습니다"
          description="새 계약을 등록해 주세요."
          action={
            <Link
              to="/admin/contracts/new"
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
              새 계약 등록
            </Link>
          }
        />
      )}

      {!isLoading && !isError && contracts.length > 0 && (
        <>
          <ContractCardList contracts={contracts} />
          <ContractTable contracts={contracts} />

          {totalPages > 1 && (
            <nav
              className="mt-6 flex min-w-0 items-center justify-center gap-3 pb-8"
              aria-label="페이지 네비게이션"
            >
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => goPage(page - 1)}
                className="h-11 lg:h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50 min-w-[64px]"
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
                className="h-11 lg:h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50 min-w-[64px]"
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
