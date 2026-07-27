import type { ReactNode } from 'react';
import clsx from 'clsx';

type SidebarProps = {
  brand?: ReactNode;
  currentLabel?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  className?: string;
};

export default function Sidebar({ brand, currentLabel, children, footer, className }: SidebarProps) {
  return (
    <aside className={clsx('hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex', className)}>
      {brand ? (
        <div className="border-b border-slate-200 px-5 py-4">
          {brand}
          {currentLabel ? <p className="mt-1 ellipsis text-sm text-slate-500">{currentLabel}</p> : null}
        </div>
      ) : null}
      <div className="flex-1 overflow-y-auto p-3">{children}</div>
      {footer ? <div className="border-t border-slate-200 p-3">{footer}</div> : null}
    </aside>
  );
}
