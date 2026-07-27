import type { ReactNode } from 'react';
import DashboardCard from './DashboardCard';

type StatusCardProps = {
  title: ReactNode;
  subtitle?: ReactNode;
  aside?: ReactNode;
  children: ReactNode;
  className?: string;
};

export default function StatusCard({ title, subtitle, aside, children, className }: StatusCardProps) {
  return (
    <DashboardCard title={title} subtitle={subtitle} aside={aside} className={className}>
      {children}
    </DashboardCard>
  );
}
