# ACEP (PlusTok Enterprise) — Hooks 및 상태관리

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 5 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Frontend Platform Team  
**Audience:** Frontend Developers  

**적용 위치:** `www/frontend/src/hooks/`, `stores/`, `types/`  
**API SSOT:** [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)  
**WS SSOT:** [05_CHAT/04_WebSocket_프로토콜_명세.md](../05_CHAT/04_WebSocket_프로토콜_명세.md)  
**아키텍처:** [01_Frontend_아키텍처.md](01_Frontend_아키텍처.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| Server State | TanStack Query (React Query) v5 |
| Client UI State | Zustand v4 |
| Custom Hooks | 6개 (useSocket, useChatRooms, useMessages, useAiRecommendations, useTyping, useReadReceipt) |
| Types | `chat.types.ts`, `socket-events.ts` |

본 문서는 ACEP Frontend의 **데이터 fetching, WebSocket 구독, optimistic update, 캐시 invalidation** 패턴을 정의한다.

---

## 1. 상태 관리 전략

### 1.1 Server State vs Client State

| 분류 | 도구 | 예시 |
|------|------|------|
| **Server State** | React Query | rooms, messages, AI recommendations |
| **Client UI State** | Zustand | activeRoomId, mobileTab, aiDrawerOpen |
| **Ephemeral WS** | useState in hooks | typing indicator, connection status |
| **Auth Token** | module variable | accessToken (memory) |

### 1.2 Decision Matrix

| 데이터 | React Query | Zustand | 이유 |
|--------|:-----------:|:-------:|------|
| chat rooms list | ✅ | | server source, cache |
| messages history | ✅ | | pagination, invalidation |
| AI recommendations | ✅ | | fetch on ai:update |
| activeRoomId | | ✅ | UI selection, no server |
| mobileTab | | ✅ | layout only |
| filter/search UI | | ✅ | debounce → query key |
| typing visible | | | local useState (ephemeral) |
| optimistic temp msg | ✅ cache | | queryClient.setQueryData |

---

## 2. TypeScript Types (`chat.types.ts`)

### 2.1 API Response Types

```typescript
// src/types/api.types.ts
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  error: ApiError | null;
  timestamp: string;
}

export interface ApiError {
  message: string;
  code: string;
}

export interface PaginationMeta {
  page: number;
  limit: number;
  total: number;
}
```

### 2.2 Chat Domain Types

```typescript
// src/types/chat.types.ts

export type RoomStatus = 'new' | 'active' | 'closed';
export type SenderType = 'customer' | 'agent' | 'system';
export type ReadStatus = 'sent' | 'delivered' | 'read';
export type AiStatus = 'pending' | 'processing' | 'completed' | 'failed';
export type MessageSource = 'manual' | 'ai_recommendation';

export interface CustomerSummary {
  id: string;
  name: string;
  phoneMasked: string;
  emailMasked?: string;
  addressMasked?: string;
  tags?: string[];
  consultationCount?: number;
}

export interface ChatRoom {
  id: string;
  customer: CustomerSummary;
  inquiryType: string;
  status: RoomStatus;
  unreadCount: number;
  contractProbability?: number;
  updatedAt: string;
  agentId?: string | null;
}

export interface ChatRoomsResponse {
  rooms: ChatRoom[];
  pagination: PaginationMeta;
}

export interface ChatMessage {
  id: string;
  roomId: string;
  senderType: SenderType;
  senderId?: string;
  content: string;
  attachmentUrl?: string | null;
  attachmentType?: 'image' | 'pdf' | 'audio' | null;
  createdAt: string;
  readStatus?: ReadStatus;
  /** optimistic only */
  tempId?: string;
  failed?: boolean;
}

export interface MessagesResponse {
  messages: ChatMessage[];
  hasMore: boolean;
}

export interface SendMessageRequest {
  content: string;
  attachmentUrl?: string | null;
  source?: MessageSource;
  recommendationId?: string;
}

export interface SendMessageResponse {
  messageId: string;
  createdAt: string;
}

export interface AiRecommendationItem {
  id: string;
  text: string;
  confidence?: number;
  rank?: number;
}

export interface FAQItem {
  question: string;
  answer: string;
}

export interface AiRecommendationsResponse {
  contractProbability?: number;
  contractLabel?: string;
  sentiment?: 'positive' | 'neutral' | 'negative';
  customerTags?: string[];
  recommendations: AiRecommendationItem[];
  faq: FAQItem[];
  aiModel?: string;
  status: AiStatus;
}

export interface ReadReceiptRequest {
  messageIds: string[];
  readerType: 'agent' | 'customer';
}

export interface ReadReceiptResponse {
  updatedCount: number;
}

export interface ChatRoomsQueryParams {
  status?: string;
  search?: string;
  page?: number;
  limit?: number;
  sort?: string;
}

export interface MessagesQueryParams {
  page?: number;
  limit?: number;
  before?: string;
}
```

### 2.3 Socket Event Types

```typescript
// src/types/socket-events.ts
// SSOT: 05_CHAT/04_WebSocket_프로토콜_명세.md §11

export interface RoomJoinPayload {
  roomId: string;
}

export interface RoomUpdatePayload {
  roomId: string;
  status?: RoomStatus;
  agentId?: string;
  unreadCount?: number;
  contractProbability?: number;
  updatedAt: string;
}

export interface MessageReceivePayload {
  messageId: string;
  roomId: string;
  content: string;
  senderType: SenderType;
  senderId: string;
  attachmentUrl?: string | null;
  attachmentType?: string | null;
  timestamp: string;
  tempId?: string;
}

export interface AiUpdatePayload {
  roomId: string;
  recommendationId: string;
  status: AiStatus;
  contractProbability?: number | null;
  timestamp: string;
}

export interface ReadUpdatePayload {
  roomId: string;
  messageId: string;
  readerType: 'customer' | 'agent';
  readAt: string;
}

export interface TypingPayload {
  roomId: string;
  userId: string;
  userName?: string;
  userType?: 'customer' | 'agent';
}

export interface SocketErrorPayload {
  code: string;
  message: string;
  tempId?: string;
  details?: Record<string, unknown>;
}

export interface ClientToServerEvents {
  'room:join': (payload: RoomJoinPayload) => void;
  'room:leave': (payload: RoomJoinPayload) => void;
  'message:send': (payload: unknown) => void;
  'typing:start': (payload: { roomId: string }) => void;
  'typing:stop': (payload: { roomId: string }) => void;
}

export interface ServerToClientEvents {
  'room:joined': (payload: RoomJoinPayload & { timestamp: string }) => void;
  'message:receive': (payload: MessageReceivePayload) => void;
  'typing:start': (payload: TypingPayload) => void;
  'typing:stop': (payload: Pick<TypingPayload, 'roomId' | 'userId'>) => void;
  'ai:update': (payload: AiUpdatePayload) => void;
  'read:update': (payload: ReadUpdatePayload) => void;
  'room:update': (payload: RoomUpdatePayload) => void;
  error: (payload: SocketErrorPayload) => void;
  pong: (payload: { ts: number }) => void;
}
```

---

## 3. Zustand UI Store

```typescript
// src/stores/ui.store.ts
import { create } from 'zustand';
import { devtools } from 'zustand/middleware';

export type MobileTab = 'list' | 'chat' | 'ai';

interface UiState {
  activeRoomId: string | null;
  mobileTab: MobileTab;
  aiDrawerOpen: boolean;
  filterStatus: ('new' | 'active' | 'closed')[];
  searchQuery: string;
  selectedRecommendationId: string | null;
  pendingAiBadge: number;

  setActiveRoomId: (id: string | null) => void;
  setMobileTab: (tab: MobileTab) => void;
  setAiDrawerOpen: (open: boolean) => void;
  setFilterStatus: (statuses: UiState['filterStatus']) => void;
  setSearchQuery: (q: string) => void;
  setSelectedRecommendationId: (id: string | null) => void;
  incrementPendingAiBadge: () => void;
  clearPendingAiBadge: () => void;
}

export const useUiStore = create<UiState>()(
  devtools(
    (set) => ({
      activeRoomId: null,
      mobileTab: 'list',
      aiDrawerOpen: false,
      filterStatus: ['new', 'active'],
      searchQuery: '',
      selectedRecommendationId: null,
      pendingAiBadge: 0,

      setActiveRoomId: (id) => set({ activeRoomId: id }),
      setMobileTab: (tab) => set({ mobileTab: tab }),
      setAiDrawerOpen: (open) => set({ aiDrawerOpen: open }),
      setFilterStatus: (statuses) => set({ filterStatus: statuses }),
      setSearchQuery: (q) => set({ searchQuery: q }),
      setSelectedRecommendationId: (id) => set({ selectedRecommendationId: id }),
      incrementPendingAiBadge: () => set((s) => ({ pendingAiBadge: s.pendingAiBadge + 1 })),
      clearPendingAiBadge: () => set({ pendingAiBadge: 0 }),
    }),
    { name: 'acep-ui' }
  )
);
```

### 3.1 Store Usage Rules

- `activeRoomId` 변경 시 hooks가 `room:leave` / `room:join` 처리
- Mobile: room 선택 → `setMobileTab('chat')`
- AI `ai:update` + mobile not on AI tab → `incrementPendingAiBadge()`

---

## 4. Query Keys

```typescript
// src/utils/queryKeys.ts
export const queryKeys = {
  me: () => ['auth', 'me'] as const,
  rooms: (params?: Record<string, unknown>) => ['chat', 'rooms', params] as const,
  messages: (roomId: string) => ['chat', 'messages', roomId] as const,
  aiRecommendations: (roomId: string) => ['ai', 'recommendations', roomId] as const,
};
```

---

## 5. useSocket.ts

**파일:** `src/hooks/useSocket.ts`  
**역할:** connect, reconnect, event subscriptions, cleanup

### 5.1 Interface

```typescript
export interface UseSocketOptions {
  roomId: string | null;
  enabled?: boolean;
}

export interface UseSocketReturn {
  isConnected: boolean;
  isReconnecting: boolean;
  lastError: string | null;
}
```

### 5.2 Full Implementation

```typescript
import { useEffect, useRef, useState, useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { getSocket, connectSocket } from '../services/socket.client';
import { tokenStore } from '../utils/token';
import { refreshAccessToken } from '../services/auth.service';
import { queryKeys } from '../utils/queryKeys';
import { showApiError } from '../utils/toast';
import type {
  MessageReceivePayload,
  AiUpdatePayload,
  ReadUpdatePayload,
  RoomUpdatePayload,
  TypingPayload,
  SocketErrorPayload,
} from '../types/socket-events';
import type { ChatMessage } from '../types/chat.types';

export function useSocket({ roomId, enabled = true }: UseSocketOptions): UseSocketReturn {
  const queryClient = useQueryClient();
  const [isConnected, setIsConnected] = useState(false);
  const [isReconnecting, setIsReconnecting] = useState(false);
  const [lastError, setLastError] = useState<string | null>(null);
  const prevRoomIdRef = useRef<string | null>(null);

  const handleMessageReceive = useCallback(
    (msg: MessageReceivePayload) => {
      queryClient.setQueryData<ChatMessage[]>(
        queryKeys.messages(msg.roomId),
        (old = []) => {
          if (msg.tempId) {
            const idx = old.findIndex((m) => m.tempId === msg.tempId);
            if (idx >= 0) {
              const next = [...old];
              next[idx] = mapWsMessageToChatMessage(msg);
              return next;
            }
          }
          if (old.some((m) => m.id === msg.messageId)) return old;
          return [...old, mapWsMessageToChatMessage(msg)];
        }
      );
      queryClient.invalidateQueries({ queryKey: queryKeys.rooms() });
    },
    [queryClient]
  );

  const handleAiUpdate = useCallback(
    (payload: AiUpdatePayload) => {
      if (payload.status === 'completed' || payload.status === 'failed') {
        queryClient.invalidateQueries({
          queryKey: queryKeys.aiRecommendations(payload.roomId),
        });
      }
    },
    [queryClient]
  );

  const handleReadUpdate = useCallback(
    (payload: ReadUpdatePayload) => {
      queryClient.setQueryData<ChatMessage[]>(
        queryKeys.messages(payload.roomId),
        (old = []) =>
          old.map((m) =>
            m.id === payload.messageId ? { ...m, readStatus: 'read' as const } : m
          )
      );
    },
    [queryClient]
  );

  const handleRoomUpdate = useCallback(
    (payload: RoomUpdatePayload) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rooms() });
    },
    [queryClient]
  );

  useEffect(() => {
    if (!enabled) return;

    const token = tokenStore.get();
    if (!token) return;

    const socket = connectSocket(token);

    const onConnect = () => {
      setIsConnected(true);
      setIsReconnecting(false);
      setLastError(null);
      if (roomId) socket.emit('room:join', { roomId });
    };

    const onDisconnect = () => {
      setIsConnected(false);
      setIsReconnecting(true);
    };

    const onConnectError = async (err: Error) => {
      if (err.message === 'UNAUTHORIZED') {
        try {
          const newToken = await refreshAccessToken();
          socket.auth = { token: newToken };
          socket.connect();
        } catch {
          setLastError('UNAUTHORIZED');
        }
      } else {
        setLastError(err.message);
      }
    };

    socket.on('connect', onConnect);
    socket.on('disconnect', onDisconnect);
    socket.on('connect_error', onConnectError);
    socket.on('message:receive', handleMessageReceive);
    socket.on('ai:update', handleAiUpdate);
    socket.on('read:update', handleReadUpdate);
    socket.on('room:update', handleRoomUpdate);
    socket.on('error', (e: SocketErrorPayload) => showApiError({ code: e.code, message: e.message }));

    if (socket.connected) onConnect();

    return () => {
      socket.off('connect', onConnect);
      socket.off('disconnect', onDisconnect);
      socket.off('connect_error', onConnectError);
      socket.off('message:receive', handleMessageReceive);
      socket.off('ai:update', handleAiUpdate);
      socket.off('read:update', handleReadUpdate);
      socket.off('room:update', handleRoomUpdate);
      if (roomId) socket.emit('room:leave', { roomId });
    };
  }, [enabled, handleMessageReceive, handleAiUpdate, handleReadUpdate, handleRoomUpdate]);

  // Room switch
  useEffect(() => {
    const socket = getSocket();
    if (!socket?.connected) return;

    const prev = prevRoomIdRef.current;
    if (prev && prev !== roomId) {
      socket.emit('room:leave', { roomId: prev });
    }
    if (roomId) {
      socket.emit('room:join', { roomId });
    }
    prevRoomIdRef.current = roomId;
  }, [roomId]);

  return { isConnected, isReconnecting, lastError };
}

function mapWsMessageToChatMessage(msg: MessageReceivePayload): ChatMessage {
  return {
    id: msg.messageId,
    roomId: msg.roomId,
    senderType: msg.senderType,
    senderId: msg.senderId,
    content: msg.content,
    attachmentUrl: msg.attachmentUrl,
    attachmentType: msg.attachmentType as ChatMessage['attachmentType'],
    createdAt: msg.timestamp,
    readStatus: 'delivered',
    tempId: msg.tempId,
  };
}
```

### 5.3 Reconnect Recovery

On reconnect (after disconnect >5s):

```typescript
// ChatScreen or useSocket extension
async function syncAfterReconnect(roomId: string) {
  await queryClient.invalidateQueries({ queryKey: queryKeys.messages(roomId) });
  await queryClient.invalidateQueries({ queryKey: queryKeys.aiRecommendations(roomId) });
  await queryClient.invalidateQueries({ queryKey: queryKeys.rooms() });
}
```

---

## 6. useChatRooms.ts

**파일:** `src/hooks/useChatRooms.ts`  
**API:** `GET /api/v1/chats/rooms`

### 6.1 Implementation

```typescript
import { useInfiniteQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { api } from '../services/api.client';
import { queryKeys } from '../utils/queryKeys';
import { useUiStore } from '../stores/ui.store';
import { formatRelativeTime } from '../utils/format';
import type { ChatRoomsResponse, ChatRoom } from '../types/chat.types';
import type { ChatRoomItem } from '../components/chat/ChatList';

export function useChatRooms() {
  const { filterStatus, searchQuery, activeRoomId } = useUiStore();

  const statusParam = filterStatus.join(',');

  const query = useInfiniteQuery({
    queryKey: queryKeys.rooms({ status: statusParam, search: searchQuery }),
    queryFn: async ({ pageParam = 1 }) => {
      const params = new URLSearchParams({
        page: String(pageParam),
        limit: '20',
        sort: 'updated_at:desc',
      });
      if (statusParam) params.set('status', statusParam);
      if (searchQuery) params.set('search', searchQuery);

      return api.get<ChatRoomsResponse>(`/chats/rooms?${params}`);
    },
    getNextPageParam: (lastPage) => {
      const { page, limit, total } = lastPage.pagination;
      return page * limit < total ? page + 1 : undefined;
    },
    initialPageParam: 1,
    staleTime: 15_000,
  });

  const rooms: ChatRoomItem[] = useMemo(() => {
    const allRooms = query.data?.pages.flatMap((p) => p.rooms) ?? [];
    return sortRooms(allRooms).map(mapRoomToListItem(activeRoomId));
  }, [query.data, activeRoomId]);

  return {
    rooms,
    rawRooms: query.data?.pages.flatMap((p) => p.rooms) ?? [],
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    fetchNextPage: query.fetchNextPage,
    hasNextPage: query.hasNextPage,
    refetch: query.refetch,
  };
}

function sortRooms(rooms: ChatRoom[]): ChatRoom[] {
  const statusOrder = { new: 0, active: 1, closed: 2 };
  return [...rooms].sort((a, b) => {
    const sa = statusOrder[a.status] - statusOrder[b.status];
    if (sa !== 0) return sa;
    const cp = (b.contractProbability ?? 0) - (a.contractProbability ?? 0);
    if (cp !== 0) return cp;
    return new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime();
  });
}

function mapRoomToListItem(activeRoomId: string | null) {
  return (room: ChatRoom): ChatRoomItem => ({
    id: room.id,
    customerName: room.customer.name,
    inquiryType: room.inquiryType,
    status: room.status,
    relativeTime: formatRelativeTime(room.updatedAt),
    unreadCount: room.unreadCount,
    contractProbability: room.contractProbability,
    isSelected: room.id === activeRoomId,
  });
}
```

### 6.2 Filters & Pagination

| Param | Source | Default |
|-------|--------|---------|
| status | `useUiStore.filterStatus` | new,active |
| search | `useUiStore.searchQuery` | '' |
| page | infinite query | 1 |
| limit | fixed | 20 |
| sort | fixed | updated_at:desc |

---

## 7. useMessages.ts

**파일:** `src/hooks/useMessages.ts`  
**API:** `GET/POST /api/v1/chats/{id}/messages`

### 7.1 Implementation

```typescript
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { api } from '../services/api.client';
import { queryKeys } from '../utils/queryKeys';
import { showApiError } from '../utils/toast';
import type { ChatMessage, MessagesResponse, SendMessageRequest, SendMessageResponse } from '../types/chat.types';

export function useMessages(roomId: string | null) {
  const queryClient = useQueryClient();

  const query = useInfiniteQuery({
    queryKey: queryKeys.messages(roomId ?? ''),
    queryFn: async ({ pageParam }) => {
      if (!roomId) throw new Error('NO_ROOM');
      const params = new URLSearchParams({ limit: '50' });
      if (pageParam) params.set('before', pageParam as string);
      return api.get<MessagesResponse>(`/chats/${roomId}/messages?${params}`);
    },
    getNextPageParam: (lastPage, _pages, _lastPageParam, allPages) => {
      if (!lastPage.hasMore) return undefined;
      const oldest = lastPage.messages[lastPage.messages.length - 1];
      return oldest?.createdAt;
    },
    initialPageParam: undefined as string | undefined,
    enabled: !!roomId,
    staleTime: 5_000,
  });

  const messages: ChatMessage[] =
    query.data?.pages
      .flatMap((p) => p.messages)
      .sort((a, b) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()) ?? [];

  const sendMutation = useMutation({
    mutationFn: async (body: SendMessageRequest & { tempId?: string }) => {
      if (!roomId) throw new Error('NO_ROOM');
      return api.post<SendMessageResponse>(`/chats/${roomId}/messages`, body);
    },
    onMutate: async (variables) => {
      const tempId = variables.tempId ?? `temp-${Date.now()}`;
      await queryClient.cancelQueries({ queryKey: queryKeys.messages(roomId!) });

      const optimistic: ChatMessage = {
        id: tempId,
        tempId,
        roomId: roomId!,
        senderType: 'agent',
        content: variables.content,
        attachmentUrl: variables.attachmentUrl,
        createdAt: new Date().toISOString(),
        readStatus: 'sent',
      };

      queryClient.setQueryData<ChatMessage[]>(queryKeys.messages(roomId!), (old = []) => [
        ...old,
        optimistic,
      ]);

      return { tempId };
    },
    onSuccess: (_data, _vars, context) => {
      // WS message:receive will replace optimistic via tempId
      queryClient.invalidateQueries({ queryKey: queryKeys.rooms() });
    },
    onError: (error, variables, context) => {
      if (!roomId || !context?.tempId) return;
      queryClient.setQueryData<ChatMessage[]>(queryKeys.messages(roomId), (old = []) =>
        old.map((m) => (m.tempId === context.tempId ? { ...m, failed: true } : m))
      );
      showApiError(error as { code: string; message: string });
    },
  });

  const sendMessage = useCallback(
    async (body: SendMessageRequest) => {
      const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2)}`;
      return sendMutation.mutateAsync({ ...body, tempId });
    },
    [sendMutation]
  );

  const retryMessage = useCallback(
    async (failedMessage: ChatMessage) => {
      if (!roomId) return;
      queryClient.setQueryData<ChatMessage[]>(queryKeys.messages(roomId), (old = []) =>
        old.filter((m) => m.id !== failedMessage.id)
      );
      await sendMessage({
        content: failedMessage.content,
        attachmentUrl: failedMessage.attachmentUrl,
        source: 'manual',
      });
    },
    [roomId, queryClient, sendMessage]
  );

  return {
    messages,
    isLoading: query.isLoading,
    isSending: sendMutation.isPending,
    hasMore: query.hasNextPage,
    fetchOlder: query.fetchNextPage,
    sendMessage,
    retryMessage,
    refetch: query.refetch,
  };
}
```

### 7.2 Optimistic Update Flow

```
sendMessage()
  → onMutate: append temp message (readStatus=sent)
  → POST /messages → 201
  → WS message:receive { tempId } → replace temp with real id
  → onError: mark failed=true, show toast
