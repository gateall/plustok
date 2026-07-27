import type { ReactNode } from 'react';
import clsx from 'clsx';
import EmptyState from '@/components/admin-ui/EmptyState';
import { Button } from '@/components/admin-ui';

export type EmptyViewVariant = 'empty' | 'error';

type EmptyViewProps = {
  title: string;
  description?: string;
  variant?: EmptyViewVariant;
  action?: ReactNode;
  onRetry?: () => void;
  className?: string;
};

/** List-page empty / error placeholder — wraps admin EmptyState. */
export default function EmptyView({
  title,
  description,
  variant = 'empty',
  action,
  onRetry,
  className,
}: EmptyViewProps) {
  const resolvedDescription =
    description ??
    (variant === 'error' ? '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.' : undefined);

  const resolvedAction =
    action ??
    (onRetry ? (
      <Button type="button" variant="secondary" onClick={onRetry}>
        다시 시도
      </Button>
    ) : undefined);

  return (
    <EmptyState
      title={title}
      description={resolvedDescription}
      action={resolvedAction}
      className={clsx(variant === 'error' && 'placeholder-error', className)}
    />
  );
}
