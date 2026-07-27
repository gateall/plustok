import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { AdminErrorState, Button } from '@/components/admin-ui';
import SiteFilters, { parseSiteFilters } from '@/components/sites/SiteFilters';
import SiteMobileList from '@/components/sites/SiteMobileList';
import SiteTable from '@/components/sites/SiteTable';
import { useSites } from '@/hooks/useSites';
import { adminErrorFromUnknown } from '@/utils/adminErrorState';

function SiteListSkeleton() {
  return (
    <div className="space-y-3" aria-label="사이트 목록 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <LoadingSkeleton key={i} className="h-28 w-full rounded-xl" />
      ))}
    </div>
  );
}

/** Sites admin — live GET /admin/sites (SiteController). */
export default function AdminSitesPage() {
  const [searchParams] = useSearchParams();
  const filters = useMemo(() => parseSiteFilters(searchParams), [searchParams]);
  const { data, isLoading, isError, error, refetch } = useSites(filters);

  const sites = data?.data ?? [];
  const total = sites.length;

  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 w-full px-4 md:px-8">
      <PageHeader
        title="사이트 관리"
        description={`등록 사이트 ${total}개`}
        actions={
          <Button href="/admin/sites/" variant="secondary">
            PHP 관리자
          </Button>
        }
      />

      <SiteFilters />

      {isLoading && <SiteListSkeleton />}

      {isError && (
        <AdminErrorState
          {...adminErrorFromUnknown(error, 'list')}
          onRetry={() => void refetch()}
        />
      )}

      {!isLoading && !isError && sites.length === 0 && (
        <EmptyState
          title="등록된 사이트가 없습니다"
          description="PHP 관리자에서 사이트를 등록하거나 검색 조건을 변경해 주세요."
          action={
            <Button href="/admin/sites/" variant="primary">
              PHP 관리자에서 등록
            </Button>
          }
        />
      )}

      {!isLoading && !isError && sites.length > 0 && (
        <>
          <SiteMobileList sites={sites} />
          <SiteTable sites={sites} />
        </>
      )}
    </div>
  );
}
