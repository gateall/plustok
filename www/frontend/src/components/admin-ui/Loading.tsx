import clsx from 'clsx';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import { Loader2 } from 'lucide-react';

type LoadingProps = {
  variant?: 'spinner' | 'skeleton';
  lines?: number;
  label?: string;
  className?: string;
};

export default function Loading({
  variant = 'spinner',
  lines = 3,
  label = '로딩 중',
  className,
}: LoadingProps) {
  if (variant === 'skeleton') {
    return (
      <div className={clsx('space-y-2', className)} aria-label={label}>
        {Array.from({ length: lines }).map((_, i) => (
          <LoadingSkeleton key={i} className="h-16 w-full rounded-xl" />
        ))}
      </div>
    );
  }

  return (
    <div
      className={clsx('flex items-center justify-center gap-2 py-8 text-sm text-slate-500', className)}
      role="status"
      aria-label={label}
    >
      <Loader2 className="h-5 w-5 animate-spin text-indigo-500" aria-hidden />
      {label}
    </div>
  );
}