```

---

## 8. useAiRecommendations.ts

**파일:** `src/hooks/useAiRecommendations.ts`  
**API:** `GET /api/v1/ai/recommendations/{roomId}`  
**WS:** `ai:update` listener (via useSocket invalidation)

### 8.1 Implementation

```typescript
import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { api } from '../services/api.client';
import { queryKeys } from '../utils/queryKeys';
import { getSocket } from '../services/socket.client';
import { useUiStore } from '../stores/ui.store';
import type { AiRecommendationsResponse, AiStatus } from '../types/chat.types';
import type { AiUpdatePayload } from '../types/socket-events';

export function useAiRecommendations(roomId: string | null) {
  const { mobileTab, incrementPendingAiBadge } = useUiStore();

  const query = useQuery({
    queryKey: queryKeys.aiRecommendations(roomId ?? ''),
    queryFn: () => {
      if (!roomId) throw new Error('NO_ROOM');
      return api.get<AiRecommendationsResponse>(`/ai/recommendations/${roomId}`);
    },
    enabled: !!roomId,
    staleTime: 10_000,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      if (status === 'pending' || status === 'processing') return 2000;
      return false;
    },
  });

  // Direct listener for mobile badge (useSocket also invalidates)
  useEffect(() => {
    const socket = getSocket();
    if (!socket || !roomId) return;

    const handler = (payload: AiUpdatePayload) => {
      if (payload.roomId !== roomId) return;
      if (payload.status === 'completed' && mobileTab !== 'ai') {
        incrementPendingAiBadge();
      }
    };

    socket.on('ai:update', handler);
    return () => { socket.off('ai:update', handler); };
  }, [roomId, mobileTab, incrementPendingAiBadge]);

  const status: AiStatus = query.data?.status ?? 'pending';

  const contractLabel =
    query.data?.contractProbability !== undefined
      ? query.data.contractProbability >= 70
        ? '높음 - 우선 대응'
        : query.data.contractProbability >= 40
          ? '보통'
          : '낮음'
      : undefined;

  return {
    data: query.data,
    recommendations: query.data?.recommendations ?? [],
    faq: query.data?.faq ?? [],
    contractProbability: query.data?.contractProbability,
    contractLabel,
    sentiment: query.data?.sentiment,
    customerTags: query.data?.customerTags,
    aiModel: query.data?.aiModel,
    status,
    isLoading: query.isLoading || status === 'pending',
    isProcessing: status === 'processing',
    isFailed: status === 'failed',
    isCompleted: status === 'completed',
    refetch: query.refetch,
  };
}
```

### 8.2 AI Status → UI Mapping

| status | AIPanelCard type | UI |
|--------|------------------|-----|
| pending | loading | skeleton |
| processing | loading | "분석 중..." |
| completed | contract/recommendations/faq | full render |
| failed | error | retry button |

---

## 9. useTyping.ts

**파일:** `src/hooks/useTyping.ts`  
**WS:** `typing:start`, `typing:stop` with debounce

### 9.1 Implementation

```typescript
import { useEffect, useRef, useState, useCallback } from 'react';
import { getSocket } from '../services/socket.client';
import type { TypingPayload } from '../types/socket-events';

