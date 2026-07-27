import { useEffect, useState } from 'react';
import clsx from 'clsx';
import TagBadge from './TagBadge';
import { presetTags, type TagData } from './Tag';
import SearchBox from './SearchBox';

type TagFilterProps = {
  value?: string;
  onChange: (tag: string | undefined) => void;
  availableTags?: TagData[];
  className?: string;
  showSearch?: boolean;
};

export default function TagFilter({
  value = '',
  onChange,
  availableTags,
  className,
  showSearch = true,
}: TagFilterProps) {
  const tags = availableTags ?? presetTags();
  const [draft, setDraft] = useState(value);

  useEffect(() => {
    setDraft(value);
  }, [value]);

  const active = value.trim().toLowerCase();

  const selectTag = (label: string) => {
    const next = active === label.toLowerCase() ? undefined : label;
    onChange(next);
  };

  const applySearch = () => {
    onChange(draft.trim() || undefined);
  };

  return (
    <div className={clsx('tag-filter space-y-2', className)}>
      {showSearch ? (
        <SearchBox
          value={draft}
          onChange={setDraft}
          onSubmit={applySearch}
          placeholder="태그 검색 (예: VIP)"
          label="태그 필터"
        />
      ) : null}

      <div className="flex flex-wrap gap-1.5" role="group" aria-label="태그 필터">
        {tags.map((tag) => (
          <TagBadge
            key={tag.id}
            tag={tag}
            compact
            selected={active === tag.label.toLowerCase()}
            onClick={() => selectTag(tag.label)}
          />
        ))}
      </div>
    </div>
  );
}
