import { Link, useLocation } from 'react-router-dom';
import clsx from 'clsx';
import { useAuth } from '@/features/auth/AuthProvider';
import { filterNavByRole, ROUTE_LABELS, SIDEBAR_NAV } from '@/config/adminNav';
import AdminNavLinkItem from './AdminNavLink';

/** Desktop sidebar — visible lg+ (1024px), matches PHP admin menu IA. */
export default function DesktopSidebar() {
  const { user } = useAuth();
  const location = useLocation();
  const items = filterNavByRole(SIDEBAR_NAV, user?.role);

  const segment = location.pathname.split('/').filter(Boolean).pop() ?? 'dashboard';
  const currentLabel = ROUTE_LABELS[segment] ?? '관리자';

  return (
    <aside
      className="hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex"
      aria-label="관리자 사이드 메뉴"
    >
      <div className="border-b border-slate-200 px-5 py-4">
        <Link to="/admin/dashboard" className="flex min-w-0 items-center gap-2">
          <span className="shrink-0 rounded-lg bg-indigo-600 px-2 py-0.5 text-xs font-bold tracking-tight text-white">
            PlusTok
          </span>
          <span className="truncate text-base font-semibold text-slate-900">CRM</span>
        </Link>
        <p className="mt-1 truncate text-xs text-slate-500">{currentLabel}</p>
      </div>

      <nav className="flex-1 space-y-0.5 overflow-y-auto p-3">
        {items.map((item) => (
          <AdminNavLinkItem key={item.id} item={item} variant="sidebar" />
        ))}
      </nav>

      <div className="border-t border-slate-200 p-3">
        <Link
          to="/chat"
          className={clsx(
            'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium',
            'text-indigo-600 hover:bg-indigo-50',
          )}
        >
          상담 화면
        </Link>
      </div>
    </aside>
  );
}
