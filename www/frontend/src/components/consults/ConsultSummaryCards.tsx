import type { ConsultSummaryStats } from '@/utils/consultSummary';

type ConsultSummaryCardsProps = {
  stats: ConsultSummaryStats;
};

const CARDS: { key: keyof ConsultSummaryStats; label: string }[] = [
  { key: 'total', label: '전체 상담' },
  { key: 'today', label: '오늘 접수' },
  { key: 'newCount', label: '신규' },
  { key: 'inProgress', label: '상담중' },
  { key: 'waiting', label: '답변대기' },
  { key: 'completed', label: '완료' },
];

export default function ConsultSummaryCards({ stats }: ConsultSummaryCardsProps) {
  return (
    <div
      className="consult-summary-cards grid grid-cols-2 gap-2 min-[768px]:grid-cols-3 min-[1024px]:grid-cols-6 min-[1024px]:gap-3"
      aria-label="상담 현황 요약"
    >
      {CARDS.map(({ key, label }) => (
        <div
          key={key}
          className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 min-[1024px]:px-4 min-[1024px]:py-3"
        >
          <p className="text-xs font-medium text-slate-500">{label}</p>
          <p className="mt-0.5 text-xl font-bold tabular-nums text-slate-900 min-[1024px]:text-2xl">
            {stats[key]}
          </p>
        </div>
      ))}
    </div>
  );
}
