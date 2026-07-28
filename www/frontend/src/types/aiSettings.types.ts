export interface AiProviderSetting {
  provider: string;
  model: string;
  hasKey: boolean;
  maskedKey: string;
  updatedAt: string | null;
  apiKey?: string; // used for updating
}

export interface AiSettings {
  enabled: boolean;
  activeProvider: string;
  providers: AiProviderSetting[];
}

export interface AiTestResponse {
  success: boolean;
  provider: string;
  latencyMs: number;
  message?: string;
  error?: string;
}
