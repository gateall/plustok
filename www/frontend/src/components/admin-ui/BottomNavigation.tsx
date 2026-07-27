import type { ReactNode } from 'react';
import clsx from 'clsx';

type BottomNavigationProps = {
  children: ReactNode;
  columns: number;
  className?: string;
};

export default function BottomNavigation({ children, columns, className }: BottomNavigationProps) {
  return (
    <nav
      className={clsx('fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white/95 pb-safe-bottom backdrop-blur transition-opacity duration-250 lg:hidden', className)}
      aria-label="관리자 하단 네비게이션"
    >
      <div className="mx-auto grid h-16 max-w-7xl" style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}>
        {children}
      </div>
    </nav>
  );
}
