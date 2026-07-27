import { Link } from 'react-router-dom';
import PageHeader from '@/components/common/PageHeader';
import EmptyState from '@/components/common/EmptyState';
import { Button } from '@/components/admin-ui';

const REQUIRED_APIS = [
  'GET /api/v1/admin/sites — 목록 (page, limit, q, status)',
  'GET /api/v1/admin/sites/{id} — 상세',
  'POST /api/v1/admin/sites — 등록',
  'PUT /api/v1/admin/sites/{id} — 수정',
  'PATCH /api/v1/admin/sites/{id}/status — 활성/중지',
  'POST /api/v1/admin/sites/{id}/regenerate-key — API Key 재발급',
  'DELETE /api/v1/admin/sites/{id} — 삭제 (상담 연결 시 차단)',
  'POST /api/v1/admin/sites/bulk-delete — 선택 삭제',
] as const;

/**
 * Sites React migration blocked — no REST API on router.php.
 * Does not fake a working admin list.
 */
export default function AdminSitesBlockPage() {
  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 px-4 md:px-8">
      <PageHeader
        title="사이트 관리"
        description="React API 연동 대기 — PHP 관리자에서 이용 가능"
      />

      <EmptyState
        title="API 연동 필요"
        description="사이트 목록·수정·키 재발급 REST API가 아직 등록되지 않았습니다. 가짜 데이터를 표시하지 않습니다."
        action={
          <div className="flex flex-col items-center gap-3 sm:flex-row">
            <Button href="/admin/sites/" variant="secondary">
              PHP 관리자에서 열기
            </Button>
            <Link
              to="/admin/dashboard"
              className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
            >
              대시보드로
            </Link>
          </div>
        }
      />

      <section className="mx-auto mt-8 max-w-2xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-left">
        <h2 className="text-sm font-semibold text-amber-900">필요 REST API (DeepSeek)</h2>
        <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-amber-900/90">
          {REQUIRED_APIS.map((line) => (
            <li key={line}>{line}</li>
          ))}
        </ul>
        <p className="mt-3 text-xs text-amber-800/80">
          PHP 참조: <code className="rounded bg-white/60 px-1">/admin/sites/index.php</code> — SiteSchema-aware
          columns, consult-block delete, API key regen.
        </p>
      </section>
    </div>
  );
}
