import { describe, expect, it, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import ConsultDetailPage from '../pages/ConsultDetailPage';

vi.mock('@/components/consults/manager/ConsultListPanel', () => ({
  default: () => <div data-testid="consult-list-panel">list</div>,
}));

vi.mock('@/components/consults/manager/ConsultDetailPanel', () => ({
  default: ({ consultId }: { consultId?: string }) => (
    <div data-testid="consult-detail-panel">{consultId ?? 'none'}</div>
  ),
}));

vi.mock('@/components/consults/manager/ConsultCustomerSidePanel', () => ({
  default: () => <div data-testid="consult-customer-side">customer</div>,
}));

vi.mock('@/hooks/useConsultDetail', () => ({
  useConsultDetail: vi.fn(() => ({ data: null, isLoading: false, error: null })),
}));

vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: vi.fn(() => true),
  useIsDesktop: vi.fn(() => true),
}));

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
    vi.clearAllMocks();
  });

  it('renders consult manager layout with detail panel for selected id', () => {
    renderDetail();
    expect(screen.getByTestId('consult-list-panel')).toBeInTheDocument();
    expect(screen.getByTestId('consult-detail-panel')).toHaveTextContent('room-uuid-1');
  });
});
