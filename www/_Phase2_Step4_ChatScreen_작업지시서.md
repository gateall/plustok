# 🎯 Phase 2 Step 4 — React ChatScreen + useSocket 훅

**프로젝트:** PlusTok V3.0  
**Phase:** 2 (Chat & AI)  
**Step:** 4 (Frontend React)  
**현재 상태:** Step 3 (AI Router/PHP) 완료 ✅  
**목표:** 3-panel ChatScreen UI + WebSocket 연동  
**예상 소요시간:** 3~4일  
**작성일:** 2026-07-21  

---

## 📍 현재 상황 정리

### ✅ Step 3까지 완료
```
✅ chat-server/ TypeScript + Socket.io v4
✅ 이벤트 정의 (SSOT 05_CHAT/01_WebSocket설계.md)
✅ JWT 인증 미들웨어
✅ PHP REST 연동 (MessageService)
✅ AI Router (PHP ai_call() 단일 진입점)
✅ Redis pub/sub (ai:update 브로드캐스트)
✅ API 추가 (retry, settings 등)
```

### ❌ Step 4 할 일
```
❌ React ChatScreen Component (3-panel UI)
   ├─ Left: ChatRoomList (채팅방 목록)
   ├─ Center: MessageList (메시지 목록)
   └─ Right: AIRecommendation (AI 추천)

❌ useSocket Hook (Socket.io 클라이언트)
   ├─ 연결 관리
   ├─ 이벤트 구독
   └─ 에러 핸들링

❌ 메시지 상태 관리 (React Query)
   ├─ 캐싱 (SoT = REST, notify = WS)
   ├─ Optimistic Update
   └─ 오프라인 처리

❌ 타입 정의 (TypeScript)
❌ 테스트 (Vitest)
```

---

## 🎯 Step 4 상세 작업 계획

### Task 4.1: TypeScript 타입 정의 (0.5일)

```typescript
// src/types/socket-events.ts
export interface SocketEvents {
  // C→S
  'room:join': (payload: RoomJoinPayload) => void
  'room:leave': (payload: RoomLeavePayload) => void
  'message:send': (payload: MessageSendPayload) => void
  'typing:start': (payload: TypingPayload) => void
  'typing:stop': (payload: TypingPayload) => void
  
  // S→C
  'room:joined': (payload: RoomJoinedPayload) => void
  'message:receive': (payload: MessageReceivePayload) => void
  'typing:start': (payload: TypingIndicatorPayload) => void
  'typing:stop': (payload: TypingIndicatorPayload) => void
  'ai:update': (payload: AiUpdatePayload) => void
  'error': (error: ErrorPayload) => void
}

// Payload 타입들
export interface RoomJoinPayload {
  room_id: number
  user_id: number
  user_type: 'customer' | 'agent'
  auth_token: string
}

export interface MessageSendPayload {
  room_id: number
  content: string
  message_type: 'text' | 'image' | 'file'
  file_id?: number
}

export interface MessageReceivePayload {
  message_id: number
  sender_id: number
  sender_type: 'agent' | 'customer' | 'ai'
  content: string
  message_type: string
  created_at: string
  read_at?: string
}

export interface AiUpdatePayload {
  room_id: number
  recommendation_id: number
  status: 'pending' | 'processing' | 'completed' | 'failed'
  data?: {
    contractProbability?: number
    sentiment?: string
    category?: string
    recommendation?: string
  }
  error?: string
}

export interface TypingPayload {
  room_id: number
  user_id: number
}

export interface TypingIndicatorPayload {
  room_id: number
  user_id: number
  is_typing: boolean
}

// DB/API 모델
export interface ChatMessage {
  id: number
  room_id: number
  sender_id: number
  sender_type: 'agent' | 'customer' | 'ai'
  sender_name?: string
  content: string
  message_type: string
  created_at: string
  read_at?: string
  status: 'pending' | 'sent' | 'delivered' | 'read'
}

export interface ChatRoom {
  id: number
  customer_id: number
  agent_id?: number
  customer_name: string
  agent_name?: string
  last_message?: string
  last_message_at?: string
  unread_count: number
  status: 'active' | 'closed'
}

export interface AiRecommendation {
  id: number
  room_id: number
  status: 'pending' | 'processing' | 'completed' | 'failed'
  contractProbability?: number
  sentiment?: string
  category?: string
  recommendation?: string
  error?: string
  created_at: string
  updated_at: string
}
```

✅ 검증:
```
[ ] 모든 이벤트 타입 정의?
[ ] Payload 타입 완전?
[ ] API 응답 타입 매칭?
```

