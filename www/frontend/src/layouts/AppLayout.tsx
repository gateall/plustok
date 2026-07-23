import { Outlet } from 'react-router-dom';

/** Root shell for nested route groups (min-height, flex column). */
export default function AppLayout() {
  return (
    <div className="flex min-h-dvh min-w-0 flex-col bg-slate-50">
      <Outlet />
    </div>
  );
}
