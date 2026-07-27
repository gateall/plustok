import type { ReactNode } from 'react';
import clsx from 'clsx';
import { AlertCircle, CheckCircle2, Info, TriangleAlert } from 'lucide-react';

export type AlertTone = 'info' | 'success' | 'warning' | 'error';

type AlertProps = {
  title?: ReactNode;
  children?: ReactNode;
  tone?: AlertTone;
  className?: string;
};

const toneStyles: Record<AlertTone, { box: string; icon: typeof Info }> = {
  info: {
    box: 'border-[var(--pt-color-info)]/30 bg-[var(--pt-color-info-muted)] text-[var(--pt-color-text)]',
    icon: Info,
  },
  success: {
    box: 'border-[var(--pt-color-success)]/30 bg-[var(--pt-color-success-muted)] text-[var(--pt-color-text)]',
    icon: CheckCircle2,
  },
  warning: {
    box: 'border-[var(--pt-color-warning)]/30 bg-[var(--pt-color-warning-muted)] text-[var(--pt-color-text)]',
    icon: TriangleAlert,
  },
  error: {
    box: 'border-[var(--pt-color-error)]/30 bg-[var(--pt-color-error-muted)] text-[var(--pt-color-text)]',
    icon: AlertCircle,
  },
};

export default function Alert({ title, children, tone = 'info', className }: AlertProps) {
  const { box, icon: Icon } = toneStyles[tone];

  return (
    <div
      role="alert"
      className={clsx('flex gap-3 rounded-[var(--pt-radius-md)] border p-4', box, className)}
    >
      <Icon className="mt-0.5 h-5 w-5 shrink-0" aria-hidden />
      <div className="min-w-0">
        {title ? <p className="text-sm font-semibold">{title}</p> : null}
        {children ? <div className={clsx('text-sm', title && 'mt-1')}>{children}</div> : null}
      </div>
    </div>
  );
}
