import type { LucideIcon } from 'lucide-react';
import {
  BarChart3,
  Bell,
  FileText,
  Globe,
  Headphones,
  LayoutDashboard,
  MessageSquare,
  Package,
  ScrollText,
  Settings,
  Sparkles,
  UserCog,
  Users,
} from 'lucide-react';

export type AdminRole = 'admin' | 'operator';

export type AdminNavItem = {
  id: string;
  label: string;
  icon: LucideIcon;
  /** HashRouter path — in-app navigation */
  to?: string;
  end?: boolean;
  /** Legacy PHP admin path — opens in new tab when React route is not ready. */
  externalHref?: string;
  /** Hidden for operator when true (admin-only menus). */
  adminOnly?: boolean;
};

/** Desktop sidebar — parity with PHP header.php 8-menu IA */
export const SIDEBAR_NAV: AdminNavItem[] = [
  { id: 'dashboard', label: '대시보드', icon: LayoutDashboard, to: '/admin/dashboard', end: true },
  { id: 'consults', label: '상담', icon: MessageSquare, to: '/admin/consults' },
  { id: 'customers', label: '고객', icon: Users, to: '/admin/customers', adminOnly: true },
  { id: 'contracts', label: '계약', icon: ScrollText, to: '/admin/contracts', adminOnly: true },
  { id: 'sites', label: '사이트', icon: Globe, to: '/admin/sites', adminOnly: true },
  { id: 'products', label: '상품', icon: Package, to: '/admin/products', adminOnly: true },
  { id: 'users', label: '사용자', icon: UserCog, to: '/admin/users', adminOnly: true },
  { id: 'stats', label: '통계', icon: BarChart3, to: '/admin/stats' },
  { id: 'settings', label: '설정', icon: Settings, to: '/admin/settings', adminOnly: true },
];

/** Bottom nav primary tabs (mobile/tablet) */
export const BOTTOM_NAV: AdminNavItem[] = [
  { id: 'dashboard', label: '홈', icon: LayoutDashboard, to: '/admin/dashboard', end: true },
  { id: 'consults', label: '상담', icon: MessageSquare, to: '/admin/consults' },
  { id: 'customers', label: '고객', icon: Users, to: '/admin/customers', adminOnly: true },
  { id: 'contracts', label: '계약', icon: ScrollText, to: '/admin/contracts', adminOnly: true },
  { id: 'stats', label: '통계', icon: BarChart3, to: '/admin/stats' },
];

/** "더보기" page + mobile drawer overflow links */
export const MORE_NAV: AdminNavItem[] = [
  { id: 'sites', label: '사이트관리', icon: Globe, to: '/admin/sites', adminOnly: true },
  { id: 'products', label: '상품관리', icon: Package, to: '/admin/products', adminOnly: true },
  { id: 'agents', label: '상담원', icon: Headphones, externalHref: '/admin/agents/', adminOnly: true },
  { id: 'ai', label: 'AI', icon: Sparkles, externalHref: '/admin/settings/ai.php', adminOnly: true },
  { id: 'files', label: '파일', icon: FileText, externalHref: '/admin/files/', adminOnly: true },
  { id: 'contracts', label: '계약관리', icon: ScrollText, to: '/admin/contracts', adminOnly: true },
  { id: 'notifications', label: '알림', icon: Bell, to: '/admin/more' },
  { id: 'settings', label: '설정', icon: Settings, to: '/admin/settings', adminOnly: true },
];

export function filterNavByRole(items: AdminNavItem[], role: string | undefined): AdminNavItem[] {
  return items.filter((item) => {
    if (item.adminOnly && role === 'operator') return false;
    return true;
  });
}

export const ROUTE_LABELS: Record<string, string> = {
  dashboard: '대시보드',
  consults: '상담 목록',
  customers: '고객',
  contracts: '계약',
  stats: '통계',
  more: '더보기',
  sites: '사이트',
  products: '상품',
  users: '사용자',
  settings: '설정',
};
