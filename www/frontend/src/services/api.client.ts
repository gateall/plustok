const API_BASE = import.meta.env.VITE_API_BASE ?? '/api/v1';
const TOKEN_STORAGE_KEY = 'acep_access_token';

let accessToken: string | null =
  typeof sessionStorage !== 'undefined' ? sessionStorage.getItem(TOKEN_STORAGE_KEY) : null;

export function setAccessToken(token: string | null): void {
  accessToken = token;
  if (typeof sessionStorage === 'undefined') return;
  if (token) {
    sessionStorage.setItem(TOKEN_STORAGE_KEY, token);
  } else {
    sessionStorage.removeItem(TOKEN_STORAGE_KEY);
  }
}

export function getAccessToken(): string | null {
  if (accessToken) {
    return accessToken;
  }
  if (typeof sessionStorage !== 'undefined') {
    accessToken = sessionStorage.getItem(TOKEN_STORAGE_KEY);
  }
  return accessToken;
}

export class ApiFetchError extends Error {
  readonly status: number;
  readonly code?: string;

  constructor(message: string, status: number, code?: string) {
    super(message);
    this.name = 'ApiFetchError';
    this.status = status;
    this.code = code;
  }
}

async function parseResponse<T>(res: Response): Promise<T> {
  const json = (await res.json()) as { success: boolean; data: T; error?: { code: string; message: string } };
  if (!res.ok || !json.success) {
    throw new ApiFetchError(json.error?.message ?? `HTTP ${res.status}`, res.status, json.error?.code);
  }
  return json.data;
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
  retried = false,
): Promise<T> {
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

  if (
    res.status === 401 &&
    !retried &&
    path !== '/auth/login' &&
    path !== '/auth/refresh'
  ) {
    try {
      await refreshToken();
      return apiFetch(path, init, true);
    } catch {
      setAccessToken(null);
      throw new Error('Session expired. Please log in again.');
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
