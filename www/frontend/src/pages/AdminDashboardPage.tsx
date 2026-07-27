import { useState } from 'react';
import { SocketProvider } from '../hooks/useSocket';

import { useAdminSocket } from '../hooks/useAdminSocket';

import PageHeader from '../components/common/PageHeader';

import AdminDashboard from '../components/Admin/AdminDashboard';



function AdminDashboardInner() {

  useAdminSocket();



  const [period, setPeriod] = useState('today');

  return (
    <div className="min-w-0 py-4 md:py-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 md:mb-6">
        <PageHeader
          title="대시보드"
          description="실시간 상담 현황과 AI 성과를 한눈에 확인하세요."
        />
        <div className="mt-4 sm:mt-0 flex shrink-0 items-center gap-2">
          <select 
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-sm focus:border-sky-500 focus:ring-sky-500"
          >
            <option value="today">오늘</option>
            <option value="week">최근 7일</option>
            <option value="month">최근 30일</option>
          </select>
        </div>
      </div>
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

