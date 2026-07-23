# 작업지시서: Premium UI/UX v2.0 리뉴얼 — 2026-07-23

**상태:** **Phase 1 구현 완료** (2026-07-23) — tokens + premium CSS + header/dashboard/landing 적용. Phase 2 대기.  
**대상 URL:** `https://plustok.mycafe24.com`  
**코드베이스:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www\`  
**문서 SSOT:** `E:\000000_www.LG15441644\www\` (STEP 1 · UI/UX · Admin)

**의도:** Enterprise CRM 수준의 Premium UI/UX v2.0 — Mobile First, Responsive, Glassmorphism, Soft 3D, 가독성·직관 UX. Salesforce / HubSpot / Notion / Linear / Apple SaaS 품질을 목표로 한다. **Cursor / Codex / Claude / Gemini** 가 본 문서만으로 Phase별 구현 가능해야 한다.

---

## §0. SSOT 교차 참조 (구현 전 필독)

| STEP | 문서 | 경로 (docs repo) | 본 작업과의 관계 |
|------|------|------------------|------------------|
| 1 | UI/UX Index | `02_UIUX/_UIUX_INDEX.md` | 컴포넌트·화면 설계 진입점 |
| 1 | UI Components Guide | `02_UIUX/UI_COMPONENTS_GUIDE.md` | Pretendard, spacing, badge, button 토큰 |
| 1 | 상담채팅 화면 | `02_UIUX/01_상담채팅화면.fig.md` | consult view · popup chat 레퍼런스 |
| 6 | 관리자 UI/UX 설계 | `07_ADMIN/01_관리자화면_UIUX_설계.md` | Admin IA, 1440px, RBAC 메뉴 |
| 6 | Dashboard 구현명세 | `07_ADMIN/02_Admin_Dashboard_구현명세.md` | KPI 카드·그리드 |
| 6 | Admin Index | `07_ADMIN/_ADMIN_INDEX.md` | Admin 모듈 전체 |
| 9 | 테스트 시나리오 | `09_DEVELOPMENT/02_테스트시나리오.md` | 회귀·E2E |

> **충돌 시:** 본 작업지시서의 **v2.0 Premium 시각 스펙**(버튼 52px, glass KPI 등)이 우선. 기능·RBAC·API·채팅 E2E는 기존 SSOT·`_작업지시서_고객채팅위젯_Phase1_최종점검.md` 를 깨지 않는다.

---

## §1. 범위 (Scope)

| 영역 | URL / 경로 | 현재 구현 |
|------|------------|-----------|
| 랜딩 | `/` → `index.php` | 인라인 `<style>` (별도 CSS 없음) |
| Admin 공통 | `/admin/*` → `includes/header.php` + `footer.php` | `assets/css/admin.css` 단일 파일 |
| 대시보드 | `/admin/dashboard.php` | `.cards` auto-fill 그리드 |
| 상담 | `/admin/consults/` · `view.php` | 테이블 + view 내 phone mockup 인라인 CSS |
| 고객 | `/admin/customers/` | header 공통 |
| 사이트·상품·담당자·통계·설정 | 각 `index.php` | header 공통 |
| 고객 popup chat | `embed/chat-widget.js` · `embed/chat-frame.php` | 420×560px iframe (스펙 420×780 미달) |
| React Frontend | `/frontend/#/*` | Tailwind (`frontend/src/index.css`) — **본 Phase 1 범위 외** (PHP Admin 우선) |

**Out of Scope (본 작업):** DB/API 변경, `ai_call()`·WebSocket 프로토콜 변경, `frontend/dist` 전면 리빌드(Phase 3 PWA 제외).

---

## §2. 코드베이스 경로 맵 (실측)

### 2.1 Admin 레이아웃 · CSS

| 역할 | 실제 경로 | 비고 |
|------|-----------|------|
| 공통 헤더 (topbar + nav) | `includes/header.php` | `$menu` 8항목, `admin.css` 1줄 로드 |
| 공통 푸터 | `includes/footer.php` | `.wrap` 닫기 + V1.0 푸터 |
| **유일 Admin CSS** | `assets/css/admin.css` | 89줄, mobile `@media (max-width:600px)` 2규칙만 |
| 로그인 (레거시 폼) | `admin/index.php` | POST만 처리, GET → `/frontend/#/login` 리다이렉트; CSS는 `admin.css` |
| SSO | `admin/sso.php` | — |

> **주의:** `admin/assets/css/` 디렉터리는 **존재하지 않음**. 신규 CSS는 **`assets/css/`** (웹 루트 기준 `/assets/css/`) 에 두는 것이 기존 패턴과 일치한다.

### 2.2 Admin 페이지 (header.php 사용 — Phase 1 일괄 영향)

| `$active` 키 | 파일 |
|--------------|------|
| `dashboard` | `admin/dashboard.php` |
| `consults` | `admin/consults/index.php`, `admin/consults/view.php` |
| `customers` | `admin/customers/index.php`, `admin/customers/view.php` |
| `sites` | `admin/sites/index.php` |
| `products` | `admin/products/index.php` |
| `users` | `admin/users/index.php` |
| `stats` | `admin/stats/index.php` |
| `settings` | `admin/settings/index.php`, `admin/settings/ai.php` |
| (없음) | `admin/account.php` |

### 2.3 랜딩 · Embed · Frontend

| 역할 | 경로 |
|------|------|
| 랜딩 Hero | `index.php` (L24–86 인라인 CSS, L81–85 `@760px` breakpoint) |
| 고객 채팅 위젯 JS | `embed/chat-widget.js` (overlay CSS 인라인 문자열) |
| 채팅 iframe 본체 | `embed/chat-frame.php` |
| 데모 | `embed/demo-chat.php`, `embed/demo.php` |
| 상담 신청 embed | `embed/embed.js`, `embed/form.php` |
| React SPA | `frontend/src/index.css`, `frontend/dist/assets/*.css` |

### 2.4 consult view — phone mockup (현재)

- **파일:** `admin/consults/view.php` L198–389 인라인 `<style>`
- **클래스:** `#consult-messaging`, `.consult-chat-phone` (max-width 380px, height 480px)
- **JS:** 동일 파일 하단 Socket.io 연동 — **스타일만 변경, JS·data-room-id·emit 경로 유지**

---

## §3. 디자인 목표 (요구사항 보존 + 코드 정합)

| # | 요구사항 | 스펙 값 | 현재 vs 목표 |
|---|----------|---------|--------------|
| 1 | Dashboard responsive grid | **4 / 3 / 2 / 1 cols** (≥1280 / ≥992 / ≥768 / <768) | `auto-fill minmax(150px)` → breakpoint grid로 교체 |
| 2 | KPI cards | Glassmorphism, soft shadow, 16–20px padding | flat `#fff` card |
| 3 | Buttons | **height 52px**, **radius 16px**, gradient primary/success/danger, glow hover | `.btn` padding 10×16, radius 8px |
| 4 | Hero `/` | Responsive type, padding, **CTA 58px** height | CTA padding 15×30, h1 34→26px @760px only |
| 5 | Admin mobile first | Hamburger sidebar, card tables, 1-col forms, vertical btn groups | horizontal sticky `.nav`, table `min-width:640px` scroll |
| 6 | consult view chat | Premium phone mockup | 기본 mockup 있음 — glass·3D·typography 강화 |
| 7 | Popup chat | Desktop **420×780** floating; mobile **fullscreen** | widget 420×**560** max |
| 8 | Typography | **Pretendard** primary, **SUIT** secondary | system-ui, Malgun Gothic |
| 9 | Mobile spacing | **16–20px** page gutter | 16px 일부, 불일치 |
| 10 | Animations | **0.25s** ease transitions | 0.12s hero CTA only |
| 11 | Dark mode | CSS variables foundation | landing `:root` only; admin none |
| 12 | Icons | Heroicons / Lucide SVG inline | emoji·text 위주 |
| 13 | Final goal | plustok.mycafe24.com 전체 Premium Enterprise CRM UX | — |

**디자인 레퍼런스 품질:** Salesforce Lightning · HubSpot · Notion · Linear · Apple SaaS — **과도한 장식보다 정보 밀도·터치 타깃·대비** 우선.

---

## §4. CSS 파일 명명 · 레이어 제안

기존 `admin.css` 를 즉시 삭제하지 않고 **레이어 추가** 방식 권장.

| 파일 (신규) | 용도 | 로드 순서 |
|-------------|------|-----------|
| `assets/css/plustok-tokens-v2.css` | `:root` / `[data-theme="dark"]` CSS variables (색·spacing·radius·shadow·font) | 1 |
| `assets/css/plustok-premium-v2.css` | glass card, btn v2, nav/sidebar, table-cards, animations | 2 |
| `assets/css/admin.css` | **유지** — 레거시 호환; Phase 1 후 점진 deprecate | 3 (또는 2와 병합 검토) |
| `assets/css/landing-premium-v2.css` | (선택) `index.php` 인라인 CSS 추출 | landing only |
| `assets/css/consult-chat-premium-v2.css` | (Phase 2) view.php 인라인 chat CSS 추출 | view only |

**header.php 수정 (Phase 1):**

```html
<link rel="stylesheet" href="/assets/css/plustok-tokens-v2.css">
<link rel="stylesheet" href="/assets/css/plustok-premium-v2.css">
<link rel="stylesheet" href="/assets/css/admin.css">
```

**폰트 CDN (Phase 1):**

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
<!-- SUIT: secondary headings — CDN 또는 self-host assets/fonts/ -->
```

---

## §5. Phase별 구현 순서

### Phase 1 — Required (먼저 ship)

| 순서 | 작업 | 주요 파일 | 산출 |
|------|------|-----------|------|
| 1.1 | Design tokens + dark mode vars (foundation only) | `assets/css/plustok-tokens-v2.css` | CSS variables |
| 1.2 | Premium components (btn, card, nav) | `assets/css/plustok-premium-v2.css` | §3 #2–3, #9–10 |
| 1.3 | header.php stylesheet 링크 + Pretendard | `includes/header.php` | 전 Admin 적용 |
| 1.4 | Dashboard 4/3/2/1 grid + glass KPI | `admin/dashboard.php`, CSS | §3 #1 |
| 1.5 | Hero responsive + CTA 58px | `index.php` 또는 `landing-premium-v2.css` | §3 #4 |
| 1.6 | Admin mobile: nav hamburger, `.wrap` gutter 16–20px | CSS + `header.php` (햄버거 버튼 markup) | §3 #5 partial |
| 1.7 | `.btn` v2 클래스 — 기존 `.btn` 하위 호환 | CSS | danger/success gradient |

### Phase 2 — Advanced

| 순서 | 작업 | 주요 파일 |
|------|------|-----------|
| 2.1 | consult phone mockup premium (glass, notch, soft 3D) | `admin/consults/view.php` → CSS 추출 |
| 2.2 | Popup chat 420×780 desktop / mobile fullscreen | `embed/chat-widget.js`, `embed/chat-frame.php` |
| 2.3 | Card-style tables (모바일 `<768px`) | CSS `.tablewrap` + `.table-card-row` 패턴 |
| 2.4 | Animations 0.25s 전역 + hover glow buttons | `plustok-premium-v2.css` |
| 2.5 | Dark mode toggle (data-theme, localStorage) — Admin only | `header.php` + tokens |
| 2.6 | Heroicons/Lucide SVG — nav·CTA·상태 아이콘 | header, dashboard, consults |

### Phase 3 — Premium

| 순서 | 작업 | 주요 파일 |
|------|------|-----------|
| 3.1 | Real-time notifications (toast + nav badge) | header + WS hook (기존 socket 재사용) |
| 3.2 | Draggable popup chat | `chat-widget.js` |
| 3.3 | PWA manifest + mobile shell UI | `frontend/` 또는 public root |
| 3.4 | Touch gestures (swipe close chat, pull refresh list) | embed + admin JS |
| 3.5 | Mobile bottom nav (Admin) | `header.php` / new partial |
| 3.6 | AI copilot side panel | `admin/consults/view.php` 또는 dashboard |

---

## §6. 상세 스펙 — 화면별

### §6.1 Dashboard (`admin/dashboard.php`)

**현재 markup:**

```html
<div class="cards">
  <div class="card">...</div>  <!-- ×4 KPI -->
</div>
```

**목표 CSS (breakpoint grid):**

| Viewport | Columns | Gap |
|----------|---------|-----|
| ≥1280px | 4 | 20px |
| 992–1279px | 3 | 18px |
| 768–991px | 2 | 16px |
| <768px | 1 | 16px |

**Glass KPI card:**

- `background: rgba(255,255,255,0.72)` + `backdrop-filter: blur(12px)`
- `border: 1px solid rgba(255,255,255,0.5)`
- `box-shadow: 0 4px 24px rgba(15,23,42,0.08), inset 0 1px 0 rgba(255,255,255,0.6)`
- `.label` 13px muted · `.value` 28–32px bold
- AI 섹션 `.cards` 동일 grid 클래스 재사용 (`cards cards--kpi`)

**금지:** PHP 쿼리·try/catch·`CrmSchema` 로직 변경 없음.

---

### §6.2 Buttons (전역 `.btn` → `.btn-v2` 또는 `.btn` 확장)

| Variant | Gradient | Hover |
|---------|----------|-------|
| primary | `#2563EB` → `#1D4ED8` | `box-shadow: 0 0 20px rgba(37,99,235,0.45)` |
| success | `#16A34A` → `#15803D` | green glow |
| danger | `#DC2626` → `#B91C1C` | red glow |

```css
.btn, .btn-v2 {
  min-height: 52px;
  padding: 0 24px;
  border-radius: 16px;
  font-size: 15px;
  font-weight: 600;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
```

**Mobile:** full-width vertical stack in `.btn-group--stack` (forms, view.php action rows).

---

### §6.3 Hero (`index.php`)

| Element | Desktop | Tablet | Mobile |
|---------|---------|--------|--------|
| h1 | 40–44px | 32px | 26–28px |
| hero padding | 56px 28px | 48px 24px | 32px 20px |
| CTA | **58px** height, 18px font | 동일 | full-width max 320px |
| section `.wrap` | padding 0 20px | 0 18px | **16px** |

Dark: 기존 `@media (prefers-color-scheme: dark)` 유지 + tokens 파일과 변수명 통일 (`--pt-bg`, `--pt-ink` 등).

---

### §6.4 Admin Mobile First

**Nav (현재):** `.topbar` + `.nav` horizontal sticky — **Phase 1에서 hamburger + off-canvas 또는 collapsible vertical nav.**

| Breakpoint | Nav behavior |
|------------|--------------|
| ≥992px | horizontal top nav (현행 유지 가능) |
| <992px | hamburger → slide-over menu, body scroll lock |

**Tables:** `<768px` — each `<tr>` as card (data-label attributes or CSS `::before` from `data-th`).

**Forms:** `grid-template-columns: 1fr` on mobile; filters `.filters` → column stack.

---

### §6.5 Consult view phone mockup (`admin/consults/view.php`)

**Enhance (Phase 2), do not rewrite JS:**

- Phone frame: max-width **390px**, height **min(720px, 70vh)**, border-radius 28px, outer shadow soft 3D
- Notch/status bar decorative strip (CSS only)
- Move L198–389 inline styles → `assets/css/consult-chat-premium-v2.css`
- Keep IDs: `#consult-messaging`, `#msg-connection-status`, `#msg-input`, socket handlers untouched

---

### §6.6 Popup chat (`embed/chat-widget.js`)

**Current (L12–17):** `max-width:420px`, `height:min(560px,...)`.

**Target:**

| Mode | Size |
|------|------|
| Desktop (≥768px) | 420×**780** fixed, bottom-right floating, `border-radius: 20px` |
| Mobile (<768px) | `100vw × 100vh`, `border-radius: 0`, safe-area-inset |

Phase 2: draggable handle (Phase 3 full drag).

---

### §6.7 Typography

| Token | Stack |
|-------|-------|
| `--font-primary` | `'Pretendard', system-ui, sans-serif` |
| `--font-secondary` | `'SUIT', 'Pretendard', sans-serif` |

Apply on `body`, `.topbar`, `.hero`, `.card`, chat bubbles.

---

### §6.8 CSS Variables (Dark mode foundation — Phase 1 tokens file)

```css
:root {
  --pt-bg: #f5f7fa;
  --pt-surface: rgba(255,255,255,0.72);
  --pt-ink: #1f2933;
  --pt-muted: #64748b;
  --pt-primary: #2563eb;
  --pt-radius-lg: 16px;
  --pt-space-page: 18px;
  --pt-transition: 0.25s ease;
}
[data-theme="dark"] {
  --pt-bg: #0f1620;
  --pt-surface: rgba(22,31,43,0.85);
  --pt-ink: #e6edf3;
  --pt-muted: #93a1b0;
}
```

Toggle UI는 Phase 2; Phase 1은 variables + `prefers-color-scheme` optional hook only.

---

## §7. Phase 1 권장 파일 Touch List

| # | 파일 | 변경 유형 |
|---|------|-----------|
| 1 | `assets/css/plustok-tokens-v2.css` | **신규** |
| 2 | `assets/css/plustok-premium-v2.css` | **신규** |
| 3 | `includes/header.php` | link tags + hamburger markup (minimal) |
| 4 | `assets/css/admin.css` | 선택: deprecated 주석, mobile nav override 제거 |
| 5 | `admin/dashboard.php` | class 추가 (`cards cards--kpi`) — markup only |
| 6 | `index.php` | CTA class, optional CSS extract |
| 7 | `includes/footer.php` | 선택: footer premium spacing |
| 8 | `admin/index.php` | login page — same CSS links if standalone |

**Phase 1에서 건드리지 않음:** `admin/consults/view.php` JS, `embed/*`, `api/*`, `chat-server/*`, `frontend/dist`.

---

## §8. 완료 기준 (Acceptance Criteria)

### Phase 1

- [x] `plustok-tokens-v2.css` + `plustok-premium-v2.css` 배포, header에서 로드
- [x] Dashboard KPI: 1280/992/768/767 viewport에서 4/3/2/1 column 확인 (Chrome DevTools)
- [x] Primary button min-height **52px**, border-radius **16px**, gradient + hover glow
- [x] Hero CTA **58px** height; mobile h1 ≥26px, gutter **16–20px**
- [x] Pretendard 적용 — Admin + Landing body font
- [ ] **채팅 E2E 회귀 없음** (§9) — 배포 후 수동 검증 필요
- [ ] iPhone SE / 390px width Admin 목록·대시보드 스크롤·탭 가능 — 배포 후 수동 검증 필요

### Phase 2

- [ ] consult view phone mockup premium 스타일, 메시지 송수신 동일
- [ ] Popup chat desktop 420×780, mobile fullscreen
- [ ] consults/customers list — mobile card table
- [ ] 전역 transition **0.25s**; dark mode toggle 동작
- [ ] Lucide/Heroicons 최소 1곳 이상 SVG (nav 또는 KPI icon)

### Phase 3

- [ ] 실시간 notification UI (mock or WS-backed)
- [ ] Draggable chat popup
- [ ] Admin mobile bottom nav OR PWA install prompt UI prototype
- [ ] AI copilot side panel shell (UI only OK)

---

## §9. 금지 · 회귀 방지 (Forbidden)

| 금지 | 이유 |
|------|------|
| `admin/consults/view.php` Socket.io·`message:send`·`roomId` 로직 변경 | Phase 1 채팅 E2E |
| `embed/chat-frame.php` WS auth handshake 변경 | 고객 위젯 |
| `api/v1/consult.php` room/JWT 생성 로직 변경 | 접수→채팅 자동 연결 |
| `ChatService::requireRoomAccess` customer 분기 삭제/완화 | 보안 |
| 하드coded API keys | enterprise rules |
| `frontend/dist` 무조건 재빌드 | PHP Admin 경로와 무관 시 불필요 |

**Phase 1 완료 후 필수 E2E (from `_작업지시서_고객채팅위젯_Phase1_최종점검.md`):**

1. Admin `view.php?no=…` → 메시지 전송  
2. 고객 `PlusTokChat.open` 위젯 → 2–3초 내 수신  
3. 연결 상태 `#msg-connection-status` **연결됨** 유지  

스타일-only diff 후에도 위 3项 PASS 필수.

---

## §10. 배포 (Cafe24 FTP)

| 항목 | 값 |
|------|-----|
| Host | Cafe24 FTP (plustok.mycafe24.com) |
| 업로드 경로 | `/www/` 또는 호스팅 루트 = repo `www/` mirror |
| Phase 1 최소 업로드 | `assets/css/plustok-*.css`, `includes/header.php`, `admin/dashboard.php`, `index.php` |
| 캐시 bust | `<link href="/assets/css/plustok-premium-v2.css?v=20260723-mobile2">` query param 권장 |
| 검증 URL | `/`, `/admin/dashboard.php`, `/admin/consults/view.php?no=…`, `/embed/demo-chat.php` |
| Render | chat-server **배포 불필요** (CSS-only Phase) |

**롤백:** header.php에서 premium link 2줄 제거 → 즉시 legacy `admin.css` only.

---

## §11. 건드리지 않아도 되는 것 (Phase 1)

- `frontend/dist` — `/frontend/#/chat` React 앱
- `chat-server/` — Render WebSocket
- `_작업지시서_고객채팅위젯_Phase1_최종점검.md` 미완 E2E — UI 작업과 병행 가능하나 **배포 전 E2E 재실행**
- 그누보드 legacy `theme/`, `adm/` — PlusTok CRM 경로와 무관

---

## §12. 에이전트 실행 체크리스트 (Cursor / Codex / Claude / Gemini)

구현 착수 시 순서:

1. [x] §0 SSOT 3문서 skim (`UI_COMPONENTS_GUIDE`, `01_관리자화면_UIUX_설계`, Phase1 채팅 점검서)
2. [x] §7 Phase 1 touch list대로 tokens → premium CSS → header 링크
3. [ ] dashboard + index 시각 확인 (4 breakpoints) — 배포 후 DevTools
4. [ ] §9 E2E 3项 — 배포 후
5. [ ] §10 FTP 업로드 + cache bust
6. [ ] Phase 2/3는 본 문서 §5 표 순서대로 별 PR/배포 단위 권장

---

**문서 작성:** 2026-07-23 · 코드베이스 실측 기준  
**다음 액션:** Phase 2 — consult phone mockup + popup chat 420×780 + mobile card tables

---

## §13. Mobile layout hotfix log (2026-07-23)

| 배포 | 캐시 bust | 증상 | 조치 |
|------|-----------|------|------|
| mobile | `?v=20260723-mobile` | 상담 view 모바일 2단·헤더 겹침·가로 스크롤 | view.php 시맨틱 클래스 + premium CSS `@768px` |
| **mobile2** | `?v=20260723-mobile2` | **증상 지속** (CSS는 prod 200, view.php 미배포 가능) | breakpoint **991px**로 nav와 통일; `body .wrap`/`table`/`grid` **!important**; tabs를 `consult-view-main` 내부로 이동; legacy inline grid fallback |

**mobile2 FTP 필수 업로드:** `includes/header.php`, `assets/css/plustok-premium-v2.css`, `admin/consults/view.php`

**모바일 섹션 순서 (≤991px):** 상담정보 → 진행상태 → 고객정보 → 채팅 → AI요약 → AI인사이트 → 탭(답변/메모/첨부/이력)
