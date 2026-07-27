import type { ReactNode } from 'react';
import clsx from 'clsx';

type LandingSectionProps = {
  id?: string;
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
  dark?: boolean;
};

export default function LandingSection({
  id,
  title,
  description,
  children,
  className,
  dark = false,
}: LandingSectionProps) {
  return (
    <section
      id={id}
      className={clsx('landing-section', dark && 'landing-section--dark', className)}
    >
      <div className="landing-container">
        <header className="landing-section__header">
          <h2 className="landing-section__title">{title}</h2>
          {description ? <p className="landing-section__desc">{description}</p> : null}
        </header>
        {children}
      </div>
    </section>
  );
}
