# PLUS톡 관리자 React Mobile First 전면 개편 — 작업지시서

**상태:** 📋 문서 only — **코드 구현 금지** (본 문서 승인 후 Phase 1부터 순차 구현)  
**작성일:** 2026-07-23  
**우선순위:** 긴급  
**코드 루트:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www\`  
**문서 SSOT:** `E:\000000_www.LG15441644\www\`  
**대상 URL:** https://plustok.mycafe24.com  
**관리자 진입 (React):** `/frontend/#/` (HashRouter)  
**공개 랜딩:** `/` → `index.php` (본 작업 범위 **외**)

> **핵심 clarification:** 실제 관리자 대시보드는 **React SPA** (`frontend/`) 입니다. PHP `/admin/dashboard.php` 는 **레거시 병행 경로**이며, 본 작업은 PHP CSS 패치가 아니라 **`frontend/src/` Mobile First 전면 재설계** 입니다.

---

## §0. SSOT 교차 참조 (구현 전 필독)

| STEP | 문서 | 경로 (docs repo) | 본 작업과의 관계 |
|------|------|------------------|------------------|
| 0 | INDEX | `INDEX.md` | 프로젝트 네비게이션 |
| 0 | PROJECT MASTER | `00_PROJECT_MASTER.md` | Admin 역할·Dashboard 모듈 |
| 1 | UI/UX Index | `02_UIUX/_UIUX_INDEX.md` | 디자인 토큰 진입 |
| 1 | UI Components | `02_UIUX/UI_COMPONENTS_GUIDE.md` | Pretendard, spacing, badge, button |
| 1 | 관리자 화면 stub | `02_UIUX/02_관리자화면.fig.md` | → 07_ADMIN 링크 |
| 5 | Frontend Index | `06_FRONTEND/_FRONTEND_INDEX.md` | React 아키텍처·ChatScreen 패턴 |
| 5 | Frontend 아키텍처 | `06_FRONTEND/01_Frontend_아키텍처.md` | Vite, auth, api client |
| 5 | ChatScreen 가이드 | `06_FRONTEND/04_ChatScreen_통합_구현가이드.md` | **모바일 탭·breakpoint 참조** |
| 6 | Admin Index | `07_ADMIN/_ADMIN_INDEX.md` | Admin 모듈 SSOT |
| 6 | 관리자 대시보드 | `07_ADMIN/01_관리자대시보드.md` | 5 Blocks KPI·API |
| 6 | 관리자 UI/UX | `07_ADMIN/01_관리자화면_UIUX_설계.md` | IA·RBAC·화면 ASCII |
| 6 | Admin API | `07_ADMIN/04_Admin_API_및_권한_명세.md` | REST·JWT·권한 |
| 9 | 테스트 시나리오 | `09_DEVELOPMENT/02_테스트시나리오.md` | 회귀·E2E |
| — | Premium UI (PHP) | `www/_작업지시서_Premium_UIUX_v2_리뉴얼.md` | **PHP Admin 전용** — React와 혼동 금지 |

**충돌 시 우선순위:** 본 작업지시서(React Mobile First) > STEP 6 PHP PC-first ASCII > Premium PHP CSS.

**Cursor Rules:** `.cursor/rules/plustok-enterprise.mdc` — `ai_call()` 단일 진입, API 키 하드코딩 금지.

---

## §A. 코드베이스 경로 맵 (실측 vs 목표)

### A.1 URL · 라우팅 매트릭스

| 역할 | 프로덕션 URL | 구현 | 본 작업 |
|------|-------------|------|---------|
| 공개 랜딩 | `https://plustok.mycafe24.com/` | `index.php` | Out of Scope |
| React SPA shell | `/frontend/` · `/frontend/index.html` | Vite build | 유지 |
| React Hash 라우트 | `/frontend/#/login` | `HashRouter` | 유지 |
| **Admin Dashboard (목표 SSOT)** | `/frontend/#/admin/dashboard` | `AdminDashboardPage.tsx` | **Phase 1 Mobile First** |
| Agent Chat | `/frontend/#/chat` | `ChatScreen.tsx` | 기존 유지 (Admin과 공유 auth) |
| PHP Admin (레거시) | `/admin/dashboard.php` | PHP + `includes/header.php` | **별도 트랙** — CSS만 Premium v2 |
| PHP 상담 목록/상세 | `/admin/consults/` · `view.php` | PHP CRM | Phase 3~4 React **마이그레이션 대상** |

> **로그인 리다이렉트 버그 (Phase 1 필수):** `frontend/src/features/auth/AuthProvider.tsx` L63–82 — admin/operator 로그인 후 `window.location.href = '/admin/dashboard.php'` 로 PHP로 보냄. **→ `/frontend/#/admin/dashboard` 로 수정**해야 React Admin SSOT와 일치.

