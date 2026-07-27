import { forwardRef, type SelectHTMLAttributes } from 'react';
import clsx from 'clsx';
import { fieldDescribedBy, fieldErrorId, fieldHintId, focusRingClass } from './fieldControl';

export type SelectOption = {
  value: string;
  label: string;
  disabled?: boolean;
};

export type SelectProps = Omit<SelectHTMLAttributes<HTMLSelectElement>, 'children'> & {
  label?: string;
  hint?: string;
  error?: string;
  fullWidth?: boolean;
  options: SelectOption[];
  placeholder?: string;
};

const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { label, hint, error, fullWidth, options, placeholder, className, id, ...rest },
  ref,
) {
  const selectId = id ?? rest.name;
  const describedBy = selectId ? fieldDescribedBy(selectId, error, hint) : undefined;

  return (
    <div className={clsx('min-w-0', fullWidth && 'w-full')}>
      {label ? (
        <label htmlFor={selectId} className="mb-1 block text-sm font-medium text-[var(--pt-color-text)]">
          {label}
        </label>
      ) : null}
      <select
        ref={ref}
        id={selectId}
        className={clsx(
          'h-12 min-w-0 rounded-[var(--pt-radius-md)] border bg-[var(--pt-color-surface)] px-3 text-base text-[var(--pt-color-text)]',
          focusRingClass,
          error ? 'border-[var(--pt-color-error)]' : 'border-[var(--pt-color-border)]',
          fullWidth && 'w-full',
          className,
        )}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        {...rest}
      >
        {placeholder ? (
          <option value="" disabled>
            {placeholder}
          </option>
        ) : null}
        {options.map((opt) => (
          <option key={opt.value} value={opt.value} disabled={opt.disabled}>
            {opt.label}
          </option>
        ))}
      </select>
      {error ? (
        <p id={selectId ? fieldErrorId(selectId) : undefined} className="mt-1 text-sm text-[var(--pt-color-error)]">
          {error}
        </p>
      ) : null}
      {!error && hint ? (
        <p id={selectId ? fieldHintId(selectId) : undefined} className="mt-1 text-sm text-[var(--pt-color-text-muted)]">
          {hint}
        </p>
      ) : null}
    </div>
  );
});

export default Select;
