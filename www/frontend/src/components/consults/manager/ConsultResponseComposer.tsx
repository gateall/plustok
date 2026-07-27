import { useState } from 'react';
import { Paperclip, Send, Sparkles, Mail, MessageCircle } from 'lucide-react';
import { Button } from '@/components/admin-ui';
import { consultService } from '@/services/consult.service';
import toast from 'react-hot-toast';

type ConsultResponseComposerProps = {
  consultId: string;
  onSend?: (payload: { mode: string; body: string }) => void;
};

const REPLY_API_GAP = '이메일/SMS 발송 API 미구현 — 레거시 PHP 사용';

export default function ConsultResponseComposer({ consultId, onSend }: ConsultResponseComposerProps) {
  const [mode, setMode] = useState<'reply' | 'ai-draft' | 'sms' | 'mail'>('reply');
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);

  const handleSend = async () => {
    const trimmed = body.trim();
    if (!trimmed) {
      toast.error('내용을 입력해 주세요.');
      return;
    }

    if (mode === 'reply') {
      setSending(true);
      try {
        await consultService.createTimelineMemo(consultId, `[고객 답변] ${trimmed}`);
        onSend?.({ mode, body: trimmed });
        toast.success('답변 기록이 저장되었습니다. (이메일 발송은 레거시 PHP 사용)');
        setBody('');
      } catch (err) {
        toast.error(err instanceof Error ? err.message : '답변 저장 실패');
      } finally {
        setSending(false);
      }
      return;
    }

    if (mode === 'sms' || mode === 'mail') {
      toast.error(REPLY_API_GAP);
      return;
    }

    if (mode === 'ai-draft') {
      toast.error('AI 초안 API 미구현');
    }
  };

  const handleAiDraft = () => {
    setMode('ai-draft');
    toast.error('AI 초안 API 미구현');
  };

  return (
    <section className="consult-response-composer border-t border-slate-200 bg-white p-3" aria-label="고객 응대">
      <div className="mb-2 flex flex-wrap gap-1">
        {(
          [
            { id: 'reply' as const, label: '답변', disabled: false },
            { id: 'ai-draft' as const, label: 'AI초안', icon: Sparkles, disabled: true, hint: 'API 미구현' },
            { id: 'sms' as const, label: 'SMS', icon: MessageCircle, disabled: true, hint: 'API 미구현' },
            { id: 'mail' as const, label: '메일', icon: Mail, disabled: true, hint: 'API 미구현' },
          ] as const
        ).map((tab) => (
          <button
            key={tab.id}
            type="button"
            disabled={tab.disabled}
            title={'hint' in tab ? tab.hint : undefined}
            className={`inline-flex min-h-9 items-center gap-1 rounded-lg px-3 text-sm font-medium ${
              mode === tab.id
                ? 'bg-indigo-100 text-indigo-800'
                : tab.disabled
                  ? 'cursor-not-allowed bg-slate-50 text-slate-400'
                  : 'bg-slate-100 text-slate-600'
            }`}
            onClick={() => {
              if (tab.disabled) {
                toast.error('hint' in tab && tab.hint ? tab.hint : '지원되지 않는 기능입니다.');
                return;
              }
              setMode(tab.id);
            }}
          >
            {'icon' in tab && tab.icon ? <tab.icon className="h-3.5 w-3.5" /> : null}
            {tab.label}
          </button>
        ))}
      </div>

      <textarea
        className="min-h-[88px] w-full resize-y rounded-xl border border-slate-200 px-3 py-2 text-base text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        placeholder={
          mode === 'reply'
            ? '고객에게 보낼 메시지를 입력하세요. (타임라인에 기록됩니다)'
            : '고객에게 보낼 메시지를 입력하세요…'
        }
        value={body}
        onChange={(e) => setBody(e.target.value)}
        disabled={sending}
        aria-label="응대 메시지"
      />

      <p className="mt-1 text-xs text-slate-500">
        {mode === 'reply'
          ? '답변은 타임라인 메모로 저장됩니다. 실제 이메일/SMS 발송은 레거시 PHP를 사용하세요.'
          : mode === 'sms' || mode === 'mail'
            ? REPLY_API_GAP
            : null}
      </p>

      <div className="mt-2 flex flex-wrap items-center gap-2">
        <Button
          variant="ghost"
          icon={<Paperclip className="h-4 w-4" />}
          onClick={() => toast.error('첨부는 파일 탭에서 업로드하세요.')}
        >
          첨부
        </Button>
        <Button variant="secondary" icon={<Sparkles className="h-4 w-4" />} onClick={handleAiDraft}>
          AI초안
        </Button>
        <Button
          variant="primary"
          icon={<Send className="h-4 w-4" />}
          className="ml-auto"
          onClick={() => void handleSend()}
          disabled={sending}
        >
          {sending ? '저장 중…' : '보내기'}
        </Button>
      </div>
    </section>
  );
}
