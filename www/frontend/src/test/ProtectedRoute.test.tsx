import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProtectedRoute } from '../features/auth/ProtectedRoute';

vi.mock('../features/auth/AuthProvider', () => ({
  useAuth: vi.fn(),
}));

import { useAuth } from '../features/auth/AuthProvider';

describe('ProtectedRoute', () => {
  it('shows loading state', () => {
    vi.mocked(useAuth).mockReturnValue({
      user: null,
      loading: true,
      login: vi.fn(),
      logout: vi.fn(),
    });
    render(
      <MemoryRouter>
        <ProtectedRoute>
          <div>Secret</div>
        </ProtectedRoute>
      </MemoryRouter>,
    );
    expect(screen.getByText('로딩 중…')).toBeInTheDocument();
  });

  it('redirects unauthenticated user to login', () => {
    vi.mocked(useAuth).mockReturnValue({
      user: null,
      loading: false,
      login: vi.fn(),
      logout: vi.fn(),
    });
    render(
      <MemoryRouter initialEntries={['/chat']}>
        <Routes>
          <Route path="/login" element={<div>Login</div>} />
          <Route
            path="/chat"
            element={
              <ProtectedRoute>
                <div>Secret</div>
              </ProtectedRoute>
            }
          />
        </Routes>
      </MemoryRouter>,
    );
    expect(screen.getByText('Login')).toBeInTheDocument();
  });

  it('renders children when authenticated', () => {
    vi.mocked(useAuth).mockReturnValue({
      user: {
        id: '1',
        loginId: 'admin',
        name: 'Admin',
        role: 'admin',
        status: 'online',
        avatarUrl: null,
        lastLoginAt: null,
      },
      loading: false,
      login: vi.fn(),
      logout: vi.fn(),
    });
    render(
      <MemoryRouter>
        <ProtectedRoute>
          <div>Secret</div>
        </ProtectedRoute>
      </MemoryRouter>,
    );
    expect(screen.getByText('Secret')).toBeInTheDocument();
  });
});
