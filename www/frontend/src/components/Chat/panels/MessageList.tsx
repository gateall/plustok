import { useEffect, useRef } from 'react';
import clsx from 'clsx';
import type { ChatMessageItem } from '@/types/chat.types';

interface MessageListProps {
  messages: ChatMessageItem[];
  typingUser?: string | null;
}

export function MessageList({ messages, typingUser }: MessageListProps) {
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, typingUser]);

  if (messages.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center p-8 text-sm text-slate-500">
        메시지가 없습니다. 첫 메시지를 보내보세요.
      </div>
    );
  }

  return (
    <div className="flex-1 space-y-3 overflow-y-auto p-4">
      {messages.map((msg) => (
        <MessageBubble key={msg.tempId ?? msg.id} message={msg} />
      ))}
      {typingUser && (
        <p className="text-xs text-slate-500">{typingUser}님이 입력 중…</p>
      )}
      <div ref={endRef} />
    </div>
  );
}

function MessageBubble({ message }: { message: ChatMessageItem }) {
  const isAgent = message.senderType === 'agent';
  const time = new Date(message.createdAt).toLocaleTimeString('ko-KR', {
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <div className={clsx('flex', isAgent ? 'justify-end' : 'justify-start')}>
      <div
        className={clsx(
          'max-w-[75%] rounded-lg px-3 py-2 text-sm shadow-sm',
          isAgent ? 'bg-acep-primary text-white' : 'bg-white text-slate-900 ring-1 ring-acep-border',
          message.clientStatus === 'failed' && 'ring-2 ring-red-400',
        )}
      >
        <p className="whitespace-pre-wrap break-words">{message.content}</p>
        <div
          className={clsx(
            'mt-1 flex items-center gap-1 text-[10px]',
            isAgent ? 'text-blue-100' : 'text-slate-400',
          )}
        >
          <span>{time}</span>
          {message.clientStatus === 'pending' && <span>⌛</span>}
          {message.clientStatus === 'sent' && <span>✓</span>}
          {message.readStatus?.read && <span>✓✓</span>}
          {message.clientStatus === 'failed' && <span className="text-red-200">⚠</span>}
        </div>
      </div>
    </div>
  );
}
