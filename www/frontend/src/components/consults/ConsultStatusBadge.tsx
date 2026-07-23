import clsx from 'clsx';
import { CONSULT_STATUS_LABELS } from '@/types/consult.types';

type ConsultStatusBadgeProps = {
  status: string;
  className?: string;
};

const STATUS_STYLES: Record<string, string> = {
  new: 'bg-blue-100 text-blue-800',
  open: 'bg-amber-100 text-amber-800',
  active: 'bg-emerald-100 text-emerald-800',
  closed: 'bg-slate-100 text-slate-600',
  pending: 'bg-amber-100 text-amber-800',
  in_progress: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-slate-100 text-slate-600',
  cancelled: 'bg-red-100 text-red-700',
};

export default function ConsultStatusBadge({ status, className }: ConsultStatusBadgeProps) {
  const normalized = status.toLowerCase();
  const label = CONSULT_STATUS_LABELS[normalized] ?? CONSULT_STATUS_LABELS[status] ?? status;
  const style = STATUS_STYLES[normalized] ?? STATUS_STYLES[status] ?? 'bg-slate-100 text-slate-700';

  return (
    <span
      className={clsx(
        'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium',
        style,
        className,
      )}
    >
      {label}
    </span>
  );
}
