import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthProvider';

const ADMIN_ROLES = new Set(['admin', 'operator']);

export function AdminRoute({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-500">
        로딩 중…
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (!ADMIN_ROLES.has(user.role)) {
    return <Navigate to="/chat" replace />;
  }

  return <>{children}</>;
}
