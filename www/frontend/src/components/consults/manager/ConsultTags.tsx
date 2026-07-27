import { useCallback, useEffect, useState } from 'react';

import { Plus } from 'lucide-react';

import clsx from 'clsx';

import {

  Button,

  TagBadge,

  presetTags,

  createTag,

  type TagData,

} from '@/components/admin-ui';

import {

  CONSULT_TAG_COLORS,

  DEFAULT_CONSULT_TAGS,

  type ConsultTag,

  type ConsultTagColorKey,

} from '@/types/consult.types';

import { consultService } from '@/services/consult.service';

import toast from 'react-hot-toast';



function chipClassFor(color: ConsultTagColorKey): string {

  return CONSULT_TAG_COLORS.find((c) => c.key === color)?.chipClass ?? CONSULT_TAG_COLORS[0].chipClass;

}



type ConsultTagsProps = {

  consultId: string;

  tags?: ConsultTag[];

  editable?: boolean;

  compact?: boolean;

  onChange?: (tags: ConsultTag[]) => void;

  className?: string;

};



/** @deprecated Use TagBadge from admin-ui */

export function ConsultTagChip({

  tag,

  onRemove,

  compact,

}: {

  tag: ConsultTag;

  onRemove?: () => void;

  compact?: boolean;

}) {

  return <TagBadge tag={tag} compact={compact} onRemove={onRemove} />;

}



export { chipClassFor };



const EMPTY_TAGS: ConsultTag[] = [];



export default function ConsultTags({

  consultId,

  tags: initialTags = EMPTY_TAGS,

  editable = false,

  compact = false,

  onChange,

  className,

}: ConsultTagsProps) {

  const [tags, setTags] = useState<ConsultTag[]>(initialTags);

  const [adding, setAdding] = useState(false);

  const [labelDraft, setLabelDraft] = useState('');

  const [colorDraft, setColorDraft] = useState<ConsultTagColorKey>('indigo');

  const [saving, setSaving] = useState(false);



  const tagsKey = initialTags.map((t) => `${t.id}:${t.label}:${t.color}`).join('|');

  const presets = presetTags();



  useEffect(() => {

    setTags(initialTags);

  }, [consultId, tagsKey]);



  const persist = useCallback(

    async (next: ConsultTag[]) => {

      setTags(next);

      onChange?.(next);

      if (!editable) return;



      setSaving(true);

      try {

        const { pending } = await consultService.setTags(consultId, next);

        if (pending) {

          toast('태그 API 연동 대기 중 (Codex)', { icon: 'ℹ️' });

        } else {

          toast.success('태그 저장됨');

        }

      } catch {

        toast.error('태그 저장 실패');

      } finally {

        setSaving(false);

      }

    },

    [consultId, editable, onChange],

  );



  const addTag = (tag?: TagData) => {

    const nextTag = tag ?? createTag(labelDraft.trim(), colorDraft);

    const label = nextTag.label;

    if (!label) return;

    if (tags.some((t) => t.label.toLowerCase() === label.toLowerCase())) {

      toast.error('이미 존재하는 태그입니다');

      return;

    }

    void persist([...tags, nextTag]);

    setLabelDraft('');

    setAdding(false);

  };



  const removeTag = (id: string) => {

    void persist(tags.filter((t) => t.id !== id));

  };



  const togglePreset = (preset: TagData) => {

    const exists = tags.some((t) => t.label.toLowerCase() === preset.label.toLowerCase());

    if (exists) {

      void persist(tags.filter((t) => t.label.toLowerCase() !== preset.label.toLowerCase()));

    } else {

      void persist([...tags, { ...preset, id: `tag-${preset.label}-${Date.now()}` }]);

    }

  };



  if (!editable && tags.length === 0) {

    return <p className="text-sm text-slate-500">태그 없음</p>;

  }



  return (

    <div className={clsx('consult-tags space-y-2', className)}>

      <div className="flex flex-wrap items-center gap-2">

        {tags.map((tag) => (

          <TagBadge

            key={tag.id}

            tag={tag}

            compact={compact}

            onRemove={editable ? () => removeTag(tag.id) : undefined}

          />

        ))}



        {editable ? (

          adding ? (

            <div className="flex min-w-0 flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-2">

              <input

                type="text"

                value={labelDraft}

                onChange={(e) => setLabelDraft(e.target.value)}

                placeholder="태그 이름"

                className="h-9 min-w-[6rem] rounded-md border border-slate-200 px-2 text-sm"

                aria-label="새 태그 이름"

                onKeyDown={(e) => {

                  if (e.key === 'Enter') {

                    e.preventDefault();

                    addTag();

                  }

                }}

                autoFocus

              />

              <select

                value={colorDraft}

                onChange={(e) => setColorDraft(e.target.value as ConsultTagColorKey)}

                className="h-9 rounded-md border border-slate-200 px-2 text-sm"

                aria-label="태그 색상"

              >

                {CONSULT_TAG_COLORS.map((c) => (

                  <option key={c.key} value={c.key}>

                    {c.label}

                  </option>

                ))}

              </select>

              <Button variant="primary" onClick={() => addTag()} disabled={saving || !labelDraft.trim()}>

                추가

              </Button>

              <Button variant="secondary" onClick={() => setAdding(false)}>

                취소

              </Button>

            </div>

          ) : (

            <button

              type="button"

              onClick={() => setAdding(true)}

              className="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-300 px-2.5 py-1 text-sm text-slate-600 hover:border-indigo-400 hover:text-indigo-700"

            >

              <Plus className="h-3.5 w-3.5" />

              태그 추가

            </button>

          )

        ) : null}

      </div>



      {editable ? (

        <div className="flex flex-wrap gap-1.5" role="group" aria-label="기본 태그">

          {presets.map((preset) => {

            const active = tags.some(

              (t) => t.label.toLowerCase() === preset.label.toLowerCase(),

            );

            return (

              <TagBadge

                key={preset.id}

                tag={preset}

                compact

                selected={active}

                onClick={() => togglePreset(preset)}

              />

            );

          })}

        </div>

      ) : null}

    </div>

  );

}



export { DEFAULT_CONSULT_TAGS };

