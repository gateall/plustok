import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import clsx from 'clsx';
import { controlDisabledClass, fieldHintId, focusRingClass, touchTargetClass } from './fieldControl';

export type SwitchProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'type' | 'role'> & {
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  label: ReactNode;
  hint?: string;
};

const Switch = forwardRef<HTMLButtonElement, SwitchProps>(function Switch(
  { checked, onCheckedChange, label, hint, className, disabled, id, ...rest },
  ref,
) {
  const switchId = id ?? rest.name;

  return (
    <div className={clsx('min-w-0', className)}>
      <div className="flex min-h-11 items-center justify-between gap-3 py-2">
        <span id={`${switchId}-label`} className="text-sm text-[var(--pt-color-text)]">
          {label}
        </span>
        <button
          ref={ref}
          id={switchId}
          type="button"
          role="switch"
          aria-checked={checked}
          aria-labelledby={`${switchId}-label`}
          aria-describedby={hint && switchId ? fieldHintId(switchId) : undefined}
          disabled={disabled}
          onClick={() => onCheckedChange(!checked)}
          className={clsx(
            'relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-250',
            focusRingClass,
            touchTargetClass,
            checked ? 'bg-[var(--pt-color-primary)]' : 'bg-[var(--pt-color-border)]',
            disabled && controlDisabledClass,
          )}
          {...rest}
        >
          <span
            aria-hidden
            className={clsx(
              'inline-block h-5 w-5 rounded-full bg-white shadow-[var(--pt-shadow-sm)] transition-transform duration-250',
              checked ? 'translate-x-6' : 'translate-x-1',
            )}
          />
        </button>
      </div>
      {hint ? (
        <p id={switchId ? fieldHintId(switchId) : undefined} className="text-sm text-[var(--pt-color-text-muted)]">
          {hint}
        </p>
      ) : null}
    </div>
  );
});

export default Switch;
