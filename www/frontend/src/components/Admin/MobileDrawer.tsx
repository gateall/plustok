import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { LogOut, MessageSquare, User, X } from 'lucide-react';
import clsx from 'clsx';
import { useAuth } from '@/features/auth/AuthProvider';
import { useAdminNav } from '@/features/admin/AdminNavContext';
import { filterNavByRole, MORE_NAV, SIDEBAR_NAV } from '@/config/adminNav';
import AdminNavLinkItem from './AdminNavLink';

/** Mobile hamburger drawer — full nav + account links, z-index above bottom nav. */
export default function MobileDrawer() {
  const { user, logout } = useAuth();
  const { drawerOpen, closeDrawer } = useAdminNav();

  const sidebarItems = filterNavByRole(SIDEBAR_NAV, user?.role);
  const moreItems = filterNavByRole(MORE_NAV, user?.role);

  useEffect(() => {
    if (!drawerOpen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeDrawer();
    };
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [drawerOpen, closeDrawer]);

  if (!drawerOpen) return null;

  return (
    <div className="fixed inset-0 z-[60] lg:hidden" role="dialog" aria-modal="true" aria-label="전체 메뉴">
      <button
        type="button"
        className="absolute inset-0 bg-slate-900/40 transition-opacity"
        aria-label="메뉴 닫기"
        onClick={closeDrawer}
      />

      <div
        className={clsx(
          'absolute inset-y-0 left-0 flex w-[min(20rem,85vw)] flex-col bg-white shadow-xl',
          'transition-transform duration-200 ease-out',
        )}
      >
        <div className="flex min-h-14 items-center justify-between border-b border-slate-200 px-4">
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-slate-900">{user?.name ?? '관리자'}</p>
            <p className="truncate text-xs text-slate-500">{user?.role === 'admin' ? '관리자' : '상담원'}</p>
          </div>
          <button
            type="button"
            onClick={closeDrawer}
            className="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
            aria-label="닫기"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto p-3" aria-label="주 메뉴">
          {sidebarItems.map((item) => (
            <AdminNavLinkItem key={item.id} item={item} variant="drawer" onNavigate={closeDrawer} />
          ))}
        </nav>

        <div className="space-y-1 border-t border-slate-200 p-3">
          <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">더보기</p>
          {moreItems.map((item) => (
            <AdminNavLinkItem key={`more-${item.id}`} item={item} variant="drawer" onNavigate={closeDrawer} />
          ))}

          <Link
            to="/chat"
            onClick={closeDrawer}
            className="flex min-h-11 items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
          >
            <MessageSquare className="h-5 w-5 shrink-0" aria-hidden />
            상담 화면
          </Link>

          <button
            type="button"
            disabled
            className="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-slate-400"
            title="Phase 6"
          >
            <User className="h-5 w-5 shrink-0" aria-hidden />
            내정보 (준비 중)
          </button>

          <button
            type="button"
            onClick={() => {
              closeDrawer();
              void logout();
            }}
            className="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-red-600 hover:bg-red-50"
          >
            <LogOut className="h-5 w-5 shrink-0" aria-hidden />
            로그아웃
          </button>
        </div>
      </div>
    </div>
  );
}