### A.2 React `frontend/src/` — 현재 (실측 2026-07-23)

```
frontend/src/
├── App.tsx                          # Routes: login, chat, admin/dashboard only
├── main.tsx                         # HashRouter, QueryClient, AuthProvider
├── index.css                        # Tailwind + Pretendard (minimal)
├── pages/
│   ├── AdminDashboardPage.tsx       # 인라인 header (AppLayout 없음)
│   ├── ChatScreen.tsx               # re-export → components/Chat
│   ├── LoginPage.tsx
│   └── …auth pages
├── components/
│   ├── Admin/
│   │   └── AdminDashboard.tsx       # 6 sections (5 Block + LiveMonitor)
│   └── Chat/                        # Agent chat (mobile tabs 패턴 존재)
├── features/auth/
│   ├── AuthProvider.tsx
│   ├── AdminRoute.tsx               # admin|operator only
│   └── ProtectedRoute.tsx
├── hooks/
│   ├── useAdminStats.ts             # React Query 60s polling
│   ├── useAdminSocket.ts            # room:update, ai:update, read:update
│   └── useSocket.tsx
├── services/
│   ├── admin.service.ts             # /admin/stats/*, /admin/monitor/rooms
│   └── api.client.ts
└── types/
    └── admin.types.ts
```

**없음 (목표에서 신규 생성):**

| 목표 경로 | 역할 |
|-----------|------|
| `layouts/AppLayout.tsx` | Admin 공통 shell (header + outlet + bottom nav) |
| `layouts/AdminLayout.tsx` | RBAC nav items, safe-area |
| `components/Admin/AdminHeader.tsx` | 모바일 compact header |
| `components/Admin/BottomNav.tsx` | xs~md 하단 5탭 |
| `components/Admin/KpiCard.tsx` | dashboard KPI (AdminDashboard에서 분리) |
| `components/Admin/DataTable.tsx` | card-table hybrid |
| `components/Admin/FilterBar.tsx` | consult list filters |
| `components/Admin/StatusBadge.tsx` | consult/agent status |
| `pages/ConsultListPage.tsx` | 상담 목록 |
| `pages/ConsultDetailPage.tsx` | 상담 상세 + chat |
| `pages/CustomerListPage.tsx` | 고객 목록 (Phase 5) |
| `pages/SitesPage.tsx` | 사이트 관리 (Phase 5) |
| `hooks/useConsults.ts` | consult list/detail queries |
| `services/consult.service.ts` | GET /admin/consults 등 |
| `styles/admin-tokens.css` 또는 `index.css` @layer | design tokens |

### A.3 PHP Admin (레거시 — 참고·병행, 본 작업 1차 타깃 아님)

| 역할 | 실제 경로 |
|------|-----------|
| 공통 헤더 | `includes/header.php` (8 menu, hamburger @992px) |
| Admin CSS | `assets/css/admin.css`, `plustok-tokens-v2.css`, `plustok-premium-v2.css` |
| 대시보드 | `admin/dashboard.php` |
| 상담 | `admin/consults/index.php`, `admin/consults/view.php` (+ Socket.io messaging) |
| 고객 | `admin/customers/index.php`, `view.php` |
| 사이트 | `admin/sites/index.php` |
| 설정 | `admin/settings/index.php`, `ai.php` |

### A.4 API (이미 구현 — React에서 소비)

| Endpoint | Router | React service |
|----------|--------|---------------|
| `GET /api/v1/admin/stats/overview` | `api/v1/router.php` | `adminService.overview()` |
| `GET /api/v1/admin/stats/sentiment` | ✓ | `adminService.sentiment()` |
| `GET /api/v1/admin/stats/funnel` | ✓ | `adminService.funnel()` |
| `GET /api/v1/admin/stats/agents` | ✓ | `adminService.agents()` |
| `GET /api/v1/admin/stats/trends` | ✓ | `adminService.trends()` |
| `GET /api/v1/admin/monitor/rooms` | ✓ | `adminService.monitorRooms()` |
| `GET /api/v1/admin/consults` | ✓ | **Phase 3** `consult.service` 신규 |

### A.5 빌드 · 배포

```bash
cd frontend
npm run build    # tsc -b && vite build → frontend/dist/
```

| 항목 | 값 |
|------|-----|
| `vite.config.ts` | `VITE_BASE_PATH=/frontend/` (Cafe24) |
| 산출물 | `frontend/dist/index.html`, `frontend/dist/assets/*` |
| 업로드 | Cafe24 FTP → `www/frontend/` (dist 내용) |
| `.htaccess` | SPA fallback (기존 `_작업지시서_Frontend404해결.md` 참조) |

---

## §1. 문서 개요