---

### Task 4.2: useSocket Hook 구현 (1일)

```typescript
// src/hooks/useSocket.ts
import { useEffect, useRef, useCallback, useState } from 'react'
import io, { Socket } from 'socket.io-client'
import type { SocketEvents, MessageReceivePayload, AiUpdatePayload } from '@/types'

interface UseSocketOptions {
  url?: string
  reconnection?: boolean
  reconnectionDelay?: number
}

export const useSocket = (options: UseSocketOptions = {}) => {
  const socketRef = useRef<Socket<SocketEvents> | null>(null)
  const [isConnected, setIsConnected] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const connect = useCallback((token: string) => {
    const url = options.url || import.meta.env.VITE_WS_URL || 'http://localhost:3001'
    
    socketRef.current = io(url, {
      auth: {
        token, // JWT (Bearer 없음)
      },
      reconnection: options.reconnection ?? true,
      reconnectionDelay: options.reconnectionDelay ?? 1000,
      transports: ['websocket', 'polling'],
    })

    socketRef.current.on('connect', () => {
      setIsConnected(true)
      setError(null)
    })

    socketRef.current.on('disconnect', () => {
      setIsConnected(false)
    })

    socketRef.current.on('error', (errorData) => {
      setError(errorData.message)
    })
  }, [options])

  const disconnect = useCallback(() => {
    if (socketRef.current) {
      socketRef.current.disconnect()
      socketRef.current = null
      setIsConnected(false)
    }
  }, [])

  const emit = useCallback(<K extends keyof SocketEvents>(
    event: K,
    payload: Parameters<SocketEvents[K]>[0]
  ) => {
    if (socketRef.current?.connected) {
      socketRef.current.emit(event, payload)
    }
  }, [])

  const on = useCallback(<K extends keyof SocketEvents>(
    event: K,
    callback: (payload: Parameters<SocketEvents[K]>[0]) => void
  ) => {
    if (!socketRef.current) return () => {}
    
    socketRef.current.on(event, callback)
    
    return () => {
      socketRef.current?.off(event, callback)
    }
  }, [])

  const off = useCallback(<K extends keyof SocketEvents>(
    event: K,
    callback?: (payload: Parameters<SocketEvents[K]>[0]) => void
  ) => {
    if (socketRef.current) {
      socketRef.current.off(event, callback)
    }
  }, [])

  useEffect(() => {
    return () => {
      disconnect()
    }
  }, [disconnect])

  return {
    socket: socketRef.current,
    isConnected,
    error,
    connect,
    disconnect,
    emit,
    on,
    off,
  }
}

// 사용 예:
/*
const { emit, on, isConnected } = useSocket()

useEffect(() => {
  const unsubscribe = on('message:receive', (msg) => {
    console.log('메시지:', msg)
  })
  
  return unsubscribe
}, [on])

const sendMessage = (content: string) => {
  emit('message:send', {
    room_id: 123,
    content,
    message_type: 'text',
  })
}
*/
```

✅ 검증:
```
[ ] 연결/재연결 정상?
[ ] 이벤트 emit/on 동작?
[ ] 에러 처리 완전?
[ ] 타입 추론 정확?
[ ] 메모리 누수 없음?
```

---

### Task 4.3: ChatScreen 3-panel UI (1.5일)

