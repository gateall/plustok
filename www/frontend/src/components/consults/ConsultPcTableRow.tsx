import clsx from 'clsx';
import { Link } from 'react-router-dom';
import type { ConsultListItem } from '@/types/consult.types';
import { StatusBadge, consultStatusBadgeProps } from '@/components/admin-ui';
import {
  displayConsultNo,
  displaySiteName,
  formatRowTime,
  inquiryPreviewParts,
} from '@/utils/consultDisplay';
import { formatTimeAgo } from '@/utils/formatTimeAgo';
import { elapsedSeverity, elapsedSeverityClass, formatElapsedSince } from '@/utils/consultElapsed';
import { isNewOrUnread } from '@/utils/consultSummary';
import { consultRowKey } from '@/utils/groupConsultsByDate';

type ConsultPcTableRowProps = {
  consult: ConsultListItem;
  selected: boolean;
  active: boolean;
  onSelect: (checked: boolean) => void;
  onClick: () => void;
  detailHref?: string;
};

export default function ConsultPcTableRow({
  consult,
  selected,
  active,
  onSelect,
  onClick,
  detailHref,
}: ConsultPcTableRowProps) {
  const consultNo = displayConsultNo(consult);
  const href = detailHref ?? `/admin/consults/${encodeURIComponent(consult.id)}`;
  const isNew = isNewOrUnread(consult);
  const { title, body } = inquiryPreviewParts(consult);
  const severity = elapsedSeverity(consult.createdAt, consult.status);
  const recentLabel = formatTimeAgo(consult.updatedAt);
  const elapsedLabel = formatElapsedSince(consult.createdAt);

  return (
    <tr
      className={clsx(
        'consult-row group cursor-pointer border-b border-slate-100 transition-colors',
        isNew && 'consult-row--new border-l-4 border-l-indigo-500',
        active && 'bg-indigo-50',
        !active && 'hover:bg-slate-50',
      )}
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
      tabIndex={0}
      role="row"
      aria-current={active ? 'true' : undefined}
      data-testid={`consult-pc-row-${consultRowKey(consult)}`}
    >
      <td className="consult-row__cell consult-row__cell--select px-2 py-3" onClick={(e) => e.stopPropagation()}>
        <input
          type="checkbox"
          className="consult-checkbox h-5 w-5 rounded border-slate-300 text-indigo-600"
          checked={selected}
          onChange={(e) => onSelect(e.target.checked)}
          aria-label={`${consultNo} 선택`}
        />
      </td>
      <td className="consult-row__cell px-2 py-3">
        <div className="flex flex-col gap-1">
          <StatusBadge {...consultStatusBadgeProps(consult.status)} />
          {isNew ? (
            <span className="inline-flex w-fit rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-indigo-800">
              NEW
            </span>
          ) : null}
        </div>
      </td>
      <td className="consult-row__cell px-2 py-3">
        <time className="font-mono text-sm tabular-nums text-slate-700" dateTime={consult.createdAt}>
          {formatRowTime(consult.createdAt)}
        </time>
        {severity ? (
          <p className={clsx('mt-0.5 text-xs', elapsedSeverityClass(severity))} title="접수 경과">
            {elapsedLabel}
          </p>
        ) : null}
      </td>
      <td className="consult-row__cell px-2 py-3">
        <span className="consult-number whitespace-nowrap font-mono text-sm font-semibold text-slate-900">
          {consultNo}
        </span>
      </td>
      <td className="consult-row__cell px-2 py-3">
        <div className="consult-customer min-w-0">
          <strong className={clsx('block truncate text-sm text-slate-900', isNew && 'font-bold')}>
            {consult.companyName ? `${consult.companyName} (${consult.customerNameMasked})` : consult.customerNameMasked}
          </strong>
          <span className="mt-0.5 block truncate text-xs text-slate-500">
            {consult.phoneMasked ?? '전화 미등록'}
          </span>
        </div>
      </td>
      <td className="consult-row__cell hidden px-2 py-3 min-[1280px]:table-cell">
        <span className="inline-flex max-w-[9rem] truncate rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
          {displaySiteName(consult)}
        </span>
      </td>
      <td className="consult-row__cell min-w-0 px-2 py-3">
        <div className="consult-preview min-w-0">
          {title ? <p className="consult-preview-title truncate text-sm font-semibold text-slate-900">{title}</p> : null}
          {body ? (
            <p className="consult-preview-body text-overflow-clamp-2 text-xs text-slate-600">{body}</p>
          ) : !title ? (
            <p className="text-xs italic text-slate-400">문의 내용 없음</p>
          ) : null}
        </div>
      </td>
      <td className="consult-row__cell hidden px-2 py-3 min-[769px]:table-cell">
        <span className="text-sm text-slate-700">{consult.agent?.displayName ?? '미배정'}</span>
      </td>
      <td className="consult-row__cell hidden px-2 py-3 min-[1280px]:table-cell">
        <span className="text-xs text-slate-500">{recentLabel}</span>
      </td>
      <td className="consult-row__cell px-2 py-3" onClick={(e) => e.stopPropagation()}>
        <Link
          to={href}
          className="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-indigo-700 transition-colors hover:border-indigo-200 hover:bg-indigo-50"
          aria-label={`${consultNo} 상세보기`}
        >
          상세
        </Link>
      </td>
    </tr>
  );
}
