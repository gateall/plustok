import { NavLink } from 'react-router-dom';
import clsx from 'clsx';
import type { AdminNavItem } from '@/config/adminNav';

type AdminNavLinkProps = {
  item: AdminNavItem;
  variant: 'sidebar' | 'drawer' | 'more';
  onNavigate?: () => void;
};

/** Renders in-app NavLink or external PHP admin link (new tab). */
export default function AdminNavLinkItem({ item, variant, onNavigate }: AdminNavLinkProps) {
  const Icon = item.icon;

  const baseClass = clsx(
    'flex min-h-11 min-w-0 items-center gap-3 rounded-lg px-3 text-sm transition-colors',
    variant === 'sidebar' && 'py-2.5',
    variant === 'drawer' && 'py-3',
    variant === 'more' && 'border border-slate-200 bg-white py-3 shadow-sm',
  );

  const activeClass = 'bg-indigo-50 font-semibold text-indigo-600';
  const inactiveClass = 'font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900';

  if (item.externalHref) {
    return (
      <a
        href={item.externalHref}
        target="_blank"
        rel="noopener noreferrer"
        className={clsx(baseClass, inactiveClass)}
        onClick={onNavigate}
        title="PHP 관리자에서 열기"
      >
        <Icon className="h-5 w-5 shrink-0" strokeWidth={2} aria-hidden />
        <span className="truncate">{item.label}</span>
        <span className="ml-auto shrink-0 text-[10px] text-slate-400">PHP</span>
      </a>
    );
  }

  if (!item.to) return null;

  return (
    <NavLink
      to={item.to}
      end={item.end}
      onClick={onNavigate}
      className={({ isActive }) => clsx(baseClass, isActive ? activeClass : inactiveClass)}
    >
      <Icon className="h-5 w-5 shrink-0" strokeWidth={2} aria-hidden />
      <span className="truncate">{item.label}</span>
    </NavLink>
  );
}
