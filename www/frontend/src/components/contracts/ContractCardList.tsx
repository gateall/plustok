import { Link } from 'react-router-dom';
import { Calendar, FileText, User, Wallet } from 'lucide-react';
import type { ContractItem } from '../../types/contract.types';
import ContractStatusBadge from './ContractStatusBadge';
import PaymentStatusBadge from './PaymentStatusBadge';
import { formatContractCurrency, formatContractDate } from '../../utils/formatContract';

function ContractCard({ contract }: { contract: ContractItem }) {
  return (
    <article className="admin-contract-card flex flex-col gap-3 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <Link
            to={`/admin/contracts/${contract.id}`}
            className="block truncate font-bold text-slate-900 hover:text-indigo-600"
          >
            {contract.title}
          </Link>
          <p className="mt-0.5 truncate text-xs text-slate-500">{contract.contractNo}</p>
        </div>
        <div className="flex shrink-0 flex-col items-end gap-1">
          <ContractStatusBadge status={contract.status} />
          <PaymentStatusBadge contract={contract} />
        </div>
      </div>

      <div className="flex flex-col gap-1.5 text-sm text-slate-600">
        <div className="flex items-center gap-2 truncate">
          <User size={14} className="shrink-0 text-slate-400" aria-hidden />
          <span className="truncate">{contract.customerName ?? '고객 미지정'}</span>
        </div>
        {contract.productName ? (
          <div className="flex items-center gap-2 truncate">
            <FileText size={14} className="shrink-0 text-slate-400" aria-hidden />
            <span className="truncate">{contract.productName}</span>
          </div>
        ) : null}
        <div className="flex items-center gap-2">
          <Wallet size={14} className="shrink-0 text-slate-400" aria-hidden />
          <span>
            {formatContractCurrency(contract.totalAmount)}
            {contract.outstandingAmount > 0 ? (
              <span className="ml-1 text-red-600">
                (미수 {formatContractCurrency(contract.outstandingAmount)})
              </span>
            ) : null}
          </span>
        </div>
        <div className="flex items-center gap-2 truncate text-xs text-slate-500">
          <Calendar size={14} className="shrink-0 text-slate-400" aria-hidden />
          <span>
            {formatContractDate(contract.startDate)} ~ {formatContractDate(contract.endDate)}
          </span>
        </div>
      </div>

      <div className="flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <span className="text-xs text-slate-400">계약일 {formatContractDate(contract.contractedAt)}</span>
        <Link
          to={`/admin/contracts/${contract.id}`}
          className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
        >
          상세
        </Link>
      </div>
    </article>
  );
}

export default function ContractCardList({ contracts }: { contracts: ContractItem[] }) {
  return (
    <div className="admin-contracts-mobile admin-mobile-list">
      {contracts.map((c) => (
        <ContractCard key={c.id} contract={c} />
      ))}
    </div>
  );
}
