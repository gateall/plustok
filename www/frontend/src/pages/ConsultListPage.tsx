import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import ConsultFilters, { parseFilters } from '@/components/consults/ConsultFilters';
import ConsultMobileList from '@/components/consults/ConsultMobileList';
import ConsultTable from '@/components/consults/ConsultTable';
import { useConsults } from '@/hooks/useConsults';
import type { ConsultListFilters, ConsultListItem } from '@/types/consult.types';

function clientFilter(consults: ConsultListItem[], filters: ConsultListFilters): ConsultListItem[] {
  let rows = consults;
  const q = filters.q?.trim().toLowerCase();
  if (q) {
    rows = rows.filter(
      (c) =>
        c.customerNameMasked.toLowerCase().includes(q) ||
        c.id.toLowerCase().includes(q) ||
        (c.consultNo?.toLowerCase().includes(q) ?? false) ||
        (c.phoneMasked?.includes(q) ?? false),
    );
  }
  if (filters.from) {
    const fromMs = new Date(filters.from).getTime();
    if (!Number.isNaN(fromMs)) {
      rows = rows.filter((c) => new Date(c.createdAt).getTime() >= fromMs);
    }
  }
  if (filters.to) {
    const toMs = new Date(`${filters.to}T23:59:59`).getTime();
    if (!Number.isNaN(toMs)) {
      rows = rows.filter((c) => new Date(c.createdAt).getTime() <= toMs);
    }
  }
  return rows;
}

function ConsultListSkeleton() {
  return (
    <div className="space-y-3" aria-label="상담 목록 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <LoadingSkeleton key={i} className="h-36 w-full rounded-xl" />
      ))}
    </div>
  );
}

export default function ConsultListPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseFilters(searchParams), [searchParams]);
  const { data, isLoading, isError, error, refetch } = useConsults(filters);

  const rawConsults = Array.isArray(data?.data) ? data.data : [];
  const consults = useMemo(
    () => clientFilter(rawConsults, filters),
    [rawConsults, filters],
  );

  const meta = data?.meta;
  const page = filters.page ?? 1;
  const total = meta?.total ?? consults.length;
  const limit = filters.limit ?? 20;
  const totalPages = Math.max(1, Math.ceil(total / limit));

  const goPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  return (
    <div className="min-w-0 py-4 md:py-6">
      <PageHeader
        title="상담 목록"
        description={`총 ${consults.length}건${meta ? ` (페이지 ${page}/${totalPages})` : ''}`}
      />

      <ConsultFilters />

      {isLoading && <ConsultListSkeleton />}

      {isError && (
        <EmptyState
          title="목록을 불러오지 못했습니다"
          description={error instanceof Error ? error.message : '잠시 후 다시 시도해 주세요.'}
          action={
            <button
              type="button"
              onClick={() => void refetch()}
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
              다시 시도
            </button>
          }
        />
      )}

      {!isLoading && !isError && consults.length === 0 && (
        <EmptyState
          title="상담이 없습니다"
          description="필터를 변경하거나 새 상담 접수를 기다려 주세요."
        />
      )}

      {!isLoading && !isError && consults.length > 0 && (
        <>
          <ConsultMobileList consults={consults} />
          <ConsultTable consults={consults} />

          {totalPages > 1 && (
            <nav
              className="mt-6 flex min-w-0 items-center justify-center gap-3"
              aria-label="페이지 네비게이션"
            >
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => goPage(page - 1)}
                className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50"
              >
                이전
              </button>
              <span className="text-sm text-slate-600">
                {page} / {totalPages}
              </span>
              <button
                type="button"
                disabled={page >= totalPages}
                onClick={() => goPage(page + 1)}
                className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50"
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
