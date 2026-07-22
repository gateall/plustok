import { apiFetch, setAccessToken } from './api.client';
import type { LoginResponse, MeResponse } from '../types/api.types';

export interface LoginCredentials {
  loginId: string;
  password: string;
}

export interface AuthUser {
  userId: string;
  username: string;
  loginId: string;
  name: string;
  role: string;
}

const USER_STORAGE_KEY = 'acep_user';

export async function login(loginId: string, password: string): Promise<LoginResponse> {
  const data = await apiFetch<LoginResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ loginId, username: loginId, password }),
  });
  setAccessToken(data.accessToken);
  if (data.agent) {
    localStorage.setItem(
      USER_STORAGE_KEY,
      JSON.stringify({
        userId: data.agent.id,
        username: loginId,
        loginId,
        name: data.agent.name,
        role: data.agent.role,
      } satisfies AuthUser),
    );
  }
  return data;
}

export async function logout(): Promise<void> {
  try {
    await apiFetch<{ loggedOut: boolean }>('/auth/logout', { method: 'POST' });
  } finally {
    setAccessToken(null);
    localStorage.removeItem(USER_STORAGE_KEY);
  }
}

export async function fetchMe(): Promise<MeResponse> {
  return apiFetch<MeResponse>('/auth/me');
}

export function getCurrentUser(): AuthUser | null {
  const raw = localStorage.getItem(USER_STORAGE_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as AuthUser;
  } catch {
    return null;
  }
}