const TYPING_IDLE_MS = 3000;
const TYPING_AUTO_HIDE_MS = 3000;

export function useTyping(roomId: string | null) {
  const [isTyping, setIsTyping] = useState(false);
  const [typingUser, setTypingUser] = useState<{ name: string; type: 'customer' | 'agent' } | null>(null);
  const idleTimerRef = useRef<ReturnType<typeof setTimeout>>();
  const hideTimerRef = useRef<ReturnType<typeof setTimeout>>();
  const isTypingRef = useRef(false);

  // Listen for peer typing
  useEffect(() => {
    const socket = getSocket();
    if (!socket || !roomId) return;

    const onStart = (payload: TypingPayload) => {
      if (payload.roomId !== roomId) return;
      setIsTyping(true);
      setTypingUser({
        name: payload.userName ?? '상대방',
        type: payload.userType ?? 'customer',
      });
      clearTimeout(hideTimerRef.current);
      hideTimerRef.current = setTimeout(() => {
        setIsTyping(false);
        setTypingUser(null);
      }, TYPING_AUTO_HIDE_MS);
    };

    const onStop = (payload: { roomId: string; userId: string }) => {
      if (payload.roomId !== roomId) return;
      setIsTyping(false);
      setTypingUser(null);
    };

    socket.on('typing:start', onStart);
    socket.on('typing:stop', onStop);
    return () => {
      socket.off('typing:start', onStart);
      socket.off('typing:stop', onStop);
    };
  }, [roomId]);

  const emitTypingStart = useCallback(() => {
    const socket = getSocket();
    if (!socket?.connected || !roomId) return;

    if (!isTypingRef.current) {
      socket.emit('typing:start', { roomId });
      isTypingRef.current = true;
    }

    clearTimeout(idleTimerRef.current);
    idleTimerRef.current = setTimeout(() => {
      socket.emit('typing:stop', { roomId });
      isTypingRef.current = false;
    }, TYPING_IDLE_MS);
  }, [roomId]);

  const emitTypingStop = useCallback(() => {
    const socket = getSocket();
    if (!socket?.connected || !roomId) return;
    clearTimeout(idleTimerRef.current);
    if (isTypingRef.current) {
      socket.emit('typing:stop', { roomId });
      isTypingRef.current = false;
    }
  }, [roomId]);

  return {
    isTyping,
    typingUser,
    emitTypingStart,
    emitTypingStop,
  };
}
```

### 9.2 InputField Integration

```tsx
<InputField
  value={draft}
  onChange={setDraft}
  onInputStart={emitTypingStart}
  onInputStop={emitTypingStop}
  onSubmit={async (text) => {
    emitTypingStop();
    await sendMessage({ content: text });
    setDraft('');
  }}
