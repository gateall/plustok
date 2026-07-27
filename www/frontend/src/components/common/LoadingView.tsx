import Loading from '@/components/admin-ui/Loading';

type LoadingViewProps = {
  label?: string;
  lines?: number;
  variant?: 'spinner' | 'skeleton';
};

export default function LoadingView({
  label = '불러오는 중…',
  lines = 3,
  variant = 'skeleton',
}: LoadingViewProps) {
  return (
    <div role="status" aria-live="polite" aria-busy="true" aria-label={label}>
      <Loading label={label} lines={lines} variant={variant} />
    </div>
  );
}
