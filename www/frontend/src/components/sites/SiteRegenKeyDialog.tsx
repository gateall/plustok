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
            <strong>{site.siteName}</strong>의 API Key를 재발급할까요?
            <br />
            기존 Key는 즉시 무효화됩니다. 새 Key는 한 번만 표시됩니다.
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
