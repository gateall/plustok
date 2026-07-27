import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { AdminErrorState, Button } from '@/components/admin-ui';
import SiteFilters, { parseSiteFilters } from '@/components/sites/SiteFilters';
import SiteStatCards from '@/components/sites/SiteStatCards';
import SiteMobileList from '@/components/sites/SiteMobileList';
import SiteTable from '@/components/sites/SiteTable';
import SiteRegenKeyDialog from '@/components/sites/SiteRegenKeyDialog';
import SiteToggleDialog from '@/components/sites/SiteToggleDialog';
import { useSites, useSiteToggle, useSiteRegenKey } from '@/hooks/useSites';
import { normalizeSiteList } from '@/services/site.service';
import { adminErrorFromUnknown } from '@/utils/adminErrorState';
import {
  computeSiteSummary,
  filterSitesByIntegration,
  hasActiveSiteFilters,
  sortSites,
} from '@/utils/siteDisplay';
import type { SiteIntegrationFilter, SiteItem } from '@/types/site.types';

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

/** Sites admin — legacy-aligned list (Mobile First 769px split) */
export default function AdminSitesPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseSiteFilters(searchParams), [searchParams]);
  const apiFilters = useMemo(
    () => ({
      ...(filters.q ? { q: filters.q } : {}),
    }),
    [filters.q],
  );

  const { data, isLoading, isError, error, refetch } = useSites(apiFilters);

  const toggleMutation = useSiteToggle();
  const regenMutation = useSiteRegenKey();

  const [toggleSite, setToggleSite] = useState<SiteItem | null>(null);
  const [regenSite, setRegenSite] = useState<SiteItem | null>(null);
  const [newApiKey, setNewApiKey] = useState<string | null>(null);
  const [togglingId, setTogglingId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

  const allFromApi = normalizeSiteList(data);
  const summary = useMemo(() => computeSiteSummary(allFromApi), [allFromApi]);
  const filteredSites = useMemo(
    () => filterSitesByIntegration(allFromApi, filters.integration),
    [allFromApi, filters.integration],
  );
  const sortedSites = useMemo(
    () => sortSites(filteredSites, filters.sort),
    [filteredSites, filters.sort],
  );

  const total = allFromApi.length;
  const page = Math.max(1, parseInt(searchParams.get('page') ?? '1', 10) || 1);
  const totalPages = Math.max(1, Math.ceil(sortedSites.length / PAGE_SIZE));
  const sites = sortedSites.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const integrationFilter = (filters.integration ?? '') as SiteIntegrationFilter;
  const filtersActive = hasActiveSiteFilters({
    q: filters.q,
    integration: filters.integration,
  });

  const goPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  const handleIntegrationSelect = (integration: SiteIntegrationFilter) => {
    const params = new URLSearchParams(searchParams);
    params.delete('page');
    if (integration) params.set('integration', integration);
    else params.delete('integration');
    setSearchParams(params, { replace: true });
  };

  const handleToggleSelect = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleToggleSelectAll = (ids: number[]) => {
    setSelectedIds((prev) => {
      const allSelected = ids.length > 0 && ids.every((id) => prev.has(id));
      if (allSelected) {
        const next = new Set(prev);
        ids.forEach((id) => next.delete(id));
        return next;
      }
      return new Set([...prev, ...ids]);
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  const handleToggleConfirm = async () => {
    if (!toggleSite) return;
    setTogglingId(toggleSite.id);
    try {
      await toggleMutation.mutateAsync(toggleSite.id);
      toast.success('상태가 변경되었습니다.');
      setToggleSite(null);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '상태 변경에 실패했습니다.');
    } finally {
      setTogglingId(null);
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
    onToggle: setToggleSite,
    onRegenKey: setRegenSite,
    selectedIds,
    onToggleSelect: handleToggleSelect,
    onToggleSelectAll: handleToggleSelectAll,
    togglingId,
  };

  return (
    <div className="admin-page-shell min-w-0 w-full bg-[var(--pt-color-bg-subtle,#f8fafc)] py-4 md:py-6 px-4 md:px-8">
      <PageHeader
        title={`사이트관리 (${total.toLocaleString('ko-KR')})`}
        description="등록된 사이트와 API 연동 상태를 관리합니다."
        actions={
          <Button variant="primary" to="/admin/sites/new" className="admin-touch-target">
            + 사이트 추가
          </Button>
        }
      />

      {newApiKey && (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <p className="mb-2 text-sm font-medium text-amber-800">새 API Key (한 번만 표시)</p>
          <code className="block break-all rounded-lg bg-white p-3 text-xs font-mono text-slate-800">
            {newApiKey}
          </code>
          <div className="mt-2 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={() => void navigator.clipboard.writeText(newApiKey)}
              className="text-xs font-medium text-amber-800 underline"
            >
              복사
            </button>
            <button
              type="button"
              onClick={() => setNewApiKey(null)}
              className="text-xs text-amber-700 underline"
            >
              닫기
            </button>
          </div>
        </div>
      )}

      {!isLoading && !isError && (
        <SiteStatCards
          summary={summary}
          selected={integrationFilter}
          onSelect={handleIntegrationSelect}
        />
      )}

      <SiteFilters onResetSelection={clearSelection} />

      {isLoading && <SiteListSkeleton />}

      {isError && (
        <AdminErrorState
          {...adminErrorFromUnknown(error, 'list')}
          title="사이트 목록을 불러오지 못했습니다."
          onRetry={() => void refetch()}
        />
      )}

      {!isLoading && !isError && allFromApi.length === 0 && !filtersActive && (
        <EmptyState
          title="등록된 사이트가 없습니다."
          description="새 사이트를 등록해 주세요."
          action={
            <Button variant="primary" to="/admin/sites/new" className="admin-touch-target">
              사이트 추가
            </Button>
          }
        />
      )}

      {!isLoading && !isError && sortedSites.length === 0 && (allFromApi.length > 0 || filtersActive) && (
        <EmptyState
          title="검색 조건에 맞는 사이트가 없습니다."
          description="다른 검색어나 필터를 사용해 보세요."
          action={
            <Button
              variant="secondary"
              onClick={() => {
                const params = new URLSearchParams(searchParams);
                params.delete('q');
                params.delete('integration');
                params.delete('sort');
                params.delete('page');
                setSearchParams(params, { replace: true });
                clearSelection();
              }}
              className="admin-touch-target"
            >
              조건 초기화
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

      <SiteToggleDialog
        open={toggleSite != null}
        site={toggleSite}
        loading={toggleMutation.isPending}
        onConfirm={() => void handleToggleConfirm()}
        onClose={() => setToggleSite(null)}
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
