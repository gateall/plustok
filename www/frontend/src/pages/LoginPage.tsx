import { FormEvent, useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import toast from 'react-hot-toast';
import { useAuth } from '../features/auth/AuthProvider';

export default function LoginPage() {
  const { user, login } = useAuth();
  const location = useLocation();
  const [loginId, setLoginId] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const from = (location.state as { from?: { pathname: string } })?.from?.pathname ?? '/chat';

  if (user) {
    return <Navigate to={from} replace />;
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      await login(loginId, password);
      toast.success('로그인되었습니다.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '로그인 실패');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-acep-surface px-4">
      <form
        onSubmit={onSubmit}
        className="w-full max-w-md rounded-xl border border-acep-border bg-white p-8 shadow-sm"
      >
        <h1 className="mb-2 text-2xl font-semibold text-slate-900">PlusTok ACEP</h1>
        <p className="mb-6 text-sm text-slate-500">상담원 로그인</p>

        <label className="mb-4 block text-sm font-medium text-slate-700">
          아이디
          <input
            className="mt-1 w-full rounded-lg border border-acep-border px-3 py-2"
            value={loginId}
            onChange={(e) => setLoginId(e.target.value)}
            autoComplete="username"
            required
          />
        </label>

        <label className="mb-6 block text-sm font-medium text-slate-700">
          비밀번호
          <input
            type="password"
            className="mt-1 w-full rounded-lg border border-acep-border px-3 py-2"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
          />
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-acep-primary py-2.5 font-medium text-white hover:bg-blue-700 disabled:opacity-60"
        >
          {submitting ? '로그인 중…' : '로그인'}
        </button>
      </form>
    </div>
  );
}
