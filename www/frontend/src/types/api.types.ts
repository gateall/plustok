export interface ApiError {
  code: string;
  message: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T | null;
  error: ApiError | null;
  timestamp: string;
}

export interface AgentSummary {
  id: string;
  name: string;
  role: string;
  status: string;
}

export interface LoginResponse {
  accessToken: string;
  expiresIn: number;
  agent: AgentSummary;
}

export interface MeResponse {
  id: string;
  loginId: string;
  name: string;
  role: string;
  status: string;
  avatarUrl: string | null;
  lastLoginAt: string | null;
}
