import { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/features/auth/AuthProvider';
import { SocketProvider, useSocket } from '@/hooks/useSocket';
import { useChatRooms } from '@/hooks/useChatRooms';
import { useMessages } from '@/hooks/useMessages';
import { useAiRecommendations } from '@/hooks/useAiRecommendations';
import { ChatRoomList } from '@/components/Chat/panels/ChatRoomList';
import { MessageList } from '@/components/Chat/panels/MessageList';
import { MessageInput } from '@/components/Chat/panels/MessageInput';
import { AIRecommendationPanel } from '@/components/Chat/panels/AIRecommendation';
import { ConnectionBanner } from '@/components/Chat/ConnectionBanner';
import type { ChatRoomItem } from '@/types/chat.types';

function ChatScreenInner() {
  const { user, logout } = useAuth();
  const { isConnected, error, emit, on } = useSocket();
  const { rooms, isLoading: roomsLoading } = useChatRooms();
  const [selectedRoom, setSelectedRoom] = useState<ChatRoomItem | null>(null);
  const [typingUser, setTypingUser] = useState<string | null>(null);

  const roomId = selectedRoom?.id ?? null;
  const { messages, isLoading: messagesLoading, sendMessage, isSending } = useMessages(roomId);
  const { recommendation, isLoading: aiLoading, isProcessing } = useAiRecommendations(roomId);

  useEffect(() => {
    if (!roomId || !isConnected) return;
    emit('room:join', { roomId });
    return () => {
      emit('room:leave', { roomId });
    };
  }, [roomId, isConnected, emit]);

  useEffect(() => {
    if (!roomId) return undefined;
    const unsubStart = on('typing:start', (payload) => {
      if (payload.roomId === roomId && payload.userId !== user?.id) {
        setTypingUser(payload.userName || '상대');
      }
    });
    const unsubStop = on('typing:stop', (payload) => {
      if (payload.roomId === roomId) {
        setTypingUser(null);
      }
    });
    return () => {
      unsubStart();
      unsubStop();
    };
  }, [roomId, on, user?.id]);

  const handleTypingStart = useCallback(() => {
    if (roomId) emit('typing:start', { roomId });
  }, [roomId, emit]);

  const handleTypingStop = useCallback(() => {
    if (roomId) emit('typing:stop', { roomId });
  }, [roomId, emit]);

  return (
    <div className="flex h-screen flex-col bg-acep-surface">
      <header className="flex shrink-0 items-center justify-between border-b border-acep-border bg-white px-6 py-3">
        <div>
          <h1 className="text-lg font-semibold text-slate-900">상담 채팅</h1>
          <p className="text-xs text-slate-500">
            {selectedRoom ? selectedRoom.customer.name : '상담방을 선택하세요'}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <span
            className={`h-2 w-2 rounded-full ${isConnected ? 'bg-green-500' : 'bg-slate-300'}`}
            title={isConnected ? '연결됨' : '끊김'}
          />
          <span className="text-sm text-slate-600">{user?.name}</span>
          <button
            type="button"
            onClick={() => logout()}
            className="rounded-lg border border-acep-border px-3 py-1.5 text-sm hover:bg-slate-50"
          >
            로그아웃
          </button>
        </div>
      </header>

      <ConnectionBanner isConnected={isConnected} error={error} />

      <div className="flex min-h-0 flex-1">
        {/* Left 320px */}
        <aside className="w-80 shrink-0 border-r border-acep-border bg-white">
          <ChatRoomList
            rooms={rooms}
            selectedRoomId={selectedRoom?.id ?? null}
            isLoading={roomsLoading}
            onSelectRoom={setSelectedRoom}
          />
        </aside>

        {/* Center flex */}
        <section className="flex min-w-0 flex-1 flex-col bg-slate-50">
          {!selectedRoom ? (
            <div className="flex flex-1 items-center justify-center text-slate-500">
              좌측에서 상담방을 선택하세요.
            </div>
          ) : (
            <>
              <MessageList
                messages={messages}
                typingUser={typingUser}
              />
              {messagesLoading && messages.length === 0 && (
                <p className="px-4 text-xs text-slate-400">메시지 불러오는 중…</p>
              )}
              <MessageInput
                disabled={!isConnected || selectedRoom.status === 'closed'}
                isSending={isSending}
                onSend={sendMessage}
                onTypingStart={handleTypingStart}
                onTypingStop={handleTypingStop}
              />
            </>
          )}
        </section>

        {/* Right 320px */}
        <aside className="w-80 shrink-0 border-l border-acep-border bg-white">
          <AIRecommendationPanel
            recommendation={recommendation}
            isLoading={aiLoading}
            isProcessing={isProcessing}
          />
        </aside>
      </div>
    </div>
  );
}

export function ChatScreen() {
  return (
    <SocketProvider>
      <ChatScreenInner />
    </SocketProvider>
  );
}

export default ChatScreen;
