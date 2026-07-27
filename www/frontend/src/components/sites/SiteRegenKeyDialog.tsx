import { ConfirmDialog } from '@/components/admin-ui';
import type { SiteItem } from '@/types/site.types';

type SiteRegenKeyDialogProps = {
  open: boolean;
  site: SiteItem | null;
  loading?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export default function SiteRegenKeyDialog({
  open,
  site,
  loading = false,
  onConfirm,
  onClose,
}: SiteRegenKeyDialogProps) {
  return (
    <ConfirmDialog
      open={open}
      title="API Key 재발급"
      description={
        site ? (
          <>
            API 키를 재발급하면 기존 연동이 중단될 수 있습니다.
            <br />
            계속하시겠습니까?
            <br />
            <strong>{site.siteName}</strong> ({site.siteCode})
          </>
        ) : null
      }
      confirmLabel="재발급"
      confirmVariant="primary"
      loading={loading}
      onConfirm={onConfirm}
      onClose={onClose}
    />
  );
}
