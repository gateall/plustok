import type { ReactNode } from 'react';

import type { KpiMetric } from '../../types/admin.types';

import { KpiGridSkeleton } from '../common/LoadingSkeleton';

import EmptyState from '../common/EmptyState';

import {

  useAdminAgents,

  useAdminFunnel,

  useAdminMonitor,

  useAdminOverview,

  useAdminSentiment,

  useAdminTrends,

} from '../../hooks/useAdminStats';



function KpiCard({

  label,

  value,

  metric,

  suffix = '',

}: {

  label: string;

  value?: number | string;

  metric?: KpiMetric;

  suffix?: string;

}) {

  const display = value ?? metric?.value ?? '—';

  const dir = metric?.deltaDirection ?? 'flat';

  const delta = metric?.deltaPercent ?? 0;

  const arrow = dir === 'up' ? '▲' : dir === 'down' ? '▼' : '—';

  const deltaClass = dir === 'down' ? 'text-emerald-600' : dir === 'up' ? 'text-amber-600' : 'text-slate-400';



  return (

    <div className="min-w-0 rounded-[14px] border border-slate-200 bg-white p-3 shadow-sm sm:p-4">

      <p className="truncate text-xs font-medium text-slate-500">{label}</p>

      <p className="mt-1 truncate text-[clamp(1.25rem,5vw,1.875rem)] font-bold leading-tight text-slate-900">

        {display}

        {suffix}

      </p>

      {metric && (

        <p className={`mt-1 truncate text-xs ${deltaClass}`}>

          {arrow} {Math.abs(delta)}% vs 비교기간

        </p>

      )}

    </div>

  );

}



function SectionShell({ title, children }: { title: string; children: ReactNode }) {

  return (

    <section className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">

      <h2 className="mb-4 text-base font-semibold text-slate-800 md:text-lg">{title}</h2>

      {children}

    </section>

  );

}



export function RealtimeSection() {

  const overview = useAdminOverview();

  const monitor = useAdminMonitor();





  if (overview.isLoading) {
    return (
      <SectionShell title="실시간 현황">
        <KpiGridSkeleton />
      </SectionShell>
    );
  }

  if (overview.isError || !overview.data) {
    return (
      <SectionShell title="실시간 현황">
        <EmptyState title="데이터를 불러오지 못했습니다" description="잠시 후 다시 시도해 주세요." />
      </SectionShell>
    );
  }



  const rooms = monitor.data?.rooms ?? [];

  const inProgress = rooms.filter((r) => r.status === 'active').length;



  return (
    <SectionShell title="핵심 지표 (Realtime)">
      <div className="grid min-w-0 grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6 md:gap-4">
        <KpiCard label="전체 상담" value={overview.data?.kpis.activeChats.value ?? 0} metric={overview.data?.kpis.activeChats} />
        <KpiCard label="진행 중 상담" value={inProgress} />
        <KpiCard label="신규 고객" value="-" />
        <KpiCard label="계약 수" value="-" />
        <KpiCard label="계약 금액" value="-" />
        <KpiCard label="미수금" value="-" />
      </div>
    </SectionShell>
  );
}



export function TodayTasksSection() {

  const monitor = useAdminMonitor();

  const waiting = (monitor.data?.rooms ?? []).filter((r) => r.status === 'new' && !r.agent);



  return (

    <SectionShell title="오늘 할 일">

      {monitor.isLoading && <p className="text-sm text-slate-500">로딩 중…</p>}

      {!monitor.isLoading && waiting.length === 0 && (

        <p className="text-sm text-slate-500">답변 대기 중인 상담이 없습니다.</p>

      )}

      {!monitor.isLoading && waiting.length > 0 && (

        <ul className="divide-y divide-slate-100 text-sm">

          {waiting.slice(0, 5).map((r) => (

            <li key={r.id} className="flex min-w-0 items-center justify-between gap-2 py-2.5">

              <div className="min-w-0 flex-1">

                <p className="truncate font-medium text-slate-900">{r.customerNameMasked}</p>

                <p className="text-overflow-clamp-2 text-xs text-slate-500">

                  {r.lastMessagePreview || '새 상담 대기'}

                </p>

              </div>

              <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">

                답변 대기

              </span>

            </li>

          ))}

        </ul>

      )}

    </SectionShell>

  );

}



export function AgentSection() {

  const { data, isLoading } = useAdminAgents();

  const agents = data?.agents ?? [];



  return (

    <SectionShell title="상담원 현황">

      {isLoading && <p className="text-slate-500">로딩 중…</p>}

      {!isLoading && (

        <>

          <div className="space-y-3 md:hidden">

            {agents.map((a) => (

              <div key={a.id} className="min-w-0 rounded-xl border border-slate-100 bg-slate-50/50 p-4">

                <div className="flex items-center justify-between gap-2">

                  <span className="truncate font-medium text-slate-900">{a.displayName}</span>

                  <span className="shrink-0 text-xs text-slate-500">{a.status}</span>

                </div>

                <div className="mt-2 space-y-1 text-sm">

                  <div className="flex justify-between gap-2">

                    <span className="text-slate-500">오늘 종료</span>

                    <span className="font-medium">{a.closedToday}</span>

                  </div>

                  <div className="flex justify-between gap-2">

                    <span className="text-slate-500">평균 응답(s)</span>

                    <span className="font-medium">{a.avgResponseSec}</span>

                  </div>

                </div>

              </div>

            ))}

            {agents.length === 0 && <p className="py-4 text-slate-400">등록된 상담원이 없습니다.</p>}

          </div>



          <div className="hidden overflow-x-auto md:block">

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

                {agents.map((a) => (

                  <tr key={a.id} className="border-b border-slate-100">

                    <td className="py-2 pr-4 font-medium">{a.displayName}</td>

                    <td className="py-2 pr-4">{a.status}</td>

                    <td className="py-2 pr-4">{a.closedToday}</td>

                    <td className="py-2">{a.avgResponseSec}</td>

                  </tr>

                ))}

                {agents.length === 0 && (

                  <tr>

                    <td colSpan={4} className="py-4 text-slate-400">

                      등록된 상담원이 없습니다.

                    </td>

                  </tr>

                )}

              </tbody>

            </table>

          </div>

        </>

      )}

    </SectionShell>

  );

}



