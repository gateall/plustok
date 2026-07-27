import { forwardRef, type InputHTMLAttributes } from 'react';
import clsx from 'clsx';
import { fieldDescribedBy, fieldErrorId, fieldHintId, focusRingClass } from './fieldControl';

export type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  hint?: string;
  error?: string;
  fullWidth?: boolean;
};

const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { label, hint, error, fullWidth, className, id, ...rest },
  ref,
) {
  const inputId = id ?? rest.name;
  const describedBy = inputId ? fieldDescribedBy(inputId, error, hint) : undefined;

  return (
    <div className={clsx('min-w-0', fullWidth && 'w-full')}>
      {label ? (
        <label htmlFor={inputId} className="mb-1 block text-sm font-medium text-[var(--pt-color-text)]">
          {label}
        </label>
      ) : null}
      <input
        ref={ref}
        id={inputId}
        className={clsx(
          'h-12 min-w-0 rounded-[var(--pt-radius-md)] border bg-[var(--pt-color-surface)] px-3 text-base text-[var(--pt-color-text)]',
          'placeholder:text-[var(--pt-color-text-muted)]',
          focusRingClass,
          error ? 'border-[var(--pt-color-error)]' : 'border-[var(--pt-color-border)]',
          fullWidth && 'w-full',
          className,
        )}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        {...rest}
      />
      {error ? (
        <p id={inputId ? fieldErrorId(inputId) : undefined} className="mt-1 text-sm text-[var(--pt-color-error)]">
          {error}
        </p>
      ) : null}
      {!error && hint ? (
        <p id={inputId ? fieldHintId(inputId) : undefined} className="mt-1 text-sm text-[var(--pt-color-text-muted)]">
          {hint}
        </p>
      ) : null}
    </div>
  );
});

export default Input;
