import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import toast from 'react-hot-toast';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import ProductFormFields, {
  buildProductPayload,
  productToFormValues,
  type ProductFormValues,
} from '@/components/products/ProductFormFields';
import { useProduct, useProductUpdate } from '@/hooks/useProducts';
import { useSites } from '@/hooks/useSites';

export default function ProductEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const productId = id ? parseInt(id, 10) : undefined;

  const { data: product, isLoading, isError, error } = useProduct(productId);
  const updateMutation = useProductUpdate();
  const { data: sitesData } = useSites();

  const [values, setValues] = useState<ProductFormValues>({
    brand: '',
    category: '',
    productName: '',
    sortOrder: '0',
    siteId: '',
  });
  const [formError, setFormError] = useState('');

  const sites = Array.isArray(sitesData?.data) ? sitesData.data : [];
  const showSiteField = sites.length > 0;

  useEffect(() => {
    if (product) {
      setValues(productToFormValues(product));
    }
  }, [product]);

  const handleChange = (field: keyof ProductFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!productId) return;
    setFormError('');
    try {
      await updateMutation.mutateAsync({
        id: productId,
        payload: buildProductPayload(values, showSiteField),
      });
      toast.success('상품이 수정되었습니다.');
      navigate('/admin/products');
    } catch (err) {
      const message = err instanceof Error ? err.message : '수정에 실패했습니다.';
      setFormError(message);
      toast.error(message);
    }
  };

  if (isLoading) {
    return (
      <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
        <LoadingSkeleton className="mb-4 h-8 w-48 rounded-lg" />
        <LoadingSkeleton className="h-96 w-full rounded-xl" />
      </div>
    );
  }

  if (isError || !product) {
    return (
      <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
        <EmptyState
          title="상품을 불러오지 못했습니다"
          description={error instanceof Error ? error.message : '잠시 후 다시 시도해 주세요.'}
          action={
            <Link to="/admin/products" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
              목록으로
            </Link>
          }
        />
      </div>
    );
  }

  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
      <Link
        to="/admin/products"
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft size={16} aria-hidden />
        상품 목록
      </Link>

      <PageHeader title="상품 수정" description={product.productName} />

      <div className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
        <ProductFormFields
          values={values}
          onChange={handleChange}
          sites={sites}
          showSiteField={showSiteField}
          error={formError}
          onSubmit={(e) => void handleSubmit(e)}
        />

        <div className="mt-4 flex gap-3">
          <button
            type="submit"
            form="product-form"
            disabled={updateMutation.isPending}
            className="admin-touch-target h-11 flex-1 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {updateMutation.isPending ? '저장 중…' : '저장'}
          </button>
          <Link
            to="/admin/products"
            className="admin-touch-target inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            취소
          </Link>
        </div>
      </div>
    </div>
  );
}
