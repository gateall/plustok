import StatusBadge from '@/components/admin-ui/StatusBadge';
import { Button } from '@/components/admin-ui';
import type { ProductItem } from '@/types/product.types';

type ProductTableProps = {
  products: ProductItem[];
  showSiteColumn: boolean;
  onEdit: (product: ProductItem) => void;
  onDelete: (product: ProductItem) => void;
  onToggle: (product: ProductItem) => void;
  togglingId?: number | null;
};

export default function ProductTable({
  products,
  showSiteColumn,
  onEdit,
  onDelete,
  onToggle,
  togglingId,
}: ProductTableProps) {
  return (
    <div className="admin-desktop-table overflow-hidden rounded-xl border border-[var(--pt-color-border)] bg-white shadow-sm">
      <div className="table-scroll w-full overflow-x-auto">
        <table className="w-full text-left text-sm text-slate-600">
          <thead className="bg-slate-50 text-slate-700">
            <tr>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">브랜드</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">카테고리</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold">상품명</th>
              {showSiteColumn && (
                <th className="whitespace-nowrap px-4 py-3 font-semibold">전용사이트</th>
              )}
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">순서</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-center">상태</th>
              <th className="whitespace-nowrap px-4 py-3 font-semibold text-right">관리</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--pt-color-border)]">
            {products.map((product) => (
              <tr key={product.id} className="transition-colors hover:bg-slate-50">
                <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                  {product.brand}
                </td>
                <td className="px-4 py-3">{product.category}</td>
                <td className="px-4 py-3 font-medium text-slate-900">{product.productName}</td>
                {showSiteColumn && (
                  <td className="px-4 py-3">
                    {product.siteName ?? <span className="text-slate-400">(공유)</span>}
                  </td>
                )}
                <td className="whitespace-nowrap px-4 py-3 text-center tabular-nums">
                  {product.sortOrder}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-center">
                  <StatusBadge
                    label={product.useYn ? '사용' : '중지'}
                    tone={product.useYn ? 'success' : 'neutral'}
                  />
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right">
                  <div className="flex justify-end gap-1">
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
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
