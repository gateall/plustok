import { describe, expect, it, vi, beforeEach } from 'vitest';
import { apiFetch, refreshToken, setAccessToken, getAccessToken } from '../services/api.client';

describe('api.client', () => {
  beforeEach(() => {
    setAccessToken(null);
    vi.restoreAllMocks();
  });

  it('stores and retrieves access token', () => {
    setAccessToken('abc');
    expect(getAccessToken()).toBe('abc');
    setAccessToken(null);
    expect(getAccessToken()).toBeNull();
  });

  it('parses successful API envelope', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: { ok: true }, error: null }),
      }),
    );
    const data = await apiFetch<{ ok: boolean }>('/system/health');
    expect(data.ok).toBe(true);
  });

  it('throws on API error envelope', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 400,
        json: async () => ({
          success: false,
          data: null,
          error: { code: 'VALIDATION_ERROR', message: 'bad request' },
        }),
      }),
    );
    await expect(apiFetch('/auth/login')).rejects.toThrow('bad request');
  });

  it('dedupes concurrent refreshToken calls into a single request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { accessToken: 'new-token' }, error: null }),
    });
    vi.stubGlobal('fetch', fetchMock);

    await Promise.all([refreshToken(), refreshToken(), refreshToken()]);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(getAccessToken()).toBe('new-token');
  });
});
