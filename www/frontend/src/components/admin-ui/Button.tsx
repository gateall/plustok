import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { Link } from 'react-router-dom';
import clsx from 'clsx';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

type SharedProps = {
  children: ReactNode;
  className?: string;
  icon?: ReactNode;
  variant?: ButtonVariant;
  fullWidth?: boolean;
};

type ButtonAsButton = SharedProps &
  ButtonHTMLAttributes<HTMLButtonElement> & {
    to?: never;
    href?: never;
  };

type ButtonAsLink = SharedProps & {
  to: string;
  href?: never;
};

type ButtonAsAnchor = SharedProps & {
  href: string;
  to?: never;
};

export type ButtonProps = ButtonAsButton | ButtonAsLink | ButtonAsAnchor;

function getVariantClass(variant: ButtonVariant): string {
  switch (variant) {
    case 'primary':
      return 'border border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700 hover:border-indigo-700';
    case 'secondary':
      return 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    case 'danger':
      return 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100';
    case 'ghost':
    default:
      return 'border border-transparent bg-slate-100 text-slate-700 hover:bg-slate-200';
  }
}

function getBaseClass(variant: ButtonVariant, fullWidth?: boolean, className?: string): string {
  return clsx(
    'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold transition-colors duration-250',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2',
    fullWidth && 'w-full',
    getVariantClass(variant),
    className,
  );
}

function Content({ icon, children }: Pick<SharedProps, 'icon' | 'children'>) {
  return (
    <>
      {icon ? <span className="shrink-0">{icon}</span> : null}
      <span className="truncate">{children}</span>
    </>
  );
}

export default function Button(props: ButtonProps) {
  const variant = props.variant ?? 'secondary';
  const className = getBaseClass(variant, props.fullWidth, props.className);

  if ('to' in props && props.to) {
    const { children, icon, to } = props;
    return (
      <Link to={to} className={className}>
        <Content icon={icon}>{children}</Content>
      </Link>
    );
  }

  if ('href' in props && props.href) {
    const { children, icon, href } = props;
    return (
      <a href={href} className={className}>
        <Content icon={icon}>{children}</Content>
      </a>
    );
  }

  const buttonProps = props as ButtonAsButton;
  const { children, icon, type, fullWidth: _fullWidth, variant: _variant, className: _className, ...rest } = buttonProps;

  return (
    <button type={type ?? 'button'} className={className} {...rest}>
      <Content icon={icon}>{children}</Content>
    </button>
  );
}
