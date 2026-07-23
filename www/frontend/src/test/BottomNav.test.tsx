import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import BottomNav from '../components/Admin/BottomNav';

vi.mock('../features/auth/AuthProvider', () => ({
  useAuth: () => ({ user: { name: '테스트', role: 'admin' } }),
}));

function renderBottomNav(initial = '/admin/dashboard') {
  return render(
    <MemoryRouter initialEntries={[initial]}>
      <BottomNav />
    </MemoryRouter>,
  );
}

describe('BottomNav', () => {
  it('renders primary tabs and 더보기', () => {
    renderBottomNav();
    expect(screen.getByRole('link', { name: /홈/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /상담/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /고객/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /통계/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /더보기/i })).toHaveAttribute('href', '/admin/more');
  });

  it('marks active tab for current route', () => {
    renderBottomNav('/admin/consults');
    const consultLink = screen.getByRole('link', { name: /상담/i });
    expect(consultLink.className).toMatch(/indigo-600/);
  });
});
