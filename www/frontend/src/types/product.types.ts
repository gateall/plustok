export interface ProductItem {
  id: number;
  brand: string;
  category: string;
  productName: string;
  sortOrder: number;
  useYn: boolean;
  siteId: number | null;
  siteName: string | null;
  createdAt: string | null;
}

export interface ProductsResponse {
  items: ProductItem[];
  total: number;
  page: number;
  limit: number;
}

export interface ProductListFilters {
  page?: number;
  limit?: number;
  q?: string;
  brand?: string;
  use_yn?: string;
}

export interface ProductCreatePayload {
  brand: string;
  category: string;
  productName: string;
  sortOrder?: number;
  siteId?: number | null;
}

export interface ProductUpdatePayload {
  brand: string;
  category: string;
  productName: string;
  sortOrder?: number;
  siteId?: number | null;
}

export const PRODUCT_USE_YN_OPTIONS = [
  { value: '', label: '전체 상태' },
  { value: '1', label: '사용' },
  { value: '0', label: '중지' },
];
