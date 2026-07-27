import { Link } from 'react-router-dom';
import type { ContractItem } from '../../types/contract.types';
import ContractStatusBadge from './ContractStatusBadge';
import PaymentStatusBadge from './PaymentStatusBadge';
import { formatContractCurrency, formatContractDate } from '../../utils/formatContract';

export default function ContractTable({ contracts }: { contracts: ContractItem[] }) {
  return (
    <div className="admin-contracts-table admin-desktop-table overflow-hidden rounded-xl border border-[var(--pt-color-border)] bg-white shadow-sm">
      <div className="table-scroll w-full overflow-x-auto">
        <table className="w-full text-left text-sm text-slate-600">
          <thead className="bg-slate-50 text-slate-700">
            <tr>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">계약번호</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">제목</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">고객</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">상품</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-right">계약금액</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-right">미수금</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">계약상태</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">결제</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">기간</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">계약일</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--pt-color-border)]">
            {contracts.map((c) => (
              <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">
                  <Link to={`/admin/contracts/${c.id}`} className="hover:text-indigo-600">
                    {c.contractNo}
                  </Link>
                </td>
                <td className="max-w-[200px] truncate px-4 py-3 font-medium text-slate-900">
                  <Link to={`/admin/contracts/${c.id}`} className="hover:text-indigo-600" title={c.title}>
                    {c.title}
                  </Link>
                </td>
                <td className="whitespace-nowrap px-4 py-3">{c.customerName ?? '-'}</td>
                <td className="max-w-[140px] truncate px-4 py-3" title={c.productName ?? ''}>
                  {c.productName ?? '-'}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-900">
                  {formatContractCurrency(c.totalAmount)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right">
                  {c.outstandingAmount > 0 ? (
                    <span className="font-medium text-red-600">
                      {formatContractCurrency(c.outstandingAmount)}
                    </span>
                  ) : (
                    <span className="text-slate-400">-</span>
                  )}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-center">
                  <ContractStatusBadge status={c.status} />
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-center">
                  <PaymentStatusBadge contract={c} />
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-xs text-slate-500">
                  {formatContractDate(c.startDate)} ~ {formatContractDate(c.endDate)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-slate-500">
                  {formatContractDate(c.contractedAt)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
