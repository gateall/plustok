import StatusBadge from '../admin-ui/StatusBadge';
import type { ContractItem } from '../../types/contract.types';
import type { PaymentStatusFilter } from '../../types/contract.types';

export function getPaymentStatus(contract: ContractItem): PaymentStatusFilter {
  const now = Date.now();
  const endMs = contract.endDate ? new Date(`${contract.endDate}T23:59:59`).getTime() : null;

  if (contract.outstandingAmount <= 0 && contract.paidAmount > 0) return 'paid';
  if (contract.paidAmount > 0 && contract.outstandingAmount > 0) return 'partial';
  if (contract.outstandingAmount > 0 && endMs !== null && endMs < now) return 'overdue';
  if (contract.outstandingAmount > 0) return 'unpaid';
  return 'unpaid';
}

const PAYMENT_LABELS: Record<Exclude<PaymentStatusFilter, ''>, string> = {
  paid: '완납',
  partial: '부분납',
  unpaid: '미납',
  overdue: '연체',
};

const PAYMENT_TONE: Record<Exclude<PaymentStatusFilter, ''>, 'neutral' | 'info' | 'warning' | 'success' | 'danger'> = {
  paid: 'success',
  partial: 'warning',
  unpaid: 'neutral',
  overdue: 'danger',
};

export default function PaymentStatusBadge({ contract }: { contract: ContractItem }) {
  const status = getPaymentStatus(contract);
  if (status === '') return null;
  return (
    <StatusBadge
      label={PAYMENT_LABELS[status]}
      tone={PAYMENT_TONE[status]}
    />
  );
}

export function filterByPaymentStatus(
  items: ContractItem[],
  paymentStatus: PaymentStatusFilter | undefined,
): ContractItem[] {
  if (!paymentStatus) return items;
  return items.filter((item) => getPaymentStatus(item) === paymentStatus);
}