| 항목 | 내용 |
|------|------|
| 프로젝트명 | PLUS톡 통합 CRM (ACEP V3.0) |
| 대상 서비스 | https://plustok.mycafe24.com/ |
| **관리자 경로 (SSOT)** | **`/frontend/#/`** — HashRouter SPA |
| 개발 방식 | **Mobile First** |
| 기준 화면 | **360px**부터 설계 → 768px 태블릿 → 1024px+ PC |
| 우선순위 | 긴급 |
| 적용 범위 | React Admin: Dashboard, 상담, 고객, 사이트, 설정 shell |
| 목표 | 모바일 글자 넘침, 카드 깨짐, 표 잘림, 버튼 겹침, 메뉴 불편 **근본 해결** |

**Out of Scope (본 작업):**

- `index.php` 랜딩 Hero 리뉴얼 (Premium PHP 작업지시서)
- PHP `includes/header.php` CSS-only 패치 (별도 트랙)
- DB 스키마 변경, `ai_call()` 변경, chat-server 프로토콜 변경
- Customer embed widget (`embed/`)

---

## §2. 핵심 원칙

이번 작업은 **기존 PC 화면을 모바일 크기로 줄이는 작업이 아니다.**

```text
모바일 화면 설계
→ 모바일 기능 우선순위 정리
→ 태블릿 확장
→ PC 다단 레이아웃 확장
```

모든 화면은 다음 기준을 지킨다.

1. **가로 스크롤을 원칙적으로 만들지 않는다.** (표·긴 코드 블록은 카드화 또는 내부 스크롤)
2. **모바일에서 표를 억지로 축소하지 않는다.** `<768px` → card-row 패턴.
3. **긴 텍스트는** `break-words`, `line-clamp-2`, `truncate` 중 맥락에 맞게 적용.
4. **자주 사용하는 기능은 엄지 존** — 하단 네비·FAB·sticky CTA.
5. **터치 타깃 최소 44×44px** (Apple HIG / Material 48dp 중간값 **48px** 권장).
6. **safe-area-inset** — iOS notch·홈 인디케이터 (`pb-safe`, `env(safe-area-inset-bottom)`).
7. **정보 우선순위** — 모바일에서 KPI 4개 → 2×2, 차트·테이블은 접기/탭.
8. **데이터 레이어 분리** — 페이지에서 `fetch` 금지; hooks + services only (ChatScreen convention).
9. **RBAC** — `AdminRoute` + 메뉴 hidden (operator read-only).
10. **PHP Admin과 기능 parity** — CRM 상담·AI 버튼은 Phase 4까지 PHP `view.php` 기능 목록 대조.

---

## §3. 브레이크포인트

Tailwind default + Admin 전용 naming (ChatScreen §4와 정합):

| Token | Width | Admin layout |
|-------|-------|----------------|
| **xs** | `<640px` | 1-col, bottom nav, stacked filters |
| **sm** | `640–767px` | 2-col KPI 가능 |
| **md** | `768–1023px` | 2-col dashboard, side drawer nav optional |
| **lg** | `1024–1279px` | sidebar or top nav, 2–3 col |
| **xl** | `≥1280px` | full dashboard grid (4 KPI, 2-col sections) |
| **2xl** | `≥1440px` | SSOT 07_ADMIN PC reference |

**설계 기준 viewport (DevTools):**

- Samsung Galaxy S8+ **360×740** (primary)
- iPhone 14 **390×844**
- iPad **768×1024**
- Desktop **1280×800**, **1440×900**

`tailwind.config.js` extend (Phase 1):

```js
screens: {
  xs: '360px',  // optional explicit
  // sm/md/lg/xl/2xl — Tailwind defaults
},
spacing: {
  'safe-bottom': 'env(safe-area-inset-bottom, 0px)',
},
```

---

## §4. 레이아웃 시스템

### 4.1 App shell (목표)

```
┌─────────────────────────────┐  xs~md
│ AdminHeader (56px sticky)   │
├─────────────────────────────┤
│                             │
│   <Outlet /> scroll         │
│   pb-[72px+safe]            │
│                             │
├─────────────────────────────┤
│ BottomNav (56px + safe)     │  md 미만 only
└─────────────────────────────┘

┌──────┬──────────────────────┐  lg+
│ Side │ Header + Outlet      │
│ 240  │ (no bottom nav)      │
└──────┴──────────────────────┘
```

### 4.2 파일

| 파일 | 책임 |
|------|------|
| `layouts/AppLayout.tsx` | min-h-dvh, flex col, outlet padding |
| `layouts/AdminLayout.tsx` | RBAC nav, breakpoint 분기 |
| `App.tsx` | nested routes under `/admin/*` |

### 4.3 Route tree (목표)

