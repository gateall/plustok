import type { ReactNode } from 'react';
import clsx from 'clsx';

type BottomActionProps = {
  children: ReactNode;
  className?: string;
};

export default function BottomAction({ children, className }: BottomActionProps) {
  return (
    <div
      className={clsx(
        'sticky bottom-0 z-20 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur',
        'pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] sm:-mx-5 sm:px-5 md:static md:mx-0 md:border-0 md:bg-transparent md:p-0',
        className,
      )}
    >
      {children}
    </div>
  );
}
