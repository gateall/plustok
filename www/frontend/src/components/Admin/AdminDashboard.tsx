import type { ReactNode } from 'react';
import type { KpiMetric } from '../../types/admin.types';
import {
  useAdminAgents,
  useAdminFunnel,
  useAdminMonitor,
  useAdminOverview,
  useAdminSentiment,
  useAdminTrends,
} from '../../hooks/useAdminStats';

function KpiCard({ label, metric, suffix = '' }: { label: string; metric?: KpiMetric; suffix?: string }) {
  const value = metric?.value ?? '—';
  const dir = metric?.deltaDirection ?? 'flat';
  const delta = metric?.deltaPercent ?? 0;
  const arrow = dir === 'up' ? '▲' : dir === 'down' ? '▼' : '—';
  const deltaClass = dir === 'down' ? 'text-emerald-600' : dir === 'up' ? 'text-amber-600' : 'text-slate-400';

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-3xl font-semibold text-slate-900">
        {value}
        {suffix}
      </p>
      {metric && (
        <p className={`mt-1 text-sm ${deltaClass}`}>
          {arrow} {Math.abs(delta)}% vs 비교기간
        </p>
      )}
    </div>
  );
}

function SectionShell({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="mb-4 text-lg font-semibold text-slate-800">{title}</h2>
      {children}
    </section>
  );
}

export function RealtimeSection() {
  const { data, isLoading, isError } = useAdminOverview();

  if (isLoading) return <SectionShell title="실시간 현황">로딩 중…</SectionShell>;
  if (isError || !data) return <SectionShell title="실시간 현황">데이터를 불러오지 못했습니다.</SectionShell>;

  return (
    <SectionShell title="실시간 현황">
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <KpiCard label="활성 상담" metric={data.kpis.activeChats} />
        <KpiCard label="평균 응답" metric={data.kpis.avgResponseSec} suffix="s" />
        <KpiCard label="AI 채택률" metric={data.kpis.aiAdoptionRate} suffix="%" />
        <KpiCard label="CRM 전환" metric={data.kpis.contractConversion} suffix="%" />
      </div>
    </SectionShell>
  );
}

export function AgentSection() {
  const { data, isLoading } = useAdminAgents();
  return (
    <SectionShell title="상담원 현황">
      {isLoading && <p className="text-slate-500">로딩 중…</p>}
      {!isLoading && (
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2 pr-4">상담원</th>
                <th className="py-2 pr-4">상태</th>
                <th className="py-2 pr-4">오늘 종료</th>
                <th className="py-2">평균 응답(s)</th>
              </tr>
            </thead>
            <tbody>
              {(data?.agents ?? []).map((a) => (
                <tr key={a.id} className="border-b border-slate-100">
                  <td className="py-2 pr-4 font-medium">{a.displayName}</td>
                  <td className="py-2 pr-4">{a.status}</td>
                  <td className="py-2 pr-4">{a.closedToday}</td>
                  <td className="py-2">{a.avgResponseSec}</td>
                </tr>
              ))}
              {(data?.agents ?? []).length === 0 && (
                <tr>
                  <td colSpan={4} className="py-4 text-slate-400">
                    등록된 상담원이 없습니다.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </SectionShell>
  );
}

export function AiPerformanceSection() {
  const sentiment = useAdminSentiment();
  const funnel = useAdminFunnel();

  return (
    <SectionShell title="AI 성과">
      <div className="grid gap-6 md:grid-cols-2">
        <div>
          <h3 className="mb-2 text-sm font-semibold text-slate-700">감정 분포</h3>
          <ul className="space-y-2 text-sm">
            {(sentiment.data?.segments ?? []).map((s) => (
              <li key={s.label} className="flex justify-between">
                <span className="capitalize">{s.label}</span>
                <span>
                  {s.count} ({s.percent}%)
                </span>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h3 className="mb-2 text-sm font-semibold text-slate-700">계약 확률 Funnel</h3>
          <ul className="space-y-2 text-sm">
            {(funnel.data?.buckets ?? []).map((b) => (
              <li key={b.label} className="flex justify-between">
                <span>{b.label}%</span>
                <span>{b.count}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </SectionShell>
  );
}

export function CustomerAnalysisSection() {
  const { data } = useAdminFunnel();
  const total = (data?.buckets ?? []).reduce((s, b) => s + b.count, 0);
  return (
    <SectionShell title="고객 분석">
      <p className="text-sm text-slate-600">
        분석 대상 AI 추천 {total > 0 ? total : '—'}건 · Funnel 기준 계약 가능성 분포
      </p>
      <div className="mt-4 flex h-4 overflow-hidden rounded-full bg-slate-100">
        {(data?.buckets ?? []).map((b, i) => (
          <div
            key={b.label}
            className={['bg-indigo-500', 'bg-violet-400', 'bg-amber-400', 'bg-slate-300'][i] ?? 'bg-slate-300'}
            style={{ width: total > 0 ? `${(b.count / total) * 100}%` : '25%' }}
            title={`${b.label}: ${b.count}`}
          />
        ))}
      </div>
    </SectionShell>
  );
}

export function HourlyTrendsSection() {
  const { data, isLoading } = useAdminTrends();
  const max = Math.max(1, ...(data?.series ?? []).map((p) => p.messages));

  return (
    <SectionShell title="시간대별 추이">
      {isLoading && <p className="text-slate-500">로딩 중…</p>}
      {!isLoading && (
        <div className="flex h-32 items-end gap-1">
          {(data?.series ?? []).slice(-24).map((p) => (
            <div
              key={p.hour}
              className="flex-1 rounded-t bg-sky-500/80"
              style={{ height: `${(p.messages / max) * 100}%`, minHeight: p.messages > 0 ? 4 : 0 }}
              title={`${p.hour}: ${p.messages} msgs`}
            />
          ))}
          {(data?.series ?? []).length === 0 && (
            <p className="text-sm text-slate-400">선택 기간에 메시지 데이터가 없습니다.</p>
          )}
        </div>
      )}
    </SectionShell>
  );
}

export function LiveMonitorSection() {
  const { data } = useAdminMonitor();
  return (
    <SectionShell title="Live Monitor">
      <ul className="divide-y divide-slate-100 text-sm">
        {(data?.rooms ?? []).slice(0, 8).map((r) => (
          <li key={r.id} className="flex items-center justify-between py-2">
            <div>
              <span className="font-medium">{r.customerNameMasked}</span>
              <span className="ml-2 text-slate-400">{r.status}</span>
              <p className="truncate text-xs text-slate-500">{r.lastMessagePreview || '—'}</p>
            </div>
            <span className="text-indigo-600">{r.contractProbability}%</span>
          </li>
        ))}
        {(data?.rooms ?? []).length === 0 && (
          <li className="py-4 text-slate-400">진행 중인 상담이 없습니다.</li>
        )}
      </ul>
    </SectionShell>
  );
}

export default function AdminDashboard() {
  return (
    <div className="space-y-6">
      <RealtimeSection />
      <div className="grid gap-6 lg:grid-cols-2">
        <AgentSection />
        <AiPerformanceSection />
      </div>
      <CustomerAnalysisSection />
      <HourlyTrendsSection />
      <LiveMonitorSection />
    </div>
  );
}
