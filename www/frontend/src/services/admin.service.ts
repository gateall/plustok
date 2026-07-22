import { apiFetch } from './api.client';
import type {
  AdminAgentsResponse,
  AdminFunnelResponse,
  AdminMonitorResponse,
  AdminOverviewResponse,
  AdminSentimentResponse,
  AdminTrendsResponse,
} from '../types/admin.types';

export const adminService = {
  overview: () => apiFetch<AdminOverviewResponse>('/admin/stats/overview'),
  sentiment: () => apiFetch<AdminSentimentResponse>('/admin/stats/sentiment'),
  funnel: () => apiFetch<AdminFunnelResponse>('/admin/stats/funnel'),
  agents: () => apiFetch<AdminAgentsResponse>('/admin/stats/agents'),
  trends: () => apiFetch<AdminTrendsResponse>('/admin/stats/trends'),
  monitorRooms: () => apiFetch<AdminMonitorResponse>('/admin/monitor/rooms'),
};
