import { useId, useState, type ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';
import clsx from 'clsx';

export type AccordionItem = {
  id: string;
  title: ReactNode;
  content: ReactNode;
  defaultOpen?: boolean;
};

type AccordionProps = {
  items: AccordionItem[];
  allowMultiple?: boolean;
  className?: string;
};

export default function Accordion({ items, allowMultiple = false, className }: AccordionProps) {
  const baseId = useId();
  const [openIds, setOpenIds] = useState<Set<string>>(
    () => new Set(items.filter((i) => i.defaultOpen).map((i) => i.id)),
  );

  const toggle = (id: string) => {
    setOpenIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else if (allowMultiple) {
        next.add(id);
      } else {
        return new Set([id]);
      }
      return next;
    });
  };

  return (
    <div className={clsx('min-w-0 divide-y divide-[var(--pt-color-border)] rounded-[var(--pt-radius-lg)] border border-[var(--pt-color-border)] bg-[var(--pt-color-surface)]', className)}>
      {items.map((item) => {
        const open = openIds.has(item.id);
        const panelId = `${baseId}-panel-${item.id}`;
        const triggerId = `${baseId}-trigger-${item.id}`;

        return (
          <div key={item.id} className="min-w-0">
            <button
              type="button"
              id={triggerId}
              aria-expanded={open}
              aria-controls={panelId}
              onClick={() => toggle(item.id)}
              className="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-[var(--pt-color-text)]"
            >
              <span className="min-w-0 truncate">{item.title}</span>
              <ChevronDown
                className={clsx('h-5 w-5 shrink-0 text-[var(--pt-color-text-muted)] transition-transform duration-250', open && 'rotate-180')}
                aria-hidden
              />
            </button>
            {open ? (
              <div id={panelId} role="region" aria-labelledby={triggerId} className="px-4 pb-4 text-sm text-[var(--pt-color-text-muted)]">
                {item.content}
              </div>
            ) : null}
          </div>
        );
      })}
    </div>
  );
}
