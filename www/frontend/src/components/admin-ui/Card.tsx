import type { ReactNode } from 'react';
import clsx from 'clsx';

type CardProps = {
  children: ReactNode;
  className?: string;
  padding?: 'none' | 'sm' | 'md';
  as?: 'article' | 'div' | 'section';
};

/** Generic card shell — wraps admin-card pattern for Sprint 3.2 prep. */
export default function Card({ children, className, padding = 'md', as: Tag = 'article' }: CardProps) {
  return (
    <Tag
      className={clsx(
        'admin-card min-w-0',
        padding === 'none' && 'p-0',
        padding === 'sm' && 'p-3',
        className,
      )}
    >
      {children}
    </Tag>
  );
}
