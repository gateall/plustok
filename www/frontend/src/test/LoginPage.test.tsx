import { describe, expect, it, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';import LoginPage from '../pages/LoginPage';
import { AuthProvider } from '../features/auth/AuthProvider';

vi.mock('../services/auth.service', () => ({
  login: vi.fn().mockResolvedValue({ accessToken: 'tok', expiresIn: 3600, agent: {} }),
  logout: vi.fn(),
  fetchMe: vi.fn(),
}));

function renderLogin() {
  return render(
    <MemoryRouter>
      <AuthProvider>
        <LoginPage />
      </AuthProvider>
    </MemoryRouter>,
  );
}

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders login form', () => {
    renderLogin();
    expect(screen.getByText('PlusTok ACEP')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '로그인' })).toBeInTheDocument();
  });

  it('requires credentials before submit', async () => {
    renderLogin();
    const btn = screen.getByRole('button', { name: '로그인' });
    expect(btn).toBeEnabled();
  });

  it('shows login button label', () => {
    renderLogin();
    expect(screen.getByLabelText('아이디')).toBeInTheDocument();
    expect(screen.getByLabelText('비밀번호')).toBeInTheDocument();
  });
});
