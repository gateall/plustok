import clsx from 'clsx';

type AvatarSize = 'sm' | 'md' | 'lg';

export type AvatarProps = {
  name?: string;
  src?: string;
  alt?: string;
  size?: AvatarSize;
  className?: string;
};

const sizeClass: Record<AvatarSize, string> = {
  sm: 'h-8 w-8 text-xs',
  md: 'h-10 w-10 text-sm',
  lg: 'h-12 w-12 text-base',
};

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export default function Avatar({ name = '', src, alt, size = 'md', className }: AvatarProps) {
  const label = alt ?? name ?? 'Avatar';

  if (src) {
    return (
      <img
        src={src}
        alt={label}
        className={clsx('shrink-0 rounded-full object-cover', sizeClass[size], className)}
      />
    );
  }

  return (
    <span
      className={clsx(
        'inline-flex shrink-0 items-center justify-center rounded-full bg-[var(--pt-color-primary-muted)] font-semibold text-[var(--pt-color-primary)]',
        sizeClass[size],
        className,
      )}
      aria-hidden={alt ? undefined : true}
      title={name || undefined}
    >
      {initials(name)}
    </span>
  );
}