```tsx
<Route path="/admin" element={<AdminRoute><AdminLayout /></AdminRoute>}>
  <Route index element={<Navigate to="dashboard" replace />} />
  <Route path="dashboard" element={<AdminDashboardPage />} />
  <Route path="consults" element={<ConsultListPage />} />
  <Route path="consults/:id" element={<ConsultDetailPage />} />
  <Route path="customers" element={<CustomerListPage />} />   {/* Phase 5 */}
  <Route path="sites" element={<SitesPage />} />               {/* Phase 5 */}
  <Route path="settings" element={<SettingsPage />} />         {/* Phase 6 */}
</Route>
```

### 4.4 Content max-width

| Breakpoint | `main` padding | max-width |
|------------|----------------|-----------|
| xs | `px-4` (16px) | 100% |
| sm | `px-5` (20px) | 100% |
| md+ | `px-6` (24px) | `max-w-7xl` mx-auto |

---

## §5. 헤더 (AdminHeader)

### 5.1 모바일 (`<md`)

```
┌────────────────────────────────────┐
│ [≡]  PlusTok CRM    [🔔] [👤]      │
│      Dashboard · 홍길동            │  subtitle optional
└────────────────────────────────────┘
```

- 높이 **56px**, `sticky top-0 z-40`, `bg-white/95 backdrop-blur border-b`
- 햄버거 → **drawer** (Phase 2) 또는 bottom nav로 대체 (Phase 1은 bottom nav 우선)
- 알림 bell — Phase 6 placeholder

### 5.2 PC (`≥lg`)

- 좌: 로고 + breadcrumb (`대시보드 > 실시간`)
- 우: `상담 화면` link → `/chat`, 로그아웃
- **현재 코드:** `AdminDashboardPage.tsx` L13–28 인라인 header → **`AdminHeader.tsx`로 추출**

### 5.3 PHP 대비

| PHP `header.php` | React AdminHeader |
|------------------|-------------------|
| `.topbar` + `.nav` horizontal | BottomNav + drawer |
| 8 menu items | 5 primary + overflow "더보기" |

---

## §6. 하단 네비게이션 (BottomNav)

**표시:** `viewport < 1024px` (`lg:hidden`)

| Tab | Icon (lucide) | Route | RBAC |
|-----|---------------|-------|------|
| 홈 | `LayoutDashboard` | `/admin/dashboard` | all admin |
| 상담 | `MessageSquare` | `/admin/consults` | all admin |
| 고객 | `Users` | `/admin/customers` | admin+ (Phase 5) |
| 통계 | `BarChart3` | `/admin/dashboard#trends` or stats | all |
| 더보기 | `Menu` | drawer: sites, settings, chat | role-based |

- 높이 **56px** + `padding-bottom: env(safe-area-inset-bottom)`
- active: `text-indigo-600` + label bold
- **`frontend/src/components/Admin/BottomNav.tsx`** 신규

---

## §7. 대시보드 (Dashboard)

**SSOT:** `07_ADMIN/01_관리자대시보드.md` §4 — 5 Blocks  
**현재:** `components/Admin/AdminDashboard.tsx` (6 section incl. LiveMonitor)

### 7.1 모바일 레이아웃

| Section | xs layout |
|---------|-----------|
| 실시간 KPI | `grid grid-cols-2 gap-3` (4 cards) |
| 상담원 현황 | card-table (not `<table>`) |
| AI 성과 | stacked 2 panels |
| 고객 분석 | funnel bar full-width |
| 시간대 추이 | spark bar `h-24`, horizontal scroll **금지** — 12h window default |
| Live Monitor | list cards, tap → consult detail |

### 7.2 PC 레이아웃

- KPI: `lg:grid-cols-4`
- Agent + AI: `lg:grid-cols-2`
- 기존 `max-w-7xl` 유지

### 7.3 실시간

- Polling: `useAdminStats.ts` (60s / 30s monitor)
- WS: `useAdminSocket.ts` — invalidate on `room:update`, `ai:update`, `read:update`
- **변경 없음** — UI만 Mobile First

### 7.4 KPI 카드 spec

| Property | Mobile | Desktop |
|----------|--------|---------|
| padding | 12–16px | 16–20px |
| value font | 24px | 30px |
| delta | 12px, single line | same |

---

## §8. 상담 목록 · 상세 (Consult List / Detail)

### 8.1 목록 — PHP 참조

- **PHP:** `admin/consults/index.php` — filters: site, status, manager, priority, date, q
- **React 목표:** `pages/ConsultListPage.tsx` + `FilterBar` + card list

**모바일 list item (card):**

```
┌─────────────────────────────────┐
│ C202607220017    [진행중]       │
│ 김** · LG15441644               │
│ 인터넷 · score 82 · 2h ago      │
└─────────────────────────────────┘
```

### 8.2 상세 — PHP 참조

