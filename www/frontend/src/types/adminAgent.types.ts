export interface AdminAgent {
  id: string;
  displayName: string;
  loginId: string;
  role: 'agent' | 'admin' | 'operator';
  status: 'online' | 'away' | 'offline';
  activeAssignments: number;
}

export interface AdminAgentListResponse {
  data: AdminAgent[];
}

export interface AdminAgentCreatePayload {
  loginId: string;
  password?: string;
  displayName: string;
  role: 'agent' | 'admin' | 'operator';
}

export interface AdminAgentUpdatePayload {
  displayName?: string;
  role?: 'agent' | 'admin' | 'operator';
  status?: 'online' | 'away' | 'offline';
}
