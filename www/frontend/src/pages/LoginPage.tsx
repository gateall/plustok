import { FormEvent, useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import toast from 'react-hot-toast';
import { useAuth } from '../features/auth/AuthProvider';

export default function LoginPage() {
  const { user, login } = useAuth();
  const location = useLocation();
  const [loginId, setLoginId] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

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
          <div className="relative mt-1">
            <input
              type={showPassword ? 'text' : 'password'}
              className="w-full rounded-lg border border-acep-border px-3 py-2 pr-10"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              required
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
              aria-label={showPassword ? '비밀번호 숨기기' : '비밀번호 표시'}
              tabIndex={-1}
            >
              {showPassword ? (
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5">
                  <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-5.05 0-9.29-3.14-11-7 .9-2.02 2.32-3.76 4.06-5.06M9.9 4.24A10.94 10.94 0 0 1 12 4c5.05 0 9.29 3.14 11 7-.5 1.13-1.2 2.16-2.05 3.05M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                  <line x1="1" y1="1" x2="23" y2="23" />
                </svg>
              ) : (
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              )}
            </button>
          </div>
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-acep-primary py-2.5 font-medium text-white hover:bg-blue-700 disabled:opacity-60"
        >
          {submitting ? '로그인 중…' : '로그인'}
        </button>

        <div className="mt-4 flex items-center justify-center gap-3 text-sm text-slate-500">
          <Link to="/find-id" className="hover:text-acep-primary hover:underline">
            아이디 찾기
          </Link>
          <span className="text-slate-300">|</span>
          <Link to="/forgot-password" className="hover:text-acep-primary hover:underline">
            비밀번호 찾기
          </Link>
        </div>
      </form>
    </div>
  );
}
