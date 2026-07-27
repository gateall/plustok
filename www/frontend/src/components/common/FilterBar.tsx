import type { ReactNode } from 'react';
import clsx from 'clsx';

type FilterBarProps = {
  children: ReactNode;
  className?: string;
  sticky?: boolean;
  /** Accessible name when the bar contains filter controls */
  'aria-label'?: string;
};

/** Mobile-first horizontal filter row — wraps on narrow viewports. */
export default function FilterBar({ children, className, sticky, 'aria-label': ariaLabel = '필터' }: FilterBarProps) {
  return (
    <div
      role="search"
      aria-label={ariaLabel}
      className={clsx(
        'flex min-w-0 flex-wrap items-center gap-2 rounded-[var(--pt-radius-lg)] border border-[var(--pt-color-border)] bg-[var(--pt-color-surface)] p-3',
        sticky && 'sticky top-0 z-20',
        className,
      )}
    >
      {children}
    </div>
  );
}
