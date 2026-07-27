import type { ReactNode } from 'react';
import clsx from 'clsx';

type AdminListCardsProps = {
  children: ReactNode;
  className?: string;
  /** list = vertical stack (default), grid = responsive 1–2 columns */
  layout?: 'list' | 'grid';
  label?: string;
};

/** Grid/list wrapper for mobile admin cards — no horizontal overflow at 360px. */
export default function AdminListCards({ children, className, layout = 'list', label }: AdminListCardsProps) {
  const listClass =
    layout === 'grid'
      ? 'grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3'
      : 'min-w-0 space-y-3';

  return (
    <ul className={clsx('admin-list-cards', layout === 'grid' && 'admin-list-cards--grid', listClass, className)} aria-label={label}>
      {children}
    </ul>
  );
}