```typescript
// src/components/Chat/ChatScreen.tsx
import { useEffect, useState, useRef } from 'react'
import { useSocket } from '@/hooks/useSocket'
import { ChatRoomList } from './panels/ChatRoomList'
import { MessageList } from './panels/MessageList'
import { MessageInput } from './panels/MessageInput'
import { AIRecommendation } from './panels/AIRecommendation'
import type { ChatRoom, ChatMessage, AiRecommendation } from '@/types'

export const ChatScreen = () => {
  const { emit, on, isConnected } = useSocket()
  const [selectedRoom, setSelectedRoom] = useState<ChatRoom | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [rooms, setRooms] = useState<ChatRoom[]>([])
  const [aiRec, setAiRec] = useState<AiRecommendation | null>(null)
  const [isTyping, setIsTyping] = useState(false)

  // 채팅방 입장
  useEffect(() => {
    if (!selectedRoom || !isConnected) return

    emit('room:join', {
      room_id: selectedRoom.id,
      user_id: 123, // 실제는 auth에서
      user_type: 'agent',
      auth_token: localStorage.getItem('token') || '',
    })
  }, [selectedRoom, isConnected, emit])

  // 메시지 수신
  useEffect(() => {
    const unsubscribe = on('message:receive', (msg: MessageReceivePayload) => {
      if (msg.room_id === selectedRoom?.id) {
        setMessages((prev) => [...prev, {
          id: msg.message_id,
          room_id: msg.room_id,
          sender_id: msg.sender_id,
          sender_type: msg.sender_type,
          content: msg.content,
          message_type: msg.message_type,
          created_at: msg.created_at,
          read_at: msg.read_at,
          status: msg.read_at ? 'read' : 'delivered',
        }])
      }
    })
    return unsubscribe
  }, [selectedRoom, on])

  // AI 업데이트 수신
  useEffect(() => {
    const unsubscribe = on('ai:update', (data: AiUpdatePayload) => {
      if (data.room_id === selectedRoom?.id) {
        setAiRec({
          id: data.recommendation_id,
          room_id: data.room_id,
          status: data.status,
          contractProbability: data.data?.contractProbability,
          sentiment: data.data?.sentiment,
          category: data.data?.category,
          recommendation: data.data?.recommendation,
          error: data.error,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        })
      }
    })
    return unsubscribe
  }, [selectedRoom, on])

  // 입력중 표시
  useEffect(() => {
    if (!isTyping || !selectedRoom) return

    const timer = setTimeout(() => {
      emit('typing:stop', {
        room_id: selectedRoom.id,
        user_id: 123,
      })
      setIsTyping(false)
    }, 3000)

    return () => clearTimeout(timer)
  }, [isTyping, selectedRoom, emit])

  const handleSendMessage = (content: string) => {
    if (!selectedRoom) return

    // Optimistic update
    const pendingMsg: ChatMessage = {
      id: -1,
      room_id: selectedRoom.id,
      sender_id: 123,
      sender_type: 'agent',
      content,
      message_type: 'text',
      created_at: new Date().toISOString(),
      status: 'pending',
    }
    setMessages((prev) => [...prev, pendingMsg])

    // 전송
    emit('message:send', {
      room_id: selectedRoom.id,
      content,
      message_type: 'text',
    })
  }

  const handleTyping = () => {
    if (!selectedRoom || isTyping) return
    
    setIsTyping(true)
    emit('typing:start', {
      room_id: selectedRoom.id,
      user_id: 123,
    })
  }

  return (
    <div className="flex h-screen">
      {/* Left Panel: ChatRoomList (320px) */}
      <div className="w-80 border-r border-gray-200 overflow-y-auto">
        <ChatRoomList
          rooms={rooms}
          selectedRoom={selectedRoom}
          onSelectRoom={setSelectedRoom}
        />
      </div>

      {/* Center Panel: MessageList (800px) */}
      <div className="flex-1 flex flex-col">
        <div className="flex-1 overflow-y-auto">
          <MessageList messages={messages} />
        </div>
        <MessageInput
          onSend={handleSendMessage}
          onTyping={handleTyping}
          isLoading={!isConnected}
        />
      </div>

      {/* Right Panel: AIRecommendation (320px) */}
      <div className="w-80 border-l border-gray-200 overflow-y-auto">
        <AIRecommendation
          recommendation={aiRec}
          isLoading={aiRec?.status === 'processing'}
        />
      </div>
    </div>
  )
}
```

**Left Panel: ChatRoomList**
```typescript
// src/components/Chat/panels/ChatRoomList.tsx
interface ChatRoomListProps {
  rooms: ChatRoom[]
  selectedRoom: ChatRoom | null
  onSelectRoom: (room: ChatRoom) => void
}

export const ChatRoomList = ({
  rooms,
  selectedRoom,
  onSelectRoom,
}: ChatRoomListProps) => {
  return (
    <div>
      <div className="p-4 border-b">
        <h2 className="font-bold text-lg">채팅</h2>
      </div>
      <div>
        {rooms.map((room) => (
          <div
            key={room.id}
            onClick={() => onSelectRoom(room)}
            className={`p-3 border-b cursor-pointer hover:bg-gray-100 ${
              selectedRoom?.id === room.id ? 'bg-blue-50' : ''
            }`}
          >
            <div className="font-medium text-sm">{room.customer_name}</div>
            <div className="text-xs text-gray-500 truncate">
              {room.last_message}
            </div>
            {room.unread_count > 0 && (
              <span className="inline-block bg-red-500 text-white rounded-full w-5 h-5 text-center text-xs">
                {room.unread_count}
              </span>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
```

