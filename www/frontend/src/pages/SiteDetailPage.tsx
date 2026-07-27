import { useState, type ReactNode } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Activity, Globe } from 'lucide-react';
import toast from 'react-hot-toast';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import StatusBadge from '@/components/admin-ui/StatusBadge';
import { Button } from '@/components/admin-ui';
import SiteDeleteDialog from '@/components/sites/SiteDeleteDialog';
import SiteRegenKeyDialog from '@/components/sites/SiteRegenKeyDialog';
import {
  useSite,
  useSiteStats,
  useSiteHealth,
  useSiteToggle,
  useSiteDelete,
  useSiteRegenKey,
  useSiteHealthCheck,
} from '@/hooks/useSites';

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 border-b border-slate-100 py-3 sm:flex-row sm:gap-4">
      <dt className="shrink-0 text-sm font-medium text-slate-500 sm:w-32">{label}</dt>
      <dd className="min-w-0 text-sm text-slate-900">{value}</dd>
    </div>
  );
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '-';
  try {
    return new Date(iso).toLocaleString('ko-KR');
  } catch {
    return iso;
  }
}

export default function SiteDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const siteId = id ? parseInt(id, 10) : undefined;

  const { data: site, isLoading, isError, error, refetch } = useSite(siteId);
  const { data: stats } = useSiteStats(siteId);
  const { data: healthData } = useSiteHealth(siteId);

  const toggleMutation = useSiteToggle();
  const deleteMutation = useSiteDelete();
  const regenMutation = useSiteRegenKey();
  const healthCheckMutation = useSiteHealthCheck();

  const [showDelete, setShowDelete] = useState(false);
  const [showRegen, setShowRegen] = useState(false);
  const [newApiKey, setNewApiKey] = useState<string | null>(null);

  const healthHistory = Array.isArray(healthData?.data) ? healthData.data : [];

  const handleToggle = async () => {
    if (!siteId) return;
    try {
      await toggleMutation.mutateAsync(siteId);
      toast.success('상태가 변경되었습니다.');
      void refetch();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '상태 변경에 실패했습니다.');
    }
  };

  const handleDelete = async () => {
    if (!siteId) return;
    try {
      await deleteMutation.mutateAsync(siteId);
      toast.success('사이트가 삭제되었습니다.');
      navigate('/admin/sites');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '삭제에 실패했습니다.');
    } finally {
      setShowDelete(false);
    }
  };

  const handleRegenKey = async () => {
    if (!siteId) return;
    try {
      const result = await regenMutation.mutateAsync(siteId);
      setNewApiKey(result.apiKey);
      toast.success('API Key가 재발급되었습니다.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Key 재발급에 실패했습니다.');
    } finally {
      setShowRegen(false);
    }
  };

  const handleHealthCheck = async () => {
    if (!siteId) return;
    try {
      await healthCheckMutation.mutateAsync(siteId);
      toast.success('헬스 체크가 완료되었습니다.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '헬스 체크에 실패했습니다.');
    }
  };

  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[960px] mx-auto w-full px-4 md:px-8">
      <Link
        to="/admin/sites"
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft size={16} aria-hidden />
        사이트 목록
      </Link>

      {isLoading && (
        <div aria-label="사이트 상세 로딩 중">
          <LoadingSkeleton className="mb-4 h-10 w-64 rounded-lg" />
          <LoadingSkeleton className="h-64 w-full rounded-xl" />
        </div>
      )}

      {isError && (
        <EmptyState
          title="사이트를 불러오지 못했습니다"
          description={error instanceof Error ? error.message : '잠시 후 다시 시도해 주세요.'}
          action={
            <button
              type="button"
              onClick={() => void refetch()}
              className="admin-touch-target rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
            >
              다시 시도
            </button>
          }
        />
      )}

      {!isLoading && !isError && site && (
        <>
          <PageHeader
            title={site.siteName}
            description={site.siteCode}
            actions={
              <div className="flex flex-wrap gap-2">
                <Button variant="ghost" to={`/admin/sites/${site.id}/edit`} className="admin-touch-target">
                  수정
                </Button>
                <Button
                  variant="secondary"
                  disabled={toggleMutation.isPending}
                  onClick={() => void handleToggle()}
                  className="admin-touch-target"
                >
                  {site.status ? '중지' : '활성'}
                </Button>
                <Button variant="secondary" onClick={() => setShowRegen(true)} className="admin-touch-target">
                  Key 재발급
                </Button>
                <Button variant="danger" onClick={() => setShowDelete(true)} className="admin-touch-target">
                  삭제
                </Button>
              </div>
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

          <div className="mb-6 grid gap-4 sm:grid-cols-3">
            <div className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
              <p className="text-xs font-medium text-slate-500">상태</p>
              <div className="mt-1">
                <StatusBadge label={site.status ? '활성' : '중지'} tone={site.status ? 'success' : 'neutral'} />
              </div>
            </div>
            <div className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
              <p className="text-xs font-medium text-slate-500">오늘 상담</p>
              <p className="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                {stats?.todayConsultCount ?? site.todayConsultCount ?? 0}
              </p>
            </div>
            <div className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
              <p className="text-xs font-medium text-slate-500">총 상담</p>
              <p className="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                {stats?.totalConsultCount ?? site.totalConsultCount ?? 0}
              </p>
            </div>
          </div>

          <section className="mb-6 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
            <h2 className="mb-2 text-base font-semibold text-slate-900">기본 정보</h2>
            <dl>
              <DetailRow label="도메인" value={<span className="inline-flex items-center gap-1"><Globe size={14} />{site.domain || '-'}</span>} />
              <DetailRow label="브랜드" value={site.brand || '-'} />
              <DetailRow label="부서" value={site.division || '-'} />
              <DetailRow label="페르소나" value={site.persona || '-'} />
              <DetailRow label="등록일" value={formatDate(site.createdAt)} />
              <DetailRow label="수정일" value={formatDate(site.updatedAt)} />
            </dl>
          </section>

          <section className="mb-6 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
            <div className="mb-4 flex items-center justify-between gap-2">
              <h2 className="text-base font-semibold text-slate-900">
                <Activity size={16} className="mr-1 inline" aria-hidden />
                헬스 체크
              </h2>
              <Button
                variant="secondary"
                disabled={healthCheckMutation.isPending}
                onClick={() => void handleHealthCheck()}
                className="admin-touch-target"
              >
                {healthCheckMutation.isPending ? '검사 중…' : '지금 검사'}
              </Button>
            </div>

            {site.lastHealthCheck ? (
              <dl>
                <DetailRow
                  label="최근 결과"
                  value={
                    <StatusBadge
                      label={site.lastHealthCheck.isHealthy ? '정상' : '점검 필요'}
                      tone={site.lastHealthCheck.isHealthy ? 'success' : 'danger'}
                    />
                  }
                />
                <DetailRow label="응답(ms)" value={site.lastHealthCheck.responseMs ?? '-'} />
                <DetailRow label="상태코드" value={site.lastHealthCheck.statusCode ?? '-'} />
                <DetailRow label="검사 시각" value={formatDate(site.lastHealthCheck.checkedAt)} />
                <DetailRow label="대상 URL" value={site.lastHealthCheck.targetUrl || '-'} />
              </dl>
            ) : (
              <p className="text-sm text-slate-500">아직 헬스 체크 기록이 없습니다.</p>
            )}

            {healthHistory.length > 1 && (
              <div className="mt-4">
                <p className="mb-2 text-xs font-medium text-slate-500">최근 이력</p>
                <ul className="space-y-1 text-xs text-slate-600">
                  {healthHistory.slice(0, 5).map((h, i) => (
                    <li key={i} className="flex justify-between gap-2">
                      <span>{formatDate(h.checkedAt)}</span>
                      <StatusBadge
                        label={h.isHealthy ? '정상' : '실패'}
                        tone={h.isHealthy ? 'success' : 'danger'}
                      />
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </section>

          {stats && (stats.lastConsultedAt || stats.lastCommunicationAt) && (
            <section className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
              <h2 className="mb-2 text-base font-semibold text-slate-900">활동 통계</h2>
              <dl>
                <DetailRow label="마지막 상담" value={formatDate(stats.lastConsultedAt)} />
                <DetailRow label="마지막 통신" value={formatDate(stats.lastCommunicationAt)} />
              </dl>
            </section>
          )}
        </>
      )}

      <SiteDeleteDialog
        open={showDelete}
        site={site ?? null}
        loading={deleteMutation.isPending}
        onConfirm={() => void handleDelete()}
        onClose={() => setShowDelete(false)}
      />

      <SiteRegenKeyDialog
        open={showRegen}
        site={site ?? null}
        loading={regenMutation.isPending}
        onConfirm={() => void handleRegenKey()}
        onClose={() => setShowRegen(false)}
      />
    </div>
  );
}
