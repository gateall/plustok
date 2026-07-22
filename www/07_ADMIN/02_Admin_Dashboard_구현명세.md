# 02 — Admin Dashboard 구현 명세

> **PlusTok ACEP** · STEP 6 · AI 운영 센터 Dashboard  
> **버전**: 1.0.0 · **작성일**: 2026-07-21  
> **UIUX**: [`01_관리자화면_UIUX_설계.md §6.1`](./01_관리자화면_UIUX_설계.md#61-dashboard-home--ai-운영-센터-개요)  
> **V2.5 Preview**: STEP 14 AI 운영 센터 통합 설계 선행 반영

---

## 목차

1. [개요](#1-개요)
2. [하이브리드 구현 전략](#2-하이브리드-구현-전략)
3. [KPI 카드 명세](#3-kpi-카드-명세)
4. [차트 위젯 명세](#4-차트-위젯-명세)
5. [실시간 위젯](#5-실시간-위젯)
6. [Admin Stats API](#6-admin-stats-api)
7. [데이터 소스 및 쿼리](#7-데이터-소스-및-쿼리)
8. [PHP 구현 (V1.0)](#8-php-구현-v10)
9. [React Dashboard (V2.0 Path)](#9-react-dashboard-v20-path)
10. [V2.5 STEP 14 Preview](#10-v25-step-14-preview)
11. [캐싱·성능](#11-캐싱성능)
12. [테스트·모니터링](#12-테스트모니터링)
13. [부록](#13-부록)

---

## 1. 개요

### 1.1 목적

PlusTok ACEP **AI 운영 센터 Dashboard**의 KPI·차트·실시간 위젯 구현 명세를 정의한다.  
STEP 6에서는 **PHP Admin + Chart.js + REST stats API**로 MVP를 구현하고, V2.0에서 React SPA로 이전한다.

### 1.2 Dashboard가 답하는 질문

| 질문 | 위젯 |
|------|------|
| 지금 몇 건이 진행 중인가? | 활성 상담 KPI |
| 응답이 느려지고 있나? | 평균 응답 시간 KPI + trend |
| AI 추천을 실제로 쓰는가? | AI 채택률 KPI |
| 상담이 계약으로 이어지는가? | 계약 전환율 KPI + Funnel |
| 고객 감정 상태는? | 감정 분포 차트 |
| 에이전트별 편차는? | 에이전트 성과 Bar chart |
| AI 장애가 있었나? | Failover top 5 위젯 |

### 1.3 범위

| Phase | 구현 | 기술 |
|-------|------|------|
| STEP 6 V1.0 | KPI 4 + Chart 3 + Widget 2 | PHP, Chart.js, REST |
| V1.5 | WebSocket live widget | WS admin namespace |
| V2.0 | Full React Dashboard SPA | 06_FRONTEND 확장 |
| V2.5 (STEP 14) | AI 운영 센터 통합 | ML pipeline metrics |

---

## 2. 하이브리드 구현 전략

### 2.1 현재 상태 (STEP 5까지)

- [`06_FRONTEND/_FRONTEND_INDEX.md`](../06_FRONTEND/_FRONTEND_INDEX.md): Customer/Agent React — **Admin out of scope**
- 기존 PHP `admin/` — CRM 상담, `ai.php` 설정 운영 중

### 2.2 STEP 6 권장 아키텍처

```
┌─────────────────────────────────────────────────────────────────┐
│                     STEP 6 Hybrid Architecture                   │
├─────────────────────────────────────────────────────────────────┤
│  Browser                                                         │
│    └── /admin/index.php (PHP layout + partials)                 │
│          ├── KPI cards (server render + AJAX refresh)           │
│          ├── Chart.js canvases (fetch /api/v1/admin/stats/*)    │
│          └── Live widget (polling → WS V1.5)                    │
├─────────────────────────────────────────────────────────────────┤
│  PHP Admin Layer                                                 │
│    includes/admin_layout.php, components/kpi_card.php           │
│    admin/api/stats_proxy.php (optional BFF for legacy)          │
├─────────────────────────────────────────────────────────────────┤
│  REST API (Admin Domain)                                         │
│    GET /api/v1/admin/stats/overview                             │
│    GET /api/v1/admin/stats/sentiment                            │
│    GET /api/v1/admin/stats/funnel                               │
│    GET /api/v1/admin/stats/agents                               │
├─────────────────────────────────────────────────────────────────┤
│  MySQL                                                           │
│    chat_rooms, messages, ai_logs, ai_recommendations, agents    │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 V2.0 Migration Path

```
Phase A (STEP 6):  PHP shell hosts dashboard, API-first stats
Phase B (V1.8):    Extract chart components → shared JS module
Phase C (V2.0):    React route /admin/dashboard mounts in PHP iframe OR
                   nginx path /admin-v2/* full SPA
Phase D (V2.5):    Unified AI Ops Center — merge STEP 14 metrics
```

### 2.4 왜 API-first인가

- PHP→React 전환 시 **동일 stats API** 재사용
- Mobile Monitor V1.5도 동일 endpoint 소비
- [`02_API설계.md`](../03_SYSTEM/02_API설계.md) Admin Domain extension과 정합

---

## 3. KPI 카드 명세

### 3.1 공통 KPI Card 구조

```html
<!-- components/kpi_card.php -->
<div class="kpi-card" data-metric="active_chats">
  <div class="kpi-label">활성 상담</div>
  <div class="kpi-value" id="kpi-active-chats">—</div>
  <div class="kpi-delta positive">▲ +8% vs 어제</div>
  <div class="kpi-sparkline"><canvas id="spark-active"></canvas></div>
</div>
```

### 3.2 KPI 정의표

| ID | Label | Key | Type | Format | Delta |
|----|-------|-----|------|--------|-------|
| KPI-01 | 활성 상담 | `active_chats` | count | `#,###` | vs yesterday |
| KPI-02 | 평균 응답 시간 | `avg_response_sec` | duration | `Nm Ns` | vs yesterday (inverse) |
| KPI-03 | AI 채택률 | `ai_adoption_rate` | percent | `N.N%` | vs 7d avg |
| KPI-04 | 계약 전환율 | `contract_conversion` | percent | `N.N%` | vs 30d avg |

### 3.3 KPI-01: 활성 상담

```sql
-- active_chats (snapshot)
SELECT COUNT(*) AS value
FROM chat_rooms
WHERE status IN ('active', 'waiting')
  AND deleted_at IS NULL;
```

**Delta**: `(today_count - yesterday_same_hour_count) / yesterday * 100`

### 3.4 KPI-02: 평균 응답 시간

**정의**: 고객 첫 메시지 → 에이전트 첫 응답까지 초(seconds).

```sql
SELECT AVG(TIMESTAMPDIFF(SECOND, first_customer_msg.created_at, first_agent_msg.created_at)) AS avg_sec
FROM chat_rooms cr
JOIN (
  SELECT chat_room_id, MIN(created_at) AS created_at
  FROM messages WHERE sender_type = 'customer'
  GROUP BY chat_room_id
) first_customer_msg ON cr.id = first_customer_msg.chat_room_id
JOIN (
  SELECT chat_room_id, MIN(created_at) AS created_at
  FROM messages WHERE sender_type = 'agent'
  GROUP BY chat_room_id
) first_agent_msg ON cr.id = first_agent_msg.chat_room_id
WHERE cr.created_at >= :period_start
  AND cr.created_at < :period_end;
```

**UI**: 102초 → `1m 42s`. Delta negative is **good** (green ▼).

### 3.5 KPI-03: AI 채택률

```sql
SELECT
  SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 AS rate
FROM ai_recommendations
WHERE created_at >= :period_start AND created_at < :period_end;
```

**Null handling**: recommendations 0건 → `N/A` display, delta hidden.

### 3.6 KPI-04: 계약 전환율

```sql
SELECT
  SUM(CASE WHEN contract_status = 'signed' THEN 1 ELSE 0 END) / COUNT(*) * 100 AS rate
FROM chat_rooms
WHERE status = 'closed'
  AND closed_at >= :period_start AND closed_at < :period_end;
```

---

## 4. 차트 위젯 명세

### 4.1 감정 분포 (Sentiment Distribution)

**Type**: Doughnut chart (Chart.js)  
**API**: `GET /api/v1/admin/stats/sentiment`

| Segment | Color | Source |
|---------|-------|--------|
| positive | `#16A34A` | `ai_logs.sentiment_label = 'positive'` |
| neutral | `#6B7280` | neutral |
| negative | `#DC2626` | negative |

**Data shape**:

```json
{
  "period": { "start": "2026-07-21T00:00:00+09:00", "end": "2026-07-22T00:00:00+09:00" },
  "distribution": [
    { "label": "positive", "count": 234, "percent": 45.2 },
    { "label": "neutral", "count": 198, "percent": 38.3 },
    { "label": "negative", "count": 85, "percent": 16.5 }
  ],
  "total_analyzed": 517
}
```

**Interaction**: segment click → Consult List filter `sentiment={label}`

### 4.2 계약 확률 Funnel

**Type**: Horizontal bar / funnel visualization  
**API**: `GET /api/v1/admin/stats/funnel`

**Buckets**:

| Stage | Range | Color |
|-------|-------|-------|
| High | ≥ 80% | `#16A34A` |
| Medium | 50–79% | `#2563EB` |
| Low | 20–49% | `#D97706` |
| Very Low | < 20% | `#DC2626` |

```json
{
  "stages": [
    { "key": "high", "label": "80%+", "count": 23, "percent_of_total": 18.1 },
    { "key": "medium", "label": "50-80%", "count": 41, "percent_of_total": 32.3 },
    { "key": "low", "label": "20-49%", "count": 38, "percent_of_total": 29.9 },
    { "key": "very_low", "label": "<20%", "count": 25, "percent_of_total": 19.7 }
  ],
  "total_rooms": 127
}
```

**Source**: Latest `ai_logs.contract_probability` per active/closed room in period.

### 4.3 에이전트 성과 (Agent Performance)

**Type**: Grouped bar chart  
**API**: `GET /api/v1/admin/stats/agents`

**Metrics per agent** (3 bars):

1. `avg_response_sec` (left axis, seconds)
2. `ai_adoption_rate` (right axis, %)
3. `contract_conversion` (right axis, %)

```json
{
  "agents": [
    {
      "agent_id": "A001",
      "display_name": "김상담",
      "avg_response_sec": 98,
      "ai_adoption_rate": 72.3,
      "contract_conversion": 14.2,
      "active_rooms": 3,
      "closed_today": 8
    }
  ],
  "limit": 10,
  "sorted_by": "closed_today DESC"
}
```

**Empty state**: agents 0 → "등록된 에이전트가 없습니다" + link to Agent Management

---

## 5. 실시간 위젯

### 5.1 Failover Top 5

**Source**: `ai_failover_log` ORDER BY created_at DESC LIMIT 5  
**Refresh**: polling 60s (Dashboard page)  
**Display**: timestamp, room_id, from→to, reason truncated

### 5.2 Active Chats Top 10

**Source**: `chat_rooms` WHERE status IN ('active','waiting') ORDER BY updated_at DESC LIMIT 10  
**Display**: room_id, customer masked name, assigned agent, duration  
**Click**: navigate to Live Monitor with room pre-selected

### 5.3 WebSocket Admin Namespace (V1.5 — Optional)

```
Namespace: /admin
Events:
  subscribe:dashboard  → server pushes KPI delta every 30s
  subscribe:monitor    → room list + message stream updates

Auth: JWT with role super|admin|operator
      handshake query: ?token={admin_jwt}
```

**Fallback**: WS disconnect → revert to HTTP polling (exponential backoff max 5min)

### 5.4 Widget Refresh Matrix

| Widget | V1.0 | V1.5 |
|--------|------|------|
| KPI cards | AJAX 60s | WS push 30s |
| Charts | on date change + 5min | on date change + WS trigger |
| Failover top 5 | 60s poll | WS event `failover:new` |
| Active chats | 30s poll | WS event `room:update` |

---

## 6. Admin Stats API

> Admin Domain extension — [`02_API설계.md`](../03_SYSTEM/02_API설계.md) 스타일 정합  
> 상세 권한: [`04_Admin_API_및_권한_명세.md`](./04_Admin_API_및_권한_명세.md)

### 6.1 공통 Conventions

| Item | Value |
|------|-------|
| Base path | `/api/v1/admin` |
| Auth | Bearer JWT, roles: super, admin, operator (read) |
| Date param | `period_start`, `period_end` ISO8601 KST |
| Default period | today 00:00 KST – now |
| Pagination | `page`, `limit` (max 100) |
| Error format | `{ "error": { "code": "...", "message": "..." } }` |

### 6.2 GET /api/v1/admin/stats/overview

**Description**: Dashboard KPI 4-pack + sparkline series

**Query**:

| Param | Type | Required |
|-------|------|----------|
| period_start | datetime | no |
| period_end | datetime | no |
| compare | enum: yesterday, 7d, 30d | no (default yesterday) |

**Response 200**:

```json
{
  "generated_at": "2026-07-21T14:35:00+09:00",
  "period": { "start": "...", "end": "..." },
  "kpis": {
    "active_chats": { "value": 127, "delta_percent": 8.2, "delta_direction": "up" },
    "avg_response_sec": { "value": 102, "delta_percent": -11.5, "delta_direction": "down" },
    "ai_adoption_rate": { "value": 68.4, "delta_percent": 2.1, "delta_direction": "up" },
    "contract_conversion": { "value": 12.3, "delta_percent": 0.0, "delta_direction": "flat" }
  },
  "sparklines": {
    "active_chats": [120, 118, 125, 127],
    "avg_response_sec": [115, 110, 105, 102]
  }
}
```

### 6.3 GET /api/v1/admin/stats/sentiment

**Response**: §4.1 JSON

### 6.4 GET /api/v1/admin/stats/funnel

**Response**: §4.2 JSON

### 6.5 GET /api/v1/admin/stats/agents

**Query**: `limit` (default 10), `sort` (default `closed_today`)

**Response**: §4.3 JSON

### 6.6 GET /api/v1/admin/stats/export

**Description**: CSV export (super, admin only — operator 403)

**Query**: `type=overview|agents|sentiment`, same period params

**Response**: `Content-Type: text/csv`, filename `plustok_stats_{date}.csv`

### 6.7 Error Codes (Stats subset)

| Code | HTTP | Meaning |
|------|------|---------|
| ADMIN_FORBIDDEN | 403 | Role insufficient |
| STATS_INVALID_PERIOD | 422 | start > end or range > 90d |
| STATS_QUERY_TIMEOUT | 504 | DB query > 10s |

---

## 7. 데이터 소스 및 쿼리

### 7.1 Entity Relationship (Dashboard scope)

```
chat_rooms ──┬── messages
             ├── ai_logs (sentiment, contract_probability)
             ├── ai_recommendations (used flag)
             └── chat_room_assignments ── agents

ai_failover_log ── ai_logs (optional FK)
```

### 7.2 Table Reference

| Table | Dashboard Usage | DB Doc |
|-------|-----------------|--------|
| `chat_rooms` | active count, conversion, funnel room set | [`01_DB설계.md`](../03_SYSTEM/01_DB설계.md) |
| `messages` | response time calculation |同上 |
| `ai_logs` | sentiment, contract_probability |同上 |
| `ai_recommendations` | adoption rate |同上 |
| `agents` | agent performance dimension |同上 |
| `ai_failover_log` | failover widget |同上 |

### 7.3 Index Requirements (STEP 6)

```sql
-- Performance indexes for dashboard queries
CREATE INDEX idx_chat_rooms_status_updated ON chat_rooms(status, updated_at);
CREATE INDEX idx_messages_room_sender_created ON messages(chat_room_id, sender_type, created_at);
CREATE INDEX idx_ai_recommendations_created_used ON ai_recommendations(created_at, used);
CREATE INDEX idx_ai_logs_room_created ON ai_logs(chat_room_id, created_at);
CREATE INDEX idx_ai_failover_log_created ON ai_failover_log(created_at DESC);
```

### 7.4 Materialized Summary (Optional V1.5)

```sql
-- admin_daily_stats: nightly cron aggregate
CREATE TABLE admin_daily_stats (
  stat_date DATE PRIMARY KEY,
  active_peak INT,
  avg_response_sec DECIMAL(10,2),
  ai_adoption_rate DECIMAL(5,2),
  contract_conversion DECIMAL(5,2),
  sentiment_json JSON,
  computed_at DATETIME
);
```

Dashboard period > 7d → read from `admin_daily_stats` when available.

---

## 8. PHP 구현 (V1.0)

### 8.1 File Structure

```
admin/
├── index.php                 # Dashboard entry
├── partials/
│   ├── dashboard_kpi.php
│   ├── dashboard_charts.php
│   └── dashboard_widgets.php
├── assets/
│   ├── js/
│   │   ├── dashboard.js      # Chart.js init, AJAX refresh
│   │   └── chart_config.js
│   └── css/
│       └── dashboard.css
└── api/
    └── stats_proxy.php       # Optional: session-auth proxy to REST
```

### 8.2 admin/index.php Skeleton

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['super', 'admin', 'operator']);

$page_title = 'AI 운영 센터';
$can_export = in_array($_SESSION['role'], ['super', 'admin']);

include __DIR__ . '/../includes/admin_layout_header.php';
?>

<div class="dashboard-toolbar">
  <?php include 'partials/date_range_picker.php'; ?>
  <button id="btn-refresh">새로고침</button>
  <?php if ($can_export): ?>
    <button id="btn-export-csv">내보내기 CSV</button>
  <?php endif; ?>
</div>

<div class="kpi-grid">
  <?php include 'partials/dashboard_kpi.php'; ?>
</div>

<div class="chart-row">
  <?php include 'partials/dashboard_charts.php'; ?>
</div>

<div class="widget-row">
  <?php include 'partials/dashboard_widgets.php'; ?>
</div>

<script src="/admin/assets/js/chart.min.js"></script>
<script src="/admin/assets/js/dashboard.js"></script>
<script>
  Dashboard.init({
    apiBase: '/api/v1/admin/stats',
    token: '<?= htmlspecialchars($jwt_for_js) ?>',
    pollIntervalMs: 60000,
    role: '<?= $_SESSION['role'] ?>'
  });
</script>

<?php include __DIR__ . '/../includes/admin_layout_footer.php'; ?>
```

### 8.3 dashboard.js Core

```javascript
const Dashboard = {
  charts: {},

  async init(config) {
    this.config = config;
    await this.loadOverview();
    await this.loadCharts();
    this.startPolling();
    this.bindEvents();
  },

  async fetch(path, params = {}) {
    const url = new URL(this.config.apiBase + path, window.location.origin);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const res = await fetch(url, {
      headers: { Authorization: 'Bearer ' + this.config.token }
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
  },

  async loadOverview() {
    const data = await this.fetch('/overview', this.getPeriodParams());
    this.renderKPIs(data.kpis);
    this.renderSparklines(data.sparklines);
  },

  async loadCharts() {
    const params = this.getPeriodParams();
    const [sentiment, funnel, agents] = await Promise.all([
      this.fetch('/sentiment', params),
      this.fetch('/funnel', params),
      this.fetch('/agents', params)
    ]);
    this.renderSentimentChart(sentiment);
    this.renderFunnelChart(funnel);
    this.renderAgentsChart(agents);
  },

  startPolling() {
    setInterval(() => this.loadOverview(), this.config.pollIntervalMs);
  }
};
```

### 8.4 Chart.js Config Example (Sentiment)

```javascript
renderSentimentChart(data) {
  const ctx = document.getElementById('chart-sentiment');
  if (this.charts.sentiment) this.charts.sentiment.destroy();
  this.charts.sentiment = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.distribution.map(d => d.label),
      datasets: [{
        data: data.distribution.map(d => d.count),
        backgroundColor: ['#16A34A', '#6B7280', '#DC2626']
      }]
    },
    options: {
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.label}: ${ctx.raw} (${data.distribution[ctx.dataIndex].percent}%)`
          }
        }
      }
    }
  });
}
```

### 8.5 Operator Read-only UI

```php
// dashboard.js — hide export if role operator
if (config.role === 'operator') {
  document.querySelectorAll('[data-write-action]').forEach(el => el.remove());
}
```

---

## 9. React Dashboard (V2.0 Path)

### 9.1 Target Structure

```
06_FRONTEND/
└── src/
    └── admin/
        ├── pages/
        │   └── DashboardPage.tsx
        ├── components/
        │   ├── KPICard.tsx
        │   ├── SentimentChart.tsx
        │   ├── FunnelChart.tsx
        │   ├── AgentPerformanceChart.tsx
        │   └── LiveWidgets.tsx
        ├── hooks/
        │   ├── useAdminStats.ts
        │   └── useAdminWebSocket.ts
        └── api/
            └── adminStatsClient.ts
```

### 9.2 DashboardPage.tsx Outline

```tsx
export function DashboardPage() {
  const { period, setPeriod } = useDateRange('today');
  const { data: overview, refetch } = useAdminStats('/overview', { period });
  const sentiment = useAdminStats('/sentiment', { period });
  const funnel = useAdminStats('/funnel', { period });
  const agents = useAdminStats('/agents', { period, limit: 10 });

  return (
    <AdminLayout title="AI 운영 센터">
      <DashboardToolbar period={period} onPeriodChange={setPeriod} onRefresh={refetch} />
      <KPIGrid kpis={overview?.kpis} />
      <ChartRow>
        <SentimentChart data={sentiment.data} />
        <FunnelChart data={funnel.data} />
      </ChartRow>
      <AgentPerformanceChart data={agents.data} />
      <LiveWidgets />
    </AdminLayout>
  );
}
```

### 9.3 Shared API Client

```typescript
// adminStatsClient.ts — same endpoints as PHP dashboard.js
export const adminStatsClient = {
  getOverview: (params: PeriodParams) =>
    api.get<OverviewResponse>('/api/v1/admin/stats/overview', { params }),
  // ...
};
```

### 9.4 PHP ↔ React Coexistence

| Option | Pros | Cons |
|--------|------|------|
| iframe embed | Zero PHP refactor | UX seam, auth token pass |
| nginx route split | Clean SPA | Dual deploy |
| Gradual replace | Low risk | Two chart libs temporarily |

**Recommendation**: STEP 6 API-first → V2.0 nginx `/admin-v2/dashboard` SPA → eventual PHP layout shell only.

---

## 10. V2.5 STEP 14 Preview

### 10.1 AI 운영 센터 확장 KPI (Preview)

| KPI | Description | Source |
|-----|-------------|--------|
| Model latency P95 | Primary vs fallback | `ai_logs.latency_ms` |
| Token cost / day | USD estimate | `ai_logs.tokens * rate card` |
| Prompt version drift | Active vs recommended | `ai_prompts` + ML |
| False positive rate | AI sentiment vs human review | review table (future) |
| Auto-resolution rate | AI-only closed rooms | V2.0 feature flag |

### 10.2 Additional Charts (Preview)

- **Latency timeline**: line chart 24h primary/fallback
- **Cost breakdown**: stacked bar by model provider
- **Prompt A/B**: conversion by prompt version

### 10.3 Dashboard Layout Extension (ASCII Preview)

```
┌─────────────────────────────────────────────────────────────────┐
│  AI 운영 센터 V2.5                        [Ops] [Cost] [Quality]  │
├─────────────────────────────────────────────────────────────────┤
│  (existing KPI row)                                              │
├─────────────────────────────────────────────────────────────────┤
│  NEW: Latency P95 timeline  │  NEW: Token cost / provider      │
├─────────────────────────────────────────────────────────────────┤
│  NEW: Prompt A/B conversion   │  NEW: Human review queue         │
└─────────────────────────────────────────────────────────────────┘
```

### 10.4 API Extensions (Future)

```
GET /api/v1/admin/stats/latency
GET /api/v1/admin/stats/cost
GET /api/v1/admin/stats/prompt-ab
```

STEP 6: **endpoint stub only in OpenAPI**, implement STEP 14.

---

## 11. 캐싱·성능

### 11.1 SLA Targets

| Metric | Target |
|--------|--------|
| Dashboard first paint | < 2s (PHP SSR shell) |
| KPI AJAX | < 500ms p95 |
| Chart data | < 1s p95 |
| Full page refresh | < 3s |

### 11.2 Caching Strategy

| Layer | TTL | Key |
|-------|-----|-----|
| Redis overview | 30s | `admin:stats:overview:{date}` |
| Redis sentiment | 60s | `admin:stats:sentiment:{start}:{end}` |
| Browser | no-cache API | `Cache-Control: private, no-store` |

### 11.3 Query Optimization

- Avoid N+1: agent stats single query with GROUP BY
- Limit funnel to rooms with latest ai_log in period
- EXPLAIN all dashboard queries before release

### 11.4 Load Test Scenarios

| Scenario | Users | Expected |
|----------|-------|----------|
| Normal | 10 concurrent admin | p95 < 1s |
| Peak | 50 concurrent (month-end) | p95 < 2s, no 504 |
| Export CSV | 5 parallel | queue or 429 rate limit |

---

## 12. 테스트·모니터링

### 12.1 Unit Tests (API)

```php
// tests/Admin/StatsOverviewTest.php
public function test_overview_returns_four_kpis(): void
public function test_operator_can_read_overview(): void
public function test_agent_role_forbidden(): void
public function test_invalid_period_returns_422(): void
```

### 12.2 Integration Tests (PHP Dashboard)

| Test | Assertion |
|------|-----------|
| index.php loads | 200, 4 kpi-card elements |
| dashboard.js fetch | mock API, KPI values update DOM |
| operator no export | #btn-export-csv absent |
| chart empty data | empty state message shown |

### 12.3 E2E (Playwright — V2.0)

```typescript
test('dashboard shows KPI after login as admin', async ({ page }) => {
  await loginAs(page, 'admin');
  await page.goto('/admin/index.php');
  await expect(page.locator('.kpi-value').first()).not.toHaveText('—');
});
```

### 12.4 Production Monitoring

| Alert | Condition |
|-------|-----------|
| stats_api_slow | p95 > 2s for 5min |
| stats_api_error | 5xx rate > 1% |
| dashboard_failover_spike | > 10 failover/hour |

---

## 13. 부록

### 13.1 OpenAPI Snippet (overview)

```yaml
/api/v1/admin/stats/overview:
  get:
    tags: [Admin Stats]
    summary: Dashboard KPI overview
    security: [{ bearerAuth: [] }]
    parameters:
      - name: period_start
        in: query
        schema: { type: string, format: date-time }
      - name: period_end
        in: query
        schema: { type: string, format: date-time }
    responses:
      '200':
        description: KPI pack
      '403':
        description: Forbidden
```

### 13.2 Related Documents

- [01_관리자화면_UIUX_설계.md](./01_관리자화면_UIUX_설계.md)
- [03_Admin_모듈_구현명세.md](./03_Admin_모듈_구현명세.md)
- [04_Admin_API_및_권한_명세.md](./04_Admin_API_및_권한_명세.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)

### 13.3 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 6 초판 — KPI, charts, stats API, hybrid path |

---

*End of document*
