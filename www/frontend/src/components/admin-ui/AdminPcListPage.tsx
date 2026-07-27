import { useMemo, useState, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import clsx from 'clsx';
import {
  AdminListCards,
  AdminPageShell,
  EmptyView,
  FilterBar,
  SearchBar,
} from '@/components/admin-ui';

export type AdminPcListColumn<T> = {
  key: string;
  label: string;
  className?: string;
  render: (item: T) => ReactNode;
};

export type AdminPcListRow = {
  id: string | number;
  detailTo?: string;
};

type AdminPcListPageProps<T extends AdminPcListRow> = {
  title: string;
  description: string;
  items: T[];
  columns: AdminPcListColumn<T>[];
  renderCard: (item: T) => ReactNode;
  cardLayout?: 'list' | 'grid';
  emptyTitle?: string;
  emptyDescription?: string;
  searchable?: boolean;
  searchPlaceholder?: string;
  searchQuery?: string;
  onSearchQueryChange?: (query: string) => void;
  clientSideSearch?: boolean;
  filterItems?: (items: T[], query: string) => T[];
  tableLabel?: string;
  cardListLabel?: string;
  onRowClick?: (item: T) => void;
  toolbar?: ReactNode;
  footer?: ReactNode;
};

export default function AdminPcListPage<T extends AdminPcListRow>({
  title,
  description,
  items,
  columns,
  renderCard,
  cardLayout = 'list',
  emptyTitle = '데이터 없음',
  emptyDescription = '표시할 항목이 없습니다.',
  searchable = false,
  searchPlaceholder = '검색…',
  searchQuery: controlledQuery,
  onSearchQueryChange,
  clientSideSearch = true,
  filterItems,
  tableLabel,
  cardListLabel,
  onRowClick,
  toolbar,
  footer,
}: AdminPcListPageProps<T>) {
  const navigate = useNavigate();
  const [localQuery, setLocalQuery] = useState('');
  const query = controlledQuery ?? localQuery;

  const setQuery = (value: string) => {
    if (onSearchQueryChange) {
      onSearchQueryChange(value);
    } else {
      setLocalQuery(value);
    }
  };

  const filtered = useMemo(() => {
    if (!searchable || !clientSideSearch) return items;
    if (filterItems) return filterItems(items, query);
    const q = query.trim().toLowerCase();
    if (!q) return items;
    return items.filter((item) => JSON.stringify(item).toLowerCase().includes(q));
  }, [items, query, searchable, clientSideSearch, filterItems]);

  const handleRowActivate = (item: T) => {
    if (onRowClick) {
      onRowClick(item);
      return;
    }
    if (item.detailTo) {
      navigate(item.detailTo);
    }
  };

  const resolvedTableLabel = tableLabel ?? `${title} 테이블`;
  const resolvedCardLabel = cardListLabel ?? `${title} 목록`;

  return (
    <AdminPageShell title={title} description={description} className="admin-pc-page">
      {searchable || toolbar ? (
        <FilterBar aria-label={`${title} 필터`} className="mb-4">
          {searchable ? (
            <SearchBar
              value={query}
              onChange={setQuery}
              placeholder={searchPlaceholder}
              label={`${title} 검색`}
              className="min-w-[12rem] flex-1"
            />
          ) : null}
          {toolbar}
        </FilterBar>
      ) : null}

      {items.length === 0 ? (
        <EmptyView title={emptyTitle} description={emptyDescription} />
      ) : filtered.length === 0 ? (
        <EmptyView title="검색 결과 없음" description={`"${query}" 와 일치하는 항목이 없습니다.`} />
      ) : (
        <>
          <AdminListCards label={resolvedCardLabel} layout={cardLayout} className="admin-pc-mobile-cards">
            {filtered.map((item) => (
              <li key={item.id} className="min-w-0 list-none">
                {renderCard(item)}
              </li>
            ))}
          </AdminListCards>

          <div className="admin-pc-table-wrap admin-pc-table min-w-0">
            <div className="admin-pc-table__scroll overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <table className="w-full min-w-0 table-fixed border-collapse text-left" aria-label={resolvedTableLabel}>
                <thead className="admin-pc-table__header">
                  <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {columns.map((column) => (
                      <th key={column.key} scope="col" className={clsx('px-3 py-2.5', column.className)}>
                        {column.label}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((item) => (
                    <tr
                      key={item.id}
                      className="admin-pc-table__row border-b border-slate-100 align-middle last:border-0"
                      tabIndex={item.detailTo || onRowClick ? 0 : undefined}
                      role={item.detailTo || onRowClick ? 'button' : undefined}
                      onClick={() => handleRowActivate(item)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault();
                          handleRowActivate(item);
                        }
                      }}
                    >
                      {columns.map((column) => (
                        <td key={column.key} className={clsx('px-3 py-2.5 text-sm text-slate-700', column.className)}>
                          {column.render(item)}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}

      {footer}
    </AdminPageShell>
  );
}
