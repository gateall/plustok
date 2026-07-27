import clsx from 'clsx';

import { Link } from 'react-router-dom';

import { MessageSquare, UserCog } from 'lucide-react';

import type { ConsultListItem } from '@/types/consult.types';

import { StatusBadge, consultStatusBadgeProps } from '@/components/admin-ui';

import { ConsultTagChip } from './ConsultTags';

import { displayConsultNo, formatReceiptTime, formatRowTime } from '@/utils/consultDisplay';

import { consultRowKey } from '@/utils/groupConsultsByDate';



type ConsultListRowProps = {

  consult: ConsultListItem;

  selected: boolean;

  active: boolean;

  onSelect: (checked: boolean) => void;

  onClick: () => void;

  detailHref?: string;

};



export default function ConsultListRow({

  consult,

  selected,

  active,

  onSelect,

  onClick,

  detailHref,

}: ConsultListRowProps) {

  const consultNo = displayConsultNo(consult);

  const href = detailHref ?? `/admin/consults/${encodeURIComponent(consult.id)}`;



  return (

    <article

      className={clsx(

        'consult-list-row group flex min-w-0 cursor-pointer gap-2 rounded-xl border px-2 py-2.5 transition-colors duration-200',

        active

          ? 'border-indigo-300 bg-indigo-50 shadow-sm'

          : 'border-transparent bg-white hover:border-slate-200 hover:bg-slate-50',

      )}

      onClick={onClick}

      onKeyDown={(e) => {

        if (e.key === 'Enter' || e.key === ' ') {

          e.preventDefault();

          onClick();

        }

      }}

      role="button"

      tabIndex={0}

      aria-current={active ? 'true' : undefined}

      data-testid={`consult-row-${consultRowKey(consult)}`}

    >

      <label

        className="flex shrink-0 items-start pt-1"

        onClick={(e) => e.stopPropagation()}

        onKeyDown={(e) => e.stopPropagation()}

      >

        <input

          type="checkbox"

          className="consult-checkbox h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"

          checked={selected}

          onChange={(e) => onSelect(e.target.checked)}

          aria-label={`${consultNo} 선택`}

        />

      </label>



      <div className="min-w-0 flex-1">

        <div className="flex min-w-0 items-start justify-between gap-2">

          <div className="min-w-0">

            <p className="break-mono truncate font-mono text-sm font-semibold text-slate-900">{consultNo}</p>

            <p className="mt-0.5 truncate text-sm font-medium text-slate-800">{consult.customerNameMasked}</p>

          </div>

          <div className="flex shrink-0 items-start gap-1">

            <StatusBadge {...consultStatusBadgeProps(consult.status)} />

            {(consult.unreadCount ?? (['new', 'open'].includes(consult.status) ? 1 : 0)) > 0 ? (

              <span

                className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white"

                aria-label="미읽음"

              >

                {consult.unreadCount ?? 1}

              </span>

            ) : null}

            <div

              className="hidden items-center gap-0.5 min-[769px]:group-hover:flex min-[769px]:group-focus-within:flex"

              onClick={(e) => e.stopPropagation()}

              onKeyDown={(e) => e.stopPropagation()}

            >

              <Link

                to={href}

                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-indigo-100 hover:text-indigo-700"

                title="상담 열기"

                aria-label={`${consultNo} 상담 열기`}

              >

                <MessageSquare className="h-4 w-4" />

              </Link>

              <Link

                to={`/admin/customers/${encodeURIComponent(consult.id)}`}

                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-indigo-100 hover:text-indigo-700"

                title="고객 정보"

                aria-label={`${consult.customerNameMasked} 고객 정보`}

              >

                <UserCog className="h-4 w-4" />

              </Link>

            </div>

          </div>

        </div>



        <div className="mt-1.5 flex min-w-0 items-center justify-between gap-2 text-xs text-slate-500">

          <span className="truncate">{formatReceiptTime(consult.createdAt, 'relative')}</span>

          <time className="shrink-0 font-mono tabular-nums" dateTime={consult.createdAt}>

            {formatRowTime(consult.createdAt)}

          </time>

        </div>



        {consult.lastMessagePreview ? (

          <p className="mt-1.5 text-overflow-clamp-2 text-xs text-slate-600">{consult.lastMessagePreview}</p>

        ) : null}



        {consult.tags && consult.tags.length > 0 ? (

          <div className="mt-1.5 flex flex-wrap gap-1">

            {consult.tags.slice(0, 3).map((tag) => (

              <ConsultTagChip key={tag.id} tag={tag} compact />

            ))}

            {consult.tags.length > 3 ? (

              <span className="text-xs text-slate-400">+{consult.tags.length - 3}</span>

            ) : null}

          </div>

        ) : null}

      </div>

    </article>

  );

}


