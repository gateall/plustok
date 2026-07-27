import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import clsx from 'clsx';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import { EmptyState, Button, Pagination } from '@/components/admin-ui';
import ConsultFilters, { parseFilters, DEFAULT_LIMIT } from '@/components/consults/ConsultFilters';
import ConsultFilterChips from '@/components/consults/ConsultFilterChips';
import ConsultSummaryCards from '@/components/consults/ConsultSummaryCards';
import ConsultPcTable from '@/components/consults/ConsultPcTable';
import ConsultBulkActionsBar from './ConsultBulkActionsBar';
import ConsultListRow from './ConsultListRow';
import { useConsults } from '@/hooks/useConsults';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { consultService } from '@/services/consult.service';
import type { ConsultListFilters, ConsultListItem, ConsultTag } from '@/types/consult.types';
import { CONSULT_STATUS_LABELS } from '@/types/consult.types';
import { groupConsultsByDate, consultRowKey } from '@/utils/groupConsultsByDate';
import { computeConsultSummary, isUnprocessedStatus } from '@/utils/consultSummary';
import { displayConsultNo, displaySiteName } from '@/utils/consultDisplay';
import toast from 'react-hot-toast';

function clientFilter(consults: ConsultListItem[], filters: ConsultListFilters): ConsultListItem[] {
  let rows = consults;
  const q = filters.q?.trim().toLowerCase();

  if (q) {
    rows = rows.filter((c) => {
      const haystack = [
        c.customerNameMasked,
        c.consultNo,
        displayConsultNo(c),
        c.phoneMasked,
        c.siteName,
        c.productName,
        c.lastMessagePreview,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      return haystack.includes(q);
    });
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

  if (filters.site) {
    const siteQ = filters.site.trim().toLowerCase();
    rows = rows.filter((c) => displaySiteName(c).toLowerCase().includes(siteQ));
  }

  if (filters.tag) {
    const tagQ = filters.tag.trim().toLowerCase();
    rows = rows.filter((c) => c.tags?.some((t) => t.label.toLowerCase().includes(tagQ)));
  }

  if (filters.assignee) {
    rows = rows.filter((c) => c.agent?.id === filters.assignee);
  }

  return rows;
}

function clientSort(rows: ConsultListItem[], sort: ConsultListFilters['sort']): ConsultListItem[] {
  const next = [...rows];

  switch (sort) {
    case 'oldest':
      return next.sort((a, b) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime());
    case 'recent_response':
      return next.sort((a, b) => new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime());
    case 'unprocessed':
      return next.sort((a, b) => {
        const aOpen = isUnprocessedStatus(a.status) ? 0 : 1;
        const bOpen = isUnprocessedStatus(b.status) ? 0 : 1;
        if (aOpen !== bOpen) return aOpen - bOpen;
        return new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime();
      });
    case 'status':
      return next.sort((a, b) =>
        (CONSULT_STATUS_LABELS[a.status] ?? a.status).localeCompare(
          CONSULT_STATUS_LABELS[b.status] ?? b.status,
          'ko',
        ),
      );
    case 'customer':
      return next.sort((a, b) => a.customerNameMasked.localeCompare(b.customerNameMasked, 'ko'));
    case 'newest':
    default:
      return next.sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());
  }
}

type ConsultListPanelProps = {
  selectedId?: string;
  onSelectConsult: (id: string) => void;
  compact?: boolean;
  layoutMode?: 'panel' | 'full';
};

export default function ConsultListPanel({
  selectedId,
  onSelectConsult,
  compact,
  layoutMode = 'panel',
}: ConsultListPanelProps) {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseFilters(searchParams), [searchParams]);
  const isPcTable = useMediaQuery('(min-width: 769px)');
  const useWideTable = layoutMode === 'full' && isPcTable;

  const { data, isLoading, isError, error, refetch } = useConsults(filters);

  const rawConsults = data?.data ?? [];
  const allConsults = useMemo(
    () => clientSort(clientFilter(rawConsults, filters), filters.sort),
    [rawConsults, filters],
  );

  const summaryStats = useMemo(() => computeConsultSummary(allConsults), [allConsults]);

  const siteOptions = useMemo(() => {
    const sites = new Set<string>();
    for (const c of rawConsults) {
      sites.add(displaySiteName(c));
    }
    return Array.from(sites).sort((a, b) => a.localeCompare(b, 'ko'));
  }, [rawConsults]);

  const total = allConsults.length;
  const limit = filters.limit ?? DEFAULT_LIMIT;
  const page = filters.page ?? 1;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const safePage = Math.min(page, totalPages);

  const consults = useMemo(() => {
    const start = (safePage - 1) * limit;
    return allConsults.slice(start, start + limit);
  }, [allConsults, safePage, limit]);

  const groups = useMemo(() => groupConsultsByDate(consults), [consults]);

  const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);

  const allKeys = useMemo(() => consults.map(consultRowKey), [consults]);
  const allSelected = allKeys.length > 0 && allKeys.every((k) => selectedKeys.has(k));

  const hasActiveFilters = Boolean(
    filters.q || filters.status || filters.from || filters.to || filters.site || filters.assignee || filters.tag,
  );

  const setPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  const clearAllFilters = () => {
    setSearchParams(new URLSearchParams(), { replace: true });
  };

  const toggleAll = (checked: boolean) => {
    setSelectedKeys(checked ? new Set(allKeys) : new Set());
  };

  const toggleOne = (key: string, checked: boolean) => {
    setSelectedKeys((prev) => {
      const next = new Set(prev);
      if (checked) next.add(key);
      else next.delete(key);
      return next;
    });
  };

  const selectedIds = useMemo(
    () => consults.filter((c) => selectedKeys.has(consultRowKey(c))).map((c) => c.id),
    [consults, selectedKeys],
  );

  const handleBulkDelete = async () => {
    setBulkBusy(true);
    try {
      const { pending } = await consultService.bulkDelete(selectedIds);
      if (pending) toast('삭제 API 연동 대기 중 (Codex)', { icon: 'ℹ️' });
      else {
        toast.success(`${selectedIds.length}건 삭제됨`);
        void refetch();
      }
      setSelectedKeys(new Set());
    } catch {
      toast.error('일괄 삭제 실패');
    } finally {
      setBulkBusy(false);
    }
  };

  const handleBulkStatus = async (status: string) => {
    setBulkBusy(true);
    try {
      const { pending } = await consultService.bulkStatus(selectedIds, status);
      if (pending) toast('상태 일괄 변경 API 연동 대기 중 (Codex)', { icon: 'ℹ️' });
      else {
        toast.success('상태 일괄 변경됨');
        void refetch();
      }
      setSelectedKeys(new Set());
    } catch {
      toast.error('상태 일괄 변경 실패');
    } finally {
      setBulkBusy(false);
    }
  };

  const handleBulkAssign = async (agentId: string) => {
    setBulkBusy(true);
    try {
      const { pending } = await consultService.bulkAssign(selectedIds, agentId || null);
      if (pending) toast('담당자 일괄 변경 API 연동 대기 중 (Codex)', { icon: 'ℹ️' });
      else {
        toast.success('담당자 일괄 변경됨');
        void refetch();
      }
      setSelectedKeys(new Set());
    } catch {
      toast.error('담당자 일괄 변경 실패');
    } finally {
      setBulkBusy(false);
    }
  };

  const handleBulkTag = async (tags: ConsultTag[], mode: 'add' | 'remove') => {
    setBulkBusy(true);
    try {
      const { pending } = await consultService.bulkTags(selectedIds, tags, mode);
      if (pending) toast('태그 API 연동 대기 중 (Codex)', { icon: 'ℹ️' });
      else {
        toast.success(`태그 ${mode === 'add' ? '추가' : '제거'}됨`);
        void refetch();
      }
      setSelectedKeys(new Set());
    } catch {
      toast.error('태그 일괄 적용 실패');
    } finally {
      setBulkBusy(false);
    }
  };

  const emptyTitle = hasActiveFilters ? '조건에 맞는 상담이 없습니다' : '아직 접수된 상담이 없습니다';
  const emptyDescription = hasActiveFilters
    ? '검색어나 필터를 변경해 주세요.'
    : '사이트 상담폼이 연결되었는지 확인해 주세요.';

  return (
    <div
      className={clsx(
        'consult-list-panel flex min-h-0 flex-col',
        compact && 'min-h-0',
        layoutMode === 'full' && 'consult-list-panel--full',
      )}
    >
      <div className="consult-list-panel__header shrink-0 border-b border-slate-200 bg-white px-3 py-3 min-[769px]:px-4">
        {layoutMode === 'full' ? (
          <div className="mb-3 min-[769px]:mb-4">
            <h1 className="text-lg font-bold text-slate-900 min-[769px]:text-xl">상담 관리</h1>
            <p className="mt-0.5 text-sm text-slate-500">고객 문의와 처리 상태를 한 화면에서 관리합니다.</p>
          </div>
        ) : (
          <div className="flex items-center justify-between gap-2">
            <h2 className="text-base font-semibold text-slate-900">상담 목록</h2>
            <span className="text-xs text-slate-500">{data?.meta?.total ?? total}건</span>
          </div>
        )}

        {layoutMode === 'full' && !isLoading && !isError ? (
          <div className="mb-3 min-[769px]:mb-4">
            <ConsultSummaryCards stats={summaryStats} />
          </div>
        ) : null}

        <ConsultFilters variant={layoutMode === 'full' ? 'page' : 'panel'} siteOptions={siteOptions} />
        <ConsultFilterChips filters={filters} onClearAll={clearAllFilters} />

        {!useWideTable && consults.length > 0 ? (
          <label className="mt-2 flex min-h-10 items-center gap-2 text-sm text-slate-700 min-[769px]:mt-3">
            <input
              type="checkbox"
              className="consult-checkbox h-5 w-5 rounded border-slate-300 text-indigo-600"
              checked={allSelected}
              onChange={(e) => toggleAll(e.target.checked)}
              aria-label="전체 선택"
            />
            전체 선택
          </label>
        ) : null}
      </div>

      <ConsultBulkActionsBar
        selectedCount={selectedIds.length}
        busy={bulkBusy}
        onDelete={() => void handleBulkDelete()}
        onStatusChange={(status) => void handleBulkStatus(status)}
        onAssigneeChange={(agentId) => void handleBulkAssign(agentId)}
        onTagApply={(tags, mode) => void handleBulkTag(tags, mode)}
        onClear={() => setSelectedKeys(new Set())}
      />

      <div className="consult-list-panel__body min-h-0 flex-1 overflow-y-auto px-2 py-2 min-[769px]:px-3">
        {isLoading && (
          <div className="space-y-3 p-2" aria-label="상담 목록 로딩 중">
            <LoadingSkeleton className="h-8 w-48 rounded-lg" />
            {Array.from({ length: useWideTable ? 6 : 4 }).map((_, i) => (
              <LoadingSkeleton key={i} className={useWideTable ? 'h-16 w-full rounded-lg' : 'h-20 w-full rounded-xl'} />
            ))}
          </div>
        )}

        {isError && (
          <EmptyState
            title="상담목록을 불러오지 못했습니다"
            description={error instanceof Error ? error.message : '잠시 후 다시 시도해 주세요.'}
            action={
              <Button variant="primary" onClick={() => void refetch()}>
                다시 불러오기
              </Button>
            }
          />
        )}

        {!isLoading && !isError && consults.length === 0 && (
          <EmptyState title={emptyTitle} description={emptyDescription} />
        )}

        {!isLoading && !isError && groups.length > 0 && useWideTable && (
          <ConsultPcTable
            groups={groups}
            selectedKeys={selectedKeys}
            activeId={selectedId}
            onToggle={toggleOne}
            onRowClick={onSelectConsult}
            allKeys={allKeys}
            onToggleAll={toggleAll}
            allSelected={allSelected}
          />
        )}

        {!isLoading && !isError && groups.length > 0 && !useWideTable && (
          <div className="consult-date-groups space-y-6">
            {groups.map((group) => (
              <section key={group.key} aria-label={group.label}>
                <div className="consult-date-header sticky top-0 z-[1] flex flex-wrap items-center justify-between gap-2 bg-slate-50/95 px-1 py-1 backdrop-blur-sm">
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">{group.label}</h3>
                  <span className="text-xs text-slate-400">총 {group.totalCount}건</span>
                </div>
                <ul className="mt-2 space-y-2">
                  {group.items.map((consult) => {
                    const key = consultRowKey(consult);
                    return (
                      <li key={key}>
                        <ConsultListRow
                          consult={consult}
                          selected={selectedKeys.has(key)}
                          active={selectedId === consult.id}
                          onSelect={(checked) => toggleOne(key, checked)}
                          onClick={() => onSelectConsult(consult.id)}
                          detailHref={`/admin/consults/${encodeURIComponent(consult.id)}`}
                        />
                      </li>
                    );
                  })}
                </ul>
              </section>
            ))}
          </div>
        )}
      </div>

      {!isLoading && !isError && totalPages > 1 && (
        <Pagination page={safePage} totalPages={totalPages} total={total} limit={limit} onPageChange={setPage} />
      )}
    </div>
  );
}
