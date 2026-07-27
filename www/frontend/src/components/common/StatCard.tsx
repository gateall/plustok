import type { ReactNode } from 'react';
import clsx from 'clsx';

type StatCardProps = {
  label: ReactNode;
  value: ReactNode;
  hint?: ReactNode;
  trend?: ReactNode;
  className?: string;
};

export default function StatCard({ label, value, hint, trend, className }: StatCardProps) {
  const labelText = typeof label === 'string' ? label : 'KPI';

  return (
    <article className={clsx('admin-kpi-card min-w-0', className)} aria-label={`${labelText}: ${value}`}>
      <p className="text-xs font-medium uppercase tracking-wide text-[var(--pt-color-text-muted)]">{label}</p>
      <p className="mt-1 text-2xl font-bold text-[var(--pt-color-text)]" aria-live="polite">
        {value}
      </p>
      {hint ? <p className="mt-1 text-sm text-[var(--pt-color-text-muted)]">{hint}</p> : null}
      {trend ? <div className="mt-2 text-sm font-medium">{trend}</div> : null}
    </article>
  );
}
