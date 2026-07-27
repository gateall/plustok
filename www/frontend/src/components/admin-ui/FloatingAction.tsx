import type { ReactNode } from 'react';
import clsx from 'clsx';

type FloatingActionProps = {
  children: ReactNode;
  className?: string;
};

export default function FloatingAction({ children, className }: FloatingActionProps) {
  return (
    <div
      className={clsx(
        'fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] right-4 z-30 lg:hidden',
        className,
      )}
    >
      {children}
    </div>
  );
}
