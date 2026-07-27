import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import Button from './Button';
import EmptyState from './EmptyState';
import type { AdminErrorPresentation } from '@/utils/adminErrorState';

type AdminErrorStateProps = AdminErrorPresentation & {
  onRetry?: () => void;
  className?: string;
  action?: ReactNode;
};

/** Status-aware admin error UI — never masks 500 as empty data. */
export default function AdminErrorState({
  title,
  description,
  showLogin,
  showRetry = true,
  onRetry,
  className,
  action,
}: AdminErrorStateProps) {
  const defaultAction =
    action ??
    (showLogin ? (
      <Link to="/login">
        <Button variant="primary">로그인</Button>
      </Link>
    ) : showRetry && onRetry ? (
      <Button variant="primary" onClick={onRetry}>
        다시 시도
      </Button>
    ) : undefined);

  return (
    <EmptyState
      title={title}
      description={description}
      action={defaultAction}
      className={clsx('admin-error-state', className)}
    />
  );
}
