import { Link, useLocation } from 'react-router-dom';
import { Bell, Menu, MessageSquare, Search, User } from 'lucide-react';
import { useAuth } from '@/features/auth/AuthProvider';
import { useAdminNav } from '@/features/admin/AdminNavContext';
import { useConsultListSearch } from '@/features/admin/ConsultListSearchContext';
import { ROUTE_LABELS } from '@/config/adminNav';

/** Mobile/tablet header — hamburger drawer, brand, quick actions (< lg). */
export default function AdminHeader() {
  const { user } = useAuth();
  const { openDrawer } = useAdminNav();
  const { openConsultSearch } = useConsultListSearch();
  const location = useLocation();

  const segment = location.pathname.split('/').filter(Boolean).pop() ?? 'dashboard';
  const pageLabel = ROUTE_LABELS[segment] ?? 'Dashboard';

  return (
    <header className="sticky top-0 z-40 min-h-14 border-b border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 lg:hidden">
      <div className="mx-auto flex min-h-14 max-w-7xl min-w-0 items-center gap-2 px-4 sm:px-5">
        <button
          type="button"
          onClick={openDrawer}
          className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100"
          aria-label="메뉴 열기"
        >
          <Menu className="h-5 w-5" />
        </button>

        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 items-center gap-2">
            <span className="shrink-0 rounded-lg bg-indigo-600 px-2 py-0.5 text-xs font-bold tracking-tight text-white">
              Smarttoktok
            </span>
            <span className="truncate text-base font-semibold text-slate-900">CRM</span>
          </div>
          <p className="truncate text-xs text-slate-500">
            {pageLabel} · {user?.name ?? '관리자'}
          </p>
        </div>

        <div className="flex shrink-0 items-center gap-0.5">
          <Link
            to="/chat"
            className="flex h-11 w-11 items-center justify-center rounded-lg text-indigo-600 hover:bg-indigo-50"
            aria-label="상담 화면"
            title="상담 화면"
          >
            <MessageSquare className="h-5 w-5" />
          </Link>

          <button
            type="button"
            onClick={openConsultSearch}
            className="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
            aria-label="상담 검색"
            title="상담 검색"
          >
            <Search className="h-5 w-5" />
          </button>

          <button
            type="button"
            className="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
            aria-label="알림 (준비 중)"
            disabled
            title="Phase 6"
          >
            <Bell className="h-5 w-5" />
          </button>

          <Link
            to="/admin/more"
            className="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200"
            aria-label="더보기 · 프로필"
            title={`${user?.name ?? '관리자'}`}
          >
            <User className="h-5 w-5" />
          </Link>
        </div>
      </div>
    </header>
  );
}
