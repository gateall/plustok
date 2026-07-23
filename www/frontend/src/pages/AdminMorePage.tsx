import { Link } from 'react-router-dom';
import { LogOut, MessageSquare, User } from 'lucide-react';
import { useAuth } from '@/features/auth/AuthProvider';
import { filterNavByRole, MORE_NAV } from '@/config/adminNav';
import AdminNavLinkItem from '@/components/Admin/AdminNavLink';
import PageHeader from '@/components/common/PageHeader';

/** "더보기" tab — overflow links (sites, agents, AI, settings) + logout. */
export default function AdminMorePage() {
  const { user, logout } = useAuth();
  const items = filterNavByRole(MORE_NAV, user?.role);

  return (
    <div className="min-w-0 py-4 md:py-6">
      <PageHeader
        title="더보기"
        description="사이트·상담원·AI·설정 등 추가 기능. PHP 전용 메뉴는 새 탭에서 열립니다."
      />

      <div className="grid min-w-0 gap-3 sm:grid-cols-2">
        {items.map((item) => (
          <AdminNavLinkItem key={item.id} item={item} variant="more" />
        ))}
      </div>

      <div className="mt-6 space-y-2 border-t border-slate-200 pt-6">
        <Link
          to="/chat"
          className="flex min-h-12 items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 text-sm font-semibold text-indigo-700"
        >
          <MessageSquare className="h-5 w-5 shrink-0" aria-hidden />
          상담 화면으로 이동
        </Link>

        <button
          type="button"
          disabled
          className="flex min-h-12 w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-400"
          title="Phase 6"
        >
          <User className="h-5 w-5 shrink-0" aria-hidden />
          내정보 (준비 중)
        </button>

        <button
          type="button"
          onClick={() => void logout()}
          className="flex min-h-12 w-full items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-700"
        >
          <LogOut className="h-5 w-5 shrink-0" aria-hidden />
          로그아웃
        </button>
      </div>
    </div>
  );
}
