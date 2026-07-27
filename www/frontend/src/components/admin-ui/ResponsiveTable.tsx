import type { ReactNode } from 'react';
import clsx from 'clsx';

type ResponsiveTableColumn<T> = {
  key: string;
  label: string;
  className?: string;
  render: (item: T) => ReactNode;
};

type ResponsiveTableProps<T> = {
  items: T[];
  columns: ResponsiveTableColumn<T>[];
  getKey: (item: T) => string;
  renderCard: (item: T) => ReactNode;
  cardListLabel: string;
  tableLabel: string;
  empty?: ReactNode;
  tableClassName?: string;
};

export default function ResponsiveTable<T>({
  items,
  columns,
  getKey,
  renderCard,
  cardListLabel,
  tableLabel,
  empty,
  tableClassName,
}: ResponsiveTableProps<T>) {
  if (items.length === 0) {
    return <>{empty ?? null}</>;
  }

  return (
    <>
      <ul className="admin-mobile-cards admin-card-list min-w-0 space-y-4 min-[1024px]:hidden" aria-label={cardListLabel}>
        {items.map((item) => (
          <li key={getKey(item)} className="min-w-0">
            {renderCard(item)}
          </li>
        ))}
      </ul>

      <div
        className={clsx(
          'admin-desktop-table table-scroll hidden min-w-0 overflow-hidden rounded-[var(--pt-radius-2xl)] border border-[var(--pt-border-color)] bg-[var(--pt-color-surface)] shadow-[var(--pt-shadow-sm)] min-[1024px]:block',
          tableClassName,
        )}
      >
        <table className="table-compact w-full min-w-[640px] border-collapse" aria-label={tableLabel}>
          <thead>
            <tr className="border-b border-[var(--pt-border-color)] bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              {columns.map((column) => (
                <th key={column.key} className={clsx('min-w-0 px-3 py-2', column.className)}>
                  {column.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr
                key={getKey(item)}
                className="border-b border-slate-100 align-top transition-colors duration-250 last:border-0 hover:bg-slate-50/70"
              >
                {columns.map((column) => (
                  <td key={column.key} className={clsx('min-w-0 px-3 py-2 text-sm text-slate-700', column.className)}>
                    {column.render(item)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}
