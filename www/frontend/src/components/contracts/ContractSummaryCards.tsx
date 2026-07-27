import type { ContractItem, ContractSummary } from '../../types/contract.types';
import { IN_PROGRESS_STATUSES } from '../../types/contract.types';
import StatCard from '../common/StatCard';
import { formatContractCurrency } from '../../utils/formatContract';

const EXPIRING_DAYS = 30;

function isExpiringSoon(contract: ContractItem): boolean {
  if (!contract.endDate) return false;
  if (!IN_PROGRESS_STATUSES.has(contract.status) && contract.status !== 'active') return false;
  const end = new Date(`${contract.endDate}T23:59:59`);
  const now = new Date();
  const diffDays = (end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
  return diffDays >= 0 && diffDays <= EXPIRING_DAYS;
}

export function computeContractSummary(
  items: ContractItem[],
  total: number,
  pageScoped: boolean,
): ContractSummary {
  const inProgress = items.filter((c) => IN_PROGRESS_STATUSES.has(c.status)).length;
  const expiringSoon = items.filter(isExpiringSoon).length;
  const outstanding = items.filter((c) => c.outstandingAmount > 0).length;

  return {
    total,
    inProgress,
    expiringSoon,
    outstanding,
    pageScoped,
  };
}

type ContractSummaryCardsProps = {
  summary: ContractSummary;
  outstandingSum: number;
};

export default function ContractSummaryCards({ summary, outstandingSum }: ContractSummaryCardsProps) {
  const pageHint = summary.pageScoped ? ' (현재 페이지)' : '';

  return (
    <div className="admin-contracts-summary mb-4 grid min-w-0 grid-cols-2 gap-3 lg:grid-cols-4">
      <StatCard label="전체" value={summary.total.toLocaleString('ko-KR')} hint={`건${pageHint}`} />
      <StatCard label="진행중" value={summary.inProgress.toLocaleString('ko-KR')} hint={`건${pageHint}`} />
      <StatCard
        label="만료예정"
        value={summary.expiringSoon.toLocaleString('ko-KR')}
        hint={`30일 이내${pageHint}`}
      />
      <StatCard
        label="미수금"
        value={formatContractCurrency(outstandingSum)}
        hint={`${summary.outstanding}건${pageHint}`}
      />
    </div>
  );
}
