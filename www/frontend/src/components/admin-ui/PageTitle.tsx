import type { ReactNode } from 'react';
import clsx from 'clsx';

type PageTitleProps = {
  children: ReactNode;
  className?: string;
  as?: 'h1' | 'h2' | 'p';
};

/** Mobile-first page title — clamp typography from design tokens. */
export default function PageTitle({ children, className, as: Tag = 'h1' }: PageTitleProps) {
  return <Tag className={clsx('page-title', className)}>{children}</Tag>;
}
