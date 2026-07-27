import type { ReactNode } from 'react';
import { AdminCard, AdminCardBody, AdminCardHeader } from '@/components/admin-ui';

type InfoCardProps = {
  title: ReactNode;
  subtitle?: ReactNode;
  children: ReactNode;
  aside?: ReactNode;
  footer?: ReactNode;
};

export default function InfoCard({ title, subtitle, children, aside, footer }: InfoCardProps) {
  return (
    <AdminCard>
      <AdminCardHeader title={title} subtitle={subtitle} aside={aside} />
      <AdminCardBody className="pt-2">{children}</AdminCardBody>
      {footer}
    </AdminCard>
  );
}
