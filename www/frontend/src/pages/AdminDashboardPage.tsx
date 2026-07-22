import { Link } from 'react-router-dom';
import { useAuth } from '../features/auth/AuthProvider';
import { SocketProvider } from '../hooks/useSocket';
import { useAdminSocket } from '../hooks/useAdminSocket';
import AdminDashboard from '../components/Admin/AdminDashboard';

function AdminDashboardInner() {
  const { user, logout } = useAuth();
  useAdminSocket();

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200 bg-white px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div>
            <h1 className="text-xl font-bold text-slate-900">PlusTok AI 운영 센터</h1>
            <p className="text-sm text-slate-500">Admin Dashboard · {user?.name}</p>
          </div>
          <nav className="flex items-center gap-4 text-sm">
            <Link to="/chat" className="text-indigo-600 hover:underline">
              상담 화면
            </Link>
            <button type="button" onClick={() => void logout()} className="text-slate-600 hover:text-slate-900">
              로그아웃
            </button>
          </nav>
        </div>
      </header>
      <main className="mx-auto max-w-7xl px-6 py-8">
        <AdminDashboard />
      </main>
    </div>
  );
}

export default function AdminDashboardPage() {
  return (
    <SocketProvider>
      <AdminDashboardInner />
    </SocketProvider>
  );
}
