import type { ReactNode } from 'react';
import clsx from 'clsx';

type DialogProps = {
  open: boolean;
  title?: ReactNode;
  description?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  onClose?: () => void;
  className?: string;
};

export default function Dialog({ open, title, description, children, footer, onClose, className }: DialogProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" role="dialog" aria-modal="true">
      <button type="button" className="absolute inset-0" aria-label="닫기" onClick={onClose} />
      <div className={clsx('relative z-10 w-full max-w-lg rounded-3xl bg-white p-5 shadow-2xl sm:p-6', className)}>
        {title ? <h2 className="text-lg font-semibold text-slate-900">{title}</h2> : null}
        {description ? <p className="mt-1 text-sm text-slate-500">{description}</p> : null}
        <div className="mt-4">{children}</div>
        {footer ? <div className="mt-4 flex flex-wrap gap-2">{footer}</div> : null}
      </div>
    </div>
  );
}
