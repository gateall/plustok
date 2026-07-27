import { useCallback, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Drawer } from '@/components/admin-ui';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import ConsultListPanel from './ConsultListPanel';
import ConsultDetailPanel from './ConsultDetailPanel';
import ConsultCustomerSidePanel from './ConsultCustomerSidePanel';
import { useConsultDetail } from '@/hooks/useConsultDetail';

export default function ConsultManagerPage() {
  const { id: selectedId } = useParams<{ id?: string }>();
  const navigate = useNavigate();
  const isDesktop = useIsDesktop(992);
  const isPcWide = useMediaQuery('(min-width: 1024px)');
  const [listDrawerOpen, setListDrawerOpen] = useState(false);
  const [customerDrawerOpen, setCustomerDrawerOpen] = useState(false);
  const listTriggerRef = useRef<HTMLButtonElement>(null);
  const customerTriggerRef = useRef<HTMLButtonElement>(null);

  const { data: consult } = useConsultDetail(selectedId);

  const selectConsult = useCallback(
    (id: string) => {
      navigate(`/admin/consults/${encodeURIComponent(id)}`);
      setListDrawerOpen(false);
    },
    [navigate],
  );

  const showListOnMobile = isDesktop || !selectedId;
  const showDetailOnMobile = isDesktop || !!selectedId;
  const listOnlyWide = isPcWide && !selectedId;

  if (listOnlyWide) {
    return (
      <div className="consult-manager-page consult-manager-page--list-only admin-consults-page flex min-h-[calc(100dvh-4rem)] min-w-0 flex-col">
        <ConsultListPanel layoutMode="full" onSelectConsult={selectConsult} />
      </div>
    );
  }

  return (
    <div className="consult-manager-page flex min-h-[calc(100dvh-8rem)] min-w-0 flex-col min-[992px]:min-h-[calc(100dvh-4rem)]">
      <div className="consult-manager-grid flex min-h-0 flex-1 overflow-hidden rounded-none border-0 bg-slate-100 min-[992px]:rounded-xl min-[992px]:border min-[992px]:border-slate-200">
        <aside
          className={`consult-manager-list w-full shrink-0 border-r border-slate-200 bg-white min-[992px]:w-1/4 ${
            showListOnMobile ? 'flex min-h-0 flex-col' : 'hidden min-[992px]:flex min-[992px]:min-h-0 min-[992px]:flex-col'
          }`}
        >
          <ConsultListPanel
            selectedId={selectedId}
            onSelectConsult={selectConsult}
            compact={!!selectedId && !isDesktop}
            layoutMode="panel"
          />
        </aside>

        <section
          className={`consult-manager-detail min-w-0 flex-1 min-[992px]:w-1/2 ${
            showDetailOnMobile ? 'flex min-h-0 flex-col' : 'hidden min-[992px]:flex min-[992px]:min-h-0 min-[992px]:flex-col'
          }`}
        >
          <ConsultDetailPanel
            consultId={selectedId}
            showMobileChrome={!isDesktop && !!selectedId}
            listTriggerRef={listTriggerRef}
            aiTriggerRef={customerTriggerRef}
            onOpenList={() => setListDrawerOpen(true)}
            onOpenAi={() => setCustomerDrawerOpen(true)}
            customerPanelLabel="고객"
          />
        </section>

        <aside className="consult-manager-customer hidden w-1/4 shrink-0 border-l border-slate-200 min-[992px]:flex min-[992px]:min-h-0 min-[992px]:flex-col">
          <ConsultCustomerSidePanel consult={consult ?? null} className="min-h-0 flex-1" />
        </aside>
      </div>

      <Drawer open={listDrawerOpen} title="상담 목록" returnFocusRef={listTriggerRef} onClose={() => setListDrawerOpen(false)}>
        <ConsultListPanel selectedId={selectedId} onSelectConsult={selectConsult} compact layoutMode="panel" />
      </Drawer>

      <Drawer
        open={customerDrawerOpen}
        title="고객 정보"
        returnFocusRef={customerTriggerRef}
        onClose={() => setCustomerDrawerOpen(false)}
        className="consult-customer-drawer left-auto right-0"
      >
        <ConsultCustomerSidePanel consult={consult ?? null} />
      </Drawer>
    </div>
  );
}
