import { useState, useEffect } from 'react';
import type { AdminAgent } from '../../types/adminAgent.types';

interface UserFormDialogProps {
  isOpen: boolean;
  onClose: () => void;
  user?: AdminAgent;
  onSave: (payload: any) => Promise<void>;
}

export default function UserFormDialog({ isOpen, onClose, user, onSave }: UserFormDialogProps) {
  const [loginId, setLoginId] = useState('');
  const [password, setPassword] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [role, setRole] = useState<'agent' | 'admin' | 'operator'>('agent');
  const [status, setStatus] = useState<'online' | 'away' | 'offline'>('offline');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (user) {
      setLoginId(user.loginId);
      setDisplayName(user.displayName);
      setRole(user.role);
      setStatus(user.status);
      setPassword(''); // Password cannot be viewed, and usually isn't updated here
    } else {
      setLoginId('');
      setDisplayName('');
      setRole('agent');
      setStatus('offline');
      setPassword('');
    }
    setError('');
  }, [user, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsSubmitting(true);
    
    try {
      if (user) {
        await onSave({ displayName, role, status });
      } else {
        await onSave({ loginId, password, displayName, role });
      }
      onClose();
    } catch (err: any) {
      setError(err.message || '저장 중 오류가 발생했습니다.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
      <div className="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
        <div className="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
          <h2 className="text-lg font-semibold text-slate-800">{user ? '상담원/관리자 수정' : '새 상담원/관리자 등록'}</h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <span className="sr-only">닫기</span>
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <div className="p-5 overflow-y-auto flex-1">
          {error && (
            <div className="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-200">
              {error}
            </div>
          )}
          
          <form id="user-form" onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">아이디</label>
              <input 
                type="text" 
                value={loginId} 
                onChange={(e) => setLoginId(e.target.value)} 
                disabled={!!user}
                required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500 disabled:bg-slate-100 disabled:text-slate-500"
                placeholder="로그인 아이디 입력"
              />
            </div>
            
            {!user && (
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">비밀번호</label>
                <input 
                  type="password" 
                  value={password} 
                  onChange={(e) => setPassword(e.target.value)} 
                  required
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500"
                  placeholder="비밀번호 입력"
                />
              </div>
            )}
            
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">이름</label>
              <input 
                type="text" 
                value={displayName} 
                onChange={(e) => setDisplayName(e.target.value)} 
                required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500"
                placeholder="사용자 이름 입력"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">권한</label>
              <select 
                value={role} 
                onChange={(e) => setRole(e.target.value as any)}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500"
              >
                <option value="agent">일반 상담원</option>
                <option value="admin">관리자</option>
                <option value="operator">운영자</option>
              </select>
            </div>
            
            {user && (
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">상태</label>
                <select 
                  value={status} 
                  onChange={(e) => setStatus(e.target.value as any)}
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500"
                >
                  <option value="offline">오프라인</option>
                  <option value="away">자리비움</option>
                  <option value="online">온라인</option>
                </select>
              </div>
            )}
          </form>
        </div>
        
        <div className="px-5 py-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
          >
            취소
          </button>
          <button
            type="submit"
            form="user-form"
            disabled={isSubmitting}
            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            {isSubmitting ? '저장 중...' : '저장'}
          </button>
        </div>
      </div>
    </div>
  );
}
