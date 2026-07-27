import type { ReactNode } from 'react';

type PageHeaderProps = {
  title: string;
  description?: string;
  actions?: ReactNode;
};

/** Page title block — mobile-first typography tokens from index.css */
export default function PageHeader({ title, description, actions }: PageHeaderProps) {
  return (
    <header className="mb-4 min-w-0 md:mb-6">
      <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <h1 className="page-title">{title}</h1>
          {description && <p className="page-description mt-1">{description}</p>}
        </div>
        {actions ? <div className="shrink-0">{actions}</div> : null}
      </div>
    </header>
  );
}