- **PHP:** `admin/consults/view.php` — CRM fields + phone mockup chat + Socket.io
- **React 목표:** `pages/ConsultDetailPage.tsx`
  - 상단: consult meta sticky
  - 중단: **reuse** `MessageList` + `MessageInput` from `components/Chat/panels/` (roomId from consult)
  - 하단: AI actions (summary/reply) — Phase 4

### 8.3 API

- `GET /api/v1/admin/consults` — list (router.php L428)
- Detail: consult id + linked `chat_rooms.legacy_consult_id` (기존 CRM 브릿지)

---

## §9. 테이블 (Tables)

### 9.1 원칙

| Viewport | Pattern |
|----------|---------|
| `<md` | **Card rows** — each field = label + value stack |
| `≥md` | `<table>` allowed with `overflow-x-auto` **only if** >6 columns |
| `≥lg` | full table |

### 9.2 공통 컴ponent

`components/Admin/DataTable.tsx`:

- props: `columns`, `rows`, `mobileCardRender`
- Agent section in `AdminDashboard.tsx` L69–96 — **1차 리팩터 대상**

### 9.3 card-row CSS pattern

```tsx
<div className="rounded-xl border bg-white p-4 md:hidden">
  {columns.map(col => (
    <div key={col.key} className="flex justify-between py-1 text-sm">
      <span className="text-slate-500">{col.label}</span>
      <span className="font-medium">{row[col.key]}</span>
    </div>
  ))}
</div>
```

---

## §10. 채팅 (Consult Detail Chat)

### 10.1 재사용 (DRY)

| 기존 | 경로 | Admin consult detail |
|------|------|----------------------|
| MessageList | `components/Chat/panels/MessageList.tsx` | wrap in mobile shell |
| MessageInput | `components/Chat/panels/MessageInput.tsx` | admin send enabled |
| useMessages | `hooks/useMessages.ts` | same roomId |
| useSocket | `hooks/useSocket.tsx` | room:join / message:send |

### 10.2 모바일 chat shell

- full viewport minus header: `flex flex-col h-[calc(100dvh-56px-56px)]` (header + bottom nav)
- consult detail **전용 route**에서 BottomNav hidden → back button in header

### 10.3 PHP parity checklist

- [ ] `room:join` on mount
- [ ] `message:send` / receive
- [ ] connection status banner (`ConnectionBanner.tsx`)
- [ ] legacy consult ↔ room mapping (`legacy_consult_id`)

---

## §11. 필터 (Filters)

### 11.1 Consult list filters

PHP `admin/consults/index.php` L28–36 동일 필드:

| Field | UI mobile | UI desktop |
|-------|-----------|------------|
| site | bottom sheet select | inline select |
| status | chip group | select |
| manager | searchable select | select |
| priority | chips | select |
| from/to | native date input | date range |
| q | sticky search bar | inline input |

### 11.2 FilterBar behavior

- mobile: **"필터" 버튼** → drawer (applied count badge)
- desktop: horizontal wrap `gap-2`
- URL sync: `useSearchParams` — shareable links

---

## §12. 타이포그래피

**SSOT:** `UI_COMPONENTS_GUIDE.md` + Pretendard (이미 `index.css`)

| Element | Mobile | Desktop | Tailwind |
|---------|--------|---------|----------|
| Page title | 18px/600 | 20px/600 | `text-lg font-semibold` |
| Section title | 16px/600 | 18px/600 | `text-base font-semibold` |
| Body | 14px/400 | 14px/400 | `text-sm` |
| Caption | 12px/400 | 12px/400 | `text-xs text-slate-500` |
| KPI value | 24–28px/700 | 30–32px/700 | `text-2xl font-bold` |

- `font-family: 'Pretendard', system-ui, sans-serif` — CDN link in `frontend/index.html` (Phase 1)
- `line-height`: body 1.5, titles 1.35

---

## §13. 간격 (Spacing)

| Token | Value | Usage |
|-------|-------|-------|
| page gutter xs | **16px** | `px-4` |
| page gutter sm+ | **20px** | `px-5` |
| page gutter md+ | **24px** | `px-6` |
| section gap | **24px** | `space-y-6` |
| card gap mobile | **12px** | `gap-3` |
| card gap desktop | **16–24px** | `gap-4` ~ `gap-6` |
| bottom nav offset | **72px+safe** | `pb-[calc(4.5rem+env(safe-area-inset-bottom))]` |

---

## §14. 버튼

| Variant | Height | Radius | Mobile |
|---------|--------|--------|--------|
| primary | **48px** min | 12px | full-width in forms |
| secondary | 44px | 12px | inline |
| ghost/icon | 44×44 | 8px | header actions |

```tsx
// Primary — tailwind
className="min-h-12 w-full rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white active:scale-[0.98] md:w-auto"
```

- **PHP Premium 52px** — React Admin은 **48px** (bottom nav와 합 104px — viewport 고려)
- danger/success: `UI_COMPONENTS_GUIDE` semantic colors

