import { Link } from 'react-router-dom';

export default function NotFoundPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4">
      <h1 className="text-2xl font-semibold">404</h1>
      <p className="text-slate-500">페이지를 찾을 수 없습니다.</p>
      <Link to="/landing" className="text-acep-primary hover:underline">
        홈으로
      </Link>
    </div>
  );
}
