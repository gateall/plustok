import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import toast from 'react-hot-toast';

import PageHeader from '@/components/common/PageHeader';
import ProductFormFields, {
  buildProductPayload,
  emptyProductFormValues,
  type ProductFormValues,
} from '@/components/products/ProductFormFields';
import { useProductCreate } from '@/hooks/useProducts';
import { useSites } from '@/hooks/useSites';

export default function ProductCreatePage() {
  const navigate = useNavigate();
  const createMutation = useProductCreate();
  const { data: sitesData } = useSites();

  const [values, setValues] = useState<ProductFormValues>(emptyProductFormValues);
  const [error, setError] = useState('');

  const sites = Array.isArray(sitesData?.data) ? sitesData.data : [];
  const showSiteField = sites.length > 0;

  const handleChange = (field: keyof ProductFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    try {
      const created = await createMutation.mutateAsync(buildProductPayload(values, showSiteField));
      toast.success('상품이 등록되었습니다.');
      navigate(`/admin/products/${created.id}/edit`);
    } catch (err) {
      const message = err instanceof Error ? err.message : '등록에 실패했습니다.';
      setError(message);
      toast.error(message);
    }
  };

  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
      <Link
        to="/admin/products"
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft size={16} aria-hidden />
        상품 목록
      </Link>

      <PageHeader title="새 상품 등록" description="필수: 브랜드, 카테고리, 상품명" />

      <div className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
        <ProductFormFields
          values={values}
          onChange={handleChange}
          sites={sites}
          showSiteField={showSiteField}
          error={error}
          onSubmit={(e) => void handleSubmit(e)}
        />

        <div className="mt-4 flex gap-3">
          <button
            type="submit"
            form="product-form"
            disabled={createMutation.isPending}
            className="admin-touch-target h-11 flex-1 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {createMutation.isPending ? '등록 중…' : '등록'}
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