---

## §15. 폼 (Forms)

- mobile: **1 column** — `grid grid-cols-1 gap-4`
- label above input (`text-sm font-medium`)
- input min-height **44px**, `rounded-lg border`
- error text `text-xs text-red-600` below field
- submit sticky bottom bar on mobile (consult memo, settings Phase 6)

---

## §16. 차트 (Charts)

**현재:** CSS bar chart only (`HourlyTrendsSection` — div bars)

| Phase | Chart |
|-------|-------|
| 1–2 | CSS bars + funnel strip (keep, mobile height `h-24`) |
| 3+ | optional Chart.js / Recharts — **번들 크기 검토 후** |

모바일:

- legend below chart
- tap tooltip (Phase 3)
- **no horizontal chart scroll** — aggregate to 12 points on xs

---

## §17. 상태 (Status badges)

**SSOT:** `UI_COMPONENTS_GUIDE` Status Badge Colors

`components/Admin/StatusBadge.tsx`:

| Status | Keys (consult) | Colors |
|--------|----------------|--------|
| new | NEW, 신규 | amber |
| active | ACTIVE, 진행중 | green |
| waiting | WAITING, 대기 | blue |
| closed | CLOSED, 종료 | gray |
| urgent priority | URGENT | red border |

Agent status: online / offline / away — dot + label

---

## §18. 사이트 (Sites)

- **PHP:** `admin/sites/index.php`
- **React:** `pages/SitesPage.tsx` (Phase 5)
- mobile: site cards (name, code, consult count)
- API: CRM sites endpoint or admin extension (implement when Phase 5 starts — check `04_Admin_API_및_권한_명세.md`)

---

## §19. 컴포넌트 구조 (Component Structure)

```
frontend/src/
├── layouts/
│   ├── AppLayout.tsx
│   └── AdminLayout.tsx
├── pages/                    # route entry, data loaders minimal
├── components/
│   └── Admin/
│       ├── AdminHeader.tsx
│       ├── BottomNav.tsx
│       ├── AdminDashboard.tsx + sections/
│       ├── KpiCard.tsx
│       ├── DataTable.tsx
│       ├── FilterBar.tsx
│       ├── StatusBadge.tsx
│       ├── ConsultList.tsx
│       └── ConsultDetailChat.tsx
├── hooks/                    # React Query
├── services/                 # apiFetch only
└── types/
```

**규칙:**

- Pages → compose components; **no business logic >20 lines**
- Sections export named (`RealtimeSection`) — already in `AdminDashboard.tsx`
- Shared UI with Chat: import from `@/components/Chat/...`

---

## §20. 화면 목록 (Screen List)

| # | Screen | React route | PHP legacy | Phase |
|---|--------|-------------|------------|-------|
| 1 | Login | `/login` | redirect to frontend | ✅ exists |
| 2 | Dashboard | `/admin/dashboard` | `/admin/dashboard.php` | 1 |
| 3 | Consult list | `/admin/consults` | `/admin/consults/` | 3 |
| 4 | Consult detail | `/admin/consults/:id` | `view.php` | 4 |
| 5 | Customer list | `/admin/customers` | `/admin/customers/` | 5 |
| 6 | Sites | `/admin/sites` | `/admin/sites/` | 5 |
| 7 | Agent chat | `/chat` | — | ✅ exists |
| 8 | Settings | `/admin/settings` | `/admin/settings/` | 6 |
| 9 | Live monitor | `/admin/monitor` | (PHP optional) | 6 |

---

## §21. Phase 1 — Layout · Tokens · Dashboard mobile

**목표:** shell + dashboard mobile usable + login redirect fix

### Checklist

- [x] `layouts/AppLayout.tsx`, `AdminLayout.tsx` 생성
- [x] `AdminHeader.tsx`, `BottomNav.tsx` 생성
- [x] `App.tsx` — `/admin` nested routes
- [x] `AuthProvider.tsx` — admin login → `/frontend/#/admin/dashboard` (PHP redirect 제거)
- [x] `index.css` / `tailwind.config.js` — tokens, safe-area
- [x] `AdminDashboardPage.tsx` — layout only, header 제거
- [x] `AdminDashboard.tsx` — KPI 2×2 mobile, sections stack
- [x] `AgentSection` — card-table on mobile
- [x] `frontend/index.html` — Pretendard CDN
- [x] `npm run build` 성공
- [ ] DevTools 360px — no horizontal scroll on dashboard

---

## §22. Phase 2 — Navigation · Drawer · Tablet

### Checklist

- [x] Hamburger drawer (md+) for overflow menu
- [x] BottomNav active states + RBAC hide
- [x] Tablet 768px: 2-col KPI, optional side nav
- [x] Link `/chat` from header
- [x] Loading / error / empty states unified
- [x] `AdminDashboard.test.tsx` — mobile snapshot assertions

