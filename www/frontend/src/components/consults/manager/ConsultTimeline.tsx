import { useCallback, useEffect, useState } from 'react';

import { Button, EmptyState, Timeline } from '@/components/admin-ui';

import type { TimelineEntry } from '@/types/consult.types';

import { consultService } from '@/services/consult.service';

import toast from 'react-hot-toast';

type ConsultTimelineProps = {
  consultId: string;
  consultCreatedAt?: string;
  status?: string;
};

export default function ConsultTimeline({ consultId }: ConsultTimelineProps) {
  const [entries, setEntries] = useState<TimelineEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [memoNote, setMemoNote] = useState('');
  const [memoSaving, setMemoSaving] = useState(false);

  const loadTimeline = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data, pending: isPending } = await consultService.getTimeline(consultId);
      setEntries(data.entries);
      setPending(isPending);
    } catch (err) {
      setEntries([]);
      setError(err instanceof Error ? err.message : '타임라인을 불러오지 못했습니다.');
    } finally {
      setLoading(false);
    }
  }, [consultId]);

  useEffect(() => {
    void loadTimeline();
  }, [loadTimeline]);

  const submitMemo = async () => {
    const note = memoNote.trim();
    if (!note) {
      toast.error('메모 내용을 입력해 주세요.');
      return;
    }
    setMemoSaving(true);
    try {
      await consultService.createTimelineMemo(consultId, note);
      setMemoNote('');
      toast.success('메모가 저장되었습니다.');
      await loadTimeline();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '메모 저장 실패');
    } finally {
      setMemoSaving(false);
    }
  };

  if (!loading && !error && entries.length === 0) {
    return (
      <div className="consult-timeline p-4">
        <EmptyState
          title="타임라인 기록 없음"
          description={
            pending
              ? '타임라인 API가 아직 배포되지 않았습니다.'
              : '상담 이력이 없습니다. 아래에 메모를 추가할 수 있습니다.'
          }
        />
        <MemoForm
          note={memoNote}
          saving={memoSaving}
          onChange={setMemoNote}
          onSubmit={() => void submitMemo()}
        />
      </div>
    );
  }

  return (
    <div className="consult-timeline">
      <Timeline
        entries={entries}
        loading={loading}
        groupBySection
        className="border-0 shadow-none"
        footer={
          error ? (
            <p className="text-center text-xs text-red-600">{error}</p>
          ) : pending ? (
            <p className="text-center text-xs text-amber-600">타임라인 API 미배포 — 빈 목록 표시</p>
          ) : undefined
        }
      />
      <MemoForm
        note={memoNote}
        saving={memoSaving}
        onChange={setMemoNote}
        onSubmit={() => void submitMemo()}
      />
    </div>
  );
}

type MemoFormProps = {
  note: string;
  saving: boolean;
  onChange: (value: string) => void;
  onSubmit: () => void;
};

function MemoForm({ note, saving, onChange, onSubmit }: MemoFormProps) {
  return (
    <div className="border-t border-slate-200 bg-white p-4">
      <label htmlFor="consult-timeline-memo" className="text-sm font-semibold text-slate-900">
        메모 추가
      </label>
      <textarea
        id="consult-timeline-memo"
        className="mt-2 min-h-[72px] w-full resize-y rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        placeholder="상담 메모를 입력하세요…"
        value={note}
        onChange={(e) => onChange(e.target.value)}
        disabled={saving}
      />
      <div className="mt-2 flex justify-end">
        <Button variant="primary" onClick={onSubmit} disabled={saving || !note.trim()}>
          {saving ? '저장 중…' : '메모 저장'}
        </Button>
      </div>
    </div>
  );
}
