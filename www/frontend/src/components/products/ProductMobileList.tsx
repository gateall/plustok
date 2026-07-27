import StatusBadge from '@/components/admin-ui/StatusBadge';
import { Button } from '@/components/admin-ui';
import type { ProductItem } from '@/types/product.types';

type ProductMobileListProps = {
  products: ProductItem[];
  showSiteColumn: boolean;
  onEdit: (product: ProductItem) => void;
  onDelete: (product: ProductItem) => void;
  onToggle: (product: ProductItem) => void;
  togglingId?: number | null;
};

function ProductCard({
  product,
  showSiteColumn,
  onEdit,
  onDelete,
  onToggle,
  togglingId,
}: {
  product: ProductItem;
  showSiteColumn: boolean;
  onEdit: (product: ProductItem) => void;
  onDelete: (product: ProductItem) => void;
  onToggle: (product: ProductItem) => void;
  togglingId?: number | null;
}) {
  return (
    <div className="flex flex-col gap-3 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate font-bold text-slate-900">{product.productName}</p>
          <p className="mt-0.5 text-xs text-slate-500">
            {product.brand} · {product.category}
          </p>
        </div>
        <StatusBadge
          label={product.useYn ? '사용' : '중지'}
          tone={product.useYn ? 'success' : 'neutral'}
        />
      </div>
      <div className="flex flex-col gap-1 text-sm text-slate-600">
        {showSiteColumn && (
          <p>
            전용사이트:{' '}
            {product.siteName ?? <span className="text-slate-400">(공유)</span>}
          </p>
        )}
        <p className="text-xs text-slate-500">정렬 순서 {product.sortOrder}</p>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="ghost" onClick={() => onEdit(product)}>
          수정
        </Button>
        <Button
          variant="secondary"
          disabled={togglingId === product.id}
          onClick={() => onToggle(product)}
        >
          {product.useYn ? '중지' : '사용'}
        </Button>
        <Button variant="danger" onClick={() => onDelete(product)}>
          삭제
        </Button>
      </div>
    </div>
  );
}

export default function ProductMobileList(props: ProductMobileListProps) {
  const { products, ...rest } = props;
  return (
    <div className="admin-mobile-list flex flex-col gap-3">
      {products.map((product) => (
        <ProductCard key={product.id} product={product} {...rest} />
      ))}
    </div>
  );
}