export function AiPerformanceSection() {

  const sentiment = useAdminSentiment();

  const funnel = useAdminFunnel();



  return (

    <SectionShell title="AI 성과">

      <div className="grid min-w-0 gap-6 md:grid-cols-2">

        <div className="min-w-0">

          <h3 className="mb-2 text-sm font-semibold text-slate-700">감정 분포</h3>

          <ul className="space-y-2 text-sm">

            {(sentiment.data?.segments ?? []).map((s) => (

              <li key={s.label} className="flex min-w-0 justify-between gap-2">

                <span className="truncate capitalize">{s.label}</span>

                <span className="shrink-0">

                  {s.count} ({s.percent}%)

                </span>

              </li>

            ))}

          </ul>

        </div>

        <div className="min-w-0">

          <h3 className="mb-2 text-sm font-semibold text-slate-700">계약 확률 Funnel</h3>

          <ul className="space-y-2 text-sm">

            {(funnel.data?.buckets ?? []).map((b) => (

              <li key={b.label} className="flex min-w-0 justify-between gap-2">

                <span className="truncate">{b.label}%</span>

                <span className="shrink-0">{b.count}</span>

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

      <p className="break-words-safe text-sm text-slate-600">

        분석 대상 AI 추천 {total > 0 ? total : '—'}건 · Funnel 기준 계약 가능성 분포

      </p>

      <div className="mt-4 flex h-4 min-w-0 overflow-hidden rounded-full bg-slate-100">

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

  const series = (data?.series ?? []).slice(-12);

  const max = Math.max(1, ...series.map((p) => p.messages));



  return (

    <SectionShell title="시간대별 추이">

      {isLoading && <p className="text-slate-500">로딩 중…</p>}

      {!isLoading && (

        <div className="flex h-24 min-w-0 items-end gap-1 sm:h-32">

          {series.map((p) => (

            <div

              key={p.hour}

              className="min-w-0 flex-1 rounded-t bg-sky-500/80"

              style={{ height: `${(p.messages / max) * 100}%`, minHeight: p.messages > 0 ? 4 : 0 }}

              title={`${p.hour}: ${p.messages} msgs`}

            />

          ))}

          {series.length === 0 && (

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

          <li key={r.id} className="flex min-w-0 items-center justify-between gap-2 py-2">

            <div className="min-w-0 flex-1">

              <div className="flex min-w-0 items-center gap-2">

                <span className="truncate font-medium">{r.customerNameMasked}</span>

                <span className="shrink-0 text-slate-400">{r.status}</span>

              </div>

              <p className="text-overflow-clamp-2 text-xs text-slate-500">{r.lastMessagePreview || '—'}</p>

            </div>

            <span className="shrink-0 text-indigo-600">{r.contractProbability}%</span>

          </li>

        ))}

        {(data?.rooms ?? []).length === 0 && (

          <li className="py-4 text-slate-400">진행 중인 상담이 없습니다.</li>

        )}

      </ul>

    </SectionShell>

  );

}



export function RecentActivitySection() {
  const monitor = useAdminMonitor();
  const rooms = monitor.data?.rooms ?? [];
  const recentRooms = rooms.slice(0, 5);

  return (
    <div className="grid min-w-0 gap-6 lg:grid-cols-3">
      <SectionShell title="최근 상담">
        {monitor.isLoading ? (
          <p className="text-sm text-slate-500">로딩 중…</p>
        ) : recentRooms.length > 0 ? (
          <ul className="divide-y divide-slate-100 text-sm">
            {recentRooms.map((r) => (
              <li key={r.id} className="flex items-center justify-between py-2">
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium">{r.customerNameMasked}</p>
                  <p className="text-xs text-slate-500 truncate">{r.lastMessagePreview || '내용 없음'}</p>
                </div>
                <span className="shrink-0 text-xs text-slate-400">{r.status}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-slate-400">최근 상담이 없습니다.</p>
        )}
      </SectionShell>
      
      <SectionShell title="최근 고객">
        <div className="flex h-full items-center justify-center p-4">
          <EmptyState title="API 미지원" description="최근 고객 목록을 불러올 수 없습니다." />
        </div>
      </SectionShell>

      <SectionShell title="최근 계약">
        <div className="flex h-full items-center justify-center p-4">
          <EmptyState title="API 미지원" description="최근 계약 목록을 불러올 수 없습니다." />
        </div>
      </SectionShell>
    </div>
  );
}

export default function AdminDashboard() {
  return (
    <div className="min-w-0 space-y-6">
      <RealtimeSection />
      
      <RecentActivitySection />

      <div className="grid min-w-0 gap-6 lg:grid-cols-2">
        <AgentSection />
        <AiPerformanceSection />
      </div>

      <CustomerAnalysisSection />
      
      <HourlyTrendsSection />
      
      <LiveMonitorSection />
    </div>
  );
}

