/** Shared brand tokens — synced with AdminHeader / DesktopSidebar (VITE_APP_*). */
export function getAppBrand() {
  return import.meta.env.VITE_APP_BRAND ?? 'PlusTok';
}

export function getAppName() {
  return import.meta.env.VITE_APP_NAME ?? 'CRM';
}

export function getAppBrandLabel() {
  return `${getAppBrand()} ${getAppName()}`;
}
