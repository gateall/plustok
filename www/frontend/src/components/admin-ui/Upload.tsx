import { useCallback, useRef, useState } from 'react';
import { Loader2, Upload as UploadIcon } from 'lucide-react';
import clsx from 'clsx';

export type UploadProgress = {
  fileName: string;
  progress: number;
  status: 'pending' | 'uploading' | 'done' | 'error';
};

type UploadProps = {
  accept?: string;
  maxBytes?: number;
  multiple?: boolean;
  disabled?: boolean;
  uploading?: boolean;
  progress?: UploadProgress[];
  onFilesSelected: (files: File[]) => void | Promise<void>;
  className?: string;
  hint?: string;
};

const DEFAULT_ACCEPT =
  'image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip';

export default function Upload({
  accept = DEFAULT_ACCEPT,
  maxBytes = 10 * 1024 * 1024,
  multiple = true,
  disabled = false,
  uploading = false,
  progress = [],
  onFilesSelected,
  className,
  hint = 'PDF, Office, ZIP, JPG, PNG, WEBP · 최대 10MB',
}: UploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);

  const handleFiles = useCallback(
    (fileList: File[]) => {
      const valid = fileList.filter((f) => f.size <= maxBytes);
      if (valid.length > 0) {
        void onFilesSelected(valid);
      }
    },
    [maxBytes, onFilesSelected],
  );

  const onDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setDragOver(false);
      if (disabled || uploading) return;
      void handleFiles(Array.from(e.dataTransfer.files));
    },
    [disabled, handleFiles, uploading],
  );

  return (
    <div className={clsx('upload-zone space-y-2', className)}>
      <div
        className={clsx(
          'flex min-h-[140px] flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors',
          dragOver ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300 bg-slate-50',
          (disabled || uploading) && 'pointer-events-none opacity-70',
        )}
        onDragOver={(e) => {
          e.preventDefault();
          if (!disabled && !uploading) setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={onDrop}
        onClick={() => !disabled && !uploading && inputRef.current?.click()}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            inputRef.current?.click();
          }
        }}
        role="button"
        tabIndex={0}
        aria-label="파일 업로드 영역"
        aria-disabled={disabled || uploading}
      >
        {uploading ? (
          <Loader2 className="h-8 w-8 animate-spin text-indigo-500" aria-hidden />
        ) : (
          <UploadIcon className="h-8 w-8 text-slate-400" aria-hidden />
        )}
        <p className="mt-2 text-sm font-medium text-slate-700">
          파일을 드래그하거나 클릭하여 업로드
        </p>
        <p className="mt-1 text-xs text-slate-500">{hint}</p>
        <input
          ref={inputRef}
          type="file"
          multiple={multiple}
          accept={accept}
          className="sr-only"
          aria-label="첨부 파일 선택"
          disabled={disabled || uploading}
          onChange={(e) => {
            void handleFiles(Array.from(e.target.files ?? []));
            e.target.value = '';
          }}
        />
      </div>

      {progress.length > 0 ? (
        <ul className="space-y-2" aria-label="업로드 진행">
          {progress.map((item) => (
            <li key={item.fileName} className="rounded-lg border border-slate-200 bg-white px-3 py-2">
              <div className="flex items-center justify-between gap-2 text-sm">
                <span className="truncate font-medium text-slate-800">{item.fileName}</span>
                <span className="shrink-0 text-xs text-slate-500">
                  {item.status === 'error'
                    ? '실패'
                    : item.status === 'done'
                      ? '완료'
                      : `${item.progress}%`}
                </span>
              </div>
              <div
                className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"
                role="progressbar"
                aria-valuenow={item.progress}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={`${item.fileName} 업로드 진행`}
              >
                <div
                  className={clsx(
                    'h-full rounded-full transition-all duration-300',
                    item.status === 'error'
                      ? 'bg-red-500'
                      : item.status === 'done'
                        ? 'bg-emerald-500'
                        : 'bg-indigo-500',
                  )}
                  style={{ width: `${item.progress}%` }}
                />
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
