import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { AdminErrorState, Button } from '@/components/admin-ui';
import SiteFilters, { parseSiteFilters } from '@/components/sites/SiteFilters';
import SiteMobileList from '@/components/sites/SiteMobileList';
import SiteTable from '@/components/sites/SiteTable';
import SiteDeleteDialog from '@/components/sites/SiteDeleteDialog';
import SiteRegenKeyDialog from '@/components/sites/SiteRegenKeyDialog';
import { useSites, useSiteToggle, useSiteDelete, useSiteRegenKey } from '@/hooks/useSites';
import { adminErrorFromUnknown } from '@/utils/adminErrorState';
import type { SiteItem } from '@/types/site.types';

const PAGE_SIZE = 20;

function SiteListSkeleton() {
  return (
    <div className="space-y-3" aria-label="사이트 목록 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <LoadingSkeleton key={i} className="h-28 w-full rounded-xl" />
      ))}
    </div>
  );
}

/** Sites admin — full CRUD via React routes */
export default function AdminSitesPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseSiteFilters(searchParams), [searchParams]);
  const { data, isLoading, isError, error, refetch } = useSites(filters);

  const toggleMutation = useSiteToggle();
  const deleteMutation = useSiteDelete();
  const regenMutation = useSiteRegenKey();

  const [deletingSite, setDeletingSite] = useState<SiteItem | null>(null);
  const [regenSite, setRegenSite] = useState<SiteItem | null>(null);
  const [newApiKey, setNewApiKey] = useState<string | null>(null);
  const [togglingId, setTogglingId] = useState<number | null>(null);

  const allSites = Array.isArray(data?.data) ? data.data : [];
  const total = allSites.length;

  const page = Math.max(1, parseInt(searchParams.get('page') ?? '1', 10) || 1);
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  const sites = allSites.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const goPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  const handleToggle = async (site: SiteItem) => {
    setTogglingId(site.id);
    try {
      await toggleMutation.mutateAsync(site.id);
      toast.success('상태가 변경되었습니다.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '상태 변경에 실패했습니다.');
    } finally {
      setTogglingId(null);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!deletingSite) return;
    try {
      await deleteMutation.mutateAsync(deletingSite.id);
      toast.success('사이트가 삭제되었습니다.');
      setDeletingSite(null);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '삭제에 실패했습니다.');
    }
  };

  const handleRegenConfirm = async () => {
    if (!regenSite) return;
    try {
      const result = await regenMutation.mutateAsync(regenSite.id);
      setNewApiKey(result.apiKey);
      toast.success('API Key가 재발급되었습니다.');
      setRegenSite(null);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Key 재발급에 실패했습니다.');
    }
  };

  const listActions = {
    onToggle: (site: SiteItem) => void handleToggle(site),
    onRegenKey: setRegenSite,
    onDelete: setDeletingSite,
    togglingId,
  };

  return (
    <div className="admin-page-shell min-w-0 w-full py-4 md:py-6 px-4 md:px-8">
      <PageHeader
        title="사이트 관리"
        description={`등록 사이트 ${total}개${totalPages > 1 ? ` (페이지 ${page}/${totalPages})` : ''}`}
        actions={
          <Button variant="primary" to="/admin/sites/new" className="admin-touch-target">
            새 사이트 등록
          </Button>
        }
      />

      {newApiKey && (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <p className="mb-2 text-sm font-medium text-amber-800">새 API Key (한 번만 표시)</p>
          <code className="block break-all rounded-lg bg-white p-3 text-xs font-mono text-slate-800">
            {newApiKey}
          </code>
          <button
            type="button"
            onClick={() => setNewApiKey(null)}
            className="mt-2 text-xs text-amber-700 underline"
          >
            닫기
          </button>
        </div>
      )}

      <SiteFilters />

      {isLoading && <SiteListSkeleton />}

      {isError && (
        <AdminErrorState
          {...adminErrorFromUnknown(error, 'list')}
          onRetry={() => void refetch()}
        />
      )}

      {!isLoading && !isError && allSites.length === 0 && (
        <EmptyState
          title="등록된 사이트가 없습니다"
          description="새 사이트를 등록하거나 검색 조건을 변경해 주세요."
          action={
            <Button variant="primary" to="/admin/sites/new" className="admin-touch-target">
              새 사이트 등록
            </Button>
          }
        />
      )}

      {!isLoading && !isError && sites.length > 0 && (
        <>
          <SiteMobileList sites={sites} {...listActions} />
          <SiteTable sites={sites} {...listActions} />

          {totalPages > 1 && (
            <nav
              className="mt-6 flex min-w-0 items-center justify-center gap-3 pb-8"
              aria-label="페이지 네비게이션"
            >
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => goPage(page - 1)}
                className="admin-touch-target h-11 min-w-[64px] rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                이전
              </button>
              <span className="text-sm font-medium text-slate-600">
                {page} / {totalPages}
              </span>
              <button
                type="button"
                disabled={page >= totalPages}
                onClick={() => goPage(page + 1)}
                className="admin-touch-target h-11 min-w-[64px] rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                다음
              </button>
            </nav>
          )}
        </>
      )}

      <SiteDeleteDialog
        open={deletingSite != null}
        site={deletingSite}
        loading={deleteMutation.isPending}
        onConfirm={() => void handleDeleteConfirm()}
        onClose={() => setDeletingSite(null)}
      />

      <SiteRegenKeyDialog
        open={regenSite != null}
        site={regenSite}
        loading={regenMutation.isPending}
        onConfirm={() => void handleRegenConfirm()}
        onClose={() => setRegenSite(null)}
      />
    </div>
  );
}
