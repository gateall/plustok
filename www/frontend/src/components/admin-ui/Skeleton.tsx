import clsx from 'clsx';

type SkeletonProps = {
  className?: string;
  rounded?: 'sm' | 'md' | 'lg' | 'full';
};

const roundedClass = {
  sm: 'rounded-[var(--pt-radius-sm)]',
  md: 'rounded-[var(--pt-radius-md)]',
  lg: 'rounded-[var(--pt-radius-lg)]',
  full: 'rounded-full',
};

/** Admin-ui skeleton block — uses animate-pulse (Tailwind). */
export default function Skeleton({ className, rounded = 'md' }: SkeletonProps) {
  return (
    <div
      className={clsx(
        'animate-pulse bg-[var(--pt-color-surface-muted)]',
        roundedClass[rounded],
        className,
      )}
      aria-hidden
    />
  );
}
