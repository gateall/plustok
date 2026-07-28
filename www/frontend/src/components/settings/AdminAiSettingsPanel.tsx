import { useState, useEffect } from 'react';
import { useAiSettings, useUpdateAiSettings, useTestAiConnection, useDeleteAiKey } from '../../hooks/useAiSettings';
import type { AiSettings, AiProviderSetting } from '../../types/aiSettings.types';
import { Bot, Key, Trash2, Activity, CheckCircle, AlertCircle, Save } from 'lucide-react';

const PROVIDER_NAMES: Record<string, string> = {
  anthropic: 'Anthropic (Claude)',
  openai: 'OpenAI (GPT)',
  gemini: 'Google Gemini',
  grok: 'xAI (Grok)',
  deepseek: 'DeepSeek',
};

const DEFAULT_MODELS: Record<string, { value: string; label: string }[]> = {
  anthropic: [
    { value: 'claude-opus-4-8', label: 'Claude Opus 4 (8K) — 최고 품질' },
    { value: 'claude-sonnet-5', label: 'Claude Sonnet 5 — 균형' },
    { value: 'claude-haiku-4-5', label: 'Claude Haiku 4.5 — 빠름/저렴' },
  ],
  openai: [
    { value: 'gpt-4o', label: 'GPT-4o (최신)' },
    { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
    { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
  ],
  gemini: [
    { value: 'gemini-1.5-pro-latest', label: 'Gemini 1.5 Pro' },
    { value: 'gemini-1.5-flash-latest', label: 'Gemini 1.5 Flash' },
  ],
  grok: [
    { value: 'grok-2-latest', label: 'Grok 2 (Latest)' },
  ],
  deepseek: [
    { value: 'deepseek-chat', label: 'DeepSeek Chat' },
  ],
};

export default function AdminAiSettingsPanel() {
  const { data: settings, isLoading, isError, error } = useAiSettings();
  const updateMutation = useUpdateAiSettings();
  const testMutation = useTestAiConnection();
  const deleteMutation = useDeleteAiKey();

  const [formData, setFormData] = useState<AiSettings | null>(null);
  const [testResults, setTestResults] = useState<Record<string, any>>({});

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
      </div>
    );
  }

  const handleGlobalChange = (field: keyof AiSettings, value: any) => {
    setFormData((prev) => prev ? { ...prev, [field]: value } : null);
  };

  const handleProviderChange = (providerName: string, field: keyof AiProviderSetting, value: string) => {
    setFormData((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        providers: prev.providers.map(p => 
          p.provider === providerName ? { ...p, [field]: value } : p
        )
      };
    });
  };

  const handleSave = async () => {
    try {
      await updateMutation.mutateAsync(formData);
      alert('AI 설정이 저장되었습니다.');
    } catch (e: any) {
      alert(`설정 저장 실패: ${e.message}`);
    }
  };

  const handleTest = async (provider: string) => {
    try {
      setTestResults(prev => ({ ...prev, [provider]: { loading: true } }));
      const result = await testMutation.mutateAsync(provider);
      setTestResults(prev => ({ ...prev, [provider]: { loading: false, data: result } }));
    } catch (e: any) {
      setTestResults(prev => ({ ...prev, [provider]: { loading: false, error: e.message } }));
    }
  };

  const handleDeleteKey = async (provider: string) => {
    if (!confirm(`${PROVIDER_NAMES[provider] || provider}의 API 키를 삭제하시겠습니까?`)) return;
    try {
      await deleteMutation.mutateAsync(provider);
      alert('키가 삭제되었습니다.');
    } catch (e: any) {
      alert(`키 삭제 실패: ${e.message}`);
    }
  };

  return (
    <div className="p-4 md:p-6 space-y-8">
      {/* Global Settings */}
      <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6 transition-all">
        <h3 className="text-lg font-bold text-slate-800 flex items-center gap-2 mb-4">
          <Bot className="w-5 h-5 text-indigo-600" />
          일반 AI 설정
        </h3>
        
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium text-slate-800">AI 기능 활성화 (마스터 스위치)</p>
              <p className="text-sm text-slate-500">시스템 전체의 AI 기능을 켜거나 끕니다.</p>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input 
                type="checkbox" 
                className="sr-only peer" 
                checked={formData.enabled}
                onChange={(e) => handleGlobalChange('enabled', e.target.checked)}
              />
              <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
          </div>

          <div>
            <p className="font-medium text-slate-800 mb-2">활성 AI 엔진 (라우팅 모드)</p>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <label className={`cursor-pointer flex items-center gap-3 p-3 border rounded-lg transition-colors ${formData.activeProvider === 'auto' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 hover:bg-slate-50'}`}>
                <input 
                  type="radio" 
                  name="activeProvider" 
                  value="auto" 
                  checked={formData.activeProvider === 'auto'}
                  onChange={() => handleGlobalChange('activeProvider', 'auto')}
                  className="w-4 h-4 text-indigo-600 focus:ring-indigo-500" 
                />
                <span className="font-medium text-sm">AUTO (자동 장애조치)</span>
              </label>
              {formData.providers.map(p => (
                <label key={p.provider} className={`cursor-pointer flex items-center gap-3 p-3 border rounded-lg transition-colors ${formData.activeProvider === p.provider ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 hover:bg-slate-50'}`}>
                  <input 
                    type="radio" 
                    name="activeProvider" 
                    value={p.provider} 
                    checked={formData.activeProvider === p.provider}
                    onChange={() => handleGlobalChange('activeProvider', p.provider)}
                    className="w-4 h-4 text-indigo-600 focus:ring-indigo-500" 
                  />
                  <span className="font-medium text-sm">{PROVIDER_NAMES[p.provider] || p.provider} 단일 사용</span>
                </label>
              ))}
            </div>
          </div>
          
          {formData.activeProvider === 'auto' && (
             <div className="mt-4 flex items-center justify-between p-4 bg-slate-50 rounded-lg border border-slate-200">
               <span className="text-sm text-slate-700 font-medium">Auto Failover 연결 테스트</span>
               <button
                 onClick={() => handleTest('auto')}
                 disabled={testResults['auto']?.loading}
                 className="flex items-center gap-2 px-3 py-1.5 text-sm bg-white border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-50 transition"
               >
                 <Activity className="w-4 h-4" />
                 {testResults['auto']?.loading ? '테스트 중...' : '테스트 실행'}
               </button>
             </div>
          )}
          
          {testResults['auto']?.data && (
            <div className={`mt-2 p-3 text-sm rounded border ${testResults['auto'].data.success ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'}`}>
              {testResults['auto'].data.success ? (
                <div className="flex items-center gap-2"><CheckCircle className="w-4 h-4"/> <b>성공!</b> ({testResults['auto'].data.latencyMs}ms) - {PROVIDER_NAMES[testResults['auto'].data.provider] || testResults['auto'].data.provider} 사용됨.</div>
              ) : (
                <div className="flex items-center gap-2"><AlertCircle className="w-4 h-4"/> 실패: {testResults['auto'].data.error}</div>
              )}
            </div>
          )}
          {testResults['auto']?.error && (
             <div className="mt-2 p-3 text-sm rounded border bg-red-50 text-red-800 border-red-200 flex items-center gap-2">
               <AlertCircle className="w-4 h-4"/> 실패: {testResults['auto'].error}
             </div>
          )}
        </div>
      </section>

      {/* Provider Cards */}
      <h3 className="text-lg font-bold text-slate-800 mt-8 mb-4 border-b pb-2">AI 벤더별 설정</h3>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {formData.providers.map(p => {
          const tResult = testResults[p.provider];
          return (
            <div key={p.provider} className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
              <div className="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                <h4 className="font-bold text-slate-800">{PROVIDER_NAMES[p.provider] || p.provider}</h4>
                {p.hasKey && (
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                    Key Registered
                  </span>
                )}
              </div>
              <div className="p-4 space-y-4 flex-1">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">모델 선택</label>
                  {DEFAULT_MODELS[p.provider] ? (
                    <select
                      value={p.model}
                      onChange={(e) => handleProviderChange(p.provider, 'model', e.target.value)}
                      className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    >
                      {DEFAULT_MODELS[p.provider].map(opt => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                      ))}
                      {/* Allow custom if not in list */}
                      {!DEFAULT_MODELS[p.provider].find(pOpt => pOpt.value === p.model) && p.model && (
                         <option value={p.model}>{p.model} (Custom)</option>
                      )}
                    </select>
                  ) : (
                    <input
                      type="text"
                      value={p.model}
                      onChange={(e) => handleProviderChange(p.provider, 'model', e.target.value)}
                      placeholder="모델명을 입력하세요 (예: gpt-4)"
                      className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    />
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Key className="h-4 w-4 text-slate-400" />
                    </div>
                    <input
                      type="password"
                      value={p.apiKey || ''}
                      onChange={(e) => handleProviderChange(p.provider, 'apiKey', e.target.value)}
                      placeholder={p.maskedKey || "API 키를 입력하세요"}
                      autoComplete="new-password"
                      className="w-full pl-10 px-3 py-2 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    />
                  </div>
                  {p.hasKey && (
                     <p className="mt-1 text-xs text-slate-500">새 키를 입력하지 않으면 기존 키가 유지됩니다.</p>
                  )}
                </div>

                {tResult && (
                  <div className={`p-3 text-sm rounded border ${tResult.data?.success ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'}`}>
                    {tResult.loading ? '연결 확인 중...' : 
                     tResult.data?.success ? (
                       <div className="flex items-center gap-1"><CheckCircle className="w-4 h-4"/> 성공 ({tResult.data.latencyMs}ms)</div>
                     ) : (
                       <div className="flex items-start gap-1"><AlertCircle className="w-4 h-4 mt-0.5 shrink-0"/> {tResult.error || tResult.data?.error || '연결 실패'}</div>
                     )}
                  </div>
                )}
              </div>
              <div className="p-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center gap-2">
                <button
                  type="button"
                  onClick={() => handleTest(p.provider)}
                  disabled={tResult?.loading || (!p.hasKey && !p.apiKey)}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-100 transition disabled:opacity-50"
                >
                  <Activity className="w-4 h-4" />
                  연결 테스트
                </button>
                {p.hasKey && (
                  <button
                    type="button"
                    onClick={() => handleDeleteKey(p.provider)}
                    className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition"
                  >
                    <Trash2 className="w-4 h-4" />
                    키 삭제
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>

      <div className="flex justify-end pt-4 border-t border-slate-200 mt-6 sticky bottom-4">
        <button
          onClick={handleSave}
          disabled={updateMutation.isPending}
          className="flex items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 border border-transparent rounded-lg shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-colors"
        >
          <Save className="w-5 h-5" />
          {updateMutation.isPending ? '저장 중...' : '전체 변경사항 저장'}
        </button>
      </div>
    </div>
  );
}