**Center Panel: MessageList**
```typescript
// src/components/Chat/panels/MessageList.tsx
export const MessageList = ({ messages }: { messages: ChatMessage[] }) => {
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  return (
    <div className="p-4 space-y-3">
      {messages.map((msg) => (
        <div
          key={msg.id}
          className={`flex ${
            msg.sender_type === 'agent' ? 'justify-end' : 'justify-start'
          }`}
        >
          <div
            className={`max-w-xs px-3 py-2 rounded-lg ${
              msg.sender_type === 'agent'
                ? 'bg-blue-500 text-white'
                : msg.sender_type === 'ai'
                ? 'bg-yellow-100 text-gray-900'
                : 'bg-gray-200 text-gray-900'
            }`}
          >
            <div className="text-sm">{msg.content}</div>
            <div className="text-xs mt-1 opacity-70">
              {msg.created_at}
              {msg.status === 'read' && ' ✓✓'}
              {msg.status === 'delivered' && ' ✓'}
            </div>
          </div>
        </div>
      ))}
      <div ref={endRef} />
    </div>
  )
}
```

**Right Panel: AIRecommendation**
```typescript
// src/components/Chat/panels/AIRecommendation.tsx
export const AIRecommendation = ({
  recommendation,
  isLoading,
}: {
  recommendation: AiRecommendation | null
  isLoading: boolean
}) => {
  return (
    <div className="p-4">
      <h3 className="font-bold text-sm mb-3">AI 추천</h3>
      {isLoading && <div className="text-gray-500">분석 중...</div>}
      {recommendation?.status === 'completed' && (
        <div className="space-y-2">
          <div>
            <span className="text-xs text-gray-500">계약확률</span>
            <div className="text-lg font-bold">
              {recommendation.contractProbability}%
            </div>
          </div>
          <div>
            <span className="text-xs text-gray-500">감정</span>
            <div>{recommendation.sentiment}</div>
          </div>
          <div>
            <span className="text-xs text-gray-500">추천</span>
            <div className="text-sm bg-blue-50 p-2 rounded">
              {recommendation.recommendation}
            </div>
          </div>
        </div>
      )}
      {recommendation?.status === 'failed' && (
        <div className="text-red-500 text-sm">
          오류: {recommendation.error}
        </div>
      )}
    </div>
  )
}
```

✅ 검증:
```
[ ] 3-panel 레이아웃 정상?
[ ] WebSocket 이벤트 연동?
[ ] 메시지 수신/전송?
[ ] AI 업데이트 표시?
[ ] Optimistic Update?
[ ] 입력중 표시?
[ ] 반응형 디자인?
```

---

### Task 4.4: React Query 캐싱 (1일)

```typescript
// src/hooks/useMessages.ts (SoT = REST, notify = WS)
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useSocket } from './useSocket'

export const useMessages = (roomId: number) => {
  const queryClient = useQueryClient()
  const { emit, on } = useSocket()

  // SoT: REST API
  const messagesQuery = useQuery({
    queryKey: ['messages', roomId],
    queryFn: async () => {
      const res = await fetch(`/api/v1/chats/${roomId}/messages?limit=50`)
      return res.json()
    },
  })

  // notify: WebSocket
  useEffect(() => {
    const unsubscribe = on('message:receive', (msg) => {
      if (msg.room_id === roomId) {
        queryClient.setQueryData(['messages', roomId], (old: any) => ({
          ...old,
          data: [...(old?.data || []), msg],
        }))
      }
    })
    return unsubscribe
  }, [roomId, on, queryClient])

  // Mutation: 메시지 전송
  const sendMessage = useMutation({
    mutationFn: async (content: string) => {
      // Optimistic update
      queryClient.setQueryData(['messages', roomId], (old: any) => ({
        ...old,
        data: [
          ...(old?.data || []),
          {
            message_id: -1,
            content,
            status: 'pending',
            created_at: new Date().toISOString(),
          },
        ],
      }))

      // WebSocket 전송
      emit('message:send', {
        room_id: roomId,
        content,
        message_type: 'text',
      })
    },
  })

  return {
    messages: messagesQuery.data?.data || [],
    isLoading: messagesQuery.isLoading,
    error: messagesQuery.error,
    sendMessage: sendMessage.mutate,
  }
}
```

✅ 검증:
```
[ ] SoT (REST) 동작?
[ ] notify (WS) 동작?
[ ] Optimistic Update?
[ ] 캐시 일관성?
[ ] 오류 처리?
```

---

### Task 4.5: 테스트 작성 (0.5일)

