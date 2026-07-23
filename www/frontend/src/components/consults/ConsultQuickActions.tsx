import { Mail, MessageSquare, Phone, UserCog, RefreshCw, MessageCircle } from 'lucide-react';
import type { ConsultDetail } from '@/types/consult.types';

type ConsultQuickActionsProps = {
  consult: ConsultDetail;
  onScrollToChat: () => void;
};

type ActionItem = {
  id: string;
  label: string;
  icon: typeof Phone;
  href?: string;
  onClick?: () => void;
  disabled?: boolean;
  title?: string;
};

function phoneDigits(masked?: string | null): string | null {
  if (!masked) return null;
  const digits = masked.replace(/[^\d+]/g, '');
  return digits.length >= 4 ? digits : null;
}

export default function ConsultQuickActions({ consult, onScrollToChat }: ConsultQuickActionsProps) {
  const tel = phoneDigits(consult.phoneMasked);
  const mailto =
    consult.email && consult.email.includes('@')
      ? `mailto:${encodeURIComponent(consult.email)}`
      : null;

  const actions: ActionItem[] = [
    {
      id: 'call',
      label: '전화',
      icon: Phone,
      href: tel ? `tel:${tel}` : undefined,
      disabled: !tel,
      title: tel ? undefined : '연락처 없음',
    },
    {
      id: 'sms',
      label: '문자',
      icon: MessageCircle,
      disabled: true,
      title: 'Phase 5 — SMS 발송',
    },
    {
      id: 'chat',
      label: '채팅',
      icon: MessageSquare,
      onClick: onScrollToChat,
      disabled: !consult.roomId,
      title: consult.roomId ? '채팅 영역으로 이동' : '연결된 채팅방 없음',
    },
    {
      id: 'mail',
      label: '메일',
      icon: Mail,
      href: mailto ?? undefined,
      disabled: !mailto,
      title: mailto ? undefined : '이메일 없음',
    },
    {
      id: 'status',
      label: '상태변경',
      icon: RefreshCw,
      disabled: true,
      title: 'Phase 5 — 상태 변경 API',
    },
    {
      id: 'assign',
      label: '담당배정',
      icon: UserCog,
      disabled: true,
      title: 'Phase 5 — 담당자 배정',
    },
  ];

  return (
    <section className="consult-detail-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 className="text-sm font-semibold text-slate-900">빠른 작업</h3>
      <div className="quick-action-grid mt-3 grid grid-cols-3 gap-2">
        {actions.map(({ id, label, icon: Icon, href, onClick, disabled, title }) => {
          const className =
            'quick-action-btn inline-flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg border px-2 text-center text-xs font-medium transition-colors';

          if (href && !disabled) {
            return (
              <a
                key={id}
                href={href}
                className={`${className} border-slate-200 bg-white text-slate-700 hover:bg-slate-50`}
                title={title}
              >
                <Icon className="h-4 w-4 shrink-0" aria-hidden />
                {label}
              </a>
            );
          }

          return (
            <button
              key={id}
              type="button"
              onClick={onClick}
              disabled={disabled}
              className={`${className} ${
                disabled
                  ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400'
                  : 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
              }`}
              title={title}
            >
              <Icon className="h-4 w-4 shrink-0" aria-hidden />
              {label}
            </button>
          );
        })}
      </div>
    </section>
  );
}
