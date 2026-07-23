import { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/features/auth/AuthProvider';
import { SocketProvider, useSocket } from '@/hooks/useSocket';
import { useMessages } from '@/hooks/useMessages';
import { MessageList } from '@/components/Chat/panels/MessageList';
import { MessageInput } from '@/components/Chat/panels/MessageInput';
import { ConnectionBanner } from '@/components/Chat/ConnectionBanner';

type ConsultChatPanelProps = {
  roomId: string;
  roomStatus?: string;
};

function ConsultChatPanelInner({ roomId, roomStatus }: ConsultChatPanelProps) {
  const { user } = useAuth();
  const { isConnected, error, emit, on } = useSocket();
  const { messages, isLoading, sendMessage, isSending } = useMessages(roomId);
  const [typingUser, setTypingUser] = useState<string | null>(null);

  const isClosed = roomStatus === 'closed';

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
    emit('typing:start', { roomId });
  }, [roomId, emit]);

  const handleTypingStop = useCallback(() => {
    emit('typing:stop', { roomId });
  }, [roomId, emit]);

  return (
    <section
      id="consult-chat-panel"
      className="consult-chat-panel consult-detail-card flex min-h-[360px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm"
      aria-label="실시간 상담 메시지"
    >
      <header className="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
        <h3 className="text-sm font-semibold text-slate-900">실시간 상담 메시지</h3>
        <span
          className={`inline-flex items-center gap-1.5 text-xs ${
            isConnected ? 'text-emerald-600' : 'text-slate-500'
          }`}
        >
          <span
            className={`h-2 w-2 rounded-full ${isConnected ? 'bg-emerald-500' : 'bg-slate-300'}`}
            aria-hidden
          />
          {isConnected ? '연결됨' : '연결 중…'}
        </span>
      </header>

      <ConnectionBanner isConnected={isConnected} error={error} />

      <div className="consult-chat-messages flex min-h-0 flex-1 flex-col">
        <MessageList messages={messages} typingUser={typingUser} />
        {isLoading && messages.length === 0 && (
          <p className="px-4 pb-2 text-xs text-slate-400">메시지 불러오는 중…</p>
        )}
      </div>

      <div className="chat-composer shrink-0">
        <MessageInput
          disabled={!isConnected || isClosed}
          isSending={isSending}
          onSend={sendMessage}
          onTypingStart={handleTypingStart}
          onTypingStop={handleTypingStop}
        />
      </div>
    </section>
  );
}

export default function ConsultChatPanel(props: ConsultChatPanelProps) {
  return (
    <SocketProvider>
      <ConsultChatPanelInner {...props} />
    </SocketProvider>
  );
}
