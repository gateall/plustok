import { forwardRef, type InputHTMLAttributes, type ReactNode } from 'react';
import clsx from 'clsx';
import { controlDisabledClass, fieldDescribedBy, fieldErrorId, fieldHintId, focusRingClass } from './fieldControl';

export type RadioProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: ReactNode;
  hint?: string;
  error?: string;
};

const Radio = forwardRef<HTMLInputElement, RadioProps>(function Radio(
  { label, hint, error, className, id, disabled, ...rest },
  ref,
) {
  const inputId = id ?? `${rest.name ?? 'radio'}-${rest.value}`;
  const describedBy = inputId ? fieldDescribedBy(inputId, error, hint) : undefined;

  return (
    <div className={clsx('min-w-0', className)}>
      <label
        htmlFor={inputId}
        className={clsx(
          'inline-flex min-h-11 cursor-pointer items-start gap-3 py-2',
          disabled && controlDisabledClass,
        )}
      >
        <input
          ref={ref}
          id={inputId}
          type="radio"
          disabled={disabled}
          className={clsx(
            'mt-0.5 h-5 w-5 shrink-0',
            focusRingClass,
            'accent-[var(--pt-color-primary)]',
          )}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...rest}
        />
        <span className="min-w-0 pt-0.5 text-sm text-[var(--pt-color-text)]">{label}</span>
      </label>
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

export default Radio;
