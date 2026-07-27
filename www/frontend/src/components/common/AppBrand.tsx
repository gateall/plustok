import { Link } from 'react-router-dom';
import clsx from 'clsx';
import { getAppBrand, getAppName } from '@/config/appBrand';
import { PUBLIC_ROUTES } from '@/config/publicNav';

type AppBrandProps = {
  /** landing = CSS hooks in landing-desktop.css; admin = Tailwind (AdminHeader parity) */
  variant?: 'landing' | 'admin';
  linkTo?: string | false;
  className?: string;
};

export default function AppBrand({ variant = 'admin', linkTo = PUBLIC_ROUTES.landing, className }: AppBrandProps) {
  const appBrand = getAppBrand();
  const appName = getAppName();

  const inner = (
    <>
      <span
        className={clsx(
          variant === 'landing' ? 'app-brand__badge' : 'shrink-0 rounded-lg bg-indigo-600 px-2 py-0.5 text-xs font-bold tracking-tight text-white',
        )}
      >
        {appBrand}
      </span>
      <span
        className={clsx(
          variant === 'landing' ? 'app-brand__name' : 'truncate text-base font-semibold text-slate-900',
        )}
      >
        {appName}
      </span>
    </>
  );

  const wrapperClass = clsx(
    variant === 'landing' ? 'app-brand app-brand--landing' : 'flex min-w-0 items-center gap-2',
    className,
  );

  if (linkTo === false) {
    return <div className={wrapperClass}>{inner}</div>;
  }

  return (
    <Link to={linkTo} className={wrapperClass} aria-label={`${appBrand} ${appName} 홈`}>
      {inner}
    </Link>
  );
}
