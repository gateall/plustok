export interface CustomerItem {
  id: string;
  customerNo: string | null;
  name: string;
  companyName: string | null;
  phone: string | null;
  emailMasked: string | null;
  status: string;
  primaryProduct: string | null;
  siteName: string | null;
  siteId: number | null;
  consultCount: number;
  managerId: string | null;
  lastConsultAt: string | null;
  firstConsultAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface CustomersResponse {
  items: CustomerItem[];
  total: number;
  page: number;
  limit: number;
}

export interface CustomerListFilters {
  page?: number;
  limit?: number;
  status?: string;
  q?: string;
  site_id?: number;
  sort?: string;
  order?: 'asc' | 'desc';
}

export const CUSTOMER_STATUS_OPTIONS = [
  { value: '', label: '전체 상태' },
  { value: 'new', label: '신규' },
  { value: 'active', label: '진행중' },
  { value: 'closed', label: '종료/완료' },
];

export const CUSTOMER_STATUS_LABELS: Record<string, string> = {
  new: '신규',
  active: '진행중',
  closed: '완료',
};
