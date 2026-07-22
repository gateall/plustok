import { describe, expect, it, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { AuthProvider, useAuth } from '../features/auth/AuthProvider';
import { setAccessToken } from '../services/api.client';

vi.mock('../services/auth.service', () => ({
  fetchMe: vi.fn(),
  login: vi.fn(),
  logout: vi.fn(),
}));

import { fetchMe } from '../services/auth.service';

describe('AuthProvider', () => {
  beforeEach(() => {
    setAccessToken(null);
    vi.clearAllMocks();
  });

  it('starts with no user when no token', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper: AuthProvider });
    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.user).toBeNull();
  });

  it('loads user when token exists', async () => {
    setAccessToken('tok');
    vi.mocked(fetchMe).mockResolvedValue({
      id: '1',
      loginId: 'admin',
      name: 'Admin',
      role: 'admin',
      status: 'online',
      avatarUrl: null,
      lastLoginAt: null,
    });
    const { result } = renderHook(() => useAuth(), { wrapper: AuthProvider });
    await waitFor(() => expect(result.current.user?.loginId).toBe('admin'));
  });
});
