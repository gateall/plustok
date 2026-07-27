import { useEffect, useRef, type ReactNode, type RefObject } from 'react';
import clsx from 'clsx';

const FOCUSABLE_SELECTOR =
  'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function getFocusableElements(container: HTMLElement): HTMLElement[] {
  return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(
    (el) => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true',
  );
}

type DrawerProps = {
  open: boolean;
  title?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  onClose?: () => void;
  className?: string;
  returnFocusRef?: RefObject<HTMLElement>;
};

export default function Drawer({
  open,
  title,
  children,
  footer,
  onClose,
  className,
  returnFocusRef,
}: DrawerProps) {
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    const panel = panelRef.current;
    const focusable = panel ? getFocusableElements(panel) : [];
    if (focusable.length > 0) {
      focusable[0].focus();
    } else {
      panel?.focus();
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose?.();
        return;
      }

      if (event.key !== 'Tab' || !panel) return;

      const items = getFocusableElements(panel);
      if (items.length === 0) return;

      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;

      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', onKeyDown);
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = '';
      returnFocusRef?.current?.focus();
    };
  }, [open, onClose, returnFocusRef]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[60] lg:hidden"
      role="dialog"
      aria-modal="true"
      aria-label={typeof title === 'string' ? title : 'Drawer'}
    >
      <button
        type="button"
        className="absolute inset-0 bg-slate-900/40 transition-opacity duration-250"
        aria-label="닫기"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        tabIndex={-1}
        className={clsx(
          'absolute inset-y-0 left-0 flex w-[min(20rem,85vw)] min-w-0 flex-col bg-white shadow-xl transition-transform duration-250 ease-out focus:outline-none',
          className,
        )}
      >
        {title ? (
          <div className="border-b border-slate-200 px-4 py-4 text-sm font-semibold text-slate-900">{title}</div>
        ) : null}
        <div className="flex-1 overflow-y-auto">{children}</div>
        {footer ? <div className="border-t border-slate-200 p-3">{footer}</div> : null}
      </div>
    </div>
  );
}
