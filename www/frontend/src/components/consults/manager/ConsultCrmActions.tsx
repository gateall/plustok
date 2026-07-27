import {
  Phone,
  FileText,
  FileSignature,
  MessageCircle,
  Mail,
  Sparkles,
  Headphones,
} from 'lucide-react';
import type { ConsultDetail } from '@/types/consult.types';
import { QuickAction } from '@/components/admin-ui';
import toast from 'react-hot-toast';

type ConsultCrmActionsProps = {
  consult: ConsultDetail;
  onScrollToChat?: () => void;
};

function phoneDigits(masked?: string | null): string | null {
  if (!masked) return null;
  const digits = masked.replace(/[^\d+]/g, '');
  return digits.length >= 4 ? digits : null;
}

export default function ConsultCrmActions({ consult, onScrollToChat }: ConsultCrmActionsProps) {
  const tel = phoneDigits(consult.phoneMasked);
  const mailto =
    consult.email && consult.email.includes('@') ? `mailto:${encodeURIComponent(consult.email)}` : null;

  const stub = (label: string) => () => toast(`${label} API 연동 대기 (Claude)`, { icon: 'ℹ️' });

  return (
    <div className="consult-crm-actions grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-7">
      <QuickAction label="응대" icon={Headphones} onClick={onScrollToChat} disabled={!consult.roomId} />
      <QuickAction label="제안" icon={FileText} onClick={stub('제안서')} />
      <QuickAction label="계약" icon={FileSignature} onClick={stub('계약')} />
      <QuickAction label="SMS" icon={MessageCircle} onClick={stub('SMS')} disabled={!tel} />
      <QuickAction label="메일" icon={Mail} href={mailto ?? undefined} disabled={!mailto} />
      <QuickAction label="AI" icon={Sparkles} onClick={stub('AI')} />
      <QuickAction label="전화" icon={Phone} href={tel ? `tel:${tel}` : undefined} disabled={!tel} />
    </div>
  );
}
