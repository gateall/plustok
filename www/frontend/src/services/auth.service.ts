import { apiFetch, setAccessToken } from './api.client';
import type { LoginResponse, MeResponse } from '../types/api.types';

export async function login(loginId: string, password: string): Promise<LoginResponse> {
  const data = await apiFetch<LoginResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ loginId, password }),
  });
  setAccessToken(data.accessToken);
  return data;
}

export async function logout(): Promise<void> {
  await apiFetch<{ loggedOut: boolean }>('/auth/logout', { method: 'POST' });
  setAccessToken(null);
}

export async function fetchMe(): Promise<MeResponse> {
  return apiFetch<MeResponse>('/auth/me');
}
