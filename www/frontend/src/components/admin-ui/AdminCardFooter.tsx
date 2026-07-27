import type { ReactNode } from 'react';
import clsx from 'clsx';

type AdminCardFooterProps = {
  children: ReactNode;
  className?: string;
};

export default function AdminCardFooter({ children, className }: AdminCardFooterProps) {
  return <div className={clsx('min-w-0-flex flex flex-wrap gap-2 border-t border-slate-100 pt-3', className)}>{children}</div>;
}
