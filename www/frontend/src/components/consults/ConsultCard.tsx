import { Link } from 'react-router-dom';
import { MessageSquare, Phone, ChevronRight } from 'lucide-react';
import type { ConsultListItem } from '@/types/consult.types';
import { formatTimeAgo } from '@/utils/formatTimeAgo';
import ConsultStatusBadge from './ConsultStatusBadge';

type ConsultCardProps = {
  consult: ConsultListItem;
};

function displayConsultNo(consult: ConsultListItem): string {
  return consult.consultNo ?? consult.id;
}

function displaySite(consult: ConsultListItem): string {
  if (consult.siteName) return consult.siteName;
  return consult.source === 'crm' ? 'CRM' : 'ACEP';
}

function displayProduct(consult: ConsultListItem): string {
  if (consult.productName) return consult.productName;
  if (consult.contractProbability != null) {
    return `score ${Math.round(consult.contractProbability)}`;
  }
  return '—';
}

function chatHref(consult: ConsultListItem): string {
  if (consult.source === 'acep') {
    return `/chat?room=${encodeURIComponent(consult.id)}`;
  }
  const no = consult.consultNo ?? consult.id;
  return `/admin/consults/view.php?no=${encodeURIComponent(no)}`;
}

export default function ConsultCard({ consult }: ConsultCardProps) {
  const consultNo = displayConsultNo(consult);
  const telHref = consult.phoneMasked ? `tel:${consult.phoneMasked.replace(/[^\d+]/g, '')}` : null;

  return (
    <article className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex min-w-0 items-start justify-between gap-2">
        <p className="min-w-0 truncate font-mono text-sm font-semibold text-slate-900">{consultNo}</p>
        <ConsultStatusBadge status={consult.status} />
      </div>

      <div className="mt-2 min-w-0 space-y-1">
        <p className="break-words-safe text-sm font-medium text-slate-900">
          {consult.companyName ? `${consult.companyName} (${consult.customerNameMasked})` : consult.customerNameMasked}
          {consult.phoneMasked && (
            <span className="font-normal text-slate-500"> · {consult.phoneMasked}</span>
          )}
        </p>
        <p className="break-words-safe truncate text-sm text-slate-600">
          {displaySite(consult)} · {displayProduct(consult)} · {formatTimeAgo(consult.updatedAt)}
        </p>
      </div>

      {consult.lastMessagePreview ? (
        <p className="text-overflow-clamp-2 mt-2 break-words-safe text-sm text-slate-500">
          {consult.lastMessagePreview}
        </p>
      ) : (
        <p className="text-overflow-clamp-2 mt-2 text-sm italic text-slate-400">
          최근 메시지 없음
        </p>
      )}

      <div className="mt-3 flex min-w-0 flex-wrap gap-2">
        {telHref ? (
          <a
            href={telHref}
            className="inline-flex h-11 min-w-[4.5rem] flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            <Phone className="h-4 w-4 shrink-0" />
            전화
          </a>
        ) : (
          <button
            type="button"
            disabled
            className="inline-flex h-11 min-w-[4.5rem] flex-1 cursor-not-allowed items-center justify-center gap-1.5 rounded-lg border border-slate-100 bg-slate-50 px-3 text-sm font-medium text-slate-400"
            title="연락처 없음"
          >
            <Phone className="h-4 w-4 shrink-0" />
            전화
          </button>
        )}

        {consult.source === 'acep' ? (
          <Link
            to={chatHref(consult)}
            className="inline-flex h-11 min-w-[4.5rem] flex-1 items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
          >
            <MessageSquare className="h-4 w-4 shrink-0" />
            채팅
          </Link>
        ) : (
          <a
            href={chatHref(consult)}
            className="inline-flex h-11 min-w-[4.5rem] flex-1 items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
          >
            <MessageSquare className="h-4 w-4 shrink-0" />
            채팅
          </a>
        )}

        <Link
          to={`/admin/consults/${encodeURIComponent(consult.id)}`}
          className="inline-flex h-11 min-w-[4.5rem] flex-1 items-center justify-center gap-1 rounded-lg bg-indigo-600 px-3 text-sm font-medium text-white hover:bg-indigo-700"
        >
          상세보기
          <ChevronRight className="h-4 w-4 shrink-0" />
        </Link>
      </div>
    </article>
  );
}
