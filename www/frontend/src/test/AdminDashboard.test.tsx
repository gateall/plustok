import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AdminDashboard from '../components/Admin/AdminDashboard';

vi.mock('../services/admin.service', () => ({
  adminService: {
    overview: vi.fn().mockResolvedValue({
      generatedAt: new Date().toISOString(),
      period: { start: '', end: '' },
      kpis: {
        activeChats: { value: 5, deltaPercent: 2, deltaDirection: 'up' },
        avgResponseSec: { value: 90, deltaPercent: -5, deltaDirection: 'down' },
        aiAdoptionRate: { value: 68, deltaPercent: 1, deltaDirection: 'up' },
        contractConversion: { value: 12, deltaPercent: 0, deltaDirection: 'flat' },
      },
      sparklines: { activeChats: [1, 2, 3, 5], avgResponseSec: [100, 95, 90, 90] },
    }),
    sentiment: vi.fn().mockResolvedValue({ generatedAt: '', segments: [] }),
    funnel: vi.fn().mockResolvedValue({ generatedAt: '', buckets: [] }),
    agents: vi.fn().mockResolvedValue({ generatedAt: '', agents: [] }),
    trends: vi.fn().mockResolvedValue({ generatedAt: '', series: [] }),
    monitorRooms: vi.fn().mockResolvedValue({ generatedAt: '', rooms: [] }),
  },
}));

function renderDashboard() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <AdminDashboard />
    </QueryClientProvider>,
  );
}

describe('AdminDashboard', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders 5 dashboard sections', async () => {
    renderDashboard();
    expect(await screen.findByText('실시간 현황')).toBeInTheDocument();
    expect(screen.getByText('상담원 현황')).toBeInTheDocument();
    expect(screen.getByText('AI 성과')).toBeInTheDocument();
    expect(screen.getByText('고객 분석')).toBeInTheDocument();
    expect(screen.getByText('시간대별 추이')).toBeInTheDocument();
    expect(screen.getByText('Live Monitor')).toBeInTheDocument();
  });

  it('shows KPI values from overview API', async () => {
    renderDashboard();
    expect(await screen.findByText('5')).toBeInTheDocument();
    expect(screen.getByText('활성 상담')).toBeInTheDocument();
  });
});