/>
```

**BR-TYPE-001~004:** [01_상담채팅화면 §7.2](../02_UIUX/01_상담채팅화면.fig.md)

---

## 10. useReadReceipt.ts

**파일:** `src/hooks/useReadReceipt.ts`  
**API:** `PUT /api/v1/chats/{id}/read`

### 10.1 Implementation

```typescript
import { useEffect, useRef, useCallback } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../services/api.client';
import type { ChatMessage, ReadReceiptRequest, ReadReceiptResponse } from '../types/chat.types';

export function useReadReceipt(roomId: string | null, messages: ChatMessage[], roomStatus?: string) {
  const markedRef = useRef<Set<string>>(new Set());

  const mutation = useMutation({
    mutationFn: (body: ReadReceiptRequest) => {
      if (!roomId) throw new Error('NO_ROOM');
      return api.put<ReadReceiptResponse>(`/chats/${roomId}/read`, body);
    },
  });

  const markAsRead = useCallback(
    (messageIds: string[]) => {
      if (!roomId || roomStatus === 'closed') return;
      const unread = messageIds.filter((id) => !markedRef.current.has(id));
      if (unread.length === 0) return;

      unread.forEach((id) => markedRef.current.add(id));
      mutation.mutate({ messageIds: unread, readerType: 'agent' });
    },
    [roomId, roomStatus, mutation]
  );

  // Auto-read on room focus: customer messages not yet read
  useEffect(() => {
    if (!roomId || !messages.length) return;

    const unreadCustomerIds = messages
      .filter((m) => m.senderType === 'customer' && m.readStatus !== 'read')
      .map((m) => m.id);

    if (unreadCustomerIds.length > 0) {
      markAsRead(unreadCustomerIds);
    }
  }, [roomId, messages, markAsRead]);

  return { markAsRead, isUpdating: mutation.isPending };
}
```

### 10.2 Business Rules

| Rule | Implementation |
|------|----------------|
| BR-READ-002 | room active + messages loaded → PUT read |
| BR-READ-003 | no auto-read without room focus (effect runs when room selected) |
| BR-READ-004 | skip if roomStatus === 'closed' |

---

## 11. Hooks Composition in ChatScreen

```typescript
function ChatScreenInner() {
  const { activeRoomId } = useUiStore();
  const { rooms, isLoading: roomsLoading, fetchNextPage } = useChatRooms();
  const { messages, sendMessage, retryMessage, isSending } = useMessages(activeRoomId);
  const ai = useAiRecommendations(activeRoomId);
  const { isTyping, typingUser, emitTypingStart, emitTypingStop } = useTyping(activeRoomId);
  const { isConnected, isReconnecting } = useSocket({ roomId: activeRoomId });
  useReadReceipt(activeRoomId, messages, rooms.find((r) => r.id === activeRoomId)?.status);

  // ... render panels
}
```

---

## 12. Cache Invalidation Matrix

| Trigger | Action |
|---------|--------|
| POST message success | invalidate rooms |
| message:receive WS | setQueryData messages + invalidate rooms |
| ai:update completed | invalidate aiRecommendations |
| room:update WS | invalidate rooms |
| read:update WS | setQueryData messages (readStatus) |
| PUT read success | (WS handles peer; local already updated) |
| room switch | no invalidate (queries keyed by roomId) |
| logout | queryClient.clear() |

---

## 13. Error Handling in Hooks

| Hook | Error | Handling |
|------|-------|----------|
| useChatRooms | network | error boundary + retry button |
| useMessages | MSG_SEND_FAILED | optimistic failed state |
| useAiRecommendations | AI_ALL_FAILED | isFailed + retry |
| useSocket | UNAUTHORIZED | refresh token |
| useReadReceipt | silent fail | log only (non-blocking) |

---

## 14. Testing Hooks

```typescript
// test/hooks/useMessages.test.ts
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useMessages } from '../../hooks/useMessages';

const wrapper = ({ children }) => (
  <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
);

test('sendMessage adds optimistic message', async () => {
  const { result } = renderHook(() => useMessages('room-1'), { wrapper });
  await result.current.sendMessage({ content: 'test', source: 'manual' });
  await waitFor(() => expect(result.current.messages.some((m) => m.content === 'test')).toBe(true));
});
```

---

## 부록 A. Hook File Checklist

| File | Export | Dependencies |
|------|--------|--------------|
| useSocket.ts | `useSocket` | socket.client, queryClient |
| useChatRooms.ts | `useChatRooms` | api.client, ui.store |
| useMessages.ts | `useMessages` | api.client, queryClient |
| useAiRecommendations.ts | `useAiRecommendations` | api.client, ui.store |
| useTyping.ts | `useTyping` | socket.client |
| useReadReceipt.ts | `useReadReceipt` | api.client |

## 부록 B. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 5 — Hooks 및 상태관리 |

---

**문서 끝 — 타입 SSOT는 API/WS 문서와 동기화 유지한다.**
