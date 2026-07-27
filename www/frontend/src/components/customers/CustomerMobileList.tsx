import type { CustomerItem } from '../../types/customer.types';
import { CUSTOMER_STATUS_LABELS } from '../../types/customer.types';
import StatusBadge from '../admin-ui/StatusBadge';
import { User, Phone, Mail, Clock, MessageCircle } from 'lucide-react';

function formatStandardDate(dateStr: string | null) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return new Intl.DateTimeFormat('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

function CustomerCard({ customer }: { customer: CustomerItem }) {
  return (
    <div className="flex flex-col gap-3 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-2 min-w-0">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
            <User size={20} />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <span className="font-bold text-slate-900 truncate">
                {customer.name}
              </span>
              <StatusBadge label={CUSTOMER_STATUS_LABELS[customer.status] || customer.status} tone={customer.status === 'new' ? 'info' : customer.status === 'active' ? 'success' : 'neutral'} />
            </div>
            <div className="mt-0.5 text-xs text-slate-500 truncate">
              {customer.companyName || '소속 없음'}
            </div>
          </div>
        </div>
      </div>
      
      <div className="flex flex-col gap-1.5 text-sm text-slate-600 mt-2">
        <div className="flex items-center gap-2 truncate">
          <Phone size={14} className="shrink-0 text-slate-400" />
          <span className="truncate">{customer.phone || '-'}</span>
        </div>
        <div className="flex items-center gap-2 truncate">
          <Mail size={14} className="shrink-0 text-slate-400" />
          <span className="truncate break-words-safe">{customer.emailMasked || '-'}</span>
        </div>
        <div className="flex items-center gap-2 truncate">
          <MessageCircle size={14} className="shrink-0 text-slate-400" />
          <span className="truncate">상담 {customer.consultCount}건</span>
        </div>
        {customer.siteName ? (
          <div className="flex items-center gap-2 truncate text-xs text-slate-500">
            <span className="shrink-0">사이트</span>
            <span className="truncate">{customer.siteName}</span>
          </div>
        ) : null}
        <div className="flex items-center gap-2 truncate">
          <Clock size={14} className="shrink-0 text-slate-400" />
          <span className="truncate">최근: {formatStandardDate(customer.lastConsultAt)}</span>
        </div>
      </div>
    </div>
  );
}

export default function CustomerMobileList({ customers }: { customers: CustomerItem[] }) {
  return (
    <div className="admin-mobile-list flex flex-col gap-3">
      {customers.map((c) => (
        <CustomerCard key={c.id} customer={c} />
      ))}
    </div>
  );
}
