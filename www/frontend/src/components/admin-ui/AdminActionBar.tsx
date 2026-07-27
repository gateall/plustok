/**
 * Internal multi-action row helper — NOT a PM frozen component name.
 * PM freeze: use QuickAction for 3-col quick-action tiles; import this file directly when a button row is required.
 */
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { MoreVertical } from 'lucide-react';
import clsx from 'clsx';
import Button from './Button';

export type AdminActionItem = {
  id: string;
  label: string;
  icon?: ReactNode;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  disabled?: boolean;
  onClick?: () => void;
  to?: string;
  href?: string;
};

type AdminActionBarProps = {
  actions: AdminActionItem[];
  className?: string;
  /** Collapse overflow actions into ⋮ menu on mobile when count exceeds this. */
  collapseAfter?: number;
};

function ActionButton({ action, fullWidth }: { action: AdminActionItem; fullWidth?: boolean }) {
  const shared = {
    variant: action.variant ?? 'secondary',
    icon: action.icon,
    disabled: action.disabled,
    fullWidth,
    className: clsx(fullWidth && 'w-full md:w-auto md:flex-1'),
    onClick: action.onClick,
  };

  if (action.to) {
    return (
      <Button to={action.to} {...shared}>
        {action.label}
      </Button>
    );
  }

  if (action.href) {
    return (
      <Button href={action.href} {...shared}>
        {action.label}
      </Button>
    );
  }

  return (
    <Button {...shared}>
      {action.label}
    </Button>
  );
}

function useIsMobile(breakpointPx = 768): boolean {
  const [isMobile, setIsMobile] = useState(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return true;
    }
    return window.matchMedia(`(max-width: ${breakpointPx - 1}px)`).matches;
  });

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') return;
    const mq = window.matchMedia(`(max-width: ${breakpointPx - 1}px)`);
    const update = () => setIsMobile(mq.matches);
    update();
    mq.addEventListener('change', update);
    return () => mq.removeEventListener('change', update);
  }, [breakpointPx]);

  return isMobile;
}

/** @deprecated Use QuickAction from `@/components/admin-ui` for 3-col quick-action tiles. */
export { default as QuickAction } from './QuickAction';

export default function AdminActionBar({ actions, className, collapseAfter = 3 }: AdminActionBarProps) {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const isMobile = useIsMobile();

  useEffect(() => {
    if (!menuOpen) return;
    const onPointerDown = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setMenuOpen(false);
      }
    };
    document.addEventListener('pointerdown', onPointerDown);
    return () => document.removeEventListener('pointerdown', onPointerDown);
  }, [menuOpen]);

  const useMobileMenu = isMobile && actions.length >= collapseAfter;
  const visibleActions = useMobileMenu ? actions.slice(0, Math.max(1, collapseAfter - 1)) : actions;
  const overflowActions = useMobileMenu ? actions.slice(Math.max(1, collapseAfter - 1)) : [];

  return (
    <div className={clsx('admin-action-bar min-w-0', className)}>
      <div className="flex min-w-0 flex-col gap-2 md:flex-row md:flex-wrap">
        {visibleActions.map((action) => (
          <ActionButton key={action.id} action={action} fullWidth={isMobile} />
        ))}

        {overflowActions.length > 0 ? (
          <div className="relative w-full md:w-auto" ref={menuRef}>
            <Button
              variant="ghost"
              fullWidth={isMobile}
              icon={<MoreVertical className="h-4 w-4" aria-hidden />}
              aria-expanded={menuOpen}
              aria-haspopup="menu"
              onClick={() => setMenuOpen((open) => !open)}
            >
              더보기
            </Button>
            {menuOpen ? (
              <div
                role="menu"
                className="admin-action-menu absolute bottom-full left-0 z-20 mb-2 w-full min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg md:bottom-auto md:top-full md:mb-0 md:mt-2 md:w-48"
              >
                {overflowActions.map((action) => (
                  <div key={action.id} role="none" className="px-2 py-1">
                    <ActionButton action={action} fullWidth />
                  </div>
                ))}
              </div>
            ) : null}
          </div>
        ) : null}
      </div>
    </div>
  );
}
