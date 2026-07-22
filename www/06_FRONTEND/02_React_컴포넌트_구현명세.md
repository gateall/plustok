# ACEP (PlusTok Enterprise) — React 컴포넌트 구현명세

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 5 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Frontend Platform Team  
**Audience:** Frontend Developers, QA  

**적용 위치:** `www/frontend/src/components/chat/`  
**UI SSOT:** [02_UIUX/UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md)  
**화면 SSOT:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md)  
**아키텍처:** [01_Frontend_아키텍처.md](01_Frontend_아키텍처.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 컴포넌트 수 | 11 + ChatScreen container |
| 스택 | React 18 + TypeScript + TailwindCSS |
| 패턴 | Presentational components, props/events SSOT = UI_COMPONENTS_GUIDE |
| Barrel export | `components/chat/index.ts` |

본 문서는 [UI_COMPONENTS_GUIDE](../02_UIUX/UI_COMPONENTS_GUIDE.md)의 **11개 컴포넌트**를 implementation-ready TypeScript/TSX 수준으로 상세 기술한다. 각 컴포넌트: **파일 경로, Props interface, 내부 state, Tailwind classes, event handlers, accessibility**.

---

## 0. 공통 규칙

### 0.1 Import & Export

```typescript
// src/components/chat/index.ts
export { MessageBubble } from './MessageBubble';
export { InputField } from './InputField';
export { FileUpload } from './FileUpload';
export { RecommendationCard } from './RecommendationCard';
export { CustomerCard } from './CustomerCard';
export { StatusBadge } from './StatusBadge';
export { Tabs } from './Tabs';
export { ChatList } from './ChatList';
export { AIPanelCard } from './AIPanelCard';
export { ActionButton } from './ActionButton';
export { TypingIndicator } from './TypingIndicator';
```

### 0.2 className 병합 유틸

```typescript
// src/utils/cn.ts
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
```

### 0.3 Props Naming Convention

- UI_COMPONENTS_GUIDE interface **그대로** 사용 (필드명 camelCase)
- Events: `onXxx` callback props
- `className` optional on all components

---

## 1. MessageBubble

**파일:** `src/components/chat/MessageBubble.tsx`  
**화면:** 중앙 패널 — [01_상담채팅화면 §4-2-B](../02_UIUX/01_상담채팅화면.fig.md)  
**UI SSOT:** [UI_COMPONENTS_GUIDE §1](../02_UIUX/UI_COMPONENTS_GUIDE.md)

### 1.1 Props Interface (UI_COMPONENTS_GUIDE exact)

```typescript
interface MessageBubbleProps {
  id: string;
  variant: 'customer' | 'agent' | 'system';
  content: string;
  timestamp: string;
  readStatus?: 'sent' | 'delivered' | 'read';
  attachmentUrl?: string | null;
  attachmentType?: 'image' | 'pdf' | 'audio' | null;
  failed?: boolean;
  className?: string;
  onRetry?: (messageId: string) => void;
  onAttachmentClick?: (url: string, type: string) => void;
  onClick?: (messageId: string) => void;
}
```

### 1.2 Internal State

| State | Trigger | Effect |
|-------|---------|--------|
| `isHovered` | mouseenter/leave | retry link visibility |
| `imageError` | img onError | fallback icon |

No persistent state — controlled by parent `useMessages`.

### 1.3 Variant Tailwind Classes

| variant | Container classes |
|---------|-------------------|
| customer | `bg-gray-100 text-gray-900 rounded-lg rounded-tl-none ml-0 mr-auto max-w-[70%]` |
| agent | `bg-blue-600 text-white rounded-lg rounded-tr-none ml-auto mr-0 max-w-[70%]` |
| system | `bg-amber-100 text-amber-800 text-center mx-auto text-xs max-w-[90%]` |

### 1.4 Implementation Snippet

```tsx
import { cn } from '../../utils/cn';
import { AlertTriangle, FileText, RotateCcw } from 'lucide-react';

const variantStyles = {
  customer: 'bg-gray-100 text-gray-900 rounded-lg rounded-tl-none ml-0 mr-auto',
  agent: 'bg-blue-600 text-white rounded-lg rounded-tr-none ml-auto mr-0',
  system: 'bg-amber-100 text-amber-800 text-center mx-auto text-xs',
} as const;

export function MessageBubble({
  id,
  variant,
  content,
  timestamp,
  readStatus,
  attachmentUrl,
  attachmentType,
  failed = false,
  className,
  onRetry,
  onAttachmentClick,
  onClick,
}: MessageBubbleProps) {
  const formattedTime = new Date(timestamp).toLocaleTimeString('ko-KR', {
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <article
      role="article"
      aria-label={`${variant} 메시지`}
      className={cn(
        'group flex flex-col gap-1 px-4 py-1 animate-fade-in',
        variant === 'agent' ? 'items-end' : variant === 'customer' ? 'items-start' : 'items-center',
        failed && 'opacity-90',
        className
      )}
      onClick={() => onClick?.(id)}
    >
      <div
        className={cn(
          'px-3 py-2 text-sm leading-relaxed shadow-sm',
          'max-w-[70%]',
          variantStyles[variant],
          failed && 'ring-1 ring-red-400'
        )}
      >
        <p className="whitespace-pre-wrap break-words">{content}</p>

        {attachmentUrl && attachmentType === 'image' && (
          <button
            type="button"
            className="mt-2 block overflow-hidden rounded-md"
            onClick={(e) => {
              e.stopPropagation();
              onAttachmentClick?.(attachmentUrl, attachmentType);
            }}
            aria-label="이미지 첨부 미리보기"
          >
            <img src={attachmentUrl} alt="" className="max-h-48 object-cover" loading="lazy" />
          </button>
        )}

        {attachmentUrl && attachmentType === 'pdf' && (
          <button
            type="button"
            className="mt-2 flex items-center gap-2 text-xs underline"
            onClick={(e) => {
              e.stopPropagation();
              onAttachmentClick?.(attachmentUrl, 'pdf');
            }}
          >
            <FileText size={16} aria-hidden />
            PDF 첨부
          </button>
        )}
      </div>

      <div className={cn('flex items-center gap-1 text-xs text-gray-400', variant === 'agent' && 'flex-row-reverse')}>
        <time dateTime={timestamp}>{formattedTime}</time>
        {variant === 'agent' && readStatus && (
          <span className={cn(readStatus === 'read' ? 'text-green-600' : 'text-gray-400')} aria-label={readStatus === 'read' ? '읽음' : '전송됨'}>
            {readStatus === 'read' ? '✓✓' : readStatus === 'delivered' ? '✓' : ''}
          </span>
        )}
        {failed && (
          <button
            type="button"
            className="flex items-center gap-1 text-red-600 hover:underline"
            onClick={(e) => {
              e.stopPropagation();
              onRetry?.(id);
            }}
            aria-label="메시지 재전송"
          >
            <AlertTriangle size={12} aria-hidden />
            재시도
          </button>
        )}
      </div>
    </article>
  );
}
```

### 1.5 Accessibility

| Attribute | Value |
|-----------|-------|
| `role` | `article` |
| `aria-label` | variant별 "고객/상담원/시스템 메시지" |
| retry button | `aria-label="메시지 재전송"` |
| attachment | `aria-label` on preview button |

### 1.6 States UI Mapping

| State | UI (UI_COMPONENTS_GUIDE) |
|-------|--------------------------|
| default | variant별 배경 |
| delivering | parent: `opacity-70` class |
| failed | ⚠️ + "재시도" link |
| read | ✓✓ green-600 |
| with-attachment | thumbnail / pdf icon |

---

## 2. InputField

**파일:** `src/components/chat/InputField.tsx`  
**화면:** 중앙 패널 + ChatList 검색 — [§4-2-A/B](../02_UIUX/01_상담채팅화면.fig.md)

### 2.1 Props Interface

```typescript
interface InputFieldProps {
  variant?: 'message' | 'search';
  value: string;
  placeholder?: string;
  maxLength?: number; // default 2000
  disabled?: boolean;
  pendingRecommendationCount?: number;
  autoFocus?: boolean;
  className?: string;
  onChange: (value: string) => void;
  onSubmit: (value: string) => void;
  onInputStart?: () => void;
  onInputStop?: () => void;
}
```

### 2.2 Ref Imperative Handle (insertText)

```typescript
export interface InputFieldHandle {
  insertText: (text: string) => void;
  focus: () => void;
}

export const InputField = forwardRef<InputFieldHandle, InputFieldProps>(function InputField(
  { variant = 'message', value, placeholder, maxLength = 2000, disabled, pendingRecommendationCount, autoFocus, className, onChange, onSubmit, onInputStart, onInputStop },
  ref
) {
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const idleTimerRef = useRef<ReturnType<typeof setTimeout>>();

  useImperativeHandle(ref, () => ({
    insertText: (text: string) => {
      onChange(value ? `${value}\n${text}` : text);
      textareaRef.current?.focus();
    },
    focus: () => textareaRef.current?.focus(),
  }));

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey && variant === 'message') {
      e.preventDefault();
      if (value.trim()) onSubmit(value.trim());
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement | HTMLInputElement>) => {
    const next = e.target.value;
    if (next.length <= maxLength) {
      onChange(next);
      onInputStart?.();
      clearTimeout(idleTimerRef.current);
      idleTimerRef.current = setTimeout(() => onInputStop?.(), 3000);
    }
  };

  const isOverLimit = value.length >= maxLength;
  const isSearch = variant === 'search';

  if (isSearch) {
    return (
      <div className={cn('relative', className)}>
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden />
        <input
          type="search"
          value={value}
          onChange={handleChange}
          placeholder={placeholder ?? '검색'}
          disabled={disabled}
          aria-label="상담 검색"
          className="h-9 w-full rounded-md border border-gray-200 pl-9 pr-3 text-sm focus:outline-none focus:ring-[var(--shadow-focus)]"
        />
      </div>
    );
  }

  return (
    <div className={cn('flex flex-col gap-1', className)}>
      <textarea
        ref={textareaRef}
        value={value}
        onChange={handleChange}
        onKeyDown={handleKeyDown}
        placeholder={placeholder ?? '메시지를 입력하세요 (Enter: 전송, Shift+Enter: 줄바꿈)'}
        disabled={disabled}
        autoFocus={autoFocus}
        aria-label="메시지 입력"
        rows={1}
        className={cn(
          'min-h-[40px] max-h-[120px] w-full resize-none rounded-md border border-gray-200 px-3 py-2 text-sm',
          'focus:outline-none focus:ring-[var(--shadow-focus)]',
          disabled && 'cursor-not-allowed bg-gray-100'
        )}
        style={{ fieldSizing: 'content' } as React.CSSProperties}
      />
      <div className="flex items-center justify-between text-xs">
        {pendingRecommendationCount ? (
          <span className="text-purple-600">✓ 추천답변 {pendingRecommendationCount}개</span>
        ) : (
          <span />
        )}
        <span className={cn(isOverLimit ? 'text-red-600' : 'text-gray-400')}>
          {value.length}/{maxLength}
        </span>
      </div>
    </div>
  );
});
```

### 2.3 Tailwind Summary

| variant | classes |
|---------|---------|
| message | min-h 40px, max-h 120px, border gray-200, radius md |
| search | h-9, pl-9 (icon), text-sm |
| focused | `--shadow-focus` ring |
| disabled | bg-gray-100 cursor-not-allowed |

### 2.4 Accessibility

- `aria-label="메시지 입력"` / `"상담 검색"`
- Enter submit (message variant only)
- Shift+Enter newline

---

## 3. FileUpload

**파일:** `src/components/chat/FileUpload.tsx`

### 3.1 Props Interface

```typescript
interface FileUploadProps {
  accept?: string[];
  maxSize?: number;
  multiple?: boolean;
  disabled?: boolean;
  uploadUrl?: string;
  className?: string;
  onUploadComplete: (files: UploadedFile[]) => void;
  onUploadError: (error: FileUploadError) => void;
  onFileSelect?: (files: File[]) => void;
}

interface UploadedFile {
  url: string;
  type: 'image' | 'pdf';
  name: string;
  size: number;
}

interface FileUploadError {
  code: 'FILE_TOO_LARGE' | 'INVALID_TYPE' | 'UPLOAD_FAILED';
  message: string;
}
```

### 3.2 Internal State

```typescript
type UploadState = 'idle' | 'uploading' | 'success' | 'error' | 'drag-over';

const [state, setState] = useState<UploadState>('idle');
const [progress, setProgress] = useState(0);
const [errorMessage, setErrorMessage] = useState<string | null>(null);
const [chips, setChips] = useState<UploadedFile[]>([]);
```

### 3.3 Implementation Snippet

```tsx
const DEFAULT_ACCEPT = ['image/jpeg', 'image/png', 'application/pdf'];
const DEFAULT_MAX = 10 * 1024 * 1024;
const MAX_FILES = 5;

export function FileUpload({
  accept = DEFAULT_ACCEPT,
  maxSize = DEFAULT_MAX,
  multiple = true,
  disabled = false,
  uploadUrl = '/api/v1/files/upload',
  className,
  onUploadComplete,
  onUploadError,
  onFileSelect,
}: FileUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [state, setState] = useState<UploadState>('idle');
  const [progress, setProgress] = useState(0);

  const validateFiles = (files: File[]): File[] | null => {
    const valid: File[] = [];
    for (const f of files.slice(0, MAX_FILES)) {
      if (!accept.includes(f.type)) {
        onUploadError({ code: 'INVALID_TYPE', message: '지원하지 않는 파일 형식입니다.' });
        return null;
      }
      if (f.size > maxSize) {
        onUploadError({ code: 'FILE_TOO_LARGE', message: '파일 크기는 10MB 이하여야 합니다.' });
        return null;
      }
      valid.push(f);
    }
    return valid;
  };

  const uploadFiles = async (files: File[]) => {
    setState('uploading');
    setProgress(0);
    try {
      const form = new FormData();
      files.forEach((f) => form.append('files[]', f));
      const res = await fetch(uploadUrl, { method: 'POST', body: form, credentials: 'include' });
      if (!res.ok) throw new Error('UPLOAD_FAILED');
      const json = await res.json();
      setState('success');
      onUploadComplete(json.data.files);
    } catch {
      setState('error');
      onUploadError({ code: 'UPLOAD_FAILED', message: '업로드에 실패했습니다.' });
    }
  };

  return (
    <div
      className={cn(
        'relative',
        state === 'drag-over' && 'rounded-md border-2 border-dashed border-blue-400 bg-blue-50',
        className
      )}
      onDragOver={(e) => { e.preventDefault(); setState('drag-over'); }}
      onDragLeave={() => setState('idle')}
      onDrop={(e) => {
        e.preventDefault();
        setState('idle');
        const files = validateFiles(Array.from(e.dataTransfer.files));
        if (files) { onFileSelect?.(files); uploadFiles(files); }
      }}
    >
      <button
        type="button"
        disabled={disabled || state === 'uploading'}
        aria-label="파일 첨부"
        aria-busy={state === 'uploading'}
        onClick={() => inputRef.current?.click()}
        className="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 disabled:opacity-50"
      >
        <Paperclip size={20} aria-hidden />
      </button>
      <input
        ref={inputRef}
        type="file"
        className="hidden"
        accept={accept.join(',')}
        multiple={multiple}
        onChange={(e) => {
          const files = validateFiles(Array.from(e.target.files ?? []));
          if (files) { onFileSelect?.(files); uploadFiles(files); }
          e.target.value = '';
        }}
      />
      {state === 'uploading' && (
        <div className="absolute inset-0 flex items-center justify-center bg-white/80">
          <div className="h-1 w-full max-w-[36px] overflow-hidden rounded bg-gray-200">
            <div className="h-full bg-blue-600 transition-all" style={{ width: `${progress}%` }} />
          </div>
        </div>
      )}
      {state === 'error' && (
        <p className="mt-1 text-xs text-red-600 border-red-500">업로드 오류</p>
      )}
    </div>
  );
}
```

### 3.4 Accessibility

- Button: `aria-label="파일 첨부"`, `aria-busy` when uploading
- Keyboard: Enter/Space on button opens file dialog

---

## 4. RecommendationCard

**파일:** `src/components/chat/RecommendationCard.tsx`  
**화면:** 우측 AI 패널 — [§4-2-C](../02_UIUX/01_상담채팅화면.fig.md)

### 4.1 Props Interface

```typescript
interface RecommendationCardProps {
  id: string;
  text: string;
  confidence?: number;
  rank?: number;
  used?: boolean;
  disabled?: boolean;
  className?: string;
  onSelect: (id: string, text: string) => void;
  onPreview?: (text: string) => void;
}
```

### 4.2 Confidence Badge Color

| confidence | dot color |
|------------|-----------|
| ≥0.9 | green-500 |
| ≥0.7 | yellow-500 |
| <0.7 | gray-400 |

### 4.3 Implementation Snippet

```tsx
export function RecommendationCard({
  id, text, confidence, rank, used = false, disabled = false, className, onSelect, onPreview,
}: RecommendationCardProps) {
  const dotColor =
    confidence === undefined ? 'bg-gray-400'
    : confidence >= 0.9 ? 'bg-green-500'
    : confidence >= 0.7 ? 'bg-yellow-500'
    : 'bg-gray-400';

  return (
    <button
      type="button"
      role="button"
      disabled={disabled || used}
      aria-label={`AI 추천답변: ${text.slice(0, 50)}`}
      className={cn(
        'w-full rounded-md border border-[#DDD6FE] bg-[var(--color-ai-bg)] px-4 py-3 text-left text-sm',
        'transition-all duration-250 animate-slide-up hover:bg-purple-100 hover:shadow-sm',
        'focus:outline-none focus:ring-2 focus:ring-blue-500',
        used && 'opacity-50 line-through',
        disabled && 'cursor-not-allowed opacity-50',
        className
      )}
      style={{ animationDelay: rank ? `${(rank - 1) * 80}ms` : undefined }}
      onClick={() => onSelect(id, text)}
      onMouseEnter={() => onPreview?.(text)}
    >
      <div className="flex items-start gap-2">
        <span className="text-green-600 shrink-0" aria-hidden>✓</span>
        <span className="flex-1 text-gray-900">{text}</span>
        {confidence !== undefined && (
          <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', dotColor)} aria-label={`신뢰도 ${Math.round(confidence * 100)}%`} />
        )}
      </div>
    </button>
  );
}
```

### 4.4 Event Flow

```
RecommendationCard.onSelect(id, text)
  → InputField ref.insertText(text)
  → (optional) mark recommendation used in local state
  → user ActionButton(send) → POST message source=ai_recommendation
```

---

## 5. CustomerCard

**파일:** `src/components/chat/CustomerCard.tsx`

### 5.1 Props Interface

```typescript
interface CustomerCardProps {
  customerId: string;
  name: string;
  phoneMasked: string;
  emailMasked?: string;
  addressMasked?: string;
  tags?: string[];
  consultationCount?: number;
  compact?: boolean;
  loading?: boolean;
  className?: string;
  onEdit?: (customerId: string) => void;
  onViewHistory?: (customerId: string) => void;
  onTagClick?: (tag: string) => void;
}
```

### 5.2 Implementation Snippet

```tsx
export function CustomerCard({
  customerId, name, phoneMasked, emailMasked, addressMasked,
  tags = [], consultationCount, compact = false, loading = false, className,
  onEdit, onViewHistory, onTagClick,
}: CustomerCardProps) {
  if (loading) {
    return (
      <div className={cn('animate-pulse rounded-md bg-purple-50 p-4', className)} aria-busy="true">
        <div className="h-4 w-24 rounded bg-gray-200" />
        <div className="mt-2 h-3 w-32 rounded bg-gray-200" />
      </div>
    );
  }

  if (!name) {
    return (
      <div className={cn('rounded-md bg-gray-50 p-4 text-sm text-gray-500', className)}>
        고객 정보 없음
      </div>
    );
  }

  if (compact) {
    return (
      <div className={cn('flex h-12 items-center gap-3 px-4', className)}>
        <span className="text-sm font-semibold text-[#1E40AF]">{name}</span>
        <span className="text-sm text-gray-600">{phoneMasked}</span>
      </div>
    );
  }

  return (
    <section className={cn('rounded-md border border-gray-100 bg-white p-4 shadow-sm', className)} aria-label="고객 정보">
      <header className="flex items-center justify-between">
        <h3 className="text-base font-semibold text-[#1E40AF]">{name}</h3>
        {onEdit && (
          <button type="button" className="text-xs text-blue-600 hover:underline" onClick={() => onEdit(customerId)}>
            수정
          </button>
        )}
      </header>
      <dl className="mt-2 space-y-1 text-sm text-gray-700">
        <div><dt className="sr-only">전화</dt><dd>{phoneMasked}</dd></div>
        {emailMasked && <div><dt className="sr-only">이메일</dt><dd>{emailMasked}</dd></div>}
        {addressMasked && <div><dt className="sr-only">주소</dt><dd>{addressMasked}</dd></div>
      </dl>
      {tags.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-1">
          {tags.map((tag) => (
            <StatusBadge key={tag} variant="tag" label={tag} onClick={() => onTagClick?.(tag)} />
          ))}
        </div>
      )}
      {consultationCount !== undefined && (
        <p className="mt-2 text-xs text-gray-500">{consultationCount}회 상담</p>
      )}
      {onViewHistory && (
        <button type="button" className="mt-2 text-xs text-blue-600 hover:underline" onClick={() => onViewHistory(customerId)}>
          상담 이력 보기
        </button>
      )}
    </section>
  );
}
```

---

## 6. StatusBadge

**파일:** `src/components/chat/StatusBadge.tsx`

### 6.1 Props Interface

```typescript
type BadgeStatus = 'new' | 'active' | 'closed';
type BadgeVariant = 'status' | 'tag' | 'sentiment';

interface StatusBadgeProps {
  variant?: BadgeVariant;
  status?: BadgeStatus;
  label?: string;
  sentiment?: 'positive' | 'neutral' | 'negative';
  size?: 'sm' | 'md';
  className?: string;
  onClick?: () => void;
}
```

### 6.2 Status Color Map (UI_COMPONENTS_GUIDE exact)

```typescript
const statusStyles: Record<BadgeStatus, string> = {
  new: 'bg-[#FEF3C7] text-[#92400E] border-[#FCD34D]',
  active: 'bg-[#D1FAE5] text-[#065F46] border-[#6EE7B7]',
  closed: 'bg-[#F3F4F6] text-[#6B7280] border-[#D1D5DB]',
};

const statusLabels: Record<BadgeStatus, string> = {
  new: '신규',
  active: '상담중',
  closed: '종료',
};
```

### 6.3 Implementation Snippet

```tsx
export function StatusBadge({
  variant = 'status', status, label, sentiment, size = 'sm', className, onClick,
}: StatusBadgeProps) {
  const displayLabel = label ?? (status ? statusLabels[status] : '');
  const Tag = onClick ? 'button' : 'span';

  let colorClass = status && variant === 'status' ? statusStyles[status] : '';
  if (variant === 'tag') colorClass = 'bg-blue-100 text-blue-800 border-blue-200';
  if (variant === 'sentiment') {
    colorClass =
      sentiment === 'positive' ? 'bg-green-100 text-green-800 border-green-200'
      : sentiment === 'negative' ? 'bg-red-100 text-red-800 border-red-200'
      : 'bg-gray-100 text-gray-700 border-gray-200';
  }

  return (
    <Tag
      type={onClick ? 'button' : undefined}
      onClick={onClick}
      className={cn(
        'inline-flex items-center rounded border font-medium',
        size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
        colorClass,
        onClick && 'cursor-pointer hover:opacity-80',
        className
      )}
    >
      {displayLabel}
    </Tag>
  );
}
```

---

## 7. Tabs

**파일:** `src/components/chat/Tabs.tsx`

### 7.1 Props Interface

```typescript
interface TabItem {
  id: string;
  label: string;
  badge?: number;
  disabled?: boolean;
}

interface TabsProps {
  items: TabItem[];
  activeId: string;
  variant?: 'filter' | 'navigation';
  fullWidth?: boolean;
  className?: string;
  onChange: (tabId: string) => void;
}
```

### 7.2 Implementation Snippet

```tsx
export function Tabs({
  items, activeId, variant = 'filter', fullWidth = variant === 'navigation', className, onChange,
}: TabsProps) {
  const listRef = useRef<HTMLDivElement>(null);

  const handleKeyDown = (e: React.KeyboardEvent, index: number) => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      e.preventDefault();
      const dir = e.key === 'ArrowRight' ? 1 : -1;
      const next = items[(index + dir + items.length) % items.length];
      if (!next.disabled) onChange(next.id);
    }
  };

  return (
    <div
      role="tablist"
      aria-label={variant === 'navigation' ? '메인 탭' : '상담 필터'}
      ref={listRef}
      className={cn(
        'flex',
        variant === 'filter' ? 'gap-2' : 'border-b border-gray-200',
        fullWidth && 'w-full',
        className
      )}
    >
      {items.map((item, index) => {
        const isActive = item.id === activeId;
        return (
          <button
            key={item.id}
            role="tab"
            type="button"
            aria-selected={isActive}
            aria-controls={`tabpanel-${item.id}`}
            tabIndex={isActive ? 0 : -1}
            disabled={item.disabled}
            onClick={() => onChange(item.id)}
            onKeyDown={(e) => handleKeyDown(e, index)}
            className={cn(
              'relative flex items-center justify-center gap-1 transition-colors duration-150',
              variant === 'filter' && 'rounded-full px-3 py-1.5 text-sm',
              variant === 'navigation' && 'h-11 flex-1 text-sm',
              isActive
                ? 'font-semibold text-blue-600 border-b-2 border-blue-600'
                : 'text-gray-500 hover:text-gray-700',
              item.disabled && 'opacity-40 cursor-not-allowed'
            )}
          >
            {item.label}
            {item.badge !== undefined && item.badge > 0 && (
              <span className="flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] text-white">
                {item.badge > 99 ? '99+' : item.badge}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
```

### 7.3 Usage Examples

**ChatList filter (좌측):**

```tsx
<Tabs
  variant="filter"
  items={[
    { id: 'new', label: '신규' },
    { id: 'active', label: '상담중' },
    { id: 'closed', label: '종료' },
  ]}
  activeId={activeFilter}
  onChange={(id) => setFilterStatus([id as BadgeStatus])}
/>
```

**Mobile navigation:**

```tsx
<Tabs
  variant="navigation"
  fullWidth
  items={[
    { id: 'list', label: '목록', badge: totalUnread },
    { id: 'chat', label: '채팅' },
    { id: 'ai', label: 'AI', badge: pendingAiCount },
  ]}
  activeId={mobileTab}
  onChange={setMobileTab}
/>
```

---

## 8. ChatList

**파일:** `src/components/chat/ChatList.tsx`

### 8.1 Props Interface

```typescript
interface ChatRoomItem {
  id: string;
  customerName: string;
  inquiryType: string;
  status: 'new' | 'active' | 'closed';
  relativeTime: string;
  unreadCount: number;
  contractProbability?: number;
  isSelected?: boolean;
}

interface ChatListProps {
  rooms: ChatRoomItem[];
  selectedRoomId?: string | null;
  loading?: boolean;
  filterStatus?: ('new' | 'active' | 'closed')[];
  searchQuery?: string;
  className?: string;
  onRoomSelect: (roomId: string) => void;
  onSearchChange: (query: string) => void;
  onFilterChange: (statuses: string[]) => void;
  onLoadMore?: () => void;
}
```

### 8.2 ChatListItem Sub-component

```tsx
function ChatListItem({
  room, isSelected, onSelect,
}: { room: ChatRoomItem; isSelected: boolean; onSelect: () => void }) {
  const highPriority = (room.contractProbability ?? 0) >= 70;

  return (
    <button
      type="button"
      role="option"
      aria-selected={isSelected}
      onClick={onSelect}
      className={cn(
        'flex min-h-[72px] w-full flex-col gap-0.5 border-b border-gray-100 px-4 py-3 text-left',
        'hover:bg-gray-50 transition-colors',
        isSelected && 'border-l-4 border-l-blue-600 bg-blue-50'
      )}
    >
      <div className="flex items-center justify-between">
        <span className={cn('text-sm font-semibold', isSelected ? 'text-[#1E40AF]' : 'text-gray-900', room.unreadCount > 0 && 'font-bold')}>
          {room.customerName}
          {room.unreadCount > 0 && <span className="ml-1" aria-label="미읽음">✈️</span>}
        </span>
        <span className="text-xs text-gray-400">{room.relativeTime}</span>
      </div>
      <span className="text-xs text-gray-700">{room.inquiryType}</span>
      <div className="mt-1 flex items-center gap-2">
        <StatusBadge status={room.status} size="sm" />
        {highPriority && (
          <span className="text-orange-500" aria-label="계약확률 높음" title="우선 대응">◉</span>
        )}
      </div>
    </button>
  );
}
```

### 8.3 ChatList Container

```tsx
export function ChatList({
  rooms, selectedRoomId, loading, searchQuery = '', className,
  onRoomSelect, onSearchChange, onLoadMore,
}: ChatListProps) {
  const listRef = useRef<HTMLDivElement>(null);

  if (loading) {
    return (
      <div className={cn('space-y-2 p-4', className)} aria-busy="true">
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="h-[72px] animate-pulse rounded bg-gray-100" />
        ))}
      </div>
    );
  }

  if (rooms.length === 0) {
    return (
      <div className={cn('flex flex-col items-center justify-center p-8 text-gray-500', className)}>
        <p className="text-sm">{searchQuery ? '검색 결과 없음' : '상담이 없습니다'}</p>
      </div>
    );
  }

  return (
    <div className={cn('flex flex-col', className)}>
      <div className="border-b border-gray-200 p-3">
        <InputField
          variant="search"
          value={searchQuery}
          onChange={onSearchChange}
          onSubmit={onSearchChange}
          placeholder="고객명, 문의유형 검색"
        />
      </div>
      <div
        role="listbox"
        aria-label="상담 목록"
        ref={listRef}
        className="flex-1 overflow-y-auto"
        onScroll={(e) => {
          const el = e.currentTarget;
          if (el.scrollHeight - el.scrollTop - el.clientHeight < 100) {
            onLoadMore?.();
          }
        }}
      >
        {rooms.map((room) => (
          <ChatListItem
            key={room.id}
            room={room}
            isSelected={room.id === selectedRoomId}
            onSelect={() => onRoomSelect(room.id)}
          />
        ))}
      </div>
    </div>
  );
}
```

---

## 9. AIPanelCard

**파일:** `src/components/chat/AIPanelCard.tsx`

### 9.1 Props Interface

```typescript
type AIPanelType = 'contract' | 'faq' | 'analysis' | 'recommendations' | 'loading' | 'error';

interface FAQItem {
  question: string;
  answer: string;
}

interface AIPanelCardProps {
  type: AIPanelType;
  title?: string;
  contractProbability?: number;
  contractLabel?: string;
  faqItems?: FAQItem[];
  sentiment?: 'positive' | 'neutral' | 'negative';
  customerTags?: string[];
  errorMessage?: string;
  aiModel?: string;
  className?: string;
  children?: React.ReactNode;
  onFAQClick?: (question: string, answer: string) => void;
  onRetry?: () => void;
  onRefresh?: () => void;
}
```

### 9.2 Contract Score Color

```typescript
function getContractColor(score: number): string {
  if (score >= 70) return 'text-green-600';
  if (score >= 40) return 'text-amber-600';
  return 'text-red-600';
}

function renderStars(score: number): string {
  const filled = Math.round(score / 20);
  return '★'.repeat(filled) + '☆'.repeat(5 - filled);
}
```

### 9.3 Type Renderers

```tsx
export function AIPanelCard({ type, title, contractProbability, contractLabel, faqItems, sentiment, customerTags, errorMessage, aiModel, className, children, onFAQClick, onRetry }: AIPanelCardProps) {
  const header = title ?? '🤖 AI Assistant';

  if (type === 'loading') {
    return (
      <div className={cn('mb-3 rounded-md bg-[var(--color-ai-bg)] p-4', className)} aria-busy="true">
        <div className="mb-2 text-base font-semibold text-purple-600">{header}</div>
        <div className="space-y-2">
          <div className="h-3 w-full animate-pulse rounded bg-purple-100" />
          <div className="h-3 w-4/5 animate-pulse rounded bg-purple-100" />
          <div className="h-3 w-3/5 animate-pulse rounded bg-purple-100" />
        </div>
        <p className="mt-2 text-xs text-gray-500">분석 중...</p>
      </div>
    );
  }

  if (type === 'error') {
    return (
      <div className={cn('mb-3 rounded-md bg-gray-100 p-4 text-gray-500', className)}>
        <p className="text-sm">{errorMessage ?? 'AI 분석 불가'}</p>
        {onRetry && (
          <ActionButton variant="ghost" size="sm" label="다시 시도" action="retry" onClick={onRetry} className="mt-2" />
        )}
      </div>
    );
  }

  return (
    <section className={cn('mb-3 rounded-md bg-[var(--color-ai-bg)] p-4 shadow-sm', className)} aria-label={header}>
      <h2 className="mb-3 text-base font-semibold text-purple-600">{header}</h2>

      {type === 'contract' && contractProbability !== undefined && (
        <div>
          <p className="text-sm text-gray-600">계약확률</p>
          <p className={cn('text-2xl font-bold', getContractColor(contractProbability))}>
            <span className="text-yellow-400">{renderStars(contractProbability)}</span>
            {' '}{contractProbability}점
          </p>
          {contractLabel && <p className="text-sm text-gray-600">{contractLabel}</p>}
        </div>
      )}

      {type === 'faq' && faqItems && (
        <ul className="space-y-2">
          {faqItems.map((item) => (
            <li key={item.question}>
              <button
                type="button"
                className="text-left text-sm text-gray-800 hover:underline"
                onClick={() => onFAQClick?.(item.question, item.answer)}
              >
                Q. {item.question}
              </button>
            </li>
          ))}
        </ul>
      )}

      {type === 'analysis' && (
        <div className="space-y-2">
          {sentiment && <StatusBadge variant="sentiment" sentiment={sentiment} label={sentiment} />}
          {customerTags && (
            <div className="flex flex-wrap gap-1">
              {customerTags.map((t) => <StatusBadge key={t} variant="tag" label={t} />)}
            </div>
          )}
        </div>
      )}

      {type === 'recommendations' && children}

      {aiModel && <p className="mt-2 text-[10px] text-gray-400">Model: {aiModel}</p>}
    </section>
  );
}
```

---

## 10. ActionButton

**파일:** `src/components/chat/ActionButton.tsx`

### 10.1 Props Interface

```typescript
type ActionVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'icon';
type ActionSize = 'sm' | 'md' | 'lg';

interface ActionButtonProps {
  variant?: ActionVariant;
  size?: ActionSize;
  label?: string;
  icon?: React.ReactNode;
  loading?: boolean;
  disabled?: boolean;
  fullWidth?: boolean;
  action?: 'send' | 'close' | 'crm' | 'quote' | 'contract' | 'schedule' | 'retry';
  className?: string;
  onClick: () => void;
}
```

### 10.2 Variant & Size Classes

```typescript
const variantClasses: Record<ActionVariant, string> = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700',
  secondary: 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50',
  danger: 'bg-red-600 text-white hover:bg-red-700',
  ghost: 'bg-transparent text-blue-600 hover:bg-blue-50',
  icon: 'bg-transparent text-gray-500 hover:bg-gray-100',
};

const sizeClasses: Record<ActionSize, string> = {
  sm: 'h-8 px-3 text-xs',
  md: 'h-10 px-4 text-sm',
  lg: 'h-12 px-6 text-base',
};
```

### 10.3 Implementation Snippet

```tsx
export function ActionButton({
  variant = 'primary', size = 'md', label, icon, loading, disabled, fullWidth, action, className, onClick,
}: ActionButtonProps) {
  return (
    <button
      type="button"
      disabled={disabled || loading}
      aria-busy={loading}
      aria-label={label ?? action}
      onClick={onClick}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-all',
        'active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed',
        variantClasses[variant],
        sizeClasses[size],
        fullWidth && 'w-full',
        className
      )}
    >
      {loading ? (
        <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden />
      ) : icon}
      {label && <span>{label}</span>}
    </button>
  );
}
```

---

## 11. TypingIndicator

**파일:** `src/components/chat/TypingIndicator.tsx`

### 11.1 Props Interface

```typescript
interface TypingIndicatorProps {
  userName?: string;
  userType?: 'customer' | 'agent';
  visible?: boolean;
  className?: string;
}
```

### 11.2 Implementation Snippet

```tsx
export function TypingIndicator({
  userName = '상대방', userType = 'customer', visible = false, className,
}: TypingIndicatorProps) {
  if (!visible) return null;

  return (
    <div
      role="status"
      aria-live="polite"
      aria-label={`${userName}이 입력 중`}
      className={cn(
        'flex items-center gap-1 px-4 py-1',
        userType === 'agent' ? 'justify-end' : 'justify-start',
        className
      )}
    >
      <div className="flex gap-1" aria-hidden>
        {[0, 1, 2].map((i) => (
          <span
            key={i}
            className="h-1.5 w-1.5 rounded-full bg-gray-400 animate-pulse"
            style={{ animationDelay: `${i * 0.2}s` }}
          />
        ))}
      </div>
      <span className="text-xs italic text-gray-500">{userName}이 입력 중...</span>
    </div>
  );
}
```

**Parent responsibility:** 3s auto-hide timeout on `typing:start` without `typing:stop`.

---

## 12. ChatScreen (Page Container)

**파일:** `src/pages/ChatScreen.tsx`  
**상세 통합:** [04_ChatScreen_통합_구현가이드.md](04_ChatScreen_통합_구현가이드.md)

### 12.1 Composition Tree

```
ChatScreen
├── AppHeader (60px)
├── ConnectionBanner
├── Desktop (≥1280px)
│   ├── ChatListPanel (320px)
│   │   ├── InputField (search)
│   │   ├── Tabs (filter)
│   │   └── ChatList
│   ├── ChatMessagePanel (flex-grow min 600px)
│   │   ├── RoomHeader
│   │   ├── MessageBubble × N
│   │   ├── TypingIndicator
│   │   ├── InputField (message) ref
│   │   ├── FileUpload
│   │   └── ActionButton (send)
│   └── AiAssistantPanel (320px)
│       ├── AIPanelCard (contract)
│       ├── AIPanelCard (recommendations) + RecommendationCard ×3
│       ├── AIPanelCard (faq)
│       ├── CustomerCard
│       └── ActionButton ×4
├── Tablet (768~1279px)
│   ├── ChatListPanel (280px)
│   ├── ChatMessagePanel
│   └── AiDrawer (slide-over 320px)
├── Mobile (<768px)
│   ├── Tabs (navigation)
│   └── [active tab panel]
└── AppFooter (40px)
    └── ActionButton ×4 (상담요약, CRM, 일정, 메모)
```

### 12.2 ChatScreen Skeleton

```tsx
export default function ChatScreen() {
  const layoutMode = useLayoutMode();
  const { activeRoomId, mobileTab, setActiveRoomId, setMobileTab } = useUiStore();
  const inputRef = useRef<InputFieldHandle>(null);

  const { rooms, isLoading: roomsLoading } = useChatRooms();
  const { messages, sendMessage } = useMessages(activeRoomId);
  const { recommendations, status: aiStatus } = useAiRecommendations(activeRoomId);
  const { isTyping, typingUser } = useTyping(activeRoomId);
  useSocket(activeRoomId);
  useReadReceipt(activeRoomId);

  const handleRecommendationSelect = (id: string, text: string) => {
    inputRef.current?.insertText(text);
    inputRef.current?.focus();
  };

  const handleSend = async (content: string) => {
    await sendMessage({ content, source: 'manual' });
  };

  const handleRoomSelect = (roomId: string) => {
    setActiveRoomId(roomId);
    if (layoutMode === 'mobile') setMobileTab('chat');
  };

  return (
    <div className="flex h-screen flex-col bg-gray-50">
      <AppHeader />
      <ConnectionBanner />

      {layoutMode === 'desktop' && (
        <div className="flex flex-1 overflow-hidden">
          <aside className="w-80 shrink-0 border-r border-gray-200 bg-white">
            <ChatListPanel rooms={rooms} loading={roomsLoading} onRoomSelect={handleRoomSelect} />
          </aside>
          <main className="flex min-w-[600px] flex-1 flex-col">
            <ChatMessagePanel
              roomId={activeRoomId}
              messages={messages}
              inputRef={inputRef}
              isTyping={isTyping}
              typingUser={typingUser}
              onSend={handleSend}
            />
          </main>
          <aside className="w-80 shrink-0 border-l border-gray-200 bg-white overflow-y-auto">
            <AiAssistantPanel
              roomId={activeRoomId}
              recommendations={recommendations}
              aiStatus={aiStatus}
              onRecommendationSelect={handleRecommendationSelect}
            />
          </aside>
        </div>
      )}

      {/* tablet + mobile: see 04_ChatScreen_통합_구현가이드.md */}

      <AppFooter roomId={activeRoomId} />
    </div>
  );
}
```

---

## 13. 컴포넌트 ↔ API 매핑

| Component | REST | WebSocket |
|-----------|------|-----------|
| ChatList | GET `/chats/rooms` | `room:update` |
| MessageBubble | GET/POST `/chats/{id}/messages` | `message:receive`, `read:update` |
| InputField | POST messages | `typing:start/stop` |
| FileUpload | POST `/files/upload` | `message:receive` |
| RecommendationCard | GET `/ai/recommendations/{id}` | `ai:update` |
| CustomerCard | (rooms embed) | — |
| AIPanelCard | GET `/ai/recommendations/{id}` | `ai:update` |
| TypingIndicator | — | `typing:start/stop` |
| ActionButton (send) | POST messages | — |
| ActionButton (close) | PUT `/chats/{id}/close` | `room:update` |

---

## 14. Accessibility Checklist

| Component | aria | keyboard |
|-----------|------|----------|
| MessageBubble | `role="article"` | — |
| InputField | `aria-label` | Enter submit |
| FileUpload | `aria-label="파일 첨부"` | Enter/Space |
| RecommendationCard | `role="button"` | Enter select |
| Tabs | `role="tablist"` | Arrow keys |
| ChatList | `role="listbox"` | Arrow + Enter |
| ActionButton | `aria-busy` | Enter/Space |
| TypingIndicator | `role="status"` | — |

Focus order (Desktop): ChatList → Message area → InputField → Send → AIPanel

---

## 15. Test Cases (Component Level)

| ID | Component | Test |
|----|-----------|------|
| TC-C01 | MessageBubble | variant colors match guide |
| TC-C02 | InputField | Enter sends, Shift+Enter newline |
| TC-C03 | FileUpload | 15MB rejects FILE_TOO_LARGE |
| TC-C04 | RecommendationCard | onSelect fires with id+text |
| TC-C05 | Tabs | Arrow key navigation |
| TC-C06 | ChatList | selected state border-l-4 |
| TC-C07 | AIPanelCard | loading skeleton 3 lines |
| TC-C08 | ActionButton | loading disables click |
| TC-C09 | TypingIndicator | hidden when visible=false |

---

## 부록 A. Tailwind Animation Extensions

```javascript
// tailwind.config.js extend
animation: {
  'fade-in': 'fadeIn 200ms ease-out',
  'slide-up': 'slideUp 250ms ease-out',
},
keyframes: {
  fadeIn: { from: { opacity: 0 }, to: { opacity: 1 } },
  slideUp: { from: { opacity: 0, transform: 'translateY(8px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
},
```

## 부록 B. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 5 — 11 components + ChatScreen |

---

**문서 끝 — Props SSOT는 [UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md) 이며, 본 문서는 React 구현 확장본이다.**
