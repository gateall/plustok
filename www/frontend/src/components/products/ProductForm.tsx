import { useEffect, useState } from 'react';

import Select from '@/components/admin-ui/Select';
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
  const [brand, setBrand] = useState('');
  const [category, setCategory] = useState('');
  const [productName, setProductName] = useState('');
  const [sortOrder, setSortOrder] = useState('0');
  const [siteId, setSiteId] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (product) {
      setBrand(product.brand);
      setCategory(product.category);
      setProductName(product.productName);
      setSortOrder(String(product.sortOrder));
      setSiteId(product.siteId != null ? String(product.siteId) : '');
    } else {
      setBrand('');
      setCategory('');
      setProductName('');
      setSortOrder('0');
      setSiteId('');
    }
    setError('');
  }, [product, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      await onSave({
        brand: brand.trim(),
        category: category.trim(),
        productName: productName.trim(),
        sortOrder: parseInt(sortOrder, 10) || 0,
        siteId: showSiteField ? (siteId ? parseInt(siteId, 10) : null) : undefined,
      });
      onClose();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : '저장 중 오류가 발생했습니다.';
      setError(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const siteOptions = [
    { value: '', label: '(브랜드 공유)' },
    ...sites.map((site) => ({
      value: String(site.id),
      label: site.siteName,
    })),
  ];

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
          {error && (
            <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {error}
            </div>
          )}

          <form id="product-form" onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">브랜드 *</label>
              <input
                type="text"
                value={brand}
                onChange={(e) => setBrand(e.target.value)}
                required
                maxLength={50}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-sky-500 focus:ring-sky-500"
                placeholder="브랜드명"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">카테고리 *</label>
              <input
                type="text"
                value={category}
                onChange={(e) => setCategory(e.target.value)}
                required
                maxLength={60}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-sky-500 focus:ring-sky-500"
                placeholder="카테고리"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">상품명 *</label>
              <input
                type="text"
                value={productName}
                onChange={(e) => setProductName(e.target.value)}
                required
                maxLength={100}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-sky-500 focus:ring-sky-500"
                placeholder="상품명"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">순서</label>
              <input
                type="number"
                value={sortOrder}
                onChange={(e) => setSortOrder(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-sky-500 focus:ring-sky-500"
              />
            </div>

            {showSiteField && (
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">
                  전용 사이트 (비우면 브랜드 공유)
                </label>
                <Select
                  value={siteId}
                  onChange={(e) => setSiteId(e.target.value)}
                  options={siteOptions}
                />
              </div>
            )}
          </form>
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
