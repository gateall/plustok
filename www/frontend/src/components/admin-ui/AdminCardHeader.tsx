import type { ReactNode } from 'react';
import clsx from 'clsx';

type AdminCardHeaderProps = {
  title: ReactNode;
  subtitle?: ReactNode;
  aside?: ReactNode;
  className?: string;
};

export default function AdminCardHeader({ title, subtitle, aside, className }: AdminCardHeaderProps) {
  return (
    <div className={clsx('admin-card-header flex items-start justify-between gap-3', className)}>
      <div className="min-w-0">
        <div className="admin-card-header__title break-text break-mono text-base font-semibold text-slate-900">{title}</div>
        {subtitle ? <div className="mt-1 break-text text-sm text-slate-500">{subtitle}</div> : null}
      </div>
      {aside ? <div className="shrink-0">{aside}</div> : null}
    </div>
  );
}
