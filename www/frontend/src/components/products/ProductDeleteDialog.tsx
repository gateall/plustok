import { ConfirmDialog } from '@/components/admin-ui';
import type { ProductItem } from '@/types/product.types';

type ProductDeleteDialogProps = {
  open: boolean;
  product: ProductItem | null;
  loading?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export default function ProductDeleteDialog({
  open,
  product,
  loading = false,
  onConfirm,
  onClose,
}: ProductDeleteDialogProps) {
  return (
    <ConfirmDialog
      open={open}
      title="상품 삭제"
      description={
        product ? (
          <>
            <strong>{product.productName}</strong> 상품을 삭제할까요?
            <br />
            상담이 연결된 상품은 삭제할 수 없습니다.
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
