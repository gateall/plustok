import { forwardRef, type TextareaHTMLAttributes } from 'react';
import clsx from 'clsx';
import { fieldDescribedBy, fieldErrorId, fieldHintId, focusRingClass } from './fieldControl';

export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label?: string;
  hint?: string;
  error?: string;
  fullWidth?: boolean;
};

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { label, hint, error, fullWidth, className, id, rows = 4, ...rest },
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
      <textarea
        ref={ref}
        id={inputId}
        rows={rows}
        className={clsx(
          'min-h-[6rem] min-w-0 rounded-[var(--pt-radius-md)] border bg-[var(--pt-color-surface)] px-3 py-2 text-base text-[var(--pt-color-text)]',
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

export default Textarea;
