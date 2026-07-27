import { apiFetch } from './api.client';
import type { SettingsResponse, UserSettings } from '../types/settings.types';

export const settingsService = {
  get: () => apiFetch<SettingsResponse>('/settings'),
  update: (settings: Partial<UserSettings>) => 
    apiFetch<SettingsResponse>('/settings', {
      method: 'PUT',
      body: JSON.stringify({ settings }),
    }),
};
