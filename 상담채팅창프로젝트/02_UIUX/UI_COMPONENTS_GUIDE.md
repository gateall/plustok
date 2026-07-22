# UI Components Guide — PlusTok Enterprise (ACEP)

**Version:** 1.0  
**Status:** Design Phase  
**Created:** 2026-07-21  
**Owner:** UI/UX Team  
**Target:** Developer Ready (React 18 + TypeScript + TailwindCSS)  

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 상위 문서 | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| 화면 설계 | [01_상담채팅화면.fig.md](01_상담채팅화면.fig.md) |
| 컴포넌트 수 | 11개 |
| 스택 | React 18, TypeScript, TailwindCSS 3.x |

본 문서는 상담채팅화면에서 사용하는 **11개 UI 컴포넌트**의 Props, Events, States, 스타일 명세를 정의한다. 모든 컴포넌트명은 화면 설계 문서와 **동일한 PascalCase** 를 사용한다.

---

## 색상 팔레트 (Color Palette)

### Primary Colors

| Token | Hex | Tailwind | 용도 |
|-------|-----|----------|------|
| `--color-primary` | `#2563EB` | `blue-600` | 상담원 MessageBubble, Primary ActionButton |
| `--color-primary-hover` | `#1D4ED8` | `blue-700` | 버튼 hover |
| `--color-primary-light` | `#DBEAFE` | `blue-100` | 선택된 ChatList item |

### Semantic Colors

| Token | Hex | Tailwind | 용도 |
|-------|-----|----------|------|
| `--color-success` | `#16A34A` | `green-600` | 읽음 ✓✓, 성공 Toast |
| `--color-warning` | `#D97706` | `amber-600` | AI 분석 중, 주의사항 |
| `--color-error` | `#DC2626` | `red-600` | 전송 실패, FileUpload error |
| `--color-info` | `#0891B2` | `cyan-600` | 시스템 MessageBubble |

### Status Badge Colors

| Status | Background | Text | Border |
|--------|------------|------|--------|
| `new` (신규) | `#FEF3C7` | `#92400E` | `#FCD34D` |
| `active` (상담중) | `#D1FAE5` | `#065F46` | `#6EE7B7` |
| `closed` (종료) | `#F3F4F6` | `#6B7280` | `#D1D5DB` |

### Neutral Colors

| Token | Hex | Tailwind | 용도 |
|-------|-----|----------|------|
| `--color-bg` | `#FFFFFF` | `white` | 패널 배경 |
| `--color-bg-secondary` | `#F9FAFB` | `gray-50` | 페이지 배경 |
| `--color-border` | `#E5E7EB` | `gray-200` | 패널 구분선 |
| `--color-text-primary` | `#111827` | `gray-900` | 본문 |
| `--color-text-secondary` | `#6B7280` | `gray-500` | 보조 텍스트 |
| `--color-text-muted` | `#9CA3AF` | `gray-400` | 타임스탬프 |

### AI Panel Accent

| Token | Hex | 용도 |
|-------|-----|------|
| `--color-ai-header` | `#7C3AED` | AI Assistant 헤더 (purple-600) |
| `--color-ai-bg` | `#F5F3FF` | AIPanelCard 배경 (purple-50) |
| `--color-contract-high` | `#16A34A` | 계약확률 70+ |
| `--color-contract-mid` | `#D97706` | 계약확률 40~69 |
| `--color-contract-low` | `#DC2626` | 계약확률 0~39 |

---

## 스타일 가이드 (Style Guide)

### Typography

| Element | Font | Size | Weight | Line Height |
|---------|------|------|--------|-------------|
| Page Title | Pretendard, sans-serif | 18px | 600 | 1.4 |
| Panel Title | Pretendard | 16px | 600 | 1.4 |
| Message Body | Pretendard | 14px | 400 | 1.5 |
| Caption / Time | Pretendard | 12px | 400 | 1.3 |
| Badge | Pretendard | 11px | 500 | 1.2 |

### Spacing Scale

| Token | Value | 용도 |
|-------|-------|------|
| `--space-xs` | 4px | Badge padding |
| `--space-sm` | 8px | MessageBubble gap |
| `--space-md` | 16px | Panel padding |
| `--space-lg` | 24px | Section gap |
| `--space-xl` | 32px | Panel margin |

### Border Radius

| Token | Value | 용도 |
|-------|-------|------|
| `--radius-sm` | 4px | StatusBadge |
| `--radius-md` | 8px | InputField, Card |
| `--radius-lg` | 12px | MessageBubble |
| `--radius-full` | 9999px | Avatar, dot indicator |

