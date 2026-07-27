import StatusBadge from '../admin-ui/StatusBadge';
import { CONTRACT_STATUS_LABELS } from '../../types/contract.types';

const STATUS_TONE: Record<string, 'neutral' | 'info' | 'warning' | 'success' | 'danger'> = {
  draft: 'neutral',
  review: 'info',
  sent: 'info',
  signature_pending: 'warning',
  signed: 'success',
  active: 'success',
  on_hold: 'warning',
  completed: 'neutral',
  cancelled: 'danger',
  expired: 'danger',
  archived: 'neutral',
};

export default function ContractStatusBadge({ status }: { status: string }) {
  return (
    <StatusBadge
      label={CONTRACT_STATUS_LABELS[status] ?? status}
      tone={STATUS_TONE[status] ?? 'neutral'}
    />
  );
}
