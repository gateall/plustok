import { ConfirmDialog } from '@/components/admin-ui';
import type { SiteItem } from '@/types/site.types';

type SiteDeleteDialogProps = {
  open: boolean;
  site: SiteItem | null;
  loading?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export default function SiteDeleteDialog({
  open,
  site,
  loading = false,
  onConfirm,
  onClose,
}: SiteDeleteDialogProps) {
  return (
    <ConfirmDialog
      open={open}
      title="사이트 삭제"
      description={
        site ? (
          <>
            <strong>{site.siteName}</strong> ({site.siteCode}) 사이트를 삭제할까요?
            <br />
            상담 이력이 있는 사이트는 삭제할 수 없습니다.
          </>
        ) : null
      }
      confirmLabel="삭제"
      confirmVariant="danger"
      loading={loading}
      onConfirm={onConfirm}
      onClose={onClose}
    />
  );
}
