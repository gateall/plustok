import { NavLink } from 'react-router-dom';
import { Menu } from 'lucide-react';
import clsx from 'clsx';
import { useAuth } from '@/features/auth/AuthProvider';
import { BOTTOM_NAV, filterNavByRole } from '@/config/adminNav';

export default function BottomNav() {
  const { user } = useAuth();
  const tabs = filterNavByRole(BOTTOM_NAV, user?.role);

  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white/95 pb-safe-bottom backdrop-blur transition-opacity duration-200 lg:hidden"
      aria-label="관리자 하단 메뉴"
    >
      <div
        className="mx-auto grid h-16 max-w-7xl"
        style={{ gridTemplateColumns: `repeat(${tabs.length + 1}, minmax(0, 1fr))` }}
      >
        {tabs.map(({ id, to, label, icon: Icon, end }) => (
          <NavLink
            key={id}
            to={to!}
            end={end}
            className={({ isActive }) =>
              clsx(
                'flex min-h-11 min-w-0 flex-col items-center justify-center gap-0.5 px-1 text-[11px] transition-colors',
                isActive ? 'font-semibold text-indigo-600' : 'font-medium text-slate-500 hover:text-slate-700',
              )
            }
          >
            <Icon className="h-5 w-5 shrink-0" strokeWidth={2} aria-hidden />
            <span className="truncate">{label}</span>
          </NavLink>
        ))}

        <NavLink
          to="/admin/more"
          className={({ isActive }) =>
            clsx(
              'flex min-h-11 min-w-0 flex-col items-center justify-center gap-0.5 px-1 text-[11px] transition-colors',
              isActive ? 'font-semibold text-indigo-600' : 'font-medium text-slate-500 hover:text-slate-700',
            )
          }
        >
          <Menu className="h-5 w-5 shrink-0" strokeWidth={2} aria-hidden />
          <span className="truncate">더보기</span>
        </NavLink>
      </div>
    </nav>
  );
}
