import { describe, expect, it, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import ConsultDetailPage from '../pages/ConsultDetailPage';
import type { ConsultDetail } from '../types/consult.types';

const mockConsult: ConsultDetail = {
  id: 'room-uuid-1',
  source: 'acep',
  consultNo: 'C202607220017',
  status: 'active',
  customerNameMasked: '김**',
  phoneMasked: '010-****-5678',
  email: null,
  siteName: 'LG15441644',
  productName: '인터넷',
  memo: '인터넷 설치 문의드립니다.',
  agent: { id: 'a1', displayName: '상담원1' },
  roomId: 'room-uuid-1',
  aiEnabled: true,
  contractProbability: 82,
  aiSummary: null,
  createdAt: new Date().toISOString(),
  updatedAt: new Date().toISOString(),
};

vi.mock('@/hooks/useConsultDetail', () => ({
  useConsultDetail: vi.fn(),
}));

vi.mock('@/components/consults/ConsultChatPanel', () => ({
  default: ({ roomId }: { roomId: string }) => (
    <div data-testid="consult-chat-panel">chat:{roomId}</div>
  ),
}));

import { useConsultDetail } from '@/hooks/useConsultDetail';

function renderDetail(id = 'room-uuid-1') {
  return render(
    <MemoryRouter initialEntries={[`/admin/consults/${id}`]}>
      <Routes>
        <Route path="/admin/consults/:id" element={<ConsultDetailPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('ConsultDetailPage', () => {
  beforeEach(() => {
    vi.mocked(useConsultDetail).mockReturnValue({
      data: mockConsult,
      isLoading: false,
      error: null,
    } as ReturnType<typeof useConsultDetail>);
  });

  it('renders consult number, summary, quick actions, and chat panel', () => {
    renderDetail();
    expect(screen.getByRole('heading', { level: 1, name: 'C202607220017' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 2, name: '김**' })).toBeInTheDocument();
    expect(screen.getByText('전화')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '채팅' })).toBeInTheDocument();
    expect(screen.getByTestId('consult-chat-panel')).toHaveTextContent('chat:room-uuid-1');
  });

  it('links back to consult list', () => {
    renderDetail();
    const back = screen.getByRole('link', { name: /목록으로/i });
    expect(back).toHaveAttribute('href', '/admin/consults');
  });

  it('shows loading state', () => {
    vi.mocked(useConsultDetail).mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
    } as ReturnType<typeof useConsultDetail>);
    renderDetail();
    expect(screen.getByText(/불러오는 중/i)).toBeInTheDocument();
  });

  it('scrolls to chat when quick action tapped', async () => {
    const user = userEvent.setup();
    renderDetail();
    const chatBtn = screen.getByRole('button', { name: '채팅' });
    await user.click(chatBtn);
    expect(screen.getByTestId('consult-chat-panel')).toBeInTheDocument();
  });
});
