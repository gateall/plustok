import { useState } from 'react';
import PageHeader from '../components/common/PageHeader';
import {
  AiPerformanceSection,
  CustomerAnalysisSection,
  HourlyTrendsSection,
} from '../components/Admin/AdminDashboard';
import { useAdminOverview } from '../hooks/useAdminStats';

export default function AdminStatsPage() {
  const [period, setPeriod] = useState('month');
  const overview = useAdminOverview();

  return (
    <div className="min-w-0 py-4 md:py-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 md:mb-6">
        <PageHeader
          title="통계 및 분석"
          description="기간별 전체 통계 데이터와 AI 분석 결과를 확인하세요."
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
            <option value="year">올해</option>
          </select>
        </div>
      </div>

      <div className="grid min-w-0 gap-6">
        {/* 요약 통계 */}
        <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-6 shadow-sm">
          <h2 className="text-base font-semibold text-slate-800 mb-4">기간 요약 (API 미지원 항목 포함)</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="p-4 bg-slate-50 rounded-lg border border-slate-100">
              <p className="text-sm text-slate-500">전체 상담</p>
              <p className="text-2xl font-bold text-slate-900 mt-1">
                {overview.data?.kpis?.activeChats?.value ?? '—'}
              </p>
            </div>
            <div className="p-4 bg-slate-50 rounded-lg border border-slate-100">
              <p className="text-sm text-slate-500">평균 응답 시간</p>
              <p className="text-2xl font-bold text-slate-900 mt-1">
                {overview.data?.kpis?.avgResponseSec?.value ?? '—'}s
              </p>
            </div>
            <div className="p-4 bg-slate-50 rounded-lg border border-slate-100">
              <p className="text-sm text-slate-500">신규 계약 수</p>
              <p className="text-2xl font-bold text-slate-900 mt-1">—</p>
            </div>
            <div className="p-4 bg-slate-50 rounded-lg border border-slate-100">
              <p className="text-sm text-slate-500">총 계약 금액</p>
              <p className="text-2xl font-bold text-slate-900 mt-1">—</p>
            </div>
          </div>
        </section>

        {/* 상세 차트 및 분석 (대시보드 컴포넌트 재사용) */}
        <div className="grid md:grid-cols-2 gap-6">
          <AiPerformanceSection />
          <div className="space-y-6">
            <CustomerAnalysisSection />
            <HourlyTrendsSection />
          </div>
        </div>
      </div>
    </div>
  );
}
