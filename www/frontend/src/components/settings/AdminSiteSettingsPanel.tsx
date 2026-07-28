import { useState, useEffect } from 'react';
import { useSiteSettings, useUpdateSiteSettings } from '../../hooks/useSiteSettings';
import type { SiteSettings } from '../../types/siteSettings.types';

export default function AdminSiteSettingsPanel() {
  const { data: settings, isLoading, isError, error, refetch } = useSiteSettings();
  const updateMutation = useUpdateSiteSettings();

  const [formData, setFormData] = useState<SiteSettings | null>(null);
  const [saveMessage, setSaveMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    if (settings) {
      setFormData(settings);
    }
  }, [settings]);

  if (isLoading) {
    return (
      <div className="p-6 md:p-12 flex justify-center items-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  if (isError || !formData) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
          <strong className="font-bold">오류 발생! </strong>
          <span className="block sm:inline">{error?.message || '설정을 불러오지 못했습니다.'}</span>
        </div>
        <button
          type="button"
          onClick={() => refetch()}
          className="mt-3 px-3 py-1.5 text-sm font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100"
        >
          다시 시도
        </button>
      </div>
    );
  }

  const handleChange = (field: keyof SiteSettings, value: string) => {
    setFormData((prev) => (prev ? { ...prev, [field]: value } : prev));
  };

  const handleSave = async () => {
    if (!formData) return;
    setSaveMessage(null);
    try {
      await updateMutation.mutateAsync(formData);
      setSaveMessage({ type: 'success', text: '설정이 저장되었습니다.' });
    } catch (e: any) {
      setSaveMessage({ type: 'error', text: e?.message || '설정 저장 중 오류가 발생했습니다.' });
    }
  };

  return (
    <div className="p-6 max-w-2xl">
      <h3 className="text-lg font-semibold text-slate-800 mb-4">사이트 설정</h3>

      {saveMessage && (
        <div
          className={`mb-4 px-4 py-3 rounded-lg text-sm ${
            saveMessage.type === 'success'
              ? 'bg-green-50 text-green-700 border border-green-200'
              : 'bg-red-50 text-red-700 border border-red-200'
          }`}
        >
          {saveMessage.text}
        </div>
      )}

      <div className="space-y-6">
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">사이트 제목 *</label>
          <input
            type="text"
            value={formData.siteTitle}
            onChange={(e) => handleChange('siteTitle', e.target.value)}
            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 text-sm"
            placeholder="PlusTok 통합 CRM"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">로고 URL</label>
          <input
            type="text"
            value={formData.logoUrl ?? ''}
            onChange={(e) => handleChange('logoUrl', e.target.value)}
            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 text-sm"
            placeholder="https://example.com/logo.png"
          />
          <p className="mt-1 text-xs text-slate-400">비워두면 기본 텍스트 로고가 표시됩니다.</p>
        </div>

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">상담 접수 알림 이메일</label>
          <input
            type="email"
            value={formData.adminNotifyEmail ?? ''}
            onChange={(e) => handleChange('adminNotifyEmail', e.target.value)}
            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 text-sm"
            placeholder="admin@example.com"
          />
        </div>

        <div className="pt-4 flex justify-end">
          <button
            onClick={handleSave}
            disabled={updateMutation.isPending}
            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            {updateMutation.isPending ? '저장 중...' : '저장'}
          </button>
        </div>
      </div>
    </div>
  );
}
