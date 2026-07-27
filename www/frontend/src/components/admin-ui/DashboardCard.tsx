import type { ReactNode } from 'react';
import AdminCard from './AdminCard';
import AdminCardBody from './AdminCardBody';
import AdminCardHeader from './AdminCardHeader';

/** Domain helper — AdminCard + AdminCardHeader + AdminCardBody preset for dashboard sections. */
type DashboardCardProps = {
  title: ReactNode;
  subtitle?: ReactNode;
  aside?: ReactNode;
  children: ReactNode;
  className?: string;
};

export default function DashboardCard({ title, subtitle, aside, children, className }: DashboardCardProps) {
  return (
    <AdminCard className={className}>
      <AdminCardHeader title={title} subtitle={subtitle} aside={aside} />
      <AdminCardBody className="pt-2">{children}</AdminCardBody>
    </AdminCard>
  );
}
