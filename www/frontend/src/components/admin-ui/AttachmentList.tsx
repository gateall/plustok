import { Download, X } from 'lucide-react';
import clsx from 'clsx';
import EmptyState from './EmptyState';
import Loading from './Loading';
import ConfirmDialog from './ConfirmDialog';
import { formatDateTime } from '@/utils/formatTimeAgo';
import {
  formatFileSize,
  getFileTypeMeta,
  isImageFile,
} from './fileIcons';
import { useState } from 'react';

export type AttachmentItem = {
  id: string;
  name: string;
  mimeType: string;
  sizeBytes: number;
  url?: string;
  thumbnailUrl?: string;
  uploadedAt: string;
  uploadedBy?: string;
};

type AttachmentListProps = {
  items: AttachmentItem[];
  loading?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
  onDelete?: (item: AttachmentItem) => void | Promise<void>;
  downloadHref?: (item: AttachmentItem) => string;
  className?: string;
};

function AttachmentPreview({ item }: { item: AttachmentItem }) {
  const meta = getFileTypeMeta(item.name, item.mimeType);
  const Icon = meta.icon;
  const imageSrc = item.thumbnailUrl ?? item.url;

  if (isImageFile(item.name, item.mimeType) && imageSrc) {
    return (
      <img
        src={imageSrc}
        alt={item.name}
        className="h-12 w-12 shrink-0 rounded-lg object-cover"
      />
    );
  }

  return (
    <div
      className={clsx(
        'flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-lg',
        meta.bgClass,
        meta.textClass,
      )}
      title={meta.label}
    >
      <Icon className="h-5 w-5" aria-hidden />
      <span className="mt-0.5 text-[9px] font-bold leading-none">{meta.label}</span>
    </div>
  );
}

export default function AttachmentList({
  items,
  loading = false,
  emptyTitle = '첨부된 파일이 없습니다',
  emptyDescription = '드래그 앤 드롭 또는 클릭으로 파일을 추가하세요.',
  onDelete,
  downloadHref,
  className,
}: AttachmentListProps) {
  const [confirmDelete, setConfirmDelete] = useState<AttachmentItem | null>(null);
  const [deleting, setDeleting] = useState(false);

  if (loading) {
    return (
      <div className={clsx('attachment-list', className)} aria-label="첨부 파일 로딩 중">
        <Loading variant="skeleton" lines={2} className="space-y-2" />
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className={clsx('attachment-list', className)}>
        <EmptyState title={emptyTitle} description={emptyDescription} />
      </div>
    );
  }

  const handleConfirmDelete = async () => {
    if (!confirmDelete || !onDelete) return;
    setDeleting(true);
    try {
      await onDelete(confirmDelete);
    } finally {
      setDeleting(false);
      setConfirmDelete(null);
    }
  };

  return (
    <>
      <ul className={clsx('attachment-list space-y-2', className)}>
        {items.map((item) => (
          <li
            key={item.id}
            className="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
          >
            <AttachmentPreview item={item} />
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium text-slate-900">{item.name}</p>
              <p className="text-xs text-slate-500">
                {formatFileSize(item.sizeBytes)}
                {item.uploadedAt ? ` · ${formatDateTime(item.uploadedAt)}` : null}
                {item.uploadedBy ? ` · ${item.uploadedBy}` : null}
              </p>
            </div>
            {downloadHref || item.url ? (
              <a
                href={downloadHref ? downloadHref(item) : item.url}
                download={item.name}
                target="_blank"
                rel="noopener noreferrer"
                className="shrink-0 rounded p-2 text-indigo-600 hover:bg-indigo-50"
                aria-label={`${item.name} 다운로드`}
                onClick={(e) => e.stopPropagation()}
              >
                <Download className="h-4 w-4" />
              </a>
            ) : null}
            {onDelete ? (
              <button
                type="button"
                className="shrink-0 rounded p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                aria-label={`${item.name} 제거`}
                onClick={() => setConfirmDelete(item)}
              >
                <X className="h-4 w-4" />
              </button>
            ) : null}
          </li>
        ))}
      </ul>

      <ConfirmDialog
        open={confirmDelete !== null}
        title="첨부 파일 삭제"
        description={`"${confirmDelete?.name ?? ''}" 파일을 삭제하시겠습니까?`}
        confirmLabel="삭제"
        confirmVariant="danger"
        loading={deleting}
        onConfirm={() => void handleConfirmDelete()}
        onClose={() => setConfirmDelete(null)}
      />
    </>
  );
}
