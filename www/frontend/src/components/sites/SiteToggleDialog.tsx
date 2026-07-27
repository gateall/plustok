import { ConfirmDialog } from '@/components/admin-ui';
import type { SiteItem } from '@/types/site.types';

type SiteToggleDialogProps = {
  open: boolean;
  site: SiteItem | null;
  loading?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export default function SiteToggleDialog({
  open,
  site,
  loading = false,
  onConfirm,
  onClose,
}: SiteToggleDialogProps) {
  const activating = site != null && !site.status;

  return (
    <ConfirmDialog
      open={open}
      title={activating ? '사이트 활성화' : '사이트 중지'}
      description={
        site ? (
          <>
            {activating ? (
              <>
                <strong>{site.siteName}</strong> 사이트를 활성화하시겠습니까?
              </>
            ) : (
              <>
                이 사이트를 중지하시겠습니까?
                <br />
                <strong>{site.siteName}</strong> ({site.siteCode})
              </>
            )}
          </>
        ) : null
      }
      confirmLabel={activating ? '활성화' : '중지'}
      confirmVariant={activating ? 'primary' : 'danger'}
      loading={loading}
      onConfirm={onConfirm}
      onClose={onClose}
    />
  );
}
