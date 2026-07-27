export interface UserSettings {
  theme: 'light' | 'dark' | 'system';
  locale: string;
  notifySound: boolean;
  desktopNotify: boolean;
  messagesPerPage: number;
}

export interface SettingsResponse {
  settings: UserSettings;
}
