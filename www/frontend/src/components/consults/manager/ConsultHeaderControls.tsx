import { useEffect, useState } from 'react';
import { CONSULT_STATUS_DROPDOWN } from '@/types/consult.types';
import { consultService } from '@/services/consult.service';
import { agentService } from '@/services/agent.service';
import type { ConsultAgent } from '@/types/consult.types';
import toast from 'react-hot-toast';

type ConsultHeaderControlsProps = {
  consultId: string;
  status: string;
  assigneeId?: string | null;
  onStatusSaved?: (status: string) => void;
  onAssigneeSaved?: (agentId: string | null) => void;
};

export default function ConsultHeaderControls({
  consultId,
  status,
  assigneeId,
  onStatusSaved,
  onAssigneeSaved,
}: ConsultHeaderControlsProps) {
  const [statusDraft, setStatusDraft] = useState(status);
  const [assigneeDraft, setAssigneeDraft] = useState(assigneeId ?? '');
  const [agents, setAgents] = useState<ConsultAgent[]>([]);
  const [agentsLoading, setAgentsLoading] = useState(true);
  const [statusSaving, setStatusSaving] = useState(false);
  const [assigneeSaving, setAssigneeSaving] = useState(false);

  useEffect(() => {
    setStatusDraft(status);
  }, [status]);

  useEffect(() => {
    setAssigneeDraft(assigneeId ?? '');
  }, [assigneeId]);

  useEffect(() => {
    let cancelled = false;
    setAgentsLoading(true);
    void (async () => {
      try {
        const rows = await agentService.list();
        if (!cancelled) setAgents(rows);
      } catch {
        if (!cancelled) {
          setAgents([]);
          toast.error('담당자 목록을 불러오지 못했습니다.');
        }
      } finally {
        if (!cancelled) setAgentsLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const saveStatus = async (next: string) => {
    const previous = statusDraft;
    setStatusDraft(next);
    setStatusSaving(true);
    try {
      await consultService.updateStatus(consultId, next);
      onStatusSaved?.(next);
      toast.success('상태 저장됨');
    } catch {
      setStatusDraft(previous);
      toast.error('상태 저장 실패');
    } finally {
      setStatusSaving(false);
    }
  };

  const saveAssignee = async (next: string) => {
    const previous = assigneeDraft;
    setAssigneeDraft(next);
    setAssigneeSaving(true);
    try {
      await consultService.updateAssignee(consultId, next || null);
      onAssigneeSaved?.(next || null);
      toast.success('담당자 저장됨');
    } catch {
      setAssigneeDraft(previous);
      toast.error('담당자 저장 실패');
    } finally {
      setAssigneeSaving(false);
    }
  };

  return (
    <div className="consult-header-controls flex min-w-0 flex-wrap gap-2">
      <label className="flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm sm:max-w-[11rem]">
        <span className="shrink-0 text-slate-500">상태</span>
        <select
          className="min-w-0 flex-1 bg-transparent font-medium text-slate-900 focus:outline-none disabled:opacity-50"
          value={statusDraft}
          onChange={(e) => void saveStatus(e.target.value)}
          disabled={statusSaving}
          aria-label="상담 상태"
          aria-busy={statusSaving}
        >
          {CONSULT_STATUS_DROPDOWN.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </label>

      <label className="flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm sm:max-w-[11rem]">
        <span className="shrink-0 text-slate-500">담당</span>
        <select
          className="min-w-0 flex-1 bg-transparent font-medium text-slate-900 focus:outline-none disabled:opacity-50"
          value={assigneeDraft}
          onChange={(e) => void saveAssignee(e.target.value)}
          disabled={assigneeSaving || agentsLoading}
          aria-label="담당자"
          aria-busy={assigneeSaving || agentsLoading}
        >
          <option value="">미배정</option>
          {agents.map((agent) => (
            <option key={agent.id} value={agent.id}>
              {agent.displayName}
            </option>
          ))}
        </select>
      </label>
    </div>
  );
}
