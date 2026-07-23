import { Link, useLocation } from 'react-router-dom';
import { LogOut } from 'lucide-react';
import { useAuth } from '@/features/auth/AuthProvider';
import { ROUTE_LABELS } from '@/config/adminNav';

/** Desktop top bar — user info, chat link, logout (lg+). */
export default function DesktopHeader() {
  const { user, logout } = useAuth();
  const location = useLocation();

  const segment = location.pathname.split('/').filter(Boolean).pop() ?? 'dashboard';
  const currentLabel = ROUTE_LABELS[segment] ?? '관리자';

  return (
    <header className="sticky top-0 z-30 hidden min-h-14 items-center border-b border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 lg:flex">
      <div className="flex min-h-14 w-full min-w-0 items-center justify-between gap-4 px-6">
        <nav aria-label="breadcrumb" className="min-w-0 truncate text-sm text-slate-500">
          <span className="text-slate-400">관리자</span>
          <span className="mx-2 text-slate-300">/</span>
          <span className="font-medium text-slate-800">{currentLabel}</span>
        </nav>

        <div className="flex shrink-0 items-center gap-4 text-sm">
          <Link to="/chat" className="font-medium text-indigo-600 hover:underline">
            상담 화면
          </Link>
          <span className="text-slate-400">|</span>
          <span className="text-slate-600">{user?.name ?? '관리자'}</span>
          <button
            type="button"
            onClick={() => void logout()}
            className="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          >
            <LogOut className="h-4 w-4" aria-hidden />
            로그아웃
          </button>
        </div>
      </div>
    </header>
  );
}
