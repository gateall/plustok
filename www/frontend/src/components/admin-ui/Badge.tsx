import clsx from 'clsx';

type BadgeTone = 'neutral' | 'info' | 'warning' | 'success' | 'danger';

type BadgeProps = {
  label: string;
  tone?: BadgeTone;
  className?: string;
};

const TONE_CLASS: Record<BadgeTone, string> = {
  neutral: 'bg-slate-100 text-slate-700',
  info: 'bg-sky-100 text-sky-700',
  warning: 'bg-amber-100 text-amber-800',
  success: 'bg-emerald-100 text-emerald-700',
  danger: 'bg-red-100 text-red-700',
};

export default function Badge({ label, tone = 'neutral', className }: BadgeProps) {
  return (
    <span
      className={clsx(
        'admin-badge inline-flex min-h-7 shrink-0 items-center whitespace-nowrap rounded-full px-2.5 text-xs font-semibold',
        TONE_CLASS[tone],
        className,
      )}
    >
      {label}
    </span>
  );
}

export type { BadgeProps, BadgeTone };
