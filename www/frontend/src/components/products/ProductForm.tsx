import { useEffect, useState } from 'react';

import ProductFormFields, {
  buildProductPayload,
  emptyProductFormValues,
  productToFormValues,
  type ProductFormValues,
} from '@/components/products/ProductFormFields';
import type { ProductItem } from '@/types/product.types';
import type { SiteItem } from '@/types/site.types';

type ProductFormProps = {
  isOpen: boolean;
  onClose: () => void;
  product?: ProductItem;
  sites: SiteItem[];
  showSiteField: boolean;
  onSave: (payload: {
    brand: string;
    category: string;
    productName: string;
    sortOrder: number;
    siteId?: number | null;
  }) => Promise<void>;
};

export default function ProductForm({
  isOpen,
  onClose,
  product,
  sites,
  showSiteField,
  onSave,
}: ProductFormProps) {
  const [values, setValues] = useState<ProductFormValues>(emptyProductFormValues);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (product) {
      setValues(productToFormValues(product));
    } else {
      setValues(emptyProductFormValues);
    }
    setError('');
  }, [product, isOpen]);

  if (!isOpen) return null;

  const handleChange = (field: keyof ProductFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      await onSave(buildProductPayload(values, showSiteField));
      onClose();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : '저장 중 오류가 발생했습니다.';
      setError(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div className="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-lg">
        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h2 className="text-lg font-semibold text-slate-800">
            {product ? '상품 수정' : '새 상품 등록'}
          </h2>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <span className="sr-only">닫기</span>
            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-5">
          <ProductFormFields
            values={values}
            onChange={handleChange}
            sites={sites}
            showSiteField={showSiteField}
            error={error}
            onSubmit={handleSubmit}
          />
        </div>

        <div className="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            취소
          </button>
          <button
            type="submit"
            form="product-form"
            disabled={isSubmitting}
            className="rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {isSubmitting ? '저장 중...' : '저장'}
          </button>
        </div>
      </div>
    </div>
  );
}
