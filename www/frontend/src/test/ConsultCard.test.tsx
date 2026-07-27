import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ConsultCard from '../components/consults/ConsultCard';
import type { ConsultListItem } from '../types/consult.types';

const sampleConsult: ConsultListItem = {
  id: 'room-uuid-1',
  source: 'acep',
  customerNameMasked: '김**',
  phoneMasked: '010-****-5678',
  agent: { id: 'a1', displayName: '상담원1' },
  status: 'active',
  aiEnabled: true,
  contractProbability: 82,
  createdAt: new Date(Date.now() - 7200000).toISOString(),
  updatedAt: new Date(Date.now() - 7200000).toISOString(),
  siteName: 'LG15441644',
  productName: '인터넷',
  lastMessagePreview: '안녕하세요, 인터넷 설치 문의드립니다.',
};

function renderCard(consult: ConsultListItem = sampleConsult) {
  return render(
    <MemoryRouter>
      <ConsultCard consult={consult} />
    </MemoryRouter>,
  );
}

describe('ConsultCard', () => {
  it('renders status badge, customer, site, and action buttons', () => {
    renderCard();
    expect(screen.getByText('상담중')).toBeInTheDocument();
    expect(screen.getByText(/김\*\*/)).toBeInTheDocument();
    expect(screen.getByText(/LG15441644/)).toBeInTheDocument();
    expect(screen.getByText('전화')).toBeInTheDocument();
    expect(screen.getByText('채팅')).toBeInTheDocument();
    expect(screen.getByText('상세보기')).toBeInTheDocument();
  });

  it('shows recent message preview with clamp class', () => {
    renderCard();
    const preview = screen.getByText('안녕하세요, 인터넷 설치 문의드립니다.');
    expect(preview).toHaveClass('text-overflow-clamp-2');
  });

  it('links detail view to consult id route', () => {
    renderCard();
    const detail = screen.getByRole('link', { name: /상세보기/i });
    expect(detail).toHaveAttribute('href', '/admin/consults/room-uuid-1');
  });

  it('disables phone when no phone masked', () => {
    renderCard({ ...sampleConsult, phoneMasked: undefined });
    expect(screen.getByRole('button', { name: /전화/i })).toBeDisabled();
  });
});
