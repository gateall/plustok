import type { ElementType, ReactNode } from 'react';
import clsx from 'clsx';

type AdminCardProps = {
  children: ReactNode;
  className?: string;
  interactive?: boolean;
  as?: ElementType;
};

/** Standard admin card shell — 14px radius, 16–20px padding, min-width 0 (360px-safe). */
export default function AdminCard({ children, className, interactive = false, as: Tag = 'article' }: AdminCardProps) {
  return (
    <Tag
      className={clsx(
        'admin-card min-w-0 rounded-[14px] transition-[box-shadow,border-color] duration-250',
        interactive && 'hover:shadow-md',
        className,
      )}
    >
      {children}
    </Tag>
  );
}
