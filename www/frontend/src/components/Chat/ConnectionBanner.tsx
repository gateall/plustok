interface ConnectionBannerProps {
  isConnected: boolean;
  error: string | null;
}

export function ConnectionBanner({ isConnected, error }: ConnectionBannerProps) {
  if (isConnected && !error) return null;

  return (
    <div
      className={`px-4 py-2 text-center text-xs ${
        error ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'
      }`}
    >
      {error === 'UNAUTHORIZED'
        ? '연결 오류: JWT 인증 실패 — 로그아웃 후 다시 로그인하세요 (Render JWT_SECRET 동기화 확인)'
        : error
          ? `연결 오류: ${error}`
          : 'WebSocket 재연결 중…'}
    </div>
  );
}
