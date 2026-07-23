import { Outlet, useLocation } from 'react-router-dom';
import clsx from 'clsx';
import { AdminNavProvider } from '@/features/admin/AdminNavContext';
import { ConsultListSearchProvider } from '@/features/admin/ConsultListSearchContext';
import AdminHeader from '@/components/Admin/AdminHeader';
import BottomNav from '@/components/Admin/BottomNav';
import DesktopHeader from '@/components/Admin/DesktopHeader';
import DesktopSidebar from '@/components/Admin/DesktopSidebar';
import MobileDrawer from '@/components/Admin/MobileDrawer';

function isConsultDetailRoute(pathname: string): boolean {
  return /^\/admin\/consults\/[^/]+$/.test(pathname);
}

/** Admin shell: sidebar (lg+), responsive header, scrollable main, bottom nav (< lg). */
export default function AdminLayout() {
  const location = useLocation();
  const hideBottomNav = isConsultDetailRoute(location.pathname);

  return (
    <AdminNavProvider>
      <ConsultListSearchProvider>
        <div className="flex min-h-dvh min-w-0 bg-slate-50">
          <DesktopSidebar />

          <div className="flex min-w-0 flex-1 flex-col">
            <AdminHeader />
            <DesktopHeader />

            <main
              className={clsx(
                'app-main mx-auto w-full min-w-0 max-w-7xl flex-1 px-4 sm:px-5 md:px-6',
                hideBottomNav && 'app-main--consult-detail',
              )}
            >
              <Outlet />
            </main>

            {!hideBottomNav && <BottomNav />}
          </div>
        </div>

        <MobileDrawer />
      </ConsultListSearchProvider>
    </AdminNavProvider>
  );
}
