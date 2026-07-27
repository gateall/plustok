import Select from '@/components/admin-ui/Select';
import type { SiteItem } from '@/types/site.types';

export type ProductFormValues = {
  brand: string;
  category: string;
  productName: string;
  sortOrder: string;
  siteId: string;
};

export type ProductFormFieldsProps = {
  values: ProductFormValues;
  onChange: (field: keyof ProductFormValues, value: string) => void;
  sites: SiteItem[];
  showSiteField: boolean;
  error?: string;
  formId?: string;
  onSubmit: (e: React.FormEvent) => void;
};

export default function ProductFormFields({
  values,
  onChange,
  sites,
  showSiteField,
  error,
  formId = 'product-form',
  onSubmit,
}: ProductFormFieldsProps) {
  const siteOptions = [
    { value: '', label: '(브랜드 공유)' },
    ...sites.map((site) => ({
      value: String(site.id),
      label: site.siteName,
    })),
  ];

  return (
    <>
      {error && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <form id={formId} onSubmit={onSubmit} className="space-y-4">
        <label className="block">
          <span className="text-sm font-medium text-slate-700">브랜드 *</span>
          <input
            type="text"
            value={values.brand}
            onChange={(e) => onChange('brand', e.target.value)}
            required
            maxLength={50}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
            placeholder="브랜드명"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">카테고리 *</span>
          <input
            type="text"
            value={values.category}
            onChange={(e) => onChange('category', e.target.value)}
            required
            maxLength={60}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
            placeholder="카테고리"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">상품명 *</span>
          <input
            type="text"
            value={values.productName}
            onChange={(e) => onChange('productName', e.target.value)}
            required
            maxLength={100}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
            placeholder="상품명"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">순서</span>
          <input
            type="number"
            value={values.sortOrder}
            onChange={(e) => onChange('sortOrder', e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        {showSiteField && (
          <label className="block">
            <span className="text-sm font-medium text-slate-700">
              전용 사이트 (비우면 브랜드 공유)
            </span>
            <Select
              value={values.siteId}
              onChange={(e) => onChange('siteId', e.target.value)}
              options={siteOptions}
            />
          </label>
        )}
      </form>
    </>
  );
}

export function buildProductPayload(
  values: ProductFormValues,
  showSiteField: boolean,
): {
  brand: string;
  category: string;
  productName: string;
  sortOrder: number;
  siteId?: number | null;
} {
  return {
    brand: values.brand.trim(),
    category: values.category.trim(),
    productName: values.productName.trim(),
    sortOrder: parseInt(values.sortOrder, 10) || 0,
    siteId: showSiteField ? (values.siteId ? parseInt(values.siteId, 10) : null) : undefined,
  };
}

export function productToFormValues(product: {
  brand: string;
  category: string;
  productName: string;
  sortOrder: number;
  siteId: number | null;
}): ProductFormValues {
  return {
    brand: product.brand,
    category: product.category,
    productName: product.productName,
    sortOrder: String(product.sortOrder),
    siteId: product.siteId != null ? String(product.siteId) : '',
  };
}

export const emptyProductFormValues: ProductFormValues = {
  brand: '',
  category: '',
  productName: '',
  sortOrder: '0',
  siteId: '',
};
