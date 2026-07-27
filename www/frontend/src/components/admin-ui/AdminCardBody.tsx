import type { ReactNode } from 'react';
import clsx from 'clsx';

type AdminCardBodyProps = {
  children: ReactNode;
  className?: string;
};

export default function AdminCardBody({ children, className }: AdminCardBodyProps) {
  return <div className={clsx('space-y-3', className)}>{children}</div>;
}
