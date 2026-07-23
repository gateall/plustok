import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import toast from 'react-hot-toast';
import { forgotId } from '../services/auth.service';

export default function FindIdPage() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      await forgotId(name, email);
      setSent(true);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '요청 실패');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-acep-surface px-4">
      <div className="w-full max-w-md rounded-xl border border-acep-border bg-white p-8 shadow-sm">
        <h1 className="mb-2 text-2xl font-semibold text-slate-900">아이디 찾기</h1>
        <p className="mb-6 text-sm text-slate-500">가입된 이름과 이메일을 입력하세요.</p>

        {sent ? (
          <p className="rounded-lg bg-green-50 p-4 text-sm text-green-700">
            입력하신 정보가 확인되면 이메일로 아이디를 발송했습니다. 메일함을 확인해주세요.
          </p>
        ) : (
          <form onSubmit={onSubmit}>
            <label className="mb-4 block text-sm font-medium text-slate-700">
              이름
              <input
                className="mt-1 w-full rounded-lg border border-acep-border px-3 py-2"
                value={name}
                onChange={(e) => setName(e.target.value)}
                autoComplete="name"
                required
              />
            </label>

            <label className="mb-6 block text-sm font-medium text-slate-700">
              이메일
              <input
                type="email"
                className="mt-1 w-full rounded-lg border border-acep-border px-3 py-2"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                autoComplete="email"
                required
              />
            </label>

            <button
              type="submit"
              disabled={submitting}
              className="w-full rounded-lg bg-acep-primary py-2.5 font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {submitting ? '전송 중…' : '아이디 이메일로 받기'}
            </button>
          </form>
        )}

        <Link to="/login" className="mt-6 block text-center text-sm text-acep-primary hover:underline">
          로그인으로 돌아가기
        </Link>
      </div>
    </div>
  );
}