---

## §23. Phase 3 — Consult List

### Checklist

- [x] `consult.service.ts`, `useConsults.ts`
- [x] `ConsultListPage.tsx`, `ConsultList.tsx` → `components/consults/*`
- [x] `FilterBar.tsx` + URL params → `ConsultFilters.tsx`
- [x] Card list mobile, table md+
- [x] Pagination or infinite scroll
- [x] Tap row → `/admin/consults/:id`

---

## §24. Phase 4 — Consult Detail + Chat

### Checklist

- [x] `ConsultDetailPage.tsx`
- [x] Load consult + resolve `roomId` via `legacy_consult_id`
- [x] Embed MessageList / MessageInput
- [x] Back nav, hide BottomNav on detail
- [ ] AI summary/reply buttons (API parity PHP)
- [ ] E2E: send message admin → customer widget

---

## §25. Phase 5 — Customers · Sites

### Checklist

- [ ] `CustomerListPage.tsx` — masked PII list
- [ ] `SitesPage.tsx` — site cards
- [ ] BottomNav "고객" tab enable
- [ ] RBAC: operator read-only

---

## §26. Phase 6 — Settings · Monitor · Polish

### Checklist

- [ ] Settings shell (AI settings link or embed)
- [ ] Live monitor route (optional)
- [ ] Dark mode tokens foundation (`prefers-color-scheme`)
- [ ] Performance: lazy routes `React.lazy`
- [ ] A11y: focus ring, aria labels on nav
- [ ] Production FTP deploy + smoke test

---

## §27. 금지 사항 (Forbidden)

1. **PHP Admin CSS만 수정**하고 React Admin mobile 문제를 "해결했다"고 보고 금지
2. **`admin/dashboard.php`를 SSOT**로 두고 React를 secondary 취급 금지
3. Page/component에서 **raw `fetch`** — `services/` + React Query only
4. **API keys / secrets** in frontend source
5. **`ai_call()` bypass** — AI features remain PHP
6. **Horizontal scroll** on main content (의도적 table wrapper 제외)
7. **`<table>` on xs** without card alternative
8. **Breaking `/chat`** agent workflow — regression test mandatory
9. **New Node backend** for admin — PHP router SSOT
10. **Implement all phases at once** — Phase gate checklist required

---

## §28. 테스트 데이터 (Test Data)

| Entity | Source | Notes |
|--------|--------|-------|
| Admin user | `agents` table, role `admin` | `/frontend/#/login` |
| Operator | role `operator` | read-only menus |
| Consult | `consults` + `legacy_consult_id` | C202607220017 pattern |
| Chat room | `chat_rooms.legacy_consult_id = consult.id` | messaging E2E |
| Site | `sites.site_code = lg15441644` | filter test |
| KPI data | seed or production read-only | stats API |

**SQL verify (HeidiSQL):**

```sql
SELECT id, legacy_consult_id, status FROM chat_rooms ORDER BY created_at DESC LIMIT 5;
SELECT id, consult_no, status FROM consults ORDER BY id DESC LIMIT 5;
```

---

## §29. 브라우저 테스트 (Browser Test)

### Devices (Chrome DevTools)

| Device | Width | Pass criteria |
|--------|-------|---------------|
| Galaxy S8+ | 360 | No overflow-x, bottom nav tappable |
| iPhone 14 | 390 | safe-area padding |
| iPad | 768 | 2-col layout |
| Desktop | 1280+ | 4 KPI, no bottom nav |

### URLs

1. `https://plustok.mycafe24.com/frontend/#/login`
2. `https://plustok.mycafe24.com/frontend/#/admin/dashboard`
3. `https://plustok.mycafe24.com/frontend/#/admin/consults` (Phase 3+)

### Manual script

1. Login as admin → lands on **React** dashboard (not PHP)
2. Scroll dashboard — no sideways scroll
3. Tap bottom nav tabs
4. Open consult → send message (Phase 4)
5. Console: **0 errors**
6. Network: `/api/v1/admin/stats/*` 200

---

## §30. 완료 기준 (Completion Criteria)

| ID | Criteria |
|----|----------|
| DONE-01 | Admin primary path = `/frontend/#/admin/*` for admin/operator login |
| DONE-02 | 360px dashboard usable — KPI, agent list, monitor visible without pinch-zoom |
| DONE-03 | Consult list + detail parity with PHP core fields (Phase 3–4) |
| DONE-04 | Chat send/receive on consult detail (Phase 4) |
| DONE-05 | `npm run test` — AdminDashboard tests pass |
| DONE-06 | `npm run build` → dist uploaded, production smoke pass |
| DONE-07 | PHP admin remains functional (no regression) for legacy bookmarks |
| DONE-08 | Documentation: 본 작업지시서 Phase checklists all checked |

