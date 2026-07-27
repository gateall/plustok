import type { LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';
import clsx from 'clsx';

type QuickActionProps = {
  label: string;
  icon: LucideIcon;
  href?: string;
  to?: string;
  onClick?: () => void;
  disabled?: boolean;
  title?: string;
  /** Primary = indigo emphasis; default = neutral link tile. */
  variant?: 'primary' | 'default';
};

const focusRing =
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2';

function tileClass(disabled: boolean | undefined, variant: 'primary' | 'default', isButtonAction: boolean): string {
  const isPrimary = variant === 'primary' || isButtonAction;
  return clsx(
    'quick-action-btn inline-flex min-h-12 flex-col items-center justify-center gap-1 rounded-[var(--pt-radius-md)] border px-2 text-center text-sm font-medium transition-colors duration-250',
    focusRing,
    disabled
      ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400'
      : isPrimary
        ? 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
        : 'border-[var(--pt-border-color)] bg-[var(--pt-color-surface)] text-slate-700 hover:bg-slate-50',
  );
}

/** Single quick-action tile — 48px touch target, used in 3-col grids. */
export default function QuickAction({
  label,
  icon: Icon,
  href,
  to,
  onClick,
  disabled,
  title,
  variant = 'default',
}: QuickActionProps) {
  const isButtonAction = !href && !to && Boolean(onClick);
  const className = tileClass(disabled, variant, isButtonAction);

  if (to && !disabled) {
    return (
      <Link to={to} className={className} title={title}>
        <Icon className="h-4 w-4 shrink-0" aria-hidden />
        {label}
      </Link>
    );
  }

  if (href && !disabled) {
    return (
      <a href={href} className={className} title={title}>
        <Icon className="h-4 w-4 shrink-0" aria-hidden />
        {label}
      </a>
    );
  }

  return (
    <button type="button" onClick={onClick} disabled={disabled} className={className} title={title}>
      <Icon className="h-4 w-4 shrink-0" aria-hidden />
      {label}
    </button>
  );
}