### Shadow

| Token | Value | 용도 |
|-------|-------|------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Card |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.07)` | Slide-over AI panel |
| `--shadow-focus` | `0 0 0 3px rgba(37,99,235,0.3)` | InputField focus ring |

### Animation

| Name | Duration | Easing | 용도 |
|------|----------|--------|------|
| fade-in | 200ms | ease-out | MessageBubble append |
| slide-up | 250ms | ease-out | RecommendationCard |
| pulse | 1.5s infinite | ease-in-out | TypingIndicator dots |
| skeleton | 1.2s infinite | linear | AI loading |

### Icon Set

- Lucide React (primary)
- Size: 16px (inline), 20px (button), 24px (header)

---

## 컴포넌트 목록 (Index)

| # | Component | 화면 위치 | [01_상담채팅화면](01_상담채팅화면.fig.md) 참조 |
|---|-----------|----------|----------------------------------------------|
| 1 | MessageBubble | 중앙 패널 | §4-2-B |
| 2 | InputField | 중앙 패널 | §4-2-B |
| 3 | FileUpload | 중앙 패널 | §4-2-B |
| 4 | RecommendationCard | 우측 패널 | §4-2-C |
| 5 | CustomerCard | 우측 패널 | §4-2-C |
| 6 | StatusBadge | 좌측 ChatList | §4-2-A |
| 7 | Tabs | 좌측 필터 / 모바일 | §4-2-A, §4-2-E |
| 8 | ChatList | 좌측 패널 | §4-2-A |
| 9 | AIPanelCard | 우측 패널 | §4-2-C |
| 10 | ActionButton | Footer / 입력창 | §4-3 |
| 11 | TypingIndicator | 중앙 패널 | §4-2-B |

---

## 1. MessageBubble

### 설명

채팅 메시지를 표시하는 핵심 컴포넌트. 고객·상담원·시스템 메시지 variant를 지원하며, 읽음 표시·첨부파일·타임스탬프를 포함한다.

**화면 참조:** [01_상담채팅화면 §4-2-B](01_상담채팅화면.fig.md)

### Props

```typescript
interface MessageBubbleProps {
  /** 메시지 고유 ID (chat_messages.id) */
  id: string;
  /** 메시지 variant */
  variant: 'customer' | 'agent' | 'system';
  /** 메시지 본문 (plain text or markdown-lite) */
  content: string;
  /** ISO 8601 타임스탬프 */
  timestamp: string;
  /** 읽음 상태 */
  readStatus?: 'sent' | 'delivered' | 'read';
  /** 첨부파일 URL */
  attachmentUrl?: string | null;
  /** 첨부 타입 */
  attachmentType?: 'image' | 'pdf' | 'audio' | null;
  /** 전송 실패 여부 */
  failed?: boolean;
  /** 추가 CSS class */
  className?: string;
}
```

### Events

```typescript
interface MessageBubbleEvents {
  /** 재시도 클릭 (failed=true) */
  onRetry?: (messageId: string) => void;
  /** 첨부파일 클릭 (미리보기/다운로드) */
  onAttachmentClick?: (url: string, type: string) => void;
  /** 메시지 클릭 (read trigger) */
  onClick?: (messageId: string) => void;
}
```

### States

| State | 조건 | UI |
|-------|------|-----|
| default | 정상 렌더 | variant별 배경색 |
| delivering | WS 전송 중 | opacity 0.7 |
| failed | POST 5xx | ⚠️ + "재시도" link |
| read | readStatus=read | ✓✓ green |
| with-attachment | attachmentUrl 존재 | image thumbnail / pdf icon |

### Styling Notes

- max-width: 70% (중앙 패널 기준)
- customer: `bg-gray-100 text-gray-900 rounded-lg rounded-tl-none ml-0 mr-auto`
- agent: `bg-blue-600 text-white rounded-lg rounded-tr-none ml-auto mr-0`
- system: `bg-amber-100 text-amber-800 text-center mx-auto text-xs`
- timestamp: `text-xs text-gray-400 mt-1`
- read indicator: `text-xs text-green-600` (✓ delivered, ✓✓ read)

---

## 2. InputField

### 설명

메시지 입력 textarea. Enter 전송, Shift+Enter 줄바꿈, AI 추천 삽입, 검색 variant(ChatList)를 지원한다.

**화면 참조:** [01_상담채팅화면 §4-2-B](01_상담채팅화면.fig.md)

### Props

```typescript
interface InputFieldProps {
  /** variant */
  variant?: 'message' | 'search';
  /** controlled value */
  value: string;
  /** placeholder */
  placeholder?: string;
  /** 최대 글자 수 */
  maxLength?: number; // default 2000
  /** 비활성 (closed room) */
  disabled?: boolean;
  /** AI 추천 대기 힌트 */
  pendingRecommendationCount?: number;
  /** auto focus */
  autoFocus?: boolean;
  className?: string;
}
```

### Events

```typescript
interface InputFieldEvents {
  onChange: (value: string) => void;
  /** Enter (without Shift) */
  onSubmit: (value: string) => void;
  /** typing:start 트리거용 */
  onInputStart?: () => void;
  /** 3s idle → typing:stop */
  onInputStop?: () => void;
  /** AI 추천 텍스트 삽입 (외부 호출) */
  insertText?: (text: string) => void; // ref imperative handle
}
```

### States

| State | UI |
|-------|-----|
| empty | placeholder 표시 |
| focused | `--shadow-focus` ring |
| disabled | gray bg, cursor not-allowed |
| has-pending-ai | 하단 `[✓ 추천답변 N개]` badge |
| over-limit | 글자수 카운터 red |

### Styling Notes

- message variant: min-height 40px, max-height 120px, auto-resize
- search variant (ChatList): height 36px, `🔍` icon left padding
- border: `1px solid var(--color-border)`, radius `--radius-md`
- font-size: 14px

---

## 3. FileUpload

### 설명

파일·이미지 첨부 버튼 및 드래그앤드롭 영역. 업로드 진행률·오류 상태를 표시한다.

**화면 참조:** [01_상담채팅화면 §4-2-B, TC-007](01_상담채팅화면.fig.md)

### Props

```typescript
interface FileUploadProps {
  /** 허용 MIME types */
  accept?: string[]; // default ['image/jpeg','image/png','application/pdf']
  /** 최대 파일 크기 (bytes) */
  maxSize?: number; // default 10 * 1024 * 1024
  /** 다중 업로드 */
  multiple?: boolean; // default true, max 5
  /** disabled */
  disabled?: boolean;
  /** 업로드 API endpoint */
  uploadUrl?: string; // default POST /api/uploads
  className?: string;
}
```

### Events

```typescript
interface FileUploadEvents {
  /** 업로드 성공 */
  onUploadComplete: (files: UploadedFile[]) => void;
  /** 업로드 실패 */
  onUploadError: (error: FileUploadError) => void;
  /** 파일 선택 (업로드 전) */
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

### States

| State | UI |
|-------|-----|
| idle | Paperclip icon button |
| uploading | progress bar overlay |
| success | green check, file name chip |
| error | red border + error message |
| drag-over | dashed border highlight |

### Styling Notes

- button size: 36×36px, icon 20px
- drag-over: `border-2 border-dashed border-blue-400 bg-blue-50`
- error: `border-red-500 text-red-600 text-xs mt-1`
- chip: `bg-gray-100 rounded-full px-2 py-1 text-xs`

---

## 4. RecommendationCard

### 설명

AI 추천답변 카드. 클릭 시 InputField에 텍스트 삽입. 최대 3개가 AIPanelCard 하위 또는 입력창 상단에 표시된다.

**화면 참조:** [01_상담채팅화면 §4-2-C, §7.3](01_상담채팅화면.fig.md)

### Props

```typescript
interface RecommendationCardProps {
  /** ai_recommendations item id */
  id: string;
  /** 추천 답변 텍스트 */
  text: string;
  /** AI confidence 0~1 */
  confidence?: number;
  /** 순위 (1~3) */
  rank?: number;
  /** 이미 사용(전송)됨 */
  used?: boolean;
  /** disabled (AI failed) */
  disabled?: boolean;
  className?: string;
}
```

### Events

```typescript
interface RecommendationCardEvents {
  /** 클릭 → InputField insert */
  onSelect: (id: string, text: string) => void;
  /** hover preview (optional) */
  onPreview?: (text: string) => void;
}
```

### States

| State | UI |
|-------|-----|
| default | `✓` prefix, hover bg purple-100 |
| hover | `--shadow-sm`, cursor pointer |
| selected | border blue-500 (InputField에 삽입됨) |
| used | opacity 0.5, strikethrough optional |
| disabled | gray, cursor not-allowed |

### Styling Notes

- padding: 12px 16px
- bg: `var(--color-ai-bg)` (#F5F3FF)
- border: `1px solid #DDD6FE`
- border-radius: `--radius-md`
- animation: slide-up 250ms (stagger by rank)
- confidence badge: ≥0.9 green dot, ≥0.7 yellow, <0.7 gray

---

## 5. CustomerCard

### 설명

우측 AI 패널의 고객 정보 카드. `customers` 테이블 데이터를 마스킹하여 표시한다.

**화면 참조:** [01_상담채팅화면 §4-2-C](01_상담채팅화면.fig.md)

### Props

```typescript
interface CustomerCardProps {
  customerId: string;
  name: string;
  phoneMasked: string;   // "010-1234-****"
  emailMasked?: string;  // "user@****.com"
  addressMasked?: string;
  tags?: string[];       // ["신규", "고가", "긍정"]
  consultationCount?: number;
  /** compact mode (Footer) */
  compact?: boolean;
  className?: string;
}
```

### Events

```typescript
interface CustomerCardEvents {
  onEdit?: (customerId: string) => void;
  onViewHistory?: (customerId: string) => void;
  onTagClick?: (tag: string) => void;
}
```

### States

| State | UI |
|-------|-----|
| default | full info |
| compact | name + phone only (Footer) |
| loading | skeleton |
| empty | "고객 정보 없음" |

### Styling Notes

- header: name 16px font-semibold `#1E40AF`
- tags: StatusBadge mini variant (inline chips)
- phone/address: 14px `#374151`
- consultation count: 12px `#6B7280`
- compact height: 48px

---

## 6. StatusBadge

### 설명

상담방 상태(신규/상담중/종료) 및 태그 표시용 배지.

**화면 참조:** [01_상담채팅화면 §4-2-A](01_상담채팅화면.fig.md)

### Props

```typescript
type BadgeStatus = 'new' | 'active' | 'closed';
type BadgeVariant = 'status' | 'tag' | 'sentiment';

interface StatusBadgeProps {
  variant?: BadgeVariant; // default 'status'
  status?: BadgeStatus;
  label?: string;        // custom label or tag text
  sentiment?: 'positive' | 'neutral' | 'negative';
  size?: 'sm' | 'md';   // default 'sm'
  className?: string;
}
```

### Events

```typescript
interface StatusBadgeEvents {
  onClick?: () => void; // tag filter (optional)
}
```

### States

| status | label | colors |
|--------|-------|--------|
| new | 신규 | amber (see palette) |
| active | 상담중 | green |
| closed | 종료 | gray |

### Styling Notes

- sm: padding 2px 8px, font 11px
- md: padding 4px 10px, font 12px
- border-radius: `--radius-sm`
- tag variant: `bg-blue-100 text-blue-800`
- sentiment positive: green, negative: red, neutral: gray

---

## 7. Tabs

### 설명

필터 탭(ChatList: 신규/상담중/종료) 및 모바일 메인 탭(목록/채팅/AI)에 사용.

**화면 참조:** [01_상담채팅화면 §4-2-A, §4-2-E](01_상담채팅화면.fig.md)

### Props

```typescript
interface TabItem {
  id: string;
  label: string;
  badge?: number; // unread count
  disabled?: boolean;
}

interface TabsProps {
  items: TabItem[];
  activeId: string;
  variant?: 'filter' | 'navigation'; // filter=좌측, navigation=모바일
  fullWidth?: boolean; // mobile default true
  className?: string;
}
```

### Events

```typescript
interface TabsEvents {
  onChange: (tabId: string) => void;
}
```

### States

| State | UI |
|-------|-----|
| active | bottom border 2px primary, font-semibold |
| inactive | text gray-500 |
| disabled | opacity 0.4 |
| has-badge | red dot or number badge |

### Styling Notes

- filter variant: horizontal pills, gap 8px
- navigation variant: equal width, height 44px, bottom border indicator
- badge: `bg-red-500 text-white rounded-full min-w-[18px] h-[18px] text-[10px]`
- transition: border-color 150ms

---

## 8. ChatList

### 설명

좌측 상담목록 패널. `GET /api/chats/rooms` 데이터를 렌더하며 검색·필터·정렬을 지원한다.

**화면 참조:** [01_상담채팅화면 §4-2-A](01_상담채팅화면.fig.md)

### Props

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
}
```

### Events

```typescript
interface ChatListEvents {
  onRoomSelect: (roomId: string) => void;
  onSearchChange: (query: string) => void;
  onFilterChange: (statuses: string[]) => void;
  onLoadMore?: () => void; // infinite scroll
}
```

### States

| State | UI |
|-------|-----|
| loading | skeleton items ×5 |
| empty | "상담이 없습니다" illustration |
| filtered-empty | "검색 결과 없음" |
| item-selected | `bg-blue-50 border-l-4 border-blue-600` |
| item-unread | bold name + ✈️ icon |
| high-priority | contractProbability≥70 → ◉ dot |

### Styling Notes

- item height: min 72px, padding 12px 16px
- divider: `border-b border-gray-100`
- customer name: 14px semibold, selected `#1E40AF`
- inquiry type: 12px `#374151`
- time: 12px `#9CA3AF` right-aligned
- scroll: virtualized if >100 items (react-window)

---

## 9. AIPanelCard

### 설명

AI Assistant 패널 내 섹션 카드. 계약확률, FAQ, 고객분석, 로딩/오류 상태를 type별로 렌더한다.

**화면 참조:** [01_상담채팅화면 §4-2-C, §8](01_상담채팅화면.fig.md)

### Props

```typescript
type AIPanelType = 'contract' | 'faq' | 'analysis' | 'recommendations' | 'loading' | 'error';

interface FAQItem {
  question: string;
  answer: string;
}

interface AIPanelCardProps {
  type: AIPanelType;
  title?: string;
  /** contract type */
  contractProbability?: number; // 0~100
  contractLabel?: string;       // "높음 - 우선 대응"
  /** faq type */
  faqItems?: FAQItem[];
  /** analysis type */
  sentiment?: 'positive' | 'neutral' | 'negative';
  customerTags?: string[];
  /** loading/error */
  errorMessage?: string;
  aiModel?: string;
  className?: string;
}
```

### Events

```typescript
interface AIPanelCardEvents {
  onFAQClick?: (question: string, answer: string) => void;
  onRetry?: () => void; // error state
  onRefresh?: () => void;
}
```

### States

| type | UI |
|------|-----|
| contract | star rating + score + label |
| faq | collapsible Q&A list |
| analysis | sentiment icon + tags |
| recommendations | RecommendationCard container |
| loading | skeleton pulse |
| error | gray panel + "AI 분석 불가" + retry |

### Styling Notes

- header: `🤖 AI Assistant` purple-600, 16px semibold
- card bg: `--color-ai-bg`, margin-bottom 12px
- contract stars: ★ filled `#FBBF24`, ☆ empty `#D1D5DB`
- score: 24px bold, color by contract level
- faq item: clickable, hover underline
- loading: 3-line skeleton
- error: `bg-gray-100 text-gray-500`

---

## 10. ActionButton

### 설명

전송·상담종료·CRM·견적 등 액션 버튼. variant와 size로 용도별 스타일 분기.

**화면 참조:** [01_상담채팅화면 §4-3 Footer](01_상담채팅화면.fig.md)

### Props

```typescript
type ActionVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'icon';
type ActionSize = 'sm' | 'md' | 'lg';

interface ActionButtonProps {
  variant?: ActionVariant; // default 'primary'
  size?: ActionSize;     // default 'md'
  label?: string;
  icon?: React.ReactNode;
  loading?: boolean;
  disabled?: boolean;
  fullWidth?: boolean;
  /** action identifier */
  action?: 'send' | 'close' | 'crm' | 'quote' | 'contract' | 'schedule' | 'retry';
  className?: string;
}
```

### Events

```typescript
interface ActionButtonEvents {
  onClick: () => void;
}
```

### States

| State | UI |
|-------|-----|
| default | variant colors |
| hover | darken 10% |
| active | scale 0.98 |
| loading | spinner, disabled |
| disabled | opacity 0.5 |

### Variant Styles

| variant | bg | text | border |
|---------|-----|------|--------|
| primary | `#2563EB` | white | none |
| secondary | white | `#374151` | `#E5E7EB` |
| danger | `#DC2626` | white | none |
| ghost | transparent | `#2563EB` | none |
| icon | transparent | `#6B7280` | none |

### Size Specs

| size | height | padding | font |
|------|--------|---------|------|
| sm | 32px | 8px 12px | 12px |
| md | 40px | 10px 16px | 14px |
| lg | 48px | 12px 24px | 16px |

---

## 11. TypingIndicator

### 설명

상대방이 입력 중일 때 표시되는 애니메이션 인디케이터. WebSocket `typing:start` / `typing:stop` 이벤트에 연동.

**화면 참조:** [01_상담채팅화면 §4-2-B, §7.2](01_상담채팅화면.fig.md)

### Props

```typescript
interface TypingIndicatorProps {
  /** 입력 중인 사용자 이름 */
  userName?: string; // default "상대방"
  /** customer | agent */
  userType?: 'customer' | 'agent';
  visible?: boolean;
  className?: string;
}
```

### Events

```typescript
// TypingIndicator는 display-only, 이벤트 없음
// 부모 ChatScreen에서 WS 이벤트 처리:
// socket.on('typing:start') → visible=true
// socket.on('typing:stop') → visible=false
```

### States

| State | UI |
|-------|-----|
| hidden | display none |
| visible | 3-dot pulse animation + "{userName}이 입력 중..." |
| agent typing | right-aligned (customer view) |
| customer typing | left-aligned (agent view) |

### Styling Notes

- dots: 6px circles, `#9CA3AF`, pulse animation stagger 0.2s
- text: 12px italic `#6B7280`
- container: flex row, gap 4px, padding 4px 0
- auto-hide: 3s timeout (parent responsibility)
- aria: `role="status"`, `aria-live="polite"`

---

## 컴포넌트 조합 (Composition)

### ChatScreen (Page Level)

```
ChatScreen
├── Header
├── Desktop (≥1280px)
│   ├── ChatList
│   │   ├── InputField (search)
│   │   ├── Tabs (filter)
│   │   └── ChatListItem × N
│   │       └── StatusBadge
│   ├── ChatPanel
│   │   ├── MessageBubble × N
│   │   ├── TypingIndicator
│   │   ├── InputField (message)
│   │   ├── FileUpload
│   │   └── ActionButton (send)
│   └── AIPanel
│       ├── AIPanelCard (contract)
│       ├── RecommendationCard × 3
│       ├── AIPanelCard (faq)
│       ├── CustomerCard
│       └── ActionButton × 4
├── Mobile (<768px)
│   ├── Tabs (navigation)
│   └── [active tab content]
└── Footer
    └── ActionButton × 4
```

### 데이터 흐름

```
GET /api/chats/rooms → ChatList
GET /api/chats/{id}/messages → MessageBubble[]
GET /api/ai/recommendations/{id} → AIPanelCard + RecommendationCard[]
WebSocket → MessageBubble, TypingIndicator, read status
RecommendationCard.onSelect → InputField.insertText
ActionButton(send) → POST /api/chats/{id}/messages
```

---

## 접근성 (Accessibility)

| 컴포넌트 | aria | keyboard |
|----------|------|----------|
| MessageBubble | `role="article"` | - |
| InputField | `aria-label="메시지 입력"` | Enter submit |
| FileUpload | `aria-label="파일 첨부"` | Enter/Space open |
| RecommendationCard | `role="button"` | Enter select |
| Tabs | `role="tablist"` | Arrow keys |
| ChatList | `role="listbox"` | Arrow + Enter |
| ActionButton | `aria-busy` when loading | Enter/Space |
| TypingIndicator | `role="status"` | - |

Focus order (Desktop): ChatList → Message area → InputField → Send → AIPanel

---

## 파일 구조 (권장)

```
src/components/chat/
├── MessageBubble.tsx
├── InputField.tsx
├── FileUpload.tsx
├── RecommendationCard.tsx
├── CustomerCard.tsx
├── StatusBadge.tsx
├── Tabs.tsx
├── ChatList.tsx
├── AIPanelCard.tsx
├── ActionButton.tsx
├── TypingIndicator.tsx
├── ChatScreen.tsx          # page composition
├── types.ts                # shared interfaces
└── index.ts                # barrel export
```

---

## API / DB Cross-Reference

| Component | API | Table |
|-----------|-----|-------|
| ChatList | GET /api/chats/rooms | chat_rooms, customers |
| MessageBubble | GET/POST /api/chats/{id}/messages | chat_messages |
| RecommendationCard | GET /api/ai/recommendations/{id} | ai_recommendations |
| CustomerCard | (rooms API embed) | customers |
| AIPanelCard | GET /api/ai/recommendations/{id} | ai_recommendations |
| TypingIndicator | WebSocket typing:* | - |
| MessageBubble (read) | PUT /api/chats/{id}/read | chat_read_status |

---

## 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | Initial 11 components |

---

**문서 끝 — 구현 시 [상담채팅화면 UI/UX](01_상담채팅화면.fig.md) 및 [PROJECT MASTER](../00_PROJECT_MASTER.md) 와 함께 참조한다.**
