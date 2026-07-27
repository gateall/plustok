import type { ReactNode } from 'react';
import DashboardCard from './DashboardCard';

type ChartCardProps = {
  title: ReactNode;
  subtitle?: ReactNode;
  children: ReactNode;
  className?: string;
};

export default function ChartCard({ title, subtitle, children, className }: ChartCardProps) {
  return (
    <DashboardCard title={title} subtitle={subtitle} className={className}>
      {children}
    </DashboardCard>
  );
}
