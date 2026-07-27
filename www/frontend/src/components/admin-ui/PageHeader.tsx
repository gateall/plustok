import type { ReactNode } from 'react';
import PageTitle from './PageTitle';

type PageHeaderProps = {
  title: ReactNode;
  description?: string;
};

/** Page title block — mobile-first typography tokens from index.css */
export default function PageHeader({ title, description }: PageHeaderProps) {
  return (
    <header className="mb-4 min-w-0 md:mb-6">
      <PageTitle>{title}</PageTitle>
      {description && <p className="page-description mt-1">{description}</p>}
    </header>
  );
}
