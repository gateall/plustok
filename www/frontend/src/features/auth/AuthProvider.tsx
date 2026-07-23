import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { fetchMe, login as loginApi, logout as logoutApi } from '../../services/auth.service';
import { getAccessToken, setAccessToken } from '../../services/api.client';
import type { AgentSummary, MeResponse } from '../../types/api.types';

function agentToMe(agent: AgentSummary, loginId: string): MeResponse {
  return {
    id: agent.id,
    loginId,
    name: agent.name,
    role: agent.role,
    status: agent.status,
    avatarUrl: null,
    lastLoginAt: null,
  };
}

interface AuthContextValue {
  user: MeResponse | null;
  loading: boolean;
  login: (loginId: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<MeResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      if (!getAccessToken()) {
        setLoading(false);
        return;
      }
      try {
        setUser(await fetchMe());
      } catch {
        setAccessToken(null);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const login = useCallback(async (loginId: string, password: string) => {
    const res = await loginApi(loginId, password);
    setAccessToken(res.accessToken);
    if (res.agent) {
      setUser(agentToMe(res.agent, loginId));
    }

    // admin/operator: 통합 로그인 화면(/frontend/#/login)에서 바로 Admin 패널로 이동
    if (res.agent && (res.agent.role === 'admin' || res.agent.role === 'operator')) {
      const ssoRes = await fetch('/admin/sso.php', {
        method: 'POST',
        credentials: 'include',
        headers: { Authorization: `Bearer ${res.accessToken}` },
      });
      if (!ssoRes.ok) {
        let detail = '';
        try {
          const body = (await ssoRes.json()) as { error?: string };
          if (body.error) {
            detail = ` (${body.error})`;
          }
        } catch {
          detail = ` (HTTP ${ssoRes.status})`;
        }
        throw new Error(`관리자 세션 생성에 실패했습니다${detail}`);
      }
      window.location.hash = '#/admin/dashboard';
      return;
    }

    try {
      setUser(await fetchMe());
    } catch {
      // Keep user from login response when /auth/me fails (e.g. Authorization header stripped)
    }
  }, []);

  const logout = useCallback(async () => {
    await logoutApi();
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({ user, loading, login, logout }),
    [user, loading, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}
