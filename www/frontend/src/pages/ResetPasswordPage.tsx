import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { resetPassword } from '../services/auth.service';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const navigate = useNavigate();

  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (password !== confirm) {
      toast.error('비밀번호가 일치하지 않습니다.');
      return;
    }
    setSubmitting(true);
    try {
      await resetPassword(token, password);
      toast.success('비밀번호가 변경되었습니다. 다시 로그인해주세요.');
      navigate('/login', { replace: true });
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '재설정 실패');
    } finally {
      setSubmitting(false);
    }
  }

  if (!token) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-acep-surface px-4">
        <div className="w-full max-w-md rounded-xl border border-acep-border bg-white p-8 shadow-sm text-center">
          <p className="text-sm text-slate-500">유효하지 않은 접근입니다.</p>
          <Link to="/forgot-password" className="mt-4 block text-sm text-acep-primary hover:underline">
            비밀번호 찾기로 이동
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-acep-surface px-4">
      <form
        onSubmit={onSubmit}
        className="w-full max-w-md rounded-xl border border-acep-border bg-white p-8 shadow-sm"
      >
        <h1 className="mb-6 text-2xl font-semibold text-slate-900">새 비밀번호 설정</h1>

        <label className="mb-4 block text-sm font-medium text-slate-700">
          새 비밀번호
          <input
            type="password"
            className="mt-1 w-full rounded-lg border border-acep-border px-3 py-2"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="new-password"
            minLength={8}
            required
          />
        </label>

        <label className="mb-6 block text-sm font-medium text-slate-700">
          새 비밀번호 확인
          <input
            type="password"
            className="mt-1 w-full rounded-lg border border-acep-border px-3 py-2"
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            autoComplete="new-password"
            minLength={8}
            required
          />
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-acep-primary py-2.5 font-medium text-white hover:bg-blue-700 disabled:opacity-60"
        >
          {submitting ? '변경 중…' : '비밀번호 변경'}
        </button>
      </form>
    </div>
  );
}
