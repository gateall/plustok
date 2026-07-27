export type SiteHealthCheck = {
  checkedAt: string | null;
  isHealthy: boolean;
  responseMs: number | null;
  statusCode: number | null;
  errorMessage: string | null;
  targetUrl: string | null;
};

export type SiteItem = {
  id: number;
  siteCode: string;
  siteName: string;
  domain: string;
  brand: string;
  division: string;
  persona: string | null;
  status: boolean;
  createdAt: string | null;
  updatedAt: string | null;
  lastHealthCheck: SiteHealthCheck | null;
  todayConsultCount?: number;
  totalConsultCount?: number;
};

export type SiteListFilters = {
  q?: string;
  status?: string;
  brand?: string;
  division?: string;
};

/** GET /admin/sites — acep envelope data shape */
export type SiteListResponse = {
  data: SiteItem[];
};
