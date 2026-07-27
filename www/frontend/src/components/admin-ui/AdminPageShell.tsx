import type { ReactNode } from 'react';
import clsx from 'clsx';
import PageHeader from '@/components/admin-ui/PageHeader';
type AdminPageShellProps = {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
};

/** PageHeader + mobile-first content gutter (360px-safe). */
export default function AdminPageShell({ title, description, children, className }: AdminPageShellProps) {
  return (
    <div className={clsx('admin-page-shell min-w-0 overflow-x-hidden py-4 md:py-6', className)}>
      <PageHeader title={title} description={description} />
      <div className="min-w-0">{children}</div>
    </div>
  );
}