---

## §31. 산출물 (Deliverables)

| # | Deliverable | Path |
|---|-------------|------|
| 1 | 본 작업지시서 | `www/_작업지시서_관리자_React_MobileFirst_전면개편.md` |
| 2 | Layout components | `frontend/src/layouts/*` |
| 3 | Admin UI components | `frontend/src/components/Admin/*` |
| 4 | Admin pages | `frontend/src/pages/Admin*.tsx`, `Consult*.tsx` |
| 5 | Hooks/services | `useConsults.ts`, `consult.service.ts` |
| 6 | Updated routes | `frontend/src/App.tsx` |
| 7 | Auth redirect fix | `AuthProvider.tsx` |
| 8 | Built assets | `frontend/dist/` |
| 9 | Test updates | `frontend/src/test/Admin*.test.tsx` |

---

## §32. 최종 지시 (Final Directive)

1. **이 문서만**으로 Cursor / Codex / Antigravity가 Phase 1부터 구현 가능해야 한다.
2. **먼저 Phase 1** — layout + dashboard mobile + login redirect. **코드 착수 전 본 문서 PR/승인.**
3. React Admin = **Mobile First SSOT**; PHP Admin = legacy parallel until full migration.
4. 각 Phase 완료 시: `npm run build` → Cafe24 `frontend/dist` 업로드 → §29 browser test.
5. **Do NOT** implement until assigned phase — document-only unless tiny scaffold note below.

### Scaffold note (Phase 1 착수 시 1-line)

```bash
cd frontend && npm run dev
# Dev: http://localhost:5173/#/admin/dashboard (after AuthProvider fix + AdminLayout)
```

---

## 부록 A. PHP vs React Admin — 마이그레이션 관계

| 기능 | PHP (legacy) | React (target) | Phase |
|------|--------------|----------------|-------|
| Login | `/admin/` managers | `/frontend/#/login` agents | ✅ |
| Dashboard KPI | `dashboard.php` | `AdminDashboard.tsx` | 1–2 |
| Consult list | `consults/index.php` | `ConsultListPage` | 3 |
| Consult detail + chat | `consults/view.php` | `ConsultDetailPage` | 4 |
| Customers | `customers/index.php` | `CustomerListPage` | 5 |
| Sites | `sites/index.php` | `SitesPage` | 5 |
| AI settings | `settings/ai.php` | Settings or iframe Phase 6 | 6 |
| SSO session | `admin/sso.php` | Keep for PHP bookmark compatibility | — |

**Dual-run policy:** Phase 1–4期间 PHP URLs still work; marketing/login should prefer React path.

---

## 부록 B. Phase 1–4 우선 터치 파일 (실측 경로)

| Phase | Files (absolute under PlusTok www) |
|-------|-------------------------------------|
| **1** | `frontend/src/layouts/AppLayout.tsx` **(new)** |
| **1** | `frontend/src/layouts/AdminLayout.tsx` **(new)** |
| **1** | `frontend/src/components/Admin/AdminHeader.tsx` **(new)** |
| **1** | `frontend/src/components/Admin/BottomNav.tsx` **(new)** |
| **1** | `frontend/src/App.tsx` |
| **1** | `frontend/src/pages/AdminDashboardPage.tsx` |
| **1** | `frontend/src/components/Admin/AdminDashboard.tsx` |
| **1** | `frontend/src/features/auth/AuthProvider.tsx` |
| **1** | `frontend/src/index.css` |
| **1** | `frontend/tailwind.config.js` |
| **1** | `frontend/index.html` |
| **2** | `frontend/src/test/AdminDashboard.test.tsx` |
| **3** | `frontend/src/pages/ConsultListPage.tsx` **(new)** |
| **3** | `frontend/src/components/Admin/ConsultList.tsx` **(new)** |
| **3** | `frontend/src/components/Admin/FilterBar.tsx` **(new)** |
| **3** | `frontend/src/hooks/useConsults.ts` **(new)** |
| **3** | `frontend/src/services/consult.service.ts` **(new)** |
| **4** | `frontend/src/pages/ConsultDetailPage.tsx` **(new)** |
| **4** | `frontend/src/components/Admin/ConsultDetailChat.tsx` **(new)** |
| **4** | `frontend/src/components/Chat/panels/MessageList.tsx` (reuse) |
| **4** | `frontend/src/components/Chat/panels/MessageInput.tsx` (reuse) |
| **4** | `frontend/src/hooks/useMessages.ts` (reuse) |

---

**변경 이력**

| 날짜 | 버전 | 내용 |
|------|------|------|
| 2026-07-23 | 1.0.0 | 초안 — codebase 실측 + user spec 29 sections + §0/§A |
