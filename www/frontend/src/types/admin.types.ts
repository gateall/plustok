export interface KpiMetric {
  value: number;
  deltaPercent: number;
  deltaDirection: 'up' | 'down' | 'flat';
}

export interface AdminOverviewResponse {
  generatedAt: string;
  period: { start: string; end: string };
  kpis: {
    activeChats: KpiMetric;
    avgResponseSec: KpiMetric;
    aiAdoptionRate: KpiMetric;
    contractConversion: KpiMetric;
  };
  sparklines: {
    activeChats: number[];
    avgResponseSec: number[];
  };
}

export interface SentimentSegment {
  label: string;
  count: number;
  percent: number;
}

export interface AdminSentimentResponse {
  generatedAt: string;
  segments: SentimentSegment[];
}

export interface FunnelBucket {
  label: string;
  count: number;
}

export interface AdminFunnelResponse {
  generatedAt: string;
  buckets: FunnelBucket[];
}

export interface AdminAgentMetric {
  id: string;
  displayName: string;
  status: string;
  closedToday: number;
  avgResponseSec: number;
}

export interface AdminAgentsResponse {
  generatedAt: string;
  agents: AdminAgentMetric[];
}

export interface HourlyTrendPoint {
  hour: string;
  messages: number;
}

export interface AdminTrendsResponse {
  generatedAt: string;
  series: HourlyTrendPoint[];
}

export interface MonitorRoom {
  id: string;
  status: string;
  customerNameMasked: string;
  agent: { id: string; displayName: string } | null;
  updatedAt: string;
  lastMessagePreview: string;
  contractProbability: number;
}

export interface AdminMonitorResponse {
  rooms: MonitorRoom[];
  generatedAt: string;
}
