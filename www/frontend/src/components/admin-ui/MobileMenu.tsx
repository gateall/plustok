import type { ReactNode } from 'react';

type MobileMenuProps = {
  children: ReactNode;
};

export default function MobileMenu({ children }: MobileMenuProps) {
  return <div className="lg:hidden">{children}</div>;
}
