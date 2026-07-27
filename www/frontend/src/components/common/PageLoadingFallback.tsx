import AppBrand from '@/components/common/AppBrand';
import Loading from '@/components/admin-ui/Loading';

/** Route-level Suspense fallback — mobile-first skeleton. */
export default function PageLoadingFallback() {
  return (
    <div className="flex min-h-dvh flex-col bg-slate-50">
      <header className="border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
        <AppBrand linkTo={false} />
      </header>
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-5">
        <Loading variant="skeleton" lines={4} label="페이지 로딩 중" />
      </main>
    </div>
  );
}
