import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import toast from 'react-hot-toast';

import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import { useSite, useSiteCreate, useSiteUpdate } from '@/hooks/useSites';

export default function SiteFormPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const isEdit = Boolean(id);
  const siteId = id ? parseInt(id, 10) : undefined;

  const { data: site, isLoading, isError, error } = useSite(isEdit ? siteId : undefined);
  const createMutation = useSiteCreate();
  const updateMutation = useSiteUpdate();

  const [siteCode, setSiteCode] = useState('');
  const [siteName, setSiteName] = useState('');
  const [domain, setDomain] = useState('');
  const [brand, setBrand] = useState('');
  const [division, setDivision] = useState('');
  const [persona, setPersona] = useState('');
  const [newApiKey, setNewApiKey] = useState<string | null>(null);

  useEffect(() => {
    if (site) {
      setSiteCode(site.siteCode);
      setSiteName(site.siteName);
      setDomain(site.domain);
      setBrand(site.brand);
      setDivision(site.division);
      setPersona(site.persona ?? '');
    }
  }, [site]);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    const payload = {
      siteCode: siteCode.trim(),
      siteName: siteName.trim(),
      domain: domain.trim(),
      brand: brand.trim(),
      division: division.trim(),
      persona: persona.trim() || null,
    };

    if (!payload.siteCode || !payload.siteName || !payload.brand) {
      toast.error('사이트 코드, 이름, 브랜드는 필수입니다.');
      return;
    }

    try {
      if (isEdit && siteId) {
        await updateMutation.mutateAsync({ id: siteId, payload });
        toast.success('사이트가 수정되었습니다.');
        navigate(`/admin/sites/${siteId}`);
      } else {
        const created = await createMutation.mutateAsync(payload);
        setNewApiKey(created.apiKey);
        toast.success('사이트가 등록되었습니다.');
      }
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '저장에 실패했습니다.');
    }
  };

  const isPending = createMutation.isPending || updateMutation.isPending;

  if (isEdit && isLoading) {
    return (
      <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
        <LoadingSkeleton className="mb-4 h-8 w-48 rounded-lg" />
        <LoadingSkeleton className="h-96 w-full rounded-xl" />
      </div>
    );
  }

  if (isEdit && isError) {
    return (
      <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
        <EmptyState
          title="사이트를 불러오지 못했습니다"
          description={error instanceof Error ? error.message : '잠시 후 다시 시도해 주세요.'}
          action={
            <Link to="/admin/sites" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
              목록으로
            </Link>
          }
        />
      </div>
    );
  }

  if (newApiKey) {
    return (
      <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
        <PageHeader title="사이트 등록 완료" description="API Key를 안전한 곳에 저장하세요." />
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
          <p className="mb-2 text-sm font-medium text-amber-800">API Key (한 번만 표시)</p>
          <code className="block break-all rounded-lg bg-white p-3 text-xs font-mono text-slate-800">
            {newApiKey}
          </code>
        </div>
        <div className="mt-4">
          <Link
            to="/admin/sites"
            className="inline-flex h-11 items-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700"
          >
            사이트 목록
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="admin-page-shell min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
      <Link
        to={isEdit && siteId ? `/admin/sites/${siteId}` : '/admin/sites'}
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft size={16} aria-hidden />
        {isEdit ? '사이트 상세' : '사이트 목록'}
      </Link>

      <PageHeader
        title={isEdit ? '사이트 수정' : '새 사이트 등록'}
        description="필수: 사이트 코드, 이름, 브랜드, 도메인, 부서"
      />

      <form
        onSubmit={(e) => void handleSubmit(e)}
        className="space-y-4 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6"
      >
        <label className="block">
          <span className="text-sm font-medium text-slate-700">사이트 코드 *</span>
          <input
            type="text"
            required
            maxLength={50}
            value={siteCode}
            onChange={(e) => setSiteCode(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base font-mono"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">사이트명 *</span>
          <input
            type="text"
            required
            maxLength={100}
            value={siteName}
            onChange={(e) => setSiteName(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">도메인 *</span>
          <input
            type="text"
            required
            maxLength={150}
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">브랜드 *</span>
          <input
            type="text"
            required
            maxLength={50}
            value={brand}
            onChange={(e) => setBrand(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">부서 *</span>
          <input
            type="text"
            required
            maxLength={50}
            value={division}
            onChange={(e) => setDivision(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">페르소나</span>
          <input
            type="text"
            maxLength={255}
            value={persona}
            onChange={(e) => setPersona(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <div className="flex gap-3 pt-2">
          <button
            type="submit"
            disabled={isPending}
            className="admin-touch-target h-11 flex-1 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {isPending ? '저장 중…' : isEdit ? '수정' : '등록'}
          </button>
          <Link
            to={isEdit && siteId ? `/admin/sites/${siteId}` : '/admin/sites'}
            className="admin-touch-target inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            취소
          </Link>
        </div>
      </form>
    </div>
  );
}
