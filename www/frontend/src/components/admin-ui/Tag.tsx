import clsx from 'clsx';
import {
  CONSULT_TAG_COLORS,
  DEFAULT_CONSULT_TAGS,
  type ConsultTag,
  type ConsultTagColorKey,
} from '@/types/consult.types';

export type TagData = ConsultTag;

export function tagChipClass(color: ConsultTagColorKey): string {
  return CONSULT_TAG_COLORS.find((c) => c.key === color)?.chipClass ?? CONSULT_TAG_COLORS[0].chipClass;
}

export function createTag(label: string, color?: ConsultTagColorKey): TagData {
  const preset = DEFAULT_CONSULT_TAGS.find((t) => t.label === label);
  return {
    id: `tag-${label}-${Date.now()}`,
    label,
    color: color ?? preset?.color ?? 'indigo',
  };
}

export function presetTags(): TagData[] {
  return DEFAULT_CONSULT_TAGS.map((t, i) => ({
    id: `preset-${i}-${t.label}`,
    label: t.label,
    color: t.color,
  }));
}

type TagProps = TagData & {
  compact?: boolean;
  className?: string;
};

export default function Tag({ label, color, compact, className }: TagProps) {
  return (
    <span
      className={clsx(
        'admin-tag inline-flex items-center rounded-full ring-1 ring-inset',
        tagChipClass(color),
        compact ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm',
        className,
      )}
    >
      {label}
    </span>
  );
}

export { DEFAULT_CONSULT_TAGS, CONSULT_TAG_COLORS };
