import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import toast from 'react-hot-toast';
import PageHeader from '@/components/common/PageHeader';
import EmptyState from '@/components/common/EmptyState';
import { useCreateContract } from '@/hooks/useContracts';

export default function ContractCreatePage() {
  const navigate = useNavigate();
  const createMutation = useCreateContract();

  const [title, setTitle] = useState('');
  const [customerId, setCustomerId] = useState('');
  const [totalAmount, setTotalAmount] = useState('');
  const [productName, setProductName] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [notes, setNotes] = useState('');

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    const amount = parseFloat(totalAmount);
    if (!title.trim() || !customerId.trim() || Number.isNaN(amount) || amount <= 0) {
      toast.error('제목, 고객 ID, 계약금액을 올바르게 입력해 주세요.');
      return;
    }

    try {
      const created = await createMutation.mutateAsync({
        title: title.trim(),
        customerId: customerId.trim(),
        totalAmount: amount,
        productName: productName.trim() || null,
        startDate: startDate || null,
        endDate: endDate || null,
        notes: notes.trim() || null,
      });
      toast.success('계약이 등록되었습니다.');
      navigate(`/admin/contracts/${created.id}`);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : '계약 등록에 실패했습니다.');
    }
  };

  return (
    <div className="admin-contract-create min-w-0 py-4 md:py-6 max-w-[640px] mx-auto w-full px-4 md:px-8">
      <Link
        to="/admin/contracts"
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft size={16} aria-hidden />
        계약 목록
      </Link>

      <PageHeader title="새 계약 등록" description="필수: 제목, 고객 ID, 계약금액" />

      {createMutation.isError && (
        <EmptyState
          title="등록 실패"
          description={
            createMutation.error instanceof Error
              ? createMutation.error.message
              : '계약을 등록할 수 없습니다.'
          }
        />
      )}

      <form onSubmit={(e) => void handleSubmit(e)} className="space-y-4 rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
        <label className="block">
          <span className="text-sm font-medium text-slate-700">제목 *</span>
          <input
            type="text"
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">고객 ID (UUID) *</span>
          <input
            type="text"
            required
            value={customerId}
            onChange={(e) => setCustomerId(e.target.value)}
            placeholder="고객 상세에서 확인한 UUID"
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base font-mono text-sm"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">계약금액 (원) *</span>
          <input
            type="number"
            required
            min={1}
            step={1}
            value={totalAmount}
            onChange={(e) => setTotalAmount(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">상품명</span>
          <input
            type="text"
            value={productName}
            onChange={(e) => setProductName(e.target.value)}
            className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
          />
        </label>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label className="block">
            <span className="text-sm font-medium text-slate-700">시작일</span>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
            />
          </label>
          <label className="block">
            <span className="text-sm font-medium text-slate-700">종료일</span>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="mt-1 h-12 w-full rounded-lg border border-slate-200 px-3 text-base"
            />
          </label>
        </div>

        <label className="block">
          <span className="text-sm font-medium text-slate-700">메모</span>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            rows={3}
            className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-base"
          />
        </label>

        <div className="flex gap-3 pt-2">
          <button
            type="submit"
            disabled={createMutation.isPending}
            className="h-11 flex-1 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 lg:h-10"
          >
            {createMutation.isPending ? '등록 중…' : '등록'}
          </button>
          <Link
            to="/admin/contracts"
            className="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 lg:h-10"
          >
            취소
          </Link>
        </div>
      </form>
    </div>
  );
}
