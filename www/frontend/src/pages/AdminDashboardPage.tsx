import { SocketProvider } from '../hooks/useSocket';

import { useAdminSocket } from '../hooks/useAdminSocket';

import PageHeader from '../components/common/PageHeader';

import AdminDashboard from '../components/Admin/AdminDashboard';



function AdminDashboardInner() {

  useAdminSocket();



  return (

    <div className="min-w-0 py-4 md:py-6">

      <PageHeader

        title="대시보드"

        description="실시간 상담 현황과 AI 성과를 한눈에 확인하세요."

      />

      <AdminDashboard />

    </div>

  );

}



export default function AdminDashboardPage() {

  return (

    <SocketProvider>

      <AdminDashboardInner />

    </SocketProvider>

  );

}

