import { useState } from 'react';
import { Trash2, RefreshCw, UserCog, Tag as TagIcon } from 'lucide-react';
import { Button, Dialog, TagBadge, presetTags, createTag } from '@/components/admin-ui';
import {
  CONSULT_STATUS_DROPDOWN,
  type ConsultTag,
} from '@/types/consult.types';
import { useAgents } from '@/hooks/useAgents';

type BulkModalKind = 'status' | 'assign' | 'tag' | 'delete' | null;

type ConsultBulkActionsBarProps = {
  selectedCount: number;
  onDelete: () => void | Promise<void>;
  onStatusChange: (status: string) => void | Promise<void>;
  onAssigneeChange: (agentId: string) => void | Promise<void>;
  onTagApply: (tags: ConsultTag[], mode: 'add' | 'remove') => void | Promise<void>;
  onClear: () => void;
  busy?: boolean;
};

export default function ConsultBulkActionsBar({
  selectedCount,
  onDelete,
  onStatusChange,
  onAssigneeChange,
  onTagApply,
  onClear,
  busy = false,
}: ConsultBulkActionsBarProps) {
  const { data: agentsRaw } = useAgents();
  const agents = Array.isArray(agentsRaw) ? agentsRaw : [];
  const [modal, setModal] = useState<BulkModalKind>(null);
  const [statusDraft, setStatusDraft] = useState('progress');
  const [assigneeDraft, setAssigneeDraft] = useState('');
  const [selectedPreset, setSelectedPreset] = useState<string>('');
  const [tagMode, setTagMode] = useState<'add' | 'remove'>('add');

  const presetTagList = presetTags();

  if (selectedCount <= 0) return null;

  const close = () => setModal(null);

  const confirmStatus = () => {
    void onStatusChange(statusDraft);
    close();
  };

  const confirmAssign = () => {
    void onAssigneeChange(assigneeDraft);
    close();
  };

  const confirmTag = () => {
    const label = selectedPreset.trim();
    if (!label) return;
    const tag: ConsultTag = createTag(label);
    void onTagApply([tag], tagMode);
    setSelectedPreset('');
    close();
  };

  const confirmDelete = () => {
    void onDelete();
    close();
  };

  return (
    <>
      <div className="consult-bulk-bar sticky top-0 z-10 flex min-w-0 flex-wrap items-center gap-2 border-b border-indigo-100 bg-indigo-50 px-3 py-2">
        <span className="text-sm font-semibold text-indigo-900">{selectedCount}건 선택</span>

        <Button
          variant="danger"
          icon={<Trash2 className="h-4 w-4" />}
          onClick={() => setModal('delete')}
          disabled={busy}
        >
          삭제
        </Button>

        <Button
          variant="secondary"
          icon={<RefreshCw className="h-4 w-4" />}
          onClick={() => setModal('status')}
          disabled={busy}
        >
          상태변경
        </Button>

        <Button
          variant="secondary"
          icon={<UserCog className="h-4 w-4" />}
          onClick={() => setModal('assign')}
          disabled={busy}
        >
          담당자변경
        </Button>

        <Button
          variant="secondary"
          icon={<TagIcon className="h-4 w-4" />}
          onClick={() => setModal('tag')}
          disabled={busy}
        >
          태그
        </Button>

        <button
          type="button"
          onClick={onClear}
          className="ml-auto text-sm font-medium text-indigo-700 underline-offset-2 hover:underline"
          disabled={busy}
        >
          선택 해제
        </button>
      </div>

      <Dialog
        open={modal === 'delete'}
        title="선택 상담 삭제"
        description={`${selectedCount}건을 삭제합니다. 이 작업은 되돌릴 수 없습니다.`}
        onClose={close}
        footer={
          <>
            <Button variant="secondary" onClick={close}>
              취소
            </Button>
            <Button variant="danger" onClick={confirmDelete} disabled={busy}>
              삭제
            </Button>
          </>
        }
      >
        <span className="sr-only">삭제 확인</span>
      </Dialog>

      <Dialog
        open={modal === 'status'}
        title="상태 일괄 변경"
        description={`선택한 ${selectedCount}건의 상태를 변경합니다.`}
        onClose={close}
        footer={
          <>
            <Button variant="secondary" onClick={close}>
              취소
            </Button>
            <Button variant="primary" onClick={confirmStatus} disabled={busy}>
              적용
            </Button>
          </>
        }
      >
        <label className="block text-sm font-medium text-slate-700">
          새 상태
          <select
            className="mt-1 h-11 w-full rounded-lg border border-slate-200 px-3 text-sm"
            value={statusDraft}
            onChange={(e) => setStatusDraft(e.target.value)}
            aria-label="일괄 상태 선택"
          >
            {CONSULT_STATUS_DROPDOWN.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </label>
      </Dialog>

      <Dialog
        open={modal === 'assign'}
        title="담당자 일괄 변경"
        description={`선택한 ${selectedCount}건의 담당자를 변경합니다.`}
        onClose={close}
        footer={
          <>
            <Button variant="secondary" onClick={close}>
              취소
            </Button>
            <Button variant="primary" onClick={confirmAssign} disabled={busy}>
              적용
            </Button>
          </>
        }
      >
        <label className="block text-sm font-medium text-slate-700">
          담당자
          <select
            className="mt-1 h-11 w-full rounded-lg border border-slate-200 px-3 text-sm"
            value={assigneeDraft}
            onChange={(e) => setAssigneeDraft(e.target.value)}
            aria-label="일괄 담당자 선택"
          >
            <option value="">미배정</option>
            {agents.map((agent) => (
              <option key={agent.id} value={agent.id}>
                {agent.displayName}
              </option>
            ))}
          </select>
        </label>
      </Dialog>

      <Dialog
        open={modal === 'tag'}
        title="태그 일괄 적용"
        description={`선택한 ${selectedCount}건에 태그를 ${tagMode === 'add' ? '추가' : '제거'}합니다.`}
        onClose={close}
        footer={
          <>
            <Button variant="secondary" onClick={close}>
              취소
            </Button>
            <Button variant="primary" onClick={confirmTag} disabled={busy || !selectedPreset.trim()}>
              적용
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          <div className="flex gap-2">
            <button
              type="button"
              className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium ${
                tagMode === 'add'
                  ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                  : 'border-slate-200 text-slate-600'
              }`}
              onClick={() => setTagMode('add')}
            >
              추가
            </button>
            <button
              type="button"
              className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium ${
                tagMode === 'remove'
                  ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                  : 'border-slate-200 text-slate-600'
              }`}
              onClick={() => setTagMode('remove')}
            >
              제거
            </button>
          </div>

          <label className="block text-sm font-medium text-slate-700">
            태그 선택
            <div className="mt-2 flex flex-wrap gap-1.5" role="group" aria-label="일괄 태그 선택">
              {presetTagList.map((tag) => (
                <TagBadge
                  key={tag.id}
                  tag={tag}
                  compact
                  selected={selectedPreset === tag.label}
                  onClick={() => setSelectedPreset(tag.label)}
                />
              ))}
            </div>
          </label>
        </div>
      </Dialog>
    </>
  );
}