```typescript
// src/components/Chat/ChatScreen.test.tsx
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ChatScreen } from './ChatScreen'
import { vi } from 'vitest'

vi.mock('@/hooks/useSocket')

describe('ChatScreen', () => {
  it('renders 3-panel layout', () => {
    render(<ChatScreen />)
    
    // Left: ChatRoomList
    expect(screen.getByText('채팅')).toBeInTheDocument()
    
    // Center: MessageInput
    expect(screen.getByPlaceholderText(/메시지/i)).toBeInTheDocument()
    
    // Right: AIRecommendation
    expect(screen.getByText('AI 추천')).toBeInTheDocument()
  })

  it('sends message on input submit', async () => {
    const user = userEvent.setup()
    const { emit } = useSocket as any
    
    render(<ChatScreen />)
    
    const input = screen.getByPlaceholderText(/메시지/i)
    await user.type(input, 'Hello')
    await user.keyboard('{Enter}')
    
    expect(emit).toHaveBeenCalledWith('message:send', expect.objectContaining({
      content: 'Hello',
    }))
  })

  it('displays typing indicator', async () => {
    const user = userEvent.setup()
    const { emit } = useSocket as any
    
    render(<ChatScreen />)
    
    const input = screen.getByPlaceholderText(/메시지/i)
    await user.type(input, 'H')
    
    expect(emit).toHaveBeenCalledWith('typing:start', expect.any(Object))
  })

  it('updates UI when AI recommendation received', async () => {
    const { mockAiUpdate } = useSocket as any
    
    render(<ChatScreen />)
    
    mockAiUpdate({
      room_id: 1,
      status: 'completed',
      data: {
        contractProbability: 85,
        recommendation: 'Test recommendation',
      },
    })
    
    await waitFor(() => {
      expect(screen.getByText('85%')).toBeInTheDocument()
      expect(screen.getByText('Test recommendation')).toBeInTheDocument()
    })
  })
})
```

---

## 📋 Step 4 체크리스트

```
Task 4.1: TypeScript 타입
[ ] SocketEvents 정의
[ ] Payload 타입
[ ] Model 타입

Task 4.2: useSocket Hook
[ ] 연결 관리
[ ] emit/on/off
[ ] 에러 처리
[ ] 타입 추론

Task 4.3: ChatScreen UI
[ ] 3-panel 레이아웃
[ ] ChatRoomList
[ ] MessageList
[ ] MessageInput
[ ] AIRecommendation
[ ] WebSocket 연동

Task 4.4: React Query
[ ] useMessages Hook
[ ] REST (SoT) 동작
[ ] WS (notify) 동작
[ ] Optimistic Update
[ ] 캐시 일관성

Task 4.5: 테스트
[ ] Unit Test
[ ] Integration Test
[ ] Snapshot Test
```

---

## ✅ Step 4 완료 조건

```
□ 3-panel ChatScreen 완성
□ useSocket Hook 완성
□ WebSocket 연동 정상
□ React Query 캐싱 정상
□ 메시지 송수신 동작
□ AI 업데이트 표시
□ 입력중 표시
□ 읽음표시
□ 테스트 80% 이상 커버리지
□ TypeScript 타입 검사 통과

🎯 최종 판정: Step 4 완료 ✅
```

---

## 🚀 Step 4 진행 명령어

```bash
# 1. 의존성 설치
cd frontend/
npm install socket.io-client @tanstack/react-query

# 2. 타입 정의
src/types/socket-events.ts 작성

# 3. Hook 구현
src/hooks/useSocket.ts 작성
src/hooks/useMessages.ts 작성

# 4. 컴포넌트 구현
src/components/Chat/ChatScreen.tsx
src/components/Chat/panels/ChatRoomList.tsx
src/components/Chat/panels/MessageList.tsx
src/components/Chat/panels/MessageInput.tsx
src/components/Chat/panels/AIRecommendation.tsx

# 5. 테스트
npm run test

# 6. 실행
npm run dev  # http://localhost:5173
# chat-server는 npm run dev로 3001에서 실행 중
```

---

## 🎊 Phase 2 완료 후

```
✅ WebSocket Server (TypeScript + Socket.io)
✅ AI Router (PHP ai_call() 단일 진입점)
✅ React ChatScreen (3-panel UI)
✅ Message Flow (REST SoT + WS notify)
✅ AI Recommendation (실시간 업데이트)

예상 완료: 2026-08-04 (1주 후)

다음: Phase 3 (CRM & Admin Dashboard)
```

---

*Phase 2 Step 4 — React ChatScreen · 2026-07-21*
