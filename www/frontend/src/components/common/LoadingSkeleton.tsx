import clsx from 'clsx';

type LoadingSkeletonProps = {
  className?: string;
  lines?: number;
};

/** Unified loading placeholder — pulse blocks for cards and lists. */
export default function LoadingSkeleton({ className, lines = 1 }: LoadingSkeletonProps) {
  if (lines <= 1) {
    return <div className={clsx('animate-pulse rounded-lg bg-slate-200', className)} aria-hidden />;
  }

  return (
    <div className={clsx('space-y-3', className)} aria-label="로딩 중">
      {Array.from({ length: lines }).map((_, i) => (
        <div key={i} className="h-4 animate-pulse rounded bg-slate-200" style={{ width: `${100 - i * 12}%` }} />
      ))}
    </div>
  );
}

export function KpiGridSkeleton() {
  return (
    <div className="grid min-w-0 grid-cols-2 gap-3 md:grid-cols-4 md:gap-4" aria-label="KPI 로딩 중">
      {Array.from({ length: 4 }).map((_, i) => (
        <div key={i} className="min-w-0 rounded-[14px] border border-slate-200 bg-white p-3 sm:p-4">
          <LoadingSkeleton className="mb-2 h-3 w-16" />
          <LoadingSkeleton className="h-8 w-20" />
          <LoadingSkeleton className="mt-2 h-3 w-24" />
        </div>
      ))}
    </div>
  );
}
