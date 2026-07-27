import { useRef, type KeyboardEvent } from 'react';
import clsx from 'clsx';
import type { ConsultDetailTab } from '@/types/consult.types';

const TABS: { id: ConsultDetailTab; label: string }[] = [
  { id: 'chat', label: '채팅' },
  { id: 'memo', label: '메모' },
  { id: 'history', label: '이력' },
  { id: 'files', label: '파일' },
];

export function tabId(tab: ConsultDetailTab): string {
  return `consult-tab-${tab}`;
}

export function tabPanelId(tab: ConsultDetailTab): string {
  return `consult-tabpanel-${tab}`;
}

type ConsultDetailTabsProps = {
  active: ConsultDetailTab;
  onChange: (tab: ConsultDetailTab) => void;
};

export default function ConsultDetailTabs({ active, onChange }: ConsultDetailTabsProps) {
  const tabRefs = useRef<(HTMLButtonElement | null)[]>([]);

  const focusTab = (index: number) => {
    const tab = TABS[index];
    if (!tab) return;
    onChange(tab.id);
    tabRefs.current[index]?.focus();
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
    let nextIndex: number | null = null;

    switch (event.key) {
      case 'ArrowRight':
        nextIndex = (index + 1) % TABS.length;
        break;
      case 'ArrowLeft':
        nextIndex = (index - 1 + TABS.length) % TABS.length;
        break;
      case 'Home':
        nextIndex = 0;
        break;
      case 'End':
        nextIndex = TABS.length - 1;
        break;
      case 'Enter':
      case ' ':
        event.preventDefault();
        onChange(TABS[index].id);
        return;
      default:
        return;
    }

    event.preventDefault();
    focusTab(nextIndex);
  };

  return (
    <div
      className="consult-detail-tabs flex min-w-0 gap-1 overflow-x-auto border-b border-slate-200 bg-white px-2"
      role="tablist"
      aria-label="상담 상세 탭"
    >
      {TABS.map((tab, index) => {
        const selected = active === tab.id;
        return (
          <button
            key={tab.id}
            ref={(el) => {
              tabRefs.current[index] = el;
            }}
            id={tabId(tab.id)}
            type="button"
            role="tab"
            aria-selected={selected}
            aria-controls={tabPanelId(tab.id)}
            tabIndex={selected ? 0 : -1}
            className={clsx(
              'shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2',
              selected
                ? 'border-indigo-600 text-indigo-700'
                : 'border-transparent text-slate-500 hover:text-slate-800',
            )}
            onClick={() => onChange(tab.id)}
            onKeyDown={(event) => handleKeyDown(event, index)}
          >
            {tab.label}
          </button>
        );
      })}
    </div>
  );
}

export { TABS };
