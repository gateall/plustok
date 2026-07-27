import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { AdminErrorState, Button } from '@/components/admin-ui';
import ProductFilters, { parseProductFilters } from '@/components/products/ProductFilters';
import ProductMobileList from '@/components/products/ProductMobileList';
import ProductTable from '@/components/products/ProductTable';
import ProductForm from '@/components/products/ProductForm';
import ProductDeleteDialog from '@/components/products/ProductDeleteDialog';
import {
  useProducts,
  useProductCreate,
  useProductUpdate,
  useProductDelete,
  useProductToggle,
} from '@/hooks/useProducts';
import { useSites } from '@/hooks/useSites';
import { adminErrorFromUnknown } from '@/utils/adminErrorState';
import type { ProductItem } from '@/types/product.types';

function ProductListSkeleton() {
  return (
    <div className="space-y-3" aria-label="상품 목록 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <LoadingSkeleton key={i} className="h-32 w-full rounded-xl" />
      ))}
    </div>
  );
}

export default function AdminProductsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseProductFilters(searchParams), [searchParams]);
  const { data, isLoading, isError, error, refetch } = useProducts(filters);
  const { data: sitesData } = useSites();
  const createMutation = useProductCreate();
  const updateMutation = useProductUpdate();
  const deleteMutation = useProductDelete();
  const toggleMutation = useProductToggle();

  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<ProductItem | undefined>();
  const [deletingProduct, setDeletingProduct] = useState<ProductItem | null>(null);
  const [togglingId, setTogglingId] = useState<number | null>(null);

  const products = data?.items ?? [];
  const total = data?.total ?? 0;
  const page = data?.page ?? filters.page ?? 1;
  const limit = data?.limit ?? filters.limit ?? 20;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const sites = sitesData?.data ?? [];
  const showSiteColumn = sites.length > 0;

  const goPage = (nextPage: number) => {
    const params = new URLSearchParams(searchParams);
    if (nextPage <= 1) params.delete('page');
    else params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  };

  const handleCreate = () => {
    setEditingProduct(undefined);
    setIsFormOpen(true);
  };

  const handleEdit = (product: ProductItem) => {
    setEditingProduct(product);
    setIsFormOpen(true);
  };

  const handleSave = async (payload: {
    brand: string;
    category: string;
    productName: string;
    sortOrder: number;
    siteId?: number | null;
  }) => {
    if (editingProduct) {
      await updateMutation.mutateAsync({ id: editingProduct.id, payload });
    } else {
      await createMutation.mutateAsync(payload);
    }
  };

  const handleToggle = async (product: ProductItem) => {
    setTogglingId(product.id);
    try {
      await toggleMutation.mutateAsync(product.id);
    } finally {
      setTogglingId(null);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!deletingProduct) return;
    await deleteMutation.mutateAsync(deletingProduct.id);
    setDeletingProduct(null);
  };

  const listActions = {
    onEdit: handleEdit,
    onDelete: setDeletingProduct,
    onToggle: handleToggle,
    togglingId,
  };

  return (
    <div className="admin-page-shell min-w-0 w-full py-4 md:py-6">
      <PageHeader
        title="상품 관리"
        description={`총 ${total}개${total > 0 ? ` (페이지 ${page}/${totalPages})` : ''}`}
        actions={
          <Button variant="primary" onClick={handleCreate}>
            새 상품 등록
          </Button>
        }
      />

      <ProductFilters />

      {isLoading && <ProductListSkeleton />}

      {isError && (
        <AdminErrorState
          {...adminErrorFromUnknown(error, 'list')}
          onRetry={() => void refetch()}
        />
      )}

      {!isLoading && !isError && products.length === 0 && (
        <EmptyState
          title="등록된 상품이 없습니다"
          description="새 상품을 등록하거나 검색 조건을 변경해 주세요."
          action={
            <Button variant="primary" onClick={handleCreate}>
              새 상품 등록
            </Button>
          }
        />
      )}

      {!isLoading && !isError && products.length > 0 && (
        <>
          <ProductMobileList products={products} showSiteColumn={showSiteColumn} {...listActions} />
          <ProductTable products={products} showSiteColumn={showSiteColumn} {...listActions} />

          {totalPages > 1 && (
            <nav
              className="mt-6 flex min-w-0 items-center justify-center gap-3 pb-8"
              aria-label="페이지 네비게이션"
            >
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => goPage(page - 1)}
                className="h-11 min-w-[64px] rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 md:h-10"
              >
                이전
              </button>
              <span className="text-sm font-medium text-slate-600">
                {page} / {totalPages}
              </span>
              <button
                type="button"
                disabled={page >= totalPages}
                onClick={() => goPage(page + 1)}
                className="h-11 min-w-[64px] rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 md:h-10"
              >
                다음
              </button>
            </nav>
          )}
        </>
      )}

      <ProductForm
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        product={editingProduct}
        sites={sites}
        showSiteField={showSiteColumn}
        onSave={handleSave}
      />

      <ProductDeleteDialog
        open={deletingProduct != null}
        product={deletingProduct}
        loading={deleteMutation.isPending}
        onConfirm={() => void handleDeleteConfirm()}
        onClose={() => setDeletingProduct(null)}
      />
    </div>
  );
}
