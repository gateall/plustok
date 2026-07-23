import type { ReactNode } from 'react';
import clsx from 'clsx';

type EmptyStateProps = {
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
};

/** Unified empty / placeholder state for admin pages. */
export default function EmptyState({ title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={clsx(
        'flex min-h-[40vh] flex-col items-center justify-center px-4 py-12 text-center',
        className,
      )}
    >
      <p className="page-title">{title}</p>
      {description && <p className="page-description mt-2 max-w-sm">{description}</p>}
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
}
