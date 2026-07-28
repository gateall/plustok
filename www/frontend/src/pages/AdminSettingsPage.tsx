import { useState, useEffect } from 'react';
import PageHeader from '../components/common/PageHeader';
import { useSettings, useSettingsUpdate } from '../hooks/useSettings';
import type { UserSettings } from '../types/settings.types';
import EmptyState from '../components/common/EmptyState';
import AdminAiSettingsPanel from '../components/settings/AdminAiSettingsPanel';
import AdminSiteSettingsPanel from '../components/settings/AdminSiteSettingsPanel';

export default function AdminSettingsPage() {
  const { data, isLoading } = useSettings();
  const updateMutation = useSettingsUpdate();

  const [formData, setFormData] = useState<UserSettings>({
    theme: 'light',
    locale: 'ko-KR',
    notifySound: true,
    desktopNotify: true,
    messagesPerPage: 50,
  });

  const [activeTab, setActiveTab] = useState<'personal' | 'site' | 'security' | 'ai'>('personal');

  useEffect(() => {
    if (data?.settings) {
      setFormData(data.settings);
    }
  }, [data]);

  const handleChange = (field: keyof UserSettings, value: any) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    try {
      await updateMutation.mutateAsync(formData);
      alert('설정이 저장되었습니다.');
    } catch (e: any) {
      alert('설정 저장 중 오류가 발생했습니다.');
    }
  };

  return (
    <div className="min-w-0 py-4 md:py-6">
      <PageHeader
        title="설정"
        description="시스템 및 개인화 설정을 관리합니다."
      />

      <div className="mt-6 flex flex-col md:flex-row gap-6">
        {/* Sidebar Nav */}
        <div className="w-full md:w-64 shrink-0">
          <nav className="flex flex-row md:flex-col gap-1 overflow-x-auto pb-2 md:pb-0">
            <button
              onClick={() => setActiveTab('personal')}
              className={`px-4 py-2 text-sm font-medium rounded-lg text-left whitespace-nowrap ${
                activeTab === 'personal'
                  ? 'bg-indigo-50 text-indigo-700'
                  : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              개인 설정
            </button>
            <button
              onClick={() => setActiveTab('site')}
              className={`px-4 py-2 text-sm font-medium rounded-lg text-left whitespace-nowrap ${
                activeTab === 'site'
                  ? 'bg-indigo-50 text-indigo-700'
                  : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              사이트 설정
            </button>
            <button
              onClick={() => setActiveTab('security')}
              className={`px-4 py-2 text-sm font-medium rounded-lg text-left whitespace-nowrap ${
                activeTab === 'security'
                  ? 'bg-indigo-50 text-indigo-700'
                  : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              보안 설정
            </button>
            <button
              onClick={() => setActiveTab('ai')}
              className={`px-4 py-2 text-sm font-medium rounded-lg text-left whitespace-nowrap ${
                activeTab === 'ai'
                  ? 'bg-indigo-50 text-indigo-700'
                  : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              AI 연동 설정
            </button>
          </nav>
        </div>

        {/* Content Area */}
        <div className="flex-1 min-w-0">
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            {activeTab === 'personal' ? (
              <div className="p-6">
                <h3 className="text-lg font-semibold text-slate-800 mb-4">개인 설정</h3>
                {isLoading ? (
                  <p className="text-slate-500">로딩 중...</p>
                ) : (
                  <div className="space-y-6 max-w-2xl">
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1">테마</label>
                      <select
                        value={formData.theme}
                        onChange={(e) => handleChange('theme', e.target.value)}
                        className="w-full sm:max-w-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 text-sm"
                      >
                        <option value="light">라이트 모드</option>
                        <option value="dark">다크 모드</option>
                        <option value="system">시스템 기본 설정</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1">언어</label>
                      <select
                        value={formData.locale}
                        onChange={(e) => handleChange('locale', e.target.value)}
                        className="w-full sm:max-w-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 text-sm"
                      >
                        <option value="ko-KR">한국어</option>
                        <option value="en-US">English</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1">페이지당 표시할 메시지 수</label>
                      <select
                        value={formData.messagesPerPage}
                        onChange={(e) => handleChange('messagesPerPage', parseInt(e.target.value, 10))}
                        className="w-full sm:max-w-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 text-sm"
                      >
                        <option value="20">20개</option>
                        <option value="50">50개</option>
                        <option value="100">100개</option>
                      </select>
                    </div>

                    <div className="pt-4 border-t border-slate-100">
                      <h4 className="text-sm font-semibold text-slate-800 mb-3">알림 설정</h4>
                      <div className="space-y-3">
                        <label className="flex items-center gap-3">
                          <input
                            type="checkbox"
                            checked={formData.notifySound}
                            onChange={(e) => handleChange('notifySound', e.target.checked)}
                            className="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500"
                          />
                          <span className="text-sm text-slate-700">새 메시지 도착 시 소리 알림</span>
                        </label>
                        <label className="flex items-center gap-3">
                          <input
                            type="checkbox"
                            checked={formData.desktopNotify}
                            onChange={(e) => handleChange('desktopNotify', e.target.checked)}
                            className="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500"
                          />
                          <span className="text-sm text-slate-700">데스크탑 브라우저 알림</span>
                        </label>
                      </div>
                    </div>

                    <div className="pt-6 flex justify-end">
                      <button
                        onClick={handleSave}
                        disabled={updateMutation.isPending}
                        className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                      >
                        {updateMutation.isPending ? '저장 중...' : '저장'}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            ) : activeTab === 'site' ? (
              <AdminSiteSettingsPanel />
            ) : activeTab === 'security' ? (
              <div className="p-12">
                <EmptyState 
                  title="보안 설정 (준비 중)" 
                  description="비밀번호 정책, IP 제한 등 보안 설정 API는 현재 연동 준비 중입니다." 
                />
              </div>
            ) : (
              <div className="bg-slate-50">
                <AdminAiSettingsPanel />
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
