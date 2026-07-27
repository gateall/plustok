import type { ReactNode } from 'react';
import clsx from 'clsx';

type InfoRowProps = {
  label: string;
  value: ReactNode;
  className?: string;
  valueClassName?: string;
};

export default function InfoRow({ label, value, className, valueClassName }: InfoRowProps) {
  return (
    <div className={clsx('admin-info-row grid grid-cols-[minmax(4.5rem,5.5rem)_minmax(0,1fr)] items-start gap-3', className)}>
      <dt className="shrink-0 whitespace-nowrap text-sm font-medium text-slate-500">{label}</dt>
      <dd className={clsx('min-w-0 break-words-safe text-[15px] font-semibold leading-snug text-slate-900', valueClassName)}>
        {value}
      </dd>
    </div>
  );
}
