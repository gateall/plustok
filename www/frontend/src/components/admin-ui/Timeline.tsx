import type { LucideIcon } from 'lucide-react';
import {
  ArrowRightLeft,
  Bot,
  CheckCircle2,
  Clock,
  FileText,
  Mail,
  MessageSquare,
  Paperclip,
  RefreshCw,
  Sparkles,
  StickyNote,
  Tag,
  UserCog,
} from 'lucide-react';
import clsx from 'clsx';
import AdminCard from './AdminCard';
import AdminCardBody from './AdminCardBody';
import EmptyState from './EmptyState';
import Loading from './Loading';
import type { TimelineEntry, TimelineEntryKind, TimelineSection } from '@/types/consult.types';
import { formatDateTime } from '@/utils/formatTimeAgo';

export type TimelineEvent = TimelineEntry;

type TimelineProps = {
  entries: TimelineEvent[];
  loading?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
  groupBySection?: boolean;
  className?: string;
  footer?: React.ReactNode;
};

const SECTION_META: Record<
  TimelineSection,
  { label: string; icon: LucideIcon; description: string }
> = {
  history: {
    label: '상담 이력',
    icon: Clock,
    description: '접수·생성 등 상담 라이프사이클',
  },
  changes: {
    label: '변경 이력',
    icon: ArrowRightLeft,
    description: '상태·담당자·태그 변경',
  },
  activity: {
    label: '활동 로그',
    icon: MessageSquare,
    description: '메시지·파일·발송 활동',
  },
};

const KIND_META: Record<
  TimelineEntryKind,
  { icon: LucideIcon; dotClass: string; label: string }
> = {
  consult_created: { icon: Clock, dotClass: 'bg-slate-500', label: '상담 생성' },
  status: { icon: RefreshCw, dotClass: 'bg-sky-500', label: '상태 변경' },
  assign: { icon: UserCog, dotClass: 'bg-amber-500', label: '담당자 변경' },
  memo: { icon: StickyNote, dotClass: 'bg-emerald-500', label: '메모' },
  ai_summary: { icon: Sparkles, dotClass: 'bg-violet-500', label: 'AI 요약' },
  ai_reply: { icon: Bot, dotClass: 'bg-indigo-500', label: 'AI 답변' },
  email: { icon: Mail, dotClass: 'bg-cyan-500', label: '이메일 발송' },
  attachment: { icon: Paperclip, dotClass: 'bg-violet-500', label: '첨부파일' },
  contract: { icon: FileText, dotClass: 'bg-emerald-600', label: '계약서 발송' },
  completed: { icon: CheckCircle2, dotClass: 'bg-emerald-500', label: '완료' },
  message: { icon: MessageSquare, dotClass: 'bg-indigo-500', label: '메시지' },
  note: { icon: StickyNote, dotClass: 'bg-emerald-500', label: '메모' },
  system: { icon: Clock, dotClass: 'bg-slate-400', label: '시스템' },
  file: { icon: Paperclip, dotClass: 'bg-violet-500', label: '파일' },
  tag: { icon: Tag, dotClass: 'bg-rose-500', label: '태그' },
};

function sortNewestFirst(entries: TimelineEvent[]): TimelineEvent[] {
  return [...entries].sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime());
}

function groupBySection(entries: TimelineEvent[]): Record<TimelineSection, TimelineEvent[]> {
  const groups: Record<TimelineSection, TimelineEvent[]> = {
    history: [],
    changes: [],
    activity: [],
  };
  for (const entry of sortNewestFirst(entries)) {
    groups[entry.section]?.push(entry);
  }
  return groups;
}

