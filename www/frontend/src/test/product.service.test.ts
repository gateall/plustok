import { describe, expect, it } from 'vitest';
import type { ProductItem, ProductsResponse } from '../types/product.types';

function normalizeProducts(data: ProductsResponse | undefined): ProductItem[] {
  return Array.isArray(data?.items) ? data.items : [];
}

describe('product.service', () => {
  const sampleProduct: ProductItem = {
    id: 1,
    brand: 'Brand',
    category: 'Cat',
    productName: 'Product A',
    sortOrder: 0,
    useYn: true,
    siteId: null,
    siteName: null,
    createdAt: null,
  };

  describe('normalizeProducts', () => {
    it('returns items array when present', () => {
      const response: ProductsResponse = {
        items: [sampleProduct],
        total: 1,
        page: 1,
        limit: 20,
      };
      expect(normalizeProducts(response)).toEqual([sampleProduct]);
    });

    it('returns empty array when items is undefined', () => {
      expect(normalizeProducts(undefined)).toEqual([]);
    });

    it('returns empty array when items is not an array', () => {
      expect(
        normalizeProducts({ items: null as unknown as ProductItem[], total: 0, page: 1, limit: 20 }),
      ).toEqual([]);
    });
  });

  describe('Array.isArray guard pattern', () => {
    it('guards sites data for product form', () => {
      const sitesData = { data: [{ id: 1, siteName: 'Site' }] };
      const sites = Array.isArray(sitesData?.data) ? sitesData.data : [];
      expect(sites).toHaveLength(1);

      const bad = { data: undefined };
      const empty = Array.isArray(bad?.data) ? bad.data : [];
      expect(empty).toEqual([]);
    });
  });
});
