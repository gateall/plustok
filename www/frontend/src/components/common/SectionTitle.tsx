import type { ReactNode } from 'react';
import clsx from 'clsx';

type SectionTitleProps = {
  title: ReactNode;
  description?: ReactNode;
  action?: ReactNode;
  className?: string;
};

export default function SectionTitle({ title, description, action, className }: SectionTitleProps) {
  const titleId = typeof title === 'string' ? `section-${title.replace(/\s+/g, '-').toLowerCase()}` : undefined;
  const descId = description && titleId ? `${titleId}-desc` : undefined;

  return (
    <div className={clsx('mb-4 flex min-w-0 flex-wrap items-start justify-between gap-3', className)}>
      <div className="min-w-0">
        <h2 id={titleId} className="text-base font-semibold text-[var(--pt-color-text)] sm:text-lg" aria-describedby={descId}>
          {title}
        </h2>
        {description ? (
          <p id={descId} className="mt-1 text-sm text-[var(--pt-color-text-muted)]">
            {description}
          </p>
        ) : null}
      </div>
      {action ? <div className="shrink-0">{action}</div> : null}
    </div>
  );
}
