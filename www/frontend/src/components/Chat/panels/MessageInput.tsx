import { FormEvent, useRef, useState } from 'react';

interface MessageInputProps {
  disabled?: boolean;
  isSending?: boolean;
  onSend: (content: string) => void;
  onTypingStart: () => void;
  onTypingStop: () => void;
}

export function MessageInput({
  disabled,
  isSending,
  onSend,
  onTypingStart,
  onTypingStop,
}: MessageInputProps) {
  const [value, setValue] = useState('');
  const typingTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  function handleChange(text: string) {
    setValue(text);
    if (text.trim()) {
      onTypingStart();
      if (typingTimer.current) clearTimeout(typingTimer.current);
      typingTimer.current = setTimeout(() => {
        onTypingStop();
      }, 2000);
    } else {
      onTypingStop();
    }
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const content = value.trim();
    if (!content || disabled || isSending) return;
    onSend(content);
    setValue('');
    onTypingStop();
    if (typingTimer.current) clearTimeout(typingTimer.current);
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="border-t border-acep-border bg-white p-4"
    >
      <div className="flex gap-2">
        <input
          type="text"
          value={value}
          onChange={(e) => handleChange(e.target.value)}
          placeholder="메시지를 입력하세요…"
          disabled={disabled || isSending}
          className="flex-1 rounded-lg border border-acep-border px-3 py-2 text-sm focus:border-acep-primary focus:outline-none focus:ring-1 focus:ring-acep-primary disabled:bg-slate-50"
        />
        <button
          type="submit"
          disabled={disabled || isSending || !value.trim()}
          className="rounded-lg bg-acep-primary px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {isSending ? '전송 중…' : '전송'}
        </button>
      </div>
    </form>
  );
}