function TimelineItem({ entry }: { entry: TimelineEvent }) {
  const meta = KIND_META[entry.kind] ?? KIND_META.system;
  const KindIcon = meta.icon;

  return (
    <li className="timeline-item relative pb-5 last:pb-0">
      <span
        className={clsx(
          'absolute -left-[1.55rem] top-1.5 flex h-5 w-5 items-center justify-center rounded-full ring-4 ring-white',
          meta.dotClass,
        )}
        aria-hidden
        title={meta.label}
      >
        <KindIcon className="h-2.5 w-2.5 text-white" />
      </span>
      <div className="min-w-0">
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
          <time className="text-xs font-medium text-slate-500" dateTime={entry.at}>
            {formatDateTime(entry.at)}
          </time>
          {entry.actor ? (
            <span className="text-xs text-slate-400">· {entry.actor}</span>
          ) : null}
        </div>
        <p className="mt-0.5 text-sm font-semibold text-slate-900">{entry.title}</p>
        {entry.detail ? (
          <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{entry.detail}</p>
        ) : null}
      </div>
    </li>
  );
}

function TimelineSectionBlock({
  section,
  entries,
}: {
  section: TimelineSection;
  entries: TimelineEvent[];
}) {
  const meta = SECTION_META[section];
  const SectionIcon = meta.icon;

  if (entries.length === 0) {
    return (
      <section className="timeline-section" aria-label={meta.label}>
        <header className="mb-3 flex items-center gap-2">
          <SectionIcon className="h-4 w-4 text-slate-500" aria-hidden />
          <div>
            <h3 className="text-sm font-semibold text-slate-900">{meta.label}</h3>
            <p className="text-xs text-slate-500">{meta.description}</p>
          </div>
        </header>
        <p className="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">
          기록 없음
        </p>
      </section>
    );
  }

  return (
    <section className="timeline-section" aria-label={meta.label}>
      <header className="mb-3 flex items-center gap-2">
        <SectionIcon className="h-4 w-4 text-slate-500" aria-hidden />
        <div>
          <h3 className="text-sm font-semibold text-slate-900">{meta.label}</h3>
          <p className="text-xs text-slate-500">{meta.description}</p>
        </div>
      </header>

      <ol className="relative space-y-0 border-l-2 border-slate-200 pl-6">
        {entries.map((entry) => (
          <TimelineItem key={entry.id} entry={entry} />
        ))}
      </ol>
    </section>
  );
}

function FlatTimelineList({ entries }: { entries: TimelineEvent[] }) {
  const sorted = sortNewestFirst(entries);

  if (sorted.length === 0) {
    return null;
  }

  return (
    <ol className="relative space-y-0 border-l-2 border-slate-200 pl-6">
      {sorted.map((entry) => (
        <TimelineItem key={entry.id} entry={entry} />
      ))}
    </ol>
  );
}

export default function Timeline({
  entries,
  loading = false,
  emptyTitle = '타임라인 기록 없음',
  emptyDescription = '상담 활동 이력이 표시됩니다.',
  groupBySection: grouped = true,
  className,
  footer,
}: TimelineProps) {
  if (loading) {
    return (
      <div className={clsx('timeline p-4', className)} aria-label="타임라인 로딩 중">
        <Loading variant="skeleton" lines={3} />
      </div>
    );
  }

  if (entries.length === 0) {
    return (
      <div className={clsx('timeline p-4', className)}>
        <EmptyState title={emptyTitle} description={emptyDescription} />
      </div>
    );
  }

  const sections: TimelineSection[] = ['history', 'changes', 'activity'];
  const groupedEntries = groupBySection(entries);

  return (
    <AdminCard className={clsx('timeline border border-slate-200 bg-white shadow-sm', className)}>
      <AdminCardBody className="space-y-6 p-4">
        {grouped ? (
          sections.map((section) => (
            <TimelineSectionBlock
              key={section}
              section={section}
              entries={groupedEntries[section]}
            />
          ))
        ) : (
          <FlatTimelineList entries={entries} />
        )}
        {footer ? <div className="border-t border-slate-100 pt-3">{footer}</div> : null}
      </AdminCardBody>
    </AdminCard>
  );
}

export { KIND_META, SECTION_META, sortNewestFirst, groupBySection };
