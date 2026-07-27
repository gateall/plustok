import type { ReactNode } from 'react';
import AdminCard from './AdminCard';

type ResponsiveCardProps = {
  children: ReactNode;
  className?: string;
  interactive?: boolean;
};

/** @deprecated Prefer AdminCard — kept for backward compatibility. */
export default function ResponsiveCard({ children, className, interactive = false }: ResponsiveCardProps) {
  return (
    <AdminCard className={className} interactive={interactive}>
      {children}
    </AdminCard>
  );
}
