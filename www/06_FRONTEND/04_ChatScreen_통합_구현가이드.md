# ACEP (PlusTok Enterprise) — ChatScreen 통합 구현가이드

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 5 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Frontend Platform Team  
**Audience:** Frontend Developers, QA  

**적용 위치:** `www/frontend/src/pages/ChatScreen.tsx`  
**UI SSOT:** [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md)  
**컴포넌트:** [02_React_컴포넌트_구현명세.md](02_React_컴포넌트_구현명세.md)  
**Hooks:** [03_Hooks_및_상태관리.md](03_Hooks_및_상태관리.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| Page | ChatScreen — 상담원 메인 워크스페이스 |
| Layouts | Desktop 3-panel / Tablet 2-panel / Mobile tabs |
| Panels | 320 + 800 + 320 (PC reference 1440px) |
| Integration | REST + WebSocket + Zustand + React Query |

본 문서는 `ChatScreen.tsx` **전체 composition**, 반응형 layout, 이벤트 wiring, AI loading states, Footer actions, **프론트엔드 통합 테스트 체크리스트**를 정의한다.

---

## 1. ChatScreen 책임

| 책임 | 구현 |
|------|------|
| Layout breakpoint 분기 | `useLayoutMode()` |
| Active room 관리 | `useUiStore.activeRoomId` |
| Hooks orchestration | useSocket + useMessages + useAiRecommendations + ... |
| Recommendation → Input → Send | ref + event handlers |
| Mobile tab state | `useUiStore.mobileTab` |
| Tablet AI drawer | `useUiStore.aiDrawerOpen` |
| Footer global actions | AppFooter feature |

**금지:** ChatScreen 내부 direct fetch — hooks/services only.

---

## 2. PC 3-Panel Layout (≥1280px, 1440 ref)

### 2.1 Dimension Spec

| 영역 | Width | Height | Component |
|------|-------|--------|-----------|
| Header | 100% | 60px | AppHeader |
| 좌측 | 320px fixed | calc(100vh - 100px) | ChatListPanel |
| 중앙 | flex-grow min 600px | calc(100vh - 100px) | ChatMessagePanel |
| 우측 | 320px fixed | calc(100vh - 100px) | AiAssistantPanel |
| Footer | 100% | 40px | AppFooter |

### 2.2 Desktop JSX Structure

```tsx
function DesktopLayout() {
  return (
    <div className="flex flex-1 overflow-hidden">
      {/* Left — 320px */}
      <aside
        className="flex w-80 shrink-0 flex-col border-r border-gray-200 bg-white"
        aria-label="상담 목록"
      >
        <ChatListPanel />
      </aside>

      {/* Center — flex-grow */}
      <main className="flex min-w-[600px] flex-1 flex-col bg-white" aria-label="채팅">
        <ChatMessagePanel />
      </main>

      {/* Right — 320px */}
      <aside
        className="flex w-80 shrink-0 flex-col overflow-y-auto border-l border-gray-200 bg-white"
        aria-label="AI Assistant"
      >
        <AiAssistantPanel />
      </aside>
    </div>
  );
}
```

### 2.3 ASCII Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│ Header (60px)                                                       │
├──────────────┬──────────────────────────────────┬─────────────────┤
│ ChatList     │  MessageBubble + InputField      │  AI Assistant   │
│ (320px)      │  (flex-grow, min 600px)          │  (320px)        │
├──────────────┴──────────────────────────────────┴─────────────────┤
│ Footer (40px)                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Tablet 2-Panel + Collapsible AI (768~1279px)

### 3.1 Layout

| 영역 | Width | Notes |
|------|-------|-------|
| ChatList | 280px fixed | 좌측 |
| ChatMessage | flex-grow | 중앙 |
| AI Panel | 320px overlay | slide-over, default hidden |

### 3.2 Tablet JSX

```tsx
function TabletLayout() {
  const { aiDrawerOpen, setAiDrawerOpen } = useUiStore();

  return (
    <div className="relative flex flex-1 overflow-hidden">
      <aside className="flex w-[280px] shrink-0 flex-col border-r border-gray-200 bg-white">
        <ChatListPanel />
      </aside>

      <main className="relative flex flex-1 flex-col">
        <ChatMessagePanel
          headerAction={
            <ActionButton
              variant="secondary"
              size="sm"
              label="AI"
              onClick={() => setAiDrawerOpen(true)}
            />
          }
        />
      </main>

      {/* Slide-over backdrop */}
      {aiDrawerOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/30"
          onClick={() => setAiDrawerOpen(false)}
          aria-hidden
        />
      )}

      {/* AI Drawer */}
      <aside
        className={cn(
          'fixed right-0 top-[60px] z-50 flex h-[calc(100vh-100px)] w-80 flex-col',
          'border-l border-gray-200 bg-white shadow-md transition-transform duration-250',
          aiDrawerOpen ? 'translate-x-0' : 'translate-x-full'
        )}
        aria-label="AI Assistant"
        aria-hidden={!aiDrawerOpen}
      >
        <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
          <span className="font-semibold text-purple-600">🤖 AI Assistant</span>
          <ActionButton variant="ghost" size="sm" label="닫기" onClick={() => setAiDrawerOpen(false)} />
        </div>
        <div className="flex-1 overflow-y-auto p-4">
          <AiAssistantPanel />
        </div>
      </aside>
    </div>
  );
}
```

### 3.3 Keyboard

- ESC → `setAiDrawerOpen(false)`
- Focus trap in drawer (V1.5)

---

## 4. Mobile Tabs (<768px, 375 ref)

### 4.1 Tab Configuration

```typescript
const MOBILE_TABS: TabItem[] = [
  { id: 'list', label: '목록', badge: totalUnread },
  { id: 'chat', label: '채팅' },
  { id: 'ai', label: 'AI', badge: pendingAiBadge },
];
```

### 4.2 Default Tab Logic

```typescript
useEffect(() => {
  if (layoutMode !== 'mobile') return;
  if (activeRoomId && mobileTab === 'list') {
    // user just selected room — auto switch handled in handleRoomSelect
  } else if (!activeRoomId) {
    setMobileTab('list');
  }
}, [activeRoomId, layoutMode]);
```

| Condition | Default Tab |
|-----------|-------------|
| No active room | 목록 |
| Room selected | 채팅 (auto on select) |
| AI update + not on AI | AI tab badge increment |

### 4.3 Mobile JSX

```tsx
function MobileLayout() {
  const { mobileTab, setMobileTab, pendingAiBadge, clearPendingAiBadge } = useUiStore();

  useEffect(() => {
    if (mobileTab === 'ai') clearPendingAiBadge();
  }, [mobileTab, clearPendingAiBadge]);

  return (
    <div className="flex flex-1 flex-col overflow-hidden">
      <Tabs
        variant="navigation"
        fullWidth
        items={[
          { id: 'list', label: '목록' },
          { id: 'chat', label: '채팅' },
          { id: 'ai', label: 'AI', badge: pendingAiBadge || undefined },
        ]}
        activeId={mobileTab}
        onChange={(id) => setMobileTab(id as MobileTab)}
      />

      <div className="flex-1 overflow-hidden" role="tabpanel" id={`tabpanel-${mobileTab}`}>
        {mobileTab === 'list' && <ChatListPanel className="h-full" />}
        {mobileTab === 'chat' && <ChatMessagePanel className="h-full" />}
        {mobileTab === 'ai' && <AiAssistantPanel className="h-full overflow-y-auto p-4" />}
      </div>
    </div>
  );
}
```

---

## 5. Feature Panels Detail

### 5.1 ChatListPanel

```tsx
// features/chat/ChatListPanel.tsx
export function ChatListPanel({ className }: { className?: string }) {
  const { filterStatus, searchQuery, setFilterStatus, setSearchQuery, activeRoomId, setActiveRoomId, setMobileTab } = useUiStore();
  const { rooms, isLoading, fetchNextPage } = useChatRooms();
  const layoutMode = useLayoutMode();

  const handleRoomSelect = (roomId: string) => {
    setActiveRoomId(roomId);
    if (layoutMode === 'mobile') setMobileTab('chat');
  };

  return (
    <div className={cn('flex h-full flex-col', className)}>
      <div className="border-b border-gray-200 p-3">
        <Tabs
          variant="filter"
          items={[
            { id: 'new', label: '신규' },
            { id: 'active', label: '상담중' },
            { id: 'closed', label: '종료' },
          ]}
          activeId={filterStatus[0] ?? 'new'}
          onChange={(id) => setFilterStatus([id as RoomStatus])}
        />
      </div>
      <ChatList
        rooms={rooms}
        selectedRoomId={activeRoomId}
        loading={isLoading}
        searchQuery={searchQuery}
        onRoomSelect={handleRoomSelect}
        onSearchChange={setSearchQuery}
        onFilterChange={(s) => setFilterStatus(s as RoomStatus[])}
        onLoadMore={() => fetchNextPage()}
        className="flex-1"
      />
    </div>
  );
}
```

### 5.2 ChatMessagePanel

```tsx
// features/chat/ChatMessagePanel.tsx
export function ChatMessagePanel({ className, headerAction }: Props) {
  const { activeRoomId } = useUiStore();
  const { rooms } = useChatRooms();
  const { messages, sendMessage, retryMessage, isSending } = useMessages(activeRoomId);
  const { isTyping, typingUser, emitTypingStart, emitTypingStop } = useTyping(activeRoomId);
  const { recommendations } = useAiRecommendations(activeRoomId);

  const inputRef = useRef<InputFieldHandle>(null);
  const [draft, setDraft] = useState('');
  const [attachments, setAttachments] = useState<UploadedFile[]>([]);
  const scrollRef = useRef<HTMLDivElement>(null);

  const activeRoom = rooms.find((r) => r.id === activeRoomId);
  const isClosed = activeRoom?.status === 'closed';

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
  }, [messages.length, isTyping]);

  const handleRecommendationFromPanel = (id: string, text: string) => {
    inputRef.current?.insertText(text);
    inputRef.current?.focus();
  };

  const handleSend = async () => {
    if (!draft.trim() && attachments.length === 0) return;
    emitTypingStop();
    await sendMessage({
      content: draft.trim(),
      attachmentUrl: attachments[0]?.url ?? null,
      source: 'manual',
    });
    setDraft('');
    setAttachments([]);
  };

  if (!activeRoomId) {
    return (
      <div className="flex flex-1 items-center justify-center text-gray-400">
        상담을 선택해 주세요
      </div>
    );
  }

  return (
    <div className={cn('flex h-full flex-col', className)}>
      {/* Room Header */}
      <header className="flex h-12 shrink-0 items-center justify-between border-b border-gray-200 px-4">
        <div>
          <h1 className="text-sm font-semibold text-gray-900">{activeRoom?.customerName}</h1>
          <p className="text-xs text-gray-500">{activeRoom?.inquiryType}</p>
        </div>
        {headerAction}
      </header>

      {/* Messages */}
      <div ref={scrollRef} className="flex-1 overflow-y-auto py-4" onClick={() => {/* read trigger */}}>
        {messages.map((msg) => (
          <MessageBubble
            key={msg.id}
            id={msg.id}
            variant={msg.senderType}
            content={msg.content}
            timestamp={msg.createdAt}
            readStatus={msg.readStatus}
            attachmentUrl={msg.attachmentUrl}
            attachmentType={msg.attachmentType}
            failed={msg.failed}
            onRetry={() => retryMessage(msg)}
          />
        ))}
        <TypingIndicator
          visible={isTyping}
          userName={typingUser?.name}
          userType={typingUser?.type}
        />
      </div>

      {/* Input Area */}
      {!isClosed && (
        <footer className="shrink-0 border-t border-gray-200 p-3">
          <div className="flex items-end gap-2">
            <FileUpload
              disabled={isSending}
              onUploadComplete={(files) => setAttachments(files)}
              onUploadError={(e) => toast.error(e.message)}
            />
            <div className="flex-1">
              <InputField
                ref={inputRef}
                value={draft}
                onChange={setDraft}
                onInputStart={emitTypingStart}
                onInputStop={emitTypingStop}
                onSubmit={handleSend}
                pendingRecommendationCount={recommendations.length}
                disabled={isSending}
              />
            </div>
            <ActionButton
              action="send"
              variant="primary"
              icon={<Send size={18} />}
              loading={isSending}
              onClick={handleSend}
              aria-label="전송"
            />
          </div>
        </footer>
      )}
    </div>
  );
}
```

### 5.3 AiAssistantPanel

```tsx
// features/chat/AiAssistantPanel.tsx
export function AiAssistantPanel({ className, onRecommendationSelect }: Props) {
  const { activeRoomId } = useUiStore();
  const { rawRooms } = useChatRooms();
  const {
    recommendations, faq, contractProbability, contractLabel,
    sentiment, customerTags, aiModel, status, isLoading, isFailed, refetch,
  } = useAiRecommendations(activeRoomId);

  const room = rawRooms.find((r) => r.id === activeRoomId);
  const customer = room?.customer;

  if (!activeRoomId) {
    return <p className="p-4 text-sm text-gray-400">상담을 선택하면 AI 분석이 표시됩니다.</p>;
  }

  if (isLoading || status === 'pending' || status === 'processing') {
    return (
      <div className={className}>
        <AIPanelCard type="loading" title="🤖 AI Assistant" />
      </div>
    );
  }

  if (isFailed) {
    return (
      <div className={className}>
        <AIPanelCard type="error" errorMessage="AI 분석 불가" onRetry={() => refetch()} />
      </div>
    );
  }

  return (
    <div className={cn('space-y-3 p-4', className)}>
      <AIPanelCard
        type="contract"
        title="계약확률"
        contractProbability={contractProbability}
        contractLabel={contractLabel}
        aiModel={aiModel}
      />

      <AIPanelCard type="recommendations" title="추천답변">
        <div className="space-y-2">
          {recommendations.slice(0, 3).map((rec, i) => (
            <RecommendationCard
              key={rec.id}
              id={rec.id}
              text={rec.text}
              confidence={rec.confidence}
              rank={rec.rank ?? i + 1}
              onSelect={(id, text) => onRecommendationSelect?.(id, text)}
            />
          ))}
        </div>
      </AIPanelCard>

      {faq.length > 0 && (
        <AIPanelCard
          type="faq"
          title="FAQ"
          faqItems={faq}
          onFAQClick={(q, a) => onRecommendationSelect?.('', a)}
        />
      )}

      {customer && (
        <CustomerCard
          customerId={customer.id}
          name={customer.name}
          phoneMasked={customer.phoneMasked}
          emailMasked={customer.emailMasked}
          addressMasked={customer.addressMasked}
          tags={customerTags ?? customer.tags}
          consultationCount={customer.consultationCount}
        />
      )}

      <div className="grid grid-cols-2 gap-2">
        <ActionButton variant="secondary" size="sm" label="CRM" action="crm" onClick={() => {}} />
        <ActionButton variant="secondary" size="sm" label="견적" action="quote" onClick={() => {}} />
        <ActionButton variant="primary" size="sm" label="계약" action="contract" onClick={() => {}} />
        <ActionButton variant="secondary" size="sm" label="일정" action="schedule" onClick={() => {}} />
      </div>
    </div>
  );
}
```

---

## 6. Event Wiring — RecommendationCard → InputField → POST

### 6.1 Sequence Diagram

```
User clicks RecommendationCard
    │
    ├─ onSelect(id, text)
    │     └─ InputField ref.insertText(text)
    │           └─ draft state updated
    │           └─ focus textarea
    │
User clicks ActionButton(send) OR Enter
    │
    ├─ handleSend()
    │     ├─ emitTypingStop()
    │     └─ sendMessage({ content, source: 'manual' | 'ai_recommendation' })
    │           ├─ onMutate: optimistic MessageBubble
    │           ├─ POST /api/v1/chats/{id}/messages
    │           └─ WS message:receive → replace tempId
    │
Backend triggers AI Router
    └─ WS ai:update → refetch recommendations
```

### 6.2 Source Tracking (AI Recommendation Send)

```typescript
const [lastSelectedRecId, setLastSelectedRecId] = useState<string | null>(null);

const handleRecommendationSelect = (id: string, text: string) => {
  inputRef.current?.insertText(text);
  setLastSelectedRecId(id);
};

const handleSend = async () => {
  await sendMessage({
    content: draft,
    source: lastSelectedRecId ? 'ai_recommendation' : 'manual',
    recommendationId: lastSelectedRecId ?? undefined,
  });
  setLastSelectedRecId(null);
  setDraft('');
};
```

### 6.3 FAQ Click → Input

FAQ answer 클릭 시 동일 `insertText` flow:

```typescript
onFAQClick={(q, a) => {
  inputRef.current?.insertText(a);
  if (layoutMode === 'mobile') setMobileTab('chat');
}}
```

---

## 7. AI Panel Loading States

### 7.1 State Machine

```
[pending] ──(worker start)──→ [processing]
     │                              │
     │                              ├──(success)──→ [completed] → render cards
     │                              │
     └──────────────────────────────┴──(fail)──→ [failed] → error + retry
```

### 7.2 UI Timing ([01_상담채팅화면 §9](../02_UIUX/01_상담채팅화면.fig.md))

| Duration | UI |
|----------|-----|
| 0~2s | AIPanelCard skeleton |
| 2~5s | skeleton + "분석 중..." text |
| 5s+ | "AI 분석 중... 잠시만 기다려주세요" |
| failed | gray panel + "AI 분석 불가" + retry |

### 7.3 Implementation

```tsx
function AiLoadingState({ status, elapsedMs }: { status: AiStatus; elapsedMs: number }) {
  const message =
    elapsedMs > 5000 ? 'AI 분석 중... 잠시만 기다려주세요'
    : elapsedMs > 2000 ? '분석 중...'
    : undefined;

  return (
    <AIPanelCard type="loading">
      {message && <p className="mt-2 text-xs text-amber-600">{message}</p>}
    </AIPanelCard>
  );
}

// In AiAssistantPanel — track elapsed since status became pending/processing
const [aiStartTime] = useState(() => Date.now());
const elapsedMs = Date.now() - aiStartTime;
```

### 7.4 WS + Polling Dual Sync

1. `ai:update` WS → invalidate React Query
2. `refetchInterval: 2000` while status pending/processing
3. On completed → stop polling, render RecommendationCard ×3

---

## 8. Footer Actions

### 8.1 Footer Spec ([§4-3](../02_UIUX/01_상담채팅화면.fig.md))

| Button | variant | Action |
|--------|---------|--------|
| 상담요약 | secondary | AI 요약 모달 |
| 고객카드 | secondary | CustomerCard 확장 modal |
| 일정 | primary | 일정 생성 (V2.0 stub) |
| 메모 | secondary | room memo editor |

### 8.2 AppFooter Implementation

```tsx
// features/chat/ChatFooter.tsx
export function AppFooter({ roomId }: { roomId: string | null }) {
  const [summaryOpen, setSummaryOpen] = useState(false);
  const [customerOpen, setCustomerOpen] = useState(false);
  const { rawRooms } = useChatRooms();
  const room = rawRooms.find((r) => r.id === roomId);

  return (
    <footer className="flex h-10 shrink-0 items-center gap-2 border-t border-gray-200 bg-white px-4">
      <ActionButton
        variant="secondary"
        size="sm"
        label="상담요약"
        disabled={!roomId}
        onClick={() => setSummaryOpen(true)}
      />
      <ActionButton
        variant="secondary"
        size="sm"
        label="고객카드"
        disabled={!room?.customer}
        onClick={() => setCustomerOpen(true)}
      />
      <ActionButton
        variant="primary"
        size="sm"
        label="일정"
        action="schedule"
        disabled={!roomId}
        onClick={() => toast('일정 생성은 V2.0에서 제공됩니다.')}
      />
      <ActionButton
        variant="secondary"
        size="sm"
        label="메모"
        disabled={!roomId}
        onClick={() => {/* memo modal */}}
      />

      <div className="ml-auto">
        <ActionButton
          variant="danger"
          size="sm"
          label="상담종료"
          action="close"
          disabled={!roomId || room?.status === 'closed'}
          onClick={() => handleCloseConsultation(roomId!)}
        />
      </div>

      {summaryOpen && <SummaryModal roomId={roomId!} onClose={() => setSummaryOpen(false)} />}
      {customerOpen && room?.customer && (
        <CustomerModal customer={room.customer} onClose={() => setCustomerOpen(false)} />
      )}
    </footer>
  );
}

async function handleCloseConsultation(roomId: string) {
  if (!confirm('상담을 종료하시겠습니까?')) return;
  await api.put(`/chats/${roomId}/close`, {});
  toast.success('상담이 종료되었습니다.');
}
```

---

## 9. Full ChatScreen.tsx

```tsx
// src/pages/ChatScreen.tsx
import { useEffect } from 'react';
import { AppHeader } from '../components/layout/AppHeader';
import { ConnectionBanner } from '../components/ui/ConnectionBanner';
import { AppFooter } from '../features/chat/ChatFooter';
import { ChatListPanel } from '../features/chat/ChatListPanel';
import { ChatMessagePanel } from '../features/chat/ChatMessagePanel';
import { AiAssistantPanel } from '../features/chat/AiAssistantPanel';
import { useLayoutMode } from '../hooks/useMediaQuery';
import { useSocket } from '../hooks/useSocket';
import { useUiStore } from '../stores/ui.store';
import { DesktopLayout, TabletLayout, MobileLayout } from '../features/chat/layouts';

export default function ChatScreen() {
  const layoutMode = useLayoutMode();
  const { activeRoomId } = useUiStore();
  const { isConnected, isReconnecting } = useSocket({ roomId: activeRoomId });

  // ESC closes tablet AI drawer
  const { aiDrawerOpen, setAiDrawerOpen } = useUiStore();
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && aiDrawerOpen) setAiDrawerOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [aiDrawerOpen, setAiDrawerOpen]);

  return (
    <div className="flex h-screen flex-col bg-gray-50">
      <AppHeader />
      <ConnectionBanner connected={isConnected} reconnecting={isReconnecting} />

      {layoutMode === 'desktop' && <DesktopLayout />}
      {layoutMode === 'tablet' && <TabletLayout />}
      {layoutMode === 'mobile' && <MobileLayout />}

      <AppFooter roomId={activeRoomId} />
    </div>
  );
}
```

---

## 10. Connection & Error UX

### 10.1 ConnectionBanner States

| State | Banner Text | Action |
|-------|-------------|--------|
| connected | hidden | — |
| reconnecting | "재연결 중..." | auto |
| disconnected 30s | "연결 끊김. [새로고침]" | refresh button |

### 10.2 Empty States

| Panel | Empty Message |
|-------|---------------|
| ChatList | "상담이 없습니다" |
| ChatMessage | "상담을 선택해 주세요" |
| AI Panel | "상담을 선택하면 AI 분석이 표시됩니다" |

---

## 11. Integration Test Checklist

프론트엔드 개발자용 통합 테스트 체크리스트. QA TC와 매핑: [01_상담채팅화면 §10](../02_UIUX/01_상담채팅화면.fig.md)

### 11.1 Auth & Routing

| # | Check | Expected |
|---|-------|----------|
| IT-01 | Unauthenticated `/chat` | Redirect `/login` |
| IT-02 | Login success | Token in memory, navigate `/chat` |
| IT-03 | 401 on API | Auto refresh → retry |
| IT-04 | Refresh fail | Redirect `/login` |
| IT-05 | Logout | Clear token, disconnect socket |

### 11.2 ChatList Panel

| # | Check | Expected |
|---|-------|----------|
| IT-10 | Load rooms | GET /chats/rooms, skeleton → list |
| IT-11 | Filter tabs | status query param updates |
| IT-12 | Search | debounced search param |
| IT-13 | Select room | activeRoomId set, highlight border-l-4 |
| IT-14 | Infinite scroll | fetchNextPage at bottom |
| IT-15 | room:update WS | list item refreshes |

### 11.3 Message Panel

| # | Check | Expected |
|---|-------|----------|
| IT-20 | Load messages | GET /messages, chronological order |
| IT-21 | Send message | optimistic bubble → WS replace |
| IT-22 | Send fail | failed state + retry |
| IT-23 | Enter send | message posted |
| IT-24 | Shift+Enter | newline, no send |
| IT-25 | File attach | upload → send with attachmentUrl |
| IT-26 | Closed room | input disabled |
| IT-27 | message:receive | append customer bubble |

### 11.4 Typing & Read

| # | Check | Expected |
|---|-------|----------|
| IT-30 | Input typing | typing:start emitted |
| IT-31 | 3s idle | typing:stop emitted |
| IT-32 | Peer typing | TypingIndicator visible |
| IT-33 | Room focus | PUT /read for unread customer msgs |
| IT-34 | read:update WS | ✓✓ on agent bubbles |

### 11.5 AI Panel

| # | Check | Expected |
|---|-------|----------|
| IT-40 | Initial load | skeleton while pending |
| IT-41 | ai:update completed | RecommendationCard ×1~3 |
| IT-42 | Click recommendation | text in InputField |
| IT-43 | Send AI text | POST source=ai_recommendation |
| IT-44 | AI failed | error panel + retry |
| IT-45 | Contract score | stars + color by level |
| IT-46 | FAQ click | answer in InputField |

### 11.6 Responsive

| # | Check | Viewport | Expected |
|---|-------|----------|----------|
| IT-50 | Desktop 3-panel | 1440px | 320+flex+320 visible |
| IT-51 | Tablet 2-panel | 768px | list+chat, AI drawer |
| IT-52 | Mobile tabs | 375px | Tabs navigation |
| IT-53 | Mobile room select | 375px | auto switch to 채팅 tab |
| IT-54 | AI badge mobile | 375px | badge on AI tab |

### 11.7 Footer

| # | Check | Expected |
|---|-------|----------|
| IT-60 | 상담요약 | modal opens |
| IT-61 | 상담종료 | confirm → PUT close → StatusBadge 종료 |
| IT-62 | Footer disabled | no room selected |

### 11.8 WebSocket Resilience

| # | Check | Expected |
|---|-------|----------|
| IT-70 | Disconnect 5s | reconnect + room:join |
| IT-71 | Gap fill | refetch messages after reconnect |
| IT-72 | UNAUTHORIZED | refresh + reconnect |

---

## 12. Performance Checklist

| Item | Target | Verify |
|------|--------|--------|
| Initial ChatScreen load | ≤3s | Lighthouse |
| Message send perceived | ≤0.1s | optimistic UI |
| AI panel after customer msg | ≤2s | ai:update + fetch |
| ChatList scroll 100+ items | 60fps | react-window |
| Bundle size (gzip) | <500KB | vite build analyze |

---

## 13. File Dependency Graph

```
ChatScreen.tsx
├── features/chat/ChatListPanel.tsx → useChatRooms, ChatList, Tabs
├── features/chat/ChatMessagePanel.tsx → useMessages, useTyping, MessageBubble, InputField, FileUpload, ActionButton
├── features/chat/AiAssistantPanel.tsx → useAiRecommendations, AIPanelCard, RecommendationCard, CustomerCard
├── features/chat/ChatFooter.tsx → ActionButton, api.client
├── hooks/useSocket.ts
├── hooks/useReadReceipt.ts
├── stores/ui.store.ts
└── hooks/useMediaQuery.ts
```

---

## 부록 A. ChatScreen Props (none — page level)

ChatScreen is a route page with no external props. All data from hooks + store.

## 부록 B. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 5 — ChatScreen 통합 가이드 |

---

**문서 끝 — 구현 시 본 문서를 ChatScreen 개발 SSOT로 사용한다.**
