/**
 * Public route paths — HashRouter in-app navigation.
 * Production PHP URLs (Codex Sprint 3.1 redirect scope):
 *   /landing → index.html#/landing
 *   /login   → index.html#/login
 *   /admin/* → index.html#/admin/* (React) or legacy PHP where externalHref applies
 */
export const PUBLIC_ROUTES = {
  landing: '/landing',
  login: '/login',
  chat: '/chat',
  adminDashboard: '/admin/dashboard',
  adminConsults: '/admin/consults',
} as const;

/** External PHP endpoints — documented for Codex .htaccess; open in new tab from React. */
export const EXTERNAL_PHP_LINKS = {
  health: '/api/v1/health.php',
  adminAgents: '/admin/agents/',
  adminAiSettings: '/admin/settings/ai.php',
  adminFiles: '/admin/files/',
} as const;

export const LANDING_SECTIONS = [
  { path: '/services', sectionId: 'services', label: '서비스' },
  { path: '/ai', sectionId: 'ai', label: 'AI' },
  { path: '/process', sectionId: 'process', label: '프로세스' },
  { path: '/compare', sectionId: 'compare', label: '비교' },
  { path: '/crm', sectionId: 'crm', label: 'CRM' },
  { path: '/dashboard', sectionId: 'dashboard', label: 'Dashboard' },
] as const;

/** @deprecated use LANDING_SECTIONS — header nav */
export const LANDING_SECTION_LINKS = LANDING_SECTIONS.map(({ path, label }) => ({
  to: path,
  label,
}));

export const PUBLIC_NAV_LINKS = [
  { to: PUBLIC_ROUTES.landing, label: '홈' },
  { to: PUBLIC_ROUTES.login, label: '로그인' },
  { to: PUBLIC_ROUTES.adminDashboard, label: '관리자' },
] as const;
