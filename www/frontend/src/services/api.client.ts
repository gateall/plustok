const API_BASE = import.meta.env.VITE_API_BASE ?? '/api/v1';

let accessToken: string | null = null;

export function setAccessToken(token: string | null): void {
  accessToken = token;
}

export function getAccessToken(): string | null {
  return accessToken;
}

async function parseResponse<T>(res: Response): Promise<T> {
  const json = (await res.json()) as { success: boolean; data: T; error?: { code: string; message: string } };
  if (!res.ok || !json.success) {
    throw new Error(json.error?.message ?? `HTTP ${res.status}`);
  }
  return json.data;
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Content-Type', 'application/json');
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`);
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
    credentials: 'include',
  });

  if (res.status === 401 && path !== '/auth/login' && path !== '/auth/refresh') {
    try {
      await refreshToken();
      return apiFetch(path, init);
    } catch {
      setAccessToken(null);
    }
  }

  return parseResponse<T>(res);
}

export async function refreshToken(): Promise<void> {
  const res = await fetch(`${API_BASE}/auth/refresh`, {
    method: 'POST',
    credentials: 'include',
  });
  const data = await parseResponse<{ accessToken: string }>(res);
  setAccessToken(data.accessToken);
}

export { API_BASE };
