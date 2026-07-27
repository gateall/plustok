import type { ReactNode } from 'react';
import { Loader2 } from 'lucide-react';
import clsx from 'clsx';
import { Alert, Button, Skeleton } from '@/components/admin-ui';

type AiLoadingProps = {
  label: string;
  /** Prefer skeleton for longer panels; spinner for compact cards. */
  variant?: 'spinner' | 'skeleton';
  lines?: number;
  className?: string;
};

/** Shared AI feature loading block — Mobile First. */
export function AiLoadingState({
  label,
  variant = 'spinner',
  lines = 3,
  className,
}: AiLoadingProps) {
  if (variant === 'skeleton') {
    return (
      <div
        className={clsx('space-y-2 rounded-xl bg-slate-50 px-3 py-3', className)}
        role="status"
        aria-live="polite"
        aria-label={label}
      >
        {Array.from({ length: lines }).map((_, i) => (
          <Skeleton
            key={i}
            className={clsx('h-3', i === lines - 1 ? 'w-2/3' : 'w-full')}
          />
        ))}
        <p className="pt-1 text-xs text-slate-500">{label}</p>
      </div>
    );
  }

  return (
    <div
      className={clsx(
        'flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-600',
        className,
      )}
      role="status"
      aria-live="polite"
    >
      <Loader2 className="h-4 w-4 shrink-0 animate-spin text-indigo-500" aria-hidden />
      {label}
    </div>
  );
}

type AiEmptyProps = {
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
};

/** Shared AI feature empty / CTA block. */
export function AiEmptyState({ title, description, action, className }: AiEmptyProps) {
  return (
    <div
      className={clsx(
        'rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center',
        className,
      )}
    >
      <p className="text-sm font-medium text-slate-800">{title}</p>
      {description ? (
        <p className="mt-1 text-xs leading-relaxed text-slate-500">{description}</p>
      ) : null}
      {action ? <div className="mt-4 flex justify-center">{action}</div> : null}
    </div>
  );
}

type AiErrorProps = {
  title: string;
  retryLabel: string;
  onRetry: () => void;
  /** Optional prior content kept visible above the error. */
  children?: ReactNode;
  className?: string;
};

/** Shared AI feature error + retry. Touch target ≥ 40px. */
export function AiErrorState({ title, retryLabel, onRetry, children, className }: AiErrorProps) {
  return (
    <div className={clsx('space-y-3', className)}>
      {children}
      <Alert tone="error" title={title}>
        <Button
          type="button"
          variant="secondary"
          className="!min-h-10 mt-2 px-3 text-xs"
          onClick={onRetry}
        >
          {retryLabel}
        </Button>
      </Alert>
    </div>
  );
}
