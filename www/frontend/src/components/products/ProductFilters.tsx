import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import FilterBar from '@/components/common/FilterBar';
import SearchBox from '@/components/admin-ui/SearchBox';
import Select from '@/components/admin-ui/Select';
import { PRODUCT_USE_YN_OPTIONS } from '@/types/product.types';
import type { ProductListFilters } from '@/types/product.types';

export function parseProductFilters(params: URLSearchParams): ProductListFilters {
  const page = parseInt(params.get('page') || '1', 10);
  const limit = parseInt(params.get('limit') || '20', 10);
  const useYn = params.get('use_yn') || undefined;
  const q = params.get('q') || undefined;
  const brand = params.get('brand') || undefined;

  return {
    page: Number.isNaN(page) ? 1 : page,
    limit: Number.isNaN(limit) ? 20 : limit,
    use_yn: useYn,
    q,
    brand,
  };
}

export default function ProductFilters() {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialQ = searchParams.get('q') ?? '';
  const initialUseYn = searchParams.get('use_yn') ?? '';

  const [q, setQ] = useState(initialQ);
  const [useYn, setUseYn] = useState(initialUseYn);

  useEffect(() => {
    setQ(searchParams.get('q') ?? '');
    setUseYn(searchParams.get('use_yn') ?? '');
  }, [searchParams]);

  const applyFilters = (newQ: string, newUseYn: string) => {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.delete('page');

    if (newQ.trim()) nextParams.set('q', newQ.trim());
    else nextParams.delete('q');

    if (newUseYn) nextParams.set('use_yn', newUseYn);
    else nextParams.delete('use_yn');

    setSearchParams(nextParams, { replace: true });
  };

  const handleSearchSubmit = () => {
    applyFilters(q, useYn);
  };

  const handleUseYnChange = (val: string) => {
    setUseYn(val);
    applyFilters(q, val);
  };

  return (
    <FilterBar className="mb-4">
      <div className="flex w-full min-w-0 flex-col gap-2 md:flex-row md:items-center">
        <Select
          value={useYn}
          onChange={(e) => handleUseYnChange(e.target.value)}
          options={PRODUCT_USE_YN_OPTIONS}
          className="w-full md:w-36"
        />
        <SearchBox
          value={q}
          onChange={setQ}
          onSubmit={handleSearchSubmit}
          placeholder="브랜드, 카테고리, 상품명 검색"
          className="w-full"
        />
      </div>
    </FilterBar>
  );
}
