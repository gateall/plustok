import { X } from 'lucide-react';
import clsx from 'clsx';
import Tag, { type TagData, tagChipClass } from './Tag';

type TagBadgeProps = {
  tag: TagData;
  compact?: boolean;
  onRemove?: () => void;
  onClick?: () => void;
  selected?: boolean;
  className?: string;
};

export default function TagBadge({
  tag,
  compact,
  onRemove,
  onClick,
  selected,
  className,
}: TagBadgeProps) {
  const interactive = Boolean(onClick);

  return (
    <span
      className={clsx(
        'tag-badge inline-flex items-center gap-1 rounded-full ring-1 ring-inset',
        tagChipClass(tag.color),
        compact ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm',
        interactive && 'cursor-pointer hover:ring-2',
        selected && 'ring-2 ring-indigo-500 ring-offset-1',
        className,
      )}
      role={interactive ? 'button' : undefined}
      tabIndex={interactive ? 0 : undefined}
      onClick={onClick}
      onKeyDown={
        interactive
          ? (e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onClick?.();
              }
            }
          : undefined
      }
    >
      {tag.label}
      {onRemove ? (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            onRemove();
          }}
          className="rounded-full p-0.5 hover:bg-black/10"
          aria-label={`${tag.label} 태그 제거`}
        >
          <X className="h-3 w-3" />
        </button>
      ) : null}
    </span>
  );
}

export { Tag };
