import clsx from 'clsx';

import StatCard from '@/components/common/StatCard';
import type { SiteIntegrationFilter, SiteListSummary } from '@/types/site.types';

type SiteStatCardsProps = {
  summary: SiteListSummary;
  selected: SiteIntegrationFilter;
  onSelect: (integration: SiteIntegrationFilter) => void;
};

const CARDS: { key: SiteIntegrationFilter; label: string; field: keyof SiteListSummary }[] = [
  { key: '', label: '전체', field: 'total' },
  { key: 'healthy', label: '정상연동', field: 'healthy' },
  { key: 'needs_check', label: '점검필요', field: 'needsCheck' },
  { key: 'inactive', label: '비활성', field: 'inactive' },
];

export default function SiteStatCards({ summary, selected, onSelect }: SiteStatCardsProps) {
  return (
    <div className="admin-sites-stats mb-4 grid min-w-0 grid-cols-2 gap-3 lg:grid-cols-4">
      {CARDS.map(({ key, label, field }) => {
        const isSelected = selected === key;
        return (
          <button
            key={key || 'all'}
            type="button"
            onClick={() => onSelect(key)}
            className={clsx(
              'min-w-0 rounded-xl text-left transition-shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--pt-color-primary)]',
              isSelected && 'ring-2 ring-[var(--pt-color-primary)] ring-offset-1',
            )}
            aria-pressed={isSelected}
          >
            <StatCard label={label} value={summary[field].toLocaleString('ko-KR')} />
          </button>
        );
      })}
    </div>
  );
}
