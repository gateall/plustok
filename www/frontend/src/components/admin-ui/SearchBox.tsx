import { forwardRef, type FormEvent } from 'react';
import { Search } from 'lucide-react';
import clsx from 'clsx';
import { focusRingClass } from './fieldControl';

type SearchBoxProps = {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  label?: string;
  className?: string;
  onSubmit?: () => void;
  name?: string;
};

const SearchBox = forwardRef<HTMLInputElement, SearchBoxProps>(function SearchBox(
  { value, onChange, placeholder = '검색', label = '검색', className, onSubmit, name = 'q' },
  ref,
) {
  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit?.();
  };

  return (
    <form onSubmit={handleSubmit} className={clsx('relative min-w-0 flex-1', className)}>
      <Search
        className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--pt-color-text-muted)]"
        aria-hidden
      />
      <input
        ref={ref}
        type="search"
        name={name}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className={clsx(
          'search-box-input h-12 w-full min-w-0 rounded-[var(--pt-radius-md)] border border-[var(--pt-border-color)] bg-[var(--pt-color-surface)] py-2 pl-10 pr-3 text-base text-[var(--pt-color-text)] placeholder:text-[var(--pt-color-text-muted)]',
          focusRingClass,
        )}
        aria-label={label}
      />
    </form>
  );
});

export default SearchBox;
