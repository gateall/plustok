import { useId, type ReactNode } from 'react';
import clsx from 'clsx';

export type TabItem = {
  id: string;
  label: ReactNode;
  disabled?: boolean;
};

type TabsProps = {
  items: TabItem[];
  activeId: string;
  onChange: (id: string) => void;
  className?: string;
  variant?: 'underline' | 'pill';
};

export default function Tabs({ items, activeId, onChange, className, variant = 'underline' }: TabsProps) {
  const baseId = useId();

  return (
    <div className={clsx('min-w-0', className)} role="tablist" aria-orientation="horizontal">
      <div
        className={clsx(
          'flex gap-1 overflow-x-auto',
          variant === 'underline' && 'border-b border-[var(--pt-color-border)]',
        )}
      >
        {items.map((item) => {
          const selected = item.id === activeId;
          const tabId = `${baseId}-${item.id}`;

          return (
            <button
              key={item.id}
              type="button"
              role="tab"
              id={tabId}
              aria-selected={selected}
              disabled={item.disabled}
              onClick={() => onChange(item.id)}
              className={clsx(
                'min-h-12 shrink-0 px-4 text-sm font-semibold transition-colors duration-250',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--pt-color-primary)]',
                variant === 'underline' &&
                  (selected
                    ? 'border-b-2 border-[var(--pt-color-primary)] text-[var(--pt-color-primary)]'
                    : 'border-b-2 border-transparent text-[var(--pt-color-text-muted)] hover:text-[var(--pt-color-text)]'),
                variant === 'pill' &&
                  (selected
                    ? 'rounded-full bg-[var(--pt-color-primary-muted)] text-[var(--pt-color-primary)]'
                    : 'rounded-full text-[var(--pt-color-text-muted)] hover:bg-[var(--pt-color-surface-muted)]'),
                item.disabled && 'cursor-not-allowed opacity-50',
              )}
            >
              {item.label}
            </button>
          );
        })}
      </div>
    </div>
  );
}
