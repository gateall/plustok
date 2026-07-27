import { useState, type ReactNode } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, FileText } from 'lucide-react';
import PageHeader from '@/components/common/PageHeader';
import LoadingSkeleton from '@/components/common/LoadingSkeleton';
import EmptyState from '@/components/common/EmptyState';
import ContractStatusBadge from '@/components/contracts/ContractStatusBadge';
import PaymentStatusBadge from '@/components/contracts/PaymentStatusBadge';
import { useContract } from '@/hooks/useContracts';
import { formatContractCurrency, formatContractDate, formatContractDateTime } from '@/utils/formatContract';
import { CONTRACT_STATUS_LABELS } from '@/types/contract.types';

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 border-b border-slate-100 py-3 sm:flex-row sm:gap-4">
      <dt className="shrink-0 text-sm font-medium text-slate-500 sm:w-32">{label}</dt>
      <dd className="min-w-0 text-sm text-slate-900">{value}</dd>
    </div>
  );
}

export default function ContractDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: contract, isLoading, isError, error, refetch } = useContract(id);
  const [docHint] = useState('준비 중');

  const isUnauthorized =
    isError && error instanceof Error && /session expired|401|unauthorized|로그인/i.test(error.message);

  return (
    <div className="admin-contract-detail min-w-0 py-4 md:py-6 max-w-[960px] mx-auto w-full px-4 md:px-8">
      <Link
        to="/admin/contracts"
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
      >
        <ArrowLeft size={16} aria-hidden />
        계약 목록
      </Link>

      {isLoading && (
        <div aria-label="계약 상세 로딩 중">
          <LoadingSkeleton className="mb-4 h-10 w-64 rounded-lg" />
          <LoadingSkeleton className="h-64 w-full rounded-xl" />
        </div>
      )}

      {isUnauthorized && (
        <EmptyState
          title="로그인이 필요합니다"
          description="계약 상세를 보려면 관리자로 로그인해 주세요."
          action={
            <Link to="/login" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
              로그인
            </Link>
          }
        />
      )}

      {isError && !isUnauthorized && (
        <EmptyState
          title="계약을 불러오지 못했습니다"
          description={error instanceof Error ? error.message : '잠시 후 다시 시도해 주세요.'}
          action={
            <button
              type="button"
              onClick={() => void refetch()}
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
            >
              다시 시도
            </button>
          }
        />
      )}

      {!isLoading && !isError && contract && (
        <>
          <PageHeader
            title={contract.title}
            description={contract.contractNo}
            actions={
              <button
                type="button"
                disabled
                title={docHint}
                className="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-400 lg:h-10"
              >
                <FileText size={16} aria-hidden />
                계약서 ({docHint})
              </button>
            }
          />

          <div className="mb-4 flex flex-wrap gap-2">
            <ContractStatusBadge status={contract.status} />
            <PaymentStatusBadge contract={contract} />
          </div>

          <section className="rounded-xl border border-[var(--pt-color-border)] bg-white p-4 shadow-sm md:p-6">
            <dl>
              <DetailRow label="고객" value={contract.customerName ?? '-'} />
              <DetailRow label="상품" value={contract.productName ?? '-'} />
              <DetailRow label="사이트 ID" value={contract.siteId ?? '-'} />
              <DetailRow label="계약금액" value={formatContractCurrency(contract.totalAmount)} />
              <DetailRow label="납부액" value={formatContractCurrency(contract.paidAmount)} />
              <DetailRow
                label="미수금"
                value={
                  contract.outstandingAmount > 0 ? (
                    <span className="font-medium text-red-600">
                      {formatContractCurrency(contract.outstandingAmount)}
                    </span>
                  ) : (
                    '-'
                  )
                }
              />
              <DetailRow
                label="계약기간"
                value={`${formatContractDate(contract.startDate)} ~ ${formatContractDate(contract.endDate)}`}
              />
              <DetailRow label="계약일" value={formatContractDate(contract.contractedAt)} />
              <DetailRow label="상태" value={CONTRACT_STATUS_LABELS[contract.status] ?? contract.status} />
              {contract.signedAt ? (
                <DetailRow
                  label="서명"
                  value={`${contract.signerName ?? '-'} (${formatContractDateTime(contract.signedAt)})`}
                />
              ) : null}
              {contract.notes ? <DetailRow label="메모" value={contract.notes} /> : null}
              {contract.cancelReason ? (
                <DetailRow label="취소 사유" value={contract.cancelReason} />
              ) : null}
            </dl>
          </section>

          <div className="mt-6 flex gap-3">
            <button
              type="button"
              onClick={() => navigate(-1)}
              className="h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 lg:h-10"
            >
              뒤로
            </button>
          </div>
        </>
      )}
    </div>
  );
}
