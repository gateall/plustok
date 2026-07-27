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
  lastConsultedAt?: string | null;
};

export type SiteIntegrationFilter = '' | 'healthy' | 'needs_check' | 'inactive';

export type SiteSortKey = 'name' | 'created' | 'consult' | 'last_consult';

export type SiteListFilters = {
  q?: string;
  status?: string;
  brand?: string;
  division?: string;
  integration?: SiteIntegrationFilter;
  sort?: SiteSortKey;
};

export type SiteListSummary = {
  total: number;
  healthy: number;
  needsCheck: number;
  inactive: number;
};

/** GET /admin/sites — acep envelope data shape */
export type SiteListResponse = {
  data: SiteItem[];
};

export type SiteCreatePayload = {
  siteCode: string;
  siteName: string;
  domain?: string;
  brand: string;
  division?: string;
  persona?: string | null;
  status?: boolean;
};

export type SiteUpdatePayload = SiteCreatePayload;

export type SiteCreateResponse = {
  id: number;
  apiKey: string;
  message: string;
};

export type SiteRegenKeyResponse = {
  id: number;
  apiKey: string;
  message: string;
};

export type SiteToggleResponse = {
  id: number;
  status: boolean;
};

export type SiteDeleteResponse = {
  deleted: boolean;
  id: number;
};

export type SiteStats = {
  id: number;
  todayConsultCount: number;
  totalConsultCount: number;
  lastConsultedAt: string | null;
  lastCommunicationAt: string | null;
};

export type SiteHealthHistoryResponse = {
  data: SiteHealthCheck[];
};

/** Site detail combines item fields with optional stats */
export type SiteDetail = SiteItem & {
  stats?: SiteStats;
};
