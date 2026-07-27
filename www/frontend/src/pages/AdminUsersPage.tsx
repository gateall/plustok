import { useState } from 'react';
import PageHeader from '../components/common/PageHeader';
import { useAdminUsers, useAdminUserCreate, useAdminUserUpdate } from '../hooks/useAdminUsers';
import UserFormDialog from '../components/Admin/UserFormDialog';
import type { AdminAgent } from '../types/adminAgent.types';

export default function AdminUsersPage() {
  const { data, isLoading, isError, refetch } = useAdminUsers();
  const createMutation = useAdminUserCreate();
  const updateMutation = useAdminUserUpdate();
  
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<AdminAgent | undefined>(undefined);

  const handleCreateNew = () => {
    setEditingUser(undefined);
    setIsDialogOpen(true);
  };

  const handleEdit = (user: AdminAgent) => {
    setEditingUser(user);
    setIsDialogOpen(true);
  };

  const handleSaveUser = async (payload: any) => {
    if (editingUser) {
      await updateMutation.mutateAsync({ id: editingUser.id, payload });
    } else {
      await createMutation.mutateAsync(payload);
    }
  };

  const agents = Array.isArray(data?.data) ? data.data : [];

  return (
    <div className="min-w-0 py-4 md:py-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 md:mb-6">
        <PageHeader
          title="사용자/권한 관리"
          description="상담원 및 관리자 계정을 등록하고 권한을 설정합니다."
        />
        <div className="mt-4 sm:mt-0 flex shrink-0 items-center gap-2">
          <button
            onClick={() => refetch()}
            className="rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50"
          >
            새로고침
          </button>
          <button
            onClick={handleCreateNew}
            className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
          >
            새 사용자 등록
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        {isLoading && <div className="p-8 text-center text-slate-500">로딩 중...</div>}
        {isError && <div className="p-8 text-center text-red-500">데이터를 불러오지 못했습니다.</div>}
        
        {!isLoading && !isError && (
          <>
            {/* Mobile View */}
            <div className="block sm:hidden divide-y divide-slate-100">
              {agents.map((agent) => (
                <div key={agent.id} className="p-4 hover:bg-slate-50">
                  <div className="flex justify-between items-start mb-2">
                    <div>
                      <h3 className="font-medium text-slate-900">{agent.displayName}</h3>
                      <p className="text-xs text-slate-500">{agent.loginId}</p>
                    </div>
                    <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                      agent.status === 'online' ? 'bg-green-50 text-green-700' :
                      agent.status === 'away' ? 'bg-amber-50 text-amber-700' :
                      'bg-slate-100 text-slate-700'
                    }`}>
                      {agent.status}
                    </span>
                  </div>
                  <div className="flex justify-between items-center text-sm">
                    <span className="text-slate-600">권한: {agent.role}</span>
                    <button 
                      onClick={() => handleEdit(agent)}
                      className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                    >
                      수정
                    </button>
                  </div>
                </div>
              ))}
              {agents.length === 0 && (
                <div className="p-8 text-center text-slate-500">등록된 사용자가 없습니다.</div>
              )}
            </div>

            {/* PC View */}
            <div className="hidden sm:block overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">이름</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">아이디</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">권한</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">상태</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">할당된 상담</th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">관리</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-slate-200">
                  {agents.map((agent) => (
                    <tr key={agent.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">{agent.displayName}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{agent.loginId}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{agent.role}</td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                          agent.status === 'online' ? 'bg-green-100 text-green-800' :
                          agent.status === 'away' ? 'bg-amber-100 text-amber-800' :
                          'bg-slate-100 text-slate-800'
                        }`}>
                          {agent.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{agent.activeAssignments}건</td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button 
                          onClick={() => handleEdit(agent)}
                          className="text-indigo-600 hover:text-indigo-900"
                        >
                          수정
                        </button>
                      </td>
                    </tr>
                  ))}
                  {agents.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-6 py-8 text-center text-sm text-slate-500">
                        등록된 사용자가 없습니다.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>

      <UserFormDialog 
        isOpen={isDialogOpen} 
        onClose={() => setIsDialogOpen(false)} 
        user={editingUser}
        onSave={handleSaveUser}
      />
    </div>
  );
}
