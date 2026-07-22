# 01 — 관리자 화면 UI/UX 설계

> **PlusTok ACEP** · STEP 6 · Admin UI/UX Specification  
> **버전**: 1.0.0 · **작성일**: 2026-07-21  
> **SSOT**: 본 문서. Figma stub: [`02_UIUX/02_관리자화면.fig.md`](../02_UIUX/02_관리자화면.fig.md)  
> **관련**: [`01_상담채팅화면.fig.md`](../02_UIUX/01_상담채팅화면.fig.md) Admin Monitor View V1.5

---

## 목차

1. [문서 목적](#1-문서-목적)
2. [범위 및 제약](#2-범위-및-제약)
3. [사용자 페르소나 및 RBAC](#3-사용자-페르소나-및-rbac)
4. [정보 구조(IA) 및 네비게이션](#4-정보-구조ia-및-네비게이션)
5. [공통 레이아웃 시스템](#5-공통-레이아웃-시스템)
6. [화면별 상세 설계](#6-화면별-상세-설계)
7. [모바일·반응형 (V1.5)](#7-모바일반응형-v15)
8. [DB·API 참조 매핑](#8-DBAPI-참조-매핑)
9. [비즈니스 규칙](#9-비즈니스-규칙)
10. [테스트 케이스](#10-테스트-케이스)
11. [디자인 토큰 및 컴포넌트](#11-디자인-토큰-및-컴포넌트)
12. [부록](#12-부록)

---

## 1. 문서 목적

### 1.1 목적

PlusTok ACEP **관리자(Admin) 콘솔**의 화면 구조, 사용자 흐름, RBAC 가시성, ASCII 와이어프레임을 정의한다.  
개발자·디자이너·QA가 동일한 화면 스펙을 참조할 수 있도록 [`01_상담채팅화면.fig.md`](../02_UIUX/01_상담채팅화면.fig.md)와 동일한 문서 체계를 따른다.

### 1.2 대상 독자

| 독자 | 활용 |
|------|------|
| Frontend/PHP 개발자 | 레이아웃·컴포넌트·상태 구현 |
| Backend 개발자 | 화면별 API·권한 연동 |
| QA | 테스트 케이스 §10 기준 검증 |
| PM/운영 | KPI·모니터링 요구사항 확인 |

### 1.3 STEP 6 vs 로드맵

| 구분 | STEP 6 (본 문서) | V1.5 | V2.0+ |
|------|------------------|------|-------|
| 플랫폼 | **PC-first 1440px** | Mobile read-only Monitor | React Admin SPA |
| 구현 | PHP Admin 확장 | WS Live Monitor | Dashboard SPA |
| Figma | stub + 본 문서 SSOT | Monitor 모바일 variant | Design system 통합 |

---

## 2. 범위 및 제약

### 2.1 In Scope (STEP 6)

- Dashboard Home (KPI·차트 개요)
- Live Chat Monitor (read-only)
- Consult List (ACEP `chat_rooms` 기반)
- Agent Management (기본 CRUD UI)
- AI Settings (`admin/settings/ai.php` 정합)
- Prompt Management (`ai_prompts` CRUD)
- Failover Log Viewer (`ai_failover_log`)
- System Settings (일반·보안·감사)

### 2.2 Out of Scope (STEP 6)

- Customer-facing 채팅 UI (STEP 4/5)
- Agent 상담 화면 본체 (STEP 5)
- 결제·청구 Admin (향후 STEP)
- React Dashboard SPA **구현** (V2.0 — 설계만 [`02_Admin_Dashboard_구현명세.md`](./02_Admin_Dashboard_구현명세.md) 참조)

### 2.3 디자인 제약

| 항목 | 값 | 비고 |
|------|-----|------|
| 기준 뷰포트 | **1440 × 900** | PC-first |
| 최소 지원 | 1280 × 720 | 사이드바 collapsed |
| Sidebar 너비 | 240px (expanded) / 64px (collapsed) | |
| Header 높이 | 56px | |
| Content padding | 24px | |
| Grid | 12-column, gutter 24px | |
| Font | Pretendard / system-ui | 상담 화면과 동일 |
| Primary color | `#2563EB` | PlusTok brand blue |
| Danger | `#DC2626` | 삭제·차단 |
| Success | `#16A34A` | KPI positive |
| Warning | `#D97706` | Failover·지연 |

---

## 3. 사용자 페르소나 및 RBAC

### 3.1 역할 정의

| 역할 | 코드 | 설명 |
|------|------|------|
| Super Admin | `super` | 전체 설정·프롬프트·API 키·감사 |
| Admin | `admin` | 운영·상담·에이전트·AI 설정 (키 마스킹) |
| Agent | `agent` | 본인 상담 + 제한적 목록 (Admin 콘솔 **미접근**) |
| Operator | `operator` | Live Monitor read-only + Consult List read |

> **주의**: `agent`는 Admin 콘솔 URL 직접 접근 시 403. Agent UI는 STEP 5 Frontend.

### 3.2 RBAC 가시성 매트릭스 (화면)

| 화면 / 메뉴 | super | admin | operator | agent |
|-------------|:-----:|:-----:|:--------:|:-----:|
| Dashboard Home | ✅ | ✅ | ✅ (read) | ❌ |
| Live Chat Monitor | ✅ | ✅ | ✅ (read) | ❌ |
| Consult List | ✅ | ✅ | ✅ (read) | ❌ |
| Agent Management | ✅ | ✅ | ❌ | ❌ |
| AI Settings | ✅ | ✅ (no key edit) | ❌ | ❌ |
| Prompt Management | ✅ | ✅ (no delete prod) | ❌ | ❌ |
| Failover Log | ✅ | ✅ | ✅ (read) | ❌ |
| System Settings | ✅ | △ (subset) | ❌ | ❌ |
| Audit Logs | ✅ | ✅ (read) | ❌ | ❌ |

**범례**: ✅ 전체 · △ 일부 · ❌ 숨김/403

### 3.3 RBAC UI 패턴

```
[메뉴 hidden]     → require_role 미달 시 사이드바 항목 미렌더
[버튼 disabled]   → 목록 조회 가능, 쓰기 action disabled + tooltip
[403 page]        → URL 직접 접근 시 includes/auth.php redirect
[API key mask]    → admin 역할: sk-****last4 표시, super만 reveal/edit
```

---

## 4. 정보 구조(IA) 및 네비게이션

### 4.1 사이드바 구조

```
PlusTok Admin
├── 📊 대시보드                    /admin/index.php
├── 💬 상담 운영
│   ├── 실시간 모니터               /admin/monitor/index.php
│   └── 상담 목록                   /admin/consults/index.php
├── 👥 에이전트 관리                /admin/agents/index.php
├── 🤖 AI 설정
│   ├── AI 모델·파라미터            /admin/settings/ai.php      ← 기존
│   ├── 프롬프트 관리               /admin/settings/prompts.php
│   └── Failover 로그              /admin/settings/failover.php
├── ⚙️ 시스템
│   ├── 일반 설정                   /admin/settings/general.php
│   └── 감사 로그                   /admin/settings/audit.php
└── (하단) 로그아웃
```

### 4.2 Breadcrumb 규칙

```
홈 > {1depth} > {2depth} > [현재 페이지]
예: 홈 > AI 설정 > 프롬프트 관리 > 편집: greeting_v2
```

### 4.3 URL 네이밍 컨벤션

| 패턴 | 예시 |
|------|------|
| 목록 | `/admin/{module}/index.php` |
| 상세 | `/admin/{module}/view.php?id={uuid}` |
| 생성/편집 | `/admin/{module}/edit.php?id={uuid}` |
| AJAX/API | `/admin/{module}/api/{action}.php` 또는 REST `/api/v1/admin/...` |

---

## 5. 공통 레이아웃 시스템

### 5.1 Master Layout ASCII (1440px)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ HEADER 56px                                              [알림] [프로필 ▼]  │
├────────────┬─────────────────────────────────────────────────────────────────┤
│            │ Breadcrumb: 홈 > ...                                            │
│  SIDEBAR   ├─────────────────────────────────────────────────────────────────┤
│   240px    │                                                                 │
│            │                     MAIN CONTENT AREA                           │
│  [로고]    │                     padding 24px                                │
│            │                                                                 │
│  · 대시보드│                                                                 │
│  · 상담    │                                                                 │
│  · 에이전트│                                                                 │
│  · AI      │                                                                 │
│  · 시스템  │                                                                 │
│            │                                                                 │
│  ────────  │                                                                 │
│  [접기 «]  │                                                                 │
└────────────┴─────────────────────────────────────────────────────────────────┘
```

### 5.2 Header 구성

| 영역 | 요소 | 동작 |
|------|------|------|
| Left | Breadcrumb | 클릭 시 상위 이동 |
| Right | 알림 bell | Failover spike, SLA breach (V1.5) |
| Right | 프로필 | 이름, 역할 badge, 로그아웃 |

### 5.3 공통 테이블 패턴

- Server-side pagination (default 20 rows)
- Column sort (created_at DESC default)
- Filter bar: date range, status, agent, search keyword
- Bulk actions: super/admin only, confirm modal
- Empty state illustration + CTA

### 5.4 공통 Form 패턴

- Required field `*` + inline validation
- Save / Cancel (Cancel → list with unsaved confirm)
- CSRF hidden field `csrf_token`
- Success toast 3s auto-dismiss
- Destructive actions: red button + type-to-confirm (prompt delete)

---

## 6. 화면별 상세 설계

---

### 6.1 Dashboard Home — AI 운영 센터 개요

**경로**: `/admin/index.php`  
**역할**: super, admin, operator (read-only KPI)

#### 6.1.1 목적

실시간·일간 AI 상담 운영 KPI를 한 화면에서 파악. V2.5 STEP 14 AI 운영 센터의 PHP 선행 버전.

#### 6.1.2 ASCII Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  AI 운영 센터 대시보드                    [오늘 ▼] [새로고침] [내보내기 CSV] │
├──────────┬──────────┬──────────┬──────────┬─────────────────────────────────┤
│ 활성상담 │ 평균응답 │ AI 채택률│ 계약전환 │  ← KPI Cards (4-up)             │
│   127    │  1m 42s  │  68.4%   │  12.3%   │                                 │
│  ▲ +8%   │  ▼ -12s  │  ▲ +2.1% │  ─ 0%    │                                 │
├──────────┴──────────┴──────────┴──────────┴─────────────────────────────────┤
│  ┌─────────────────────────────┐  ┌─────────────────────────────┐          │
│  │  감정 분포 (Pie/Donut)       │  │  계약 확률 Funnel            │          │
│  │  긍정 45% 중립 38% 부정 17%  │  │  ████████░░ 80%+ : 23건      │          │
│  │                              │  │  ██████░░░░ 50-80%: 41건     │          │
│  └─────────────────────────────┘  └─────────────────────────────┘          │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  에이전트 성과 (Bar chart — 응답시간, AI채택, 전환율 by agent)         │  │
│  │  ████ agent_kim  ███ agent_lee  ██ agent_park  ...                     │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────┐  ┌─────────────────────────────┐          │
│  │  최근 Failover (top 5)       │  │  실시간 활성 상담 (top 10)    │          │
│  │  · GPT→Claude 14:32         │  │  · #CR-1024  김고객  agent1  │          │
│  │  · timeout→fallback 14:28   │  │  · #CR-1025  이고객  agent2  │          │
│  └─────────────────────────────┘  └─────────────────────────────┘          │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 6.1.3 KPI 정의

| KPI | 데이터 소스 | 계산 |
|-----|-------------|------|
| 활성 상담 | `chat_rooms` | `status IN ('active','waiting')` COUNT |
| 평균 응답 시간 | `messages` | agent 첫 응답까지 avg (당일) |
| AI 채택률 | `ai_recommendations` | `used=true` / total recommendations |
| 계약 전환율 | `chat_rooms` | `contract_status='signed'` / closed |

#### 6.1.4 인터랙션

- Date range: 오늘 / 7일 / 30일 / custom
- KPI card click → drill-down filter applied Consult List
- 실시간 위젯: polling 30s (V1.5: WebSocket admin namespace)

---

### 6.2 Live Chat Monitor — 실시간 상담 모니터

**경로**: `/admin/monitor/index.php`  
**역할**: super, admin, operator (read-only)  
**연관**: [`01_상담채팅화면.fig.md`](../02_UIUX/01_상담채팅화면.fig.md) § Admin Monitor View V1.5

#### 6.2.1 목적

진행 중인 모든 상담을 **읽기 전용**으로 모니터링. 슈퍼바이저·운영자의 품질 관리.

#### 6.2.2 ASCII Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  실시간 상담 모니터          [전체 에이전트 ▼] [상태 ▼] [🔴 LIVE · 30s poll]│
├──────────────────┬──────────────────────────────────────────────────────────┤
│  ROOM LIST       │  MESSAGE STREAM (read-only)                              │
│  320px           │                                                          │
│ ┌──────────────┐ │  Room #CR-1024 · 김고객 · agent_kim · AI:ON             │
│ │● CR-1024     │ │  ─────────────────────────────────────────────────────  │
│ │  김고객       │ │  [14:30] 고객: 안녕하세요, 요금 문의드립니다.            │
│ │  agent_kim   │ │  [14:31] AI추천: (감정:neutral 72%) "안녕하세요..."     │
│ │  2m ago      │ │  [14:31] agent_kim: 안녕하세요! PlusTok 요금은...       │
│ ├──────────────┤ │  [14:32] 고객: 5G 결합 할인 있나요?                      │
│ │○ CR-1025     │ │                                                          │
│ │  이고객       │ │  ┌─ AI Insight Panel ─────────────────────────────┐    │
│ │  agent_lee   │ │  │ 감정: neutral → positive  │ 계약확률: 67%      │    │
│ │  5m ago      │ │  │ 추천 채택: 2/3  │ Failover: 0                  │    │
│ └──────────────┘ │  └────────────────────────────────────────────────┘    │
│  ...             │                                                          │
└──────────────────┴──────────────────────────────────────────────────────────┘
```

#### 6.2.3 제약 (read-only)

- 메시지 입력 필드 **없음**
- 상담 개입·이관 버튼 **없음** (V2.0 supervisor mode 별도)
- AI Insight Panel: `ai_logs`, `ai_recommendations` 최신 snapshot

#### 6.2.4 실시간 전략

| Phase | 방식 |
|-------|------|
| STEP 6 V1.0 | HTTP polling 30s + manual refresh |
| V1.5 | WebSocket `admin:monitor` namespace subscribe |
| Fallback | polling 유지 (WS disconnect 시) |

---

### 6.3 Consult List — 상담 목록

**경로**: `/admin/consults/index.php` (기존 CRM 확장)  
**역할**: super, admin (CRUD), operator (read)

#### 6.3.1 목적

Legacy CRM 상담 + ACEP `chat_rooms` 통합 목록. AI 액션(ai_summary, ai_reply, ai_analyze) 진입점.

#### 6.3.2 ASCII Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  상담 목록                                    [+ 수동 상담 생성] (admin+)    │
├─────────────────────────────────────────────────────────────────────────────┤
│  [검색________] [기간____] [상태 ▼] [에이전트 ▼] [AI사용 ▼] [적용] [초기화]  │
├──────┬────────┬──────────┬─────────┬─────────┬──────────┬───────────────────┤
│ ID   │ 고객   │ 에이전트  │ 상태    │ AI      │ 계약확률 │ Actions           │
├──────┼────────┼──────────┼─────────┼─────────┼──────────┼───────────────────┤
│1024  │김**    │agent_kim │ active  │ ON 68%  │ 72%      │[보기][요약][분석] │
│1023  │박**    │agent_lee │ closed  │ ON 45%  │ 31%      │[보기][요약]       │
│ ...  │        │          │         │         │          │                   │
├──────┴────────┴──────────┴─────────┴─────────┴──────────┴───────────────────┤
│  « 1 2 3 ... 10 »                                    20건/페이지  총 1,247건 │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 6.3.3 Actions (기존 PHP 연동)

| 버튼 | Endpoint | 설명 |
|------|----------|------|
| 보기 | `view.php?id=` | 상담 상세·메시지 타임라인 |
| AI 요약 | `ai_summary.php` | [`admin/consults/ai_summary.php`](../../admin/consults/ai_summary.php) |
| AI 답변 | `ai_reply.php` | 추천 답변 생성 |
| AI 분석 | `ai_analyze.php` | 감정·계약 확률 |

#### 6.3.4 CRM → ACEP 마이그레이션 UI

- Legacy row: badge `CRM` (회색)
- ACEP row: badge `ACEP` (blue)
- 통합 검색: `external_id` + `chat_room_id` 매핑
- Filter "ACEP only" / "Legacy only"

---

### 6.4 Agent Management — 에이전트 관리

**경로**: `/admin/agents/index.php` (STEP 6 신규)  
**역할**: super, admin

#### 6.4.1 ASCII Layout — List

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  에이전트 관리                                         [+ 에이전트 등록]     │
├─────────────────────────────────────────────────────────────────────────────┤
│  [검색________] [상태 ▼: active/inactive] [역할 ▼]              [적용]     │
├──────┬────────────┬──────────┬─────────┬──────────┬──────────┬─────────────┤
│ ID   │ 이름       │ 이메일    │ 역할    │ 동시상담  │ 오늘상담  │ Actions     │
├──────┼────────────┼──────────┼─────────┼──────────┼──────────┼─────────────┤
│ A001 │ 김상담     │ kim@...  │ agent   │ 3/5      │ 12       │[편집][배정] │
│ A002 │ 이운영     │ lee@...  │ operator│ —        │ —        │[편집]       │
└──────┴────────────┴──────────┴─────────┴──────────┴──────────┴─────────────┘
```

#### 6.4.2 ASCII Layout — Edit / Assignment

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  에이전트 편집: 김상담                                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│  이름 *        [김상담_______________]                                       │
│  이메일 *      [kim@plustok.kr_______]                                       │
│  역할 *        ( ) agent  ( ) operator  ( ) admin  ( ) super               │
│  상태          [x] 활성                                                      │
│  최대 동시상담  [5___] (1-10)                                                │
│  ─────────────────────────────────────────────────────────────────────────  │
│  현재 배정 상담 (chat_room_assignments)                                      │
│  · CR-1024  김고객  active  [해제]                                          │
│  · CR-1019  박고객  waiting [해제]                                          │
│                              [저장]  [취소]                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 6.4.3 DB

- `agents` table — [`01_DB설계.md`](../03_SYSTEM/01_DB설계.md)
- `chat_room_assignments` — room ↔ agent M:N with `assigned_at`, `released_at`

---

### 6.5 AI Settings — AI 모델·파라미터

**경로**: `/admin/settings/ai.php` (**기존 참조 구현**)  
**역할**: super (full), admin (read + non-secret edit)

#### 6.5.1 목적

기존 `ai.php` 동작을 문서화하고 ACEP 필드 확장점을 정의. **구현과 모순 금지.**

#### 6.5.2 ASCII Layout (기존 정합)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  AI 설정                                                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│  ■ Primary Model                                                             │
│    Provider      [OpenAI ▼]                                                  │
│    Model         [gpt-4o_______________]                                     │
│    API Key       [sk-••••••••••••3kFa]  [Reveal] (super only)               │
│  ■ Fallback Model                                                            │
│    Provider      [Anthropic ▼]                                               │
│    Model         [claude-3-5-sonnet________]                                 │
│    API Key       [sk-••••••••••••7xQ2]  [Reveal]                            │
│  ■ Parameters                                                                │
│    Temperature   [0.7____] (0.0 - 2.0)                                       │
│    Max Tokens    [2048___]                                                   │
│    Timeout (sec) [30_____]                                                   │
│  ■ Feature Flags                                                             │
│    [x] AI 추천 답변 활성화                                                    │
│    [x] 자동 감정 분석                                                         │
│    [ ] AI 자동 응답 (V2.0 — 비활성)                                          │
│  ─────────────────────────────────────────────────────────────────────────  │
│                              [테스트 연결]  [저장]                           │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 6.5.3 기존 ai.php 정합 규칙

| 필드 | 저장 위치 | 비고 |
|------|-----------|------|
| Provider/Model | `settings` KV 또는 `ai_config` JSON | 기존 로직 유지 |
| API Key | encrypted column | super only edit |
| Temperature | float 0-2 | validation 동일 |
| Test Connection | AJAX POST | OpenAI/Anthropic ping |

#### 6.5.4 ACEP 확장 (STEP 6 additive)

- Failover threshold (연속 실패 N회 → fallback)
- Recommendation display mode (inline / sidebar)
- Log retention days (ai_logs purge policy) — super only

---

### 6.6 Prompt Management — 프롬프트 관리

**경로**: `/admin/settings/prompts.php`  
**역할**: super, admin (admin: prod delete 불가)

#### 6.6.1 ASCII Layout — List

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  프롬프트 관리                                    [+ 새 프롬프트] (super+)   │
├─────────────────────────────────────────────────────────────────────────────┤
│  [검색________] [타입 ▼: system|greeting|summary|reply|analyze] [활성 ▼]   │
├──────────┬─────────────┬─────────┬─────────┬──────────┬─────────────────────┤
│ Key      │ 이름         │ 버전    │ 타입    │ 수정일    │ Actions             │
├──────────┼─────────────┼─────────┼─────────┼──────────┼─────────────────────┤
│ sys_v3   │ System Base  │ v3      │ system  │ 07-20    │[편집][미리보기][↓]  │
│ greet_v2 │ 인사말       │ v2      │ greeting│ 07-18    │[편집][미리보기]     │
└──────────┴─────────────┴─────────┴─────────┴──────────┴─────────────────────┘
```

#### 6.6.2 ASCII Layout — Editor

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  프롬프트 편집: sys_v3                              [버전 히스토리] [활성화]  │
├─────────────────────────────────────────────────────────────────────────────┤
│  Key *         [sys_v3______________] (immutable after create)               │
│  이름 *        [System Base Prompt___]                                       │
│  타입 *        [system ▼]                                                    │
│  ── Template ──────────────────────────────────────────────────────────────  │
│  │ You are PlusTok AI assistant for LG U+ ...                               │
│  │ Variables: {{customer_name}}, {{product_line}}, {{context}}              │
│  │                                                     [변수 삽입 ▼]        │
│  └──────────────────────────────────────────────────────────────────────────│
│  ── Preview (sample context) ─────────────────────────────────────────────  │
│  │ Rendered output preview...                                                │
│  └──────────────────────────────────────────────────────────────────────────│
│                              [저장 초안]  [배포(activate)]  [취소]           │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 6.6.3 DB: `ai_prompts`

| Column | Type | Note |
|--------|------|------|
| id | UUID | PK |
| prompt_key | VARCHAR | unique |
| version | INT | increment on save |
| template | TEXT | Handlebars-style vars |
| is_active | BOOL | one active per key |
| created_by | FK agents | audit |

---

### 6.7 Failover Log Viewer

**경로**: `/admin/settings/failover.php`  
**역할**: super, admin, operator (read)

#### 6.7.1 ASCII Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  AI Failover 로그                              [기간 ▼] [Provider ▼] [내보내기]│
├─────────────────────────────────────────────────────────────────────────────┤
│  ⚠ 지난 24시간 Failover 14건 · Primary 실패율 2.3%                          │
├──────────┬──────────┬─────────────┬─────────────┬──────────┬────────────────┤
│ 시각     │ Room ID  │ From → To   │ 사유        │ Latency  │ Detail         │
├──────────┼──────────┼─────────────┼─────────────┼──────────┼────────────────┤
│ 14:32:01 │ CR-1024  │ GPT→Claude  │ timeout 30s │ 31.2s    │ [펼치기]       │
│ 14:28:44 │ CR-1019  │ GPT→Claude  │ rate_limit  │ 0.8s     │ [펼치기]       │
└──────────┴──────────┴─────────────┴─────────────┴──────────┴────────────────┘
```

#### 6.7.2 Detail Expand

```
  Request ID: req_abc123
  Error: OpenAI API timeout after 30000ms
  Retry count: 1
  Fallback model: claude-3-5-sonnet
  ai_log_id: log_xyz789 → [ai_logs 상세 링크]
```

#### 6.7.3 DB: `ai_failover_log`

---

### 6.8 System Settings — 시스템 설정

**경로**: `/admin/settings/general.php`, `/admin/settings/audit.php`  
**역할**: super (full), admin (general subset)

#### 6.8.1 General Settings ASCII

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  일반 설정                                                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  서비스명           [PlusTok ACEP___________]                                │
│  SLA 목표 응답(초)   [180___]                                                 │
│  상담 자동종료(분)   [30____]  (무응답)                                       │
│  WebSocket URL      [wss://ws.plustok.kr____] (super)                        │
│  Maintenance Mode   [ ] 활성  (super only — 전체 서비스 점검 배너)           │
│                              [저장]                                          │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 6.8.2 Audit Log Viewer ASCII

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  감사 로그                     [기간] [Actor ▼] [Action ▼] [Resource ▼]       │
├──────────┬──────────┬─────────────┬──────────────────────┬──────────────────┤
│ 시각     │ Actor    │ Action      │ Resource             │ IP               │
├──────────┼──────────┼─────────────┼──────────────────────┼──────────────────┤
│ 14:00:01 │ admin01  │ prompt.update│ ai_prompts/sys_v3   │ 203.0.113.1      │
│ 13:55:22 │ super01  │ ai_key.update│ settings/ai          │ 203.0.113.2      │
└──────────┴──────────┴─────────────┴──────────────────────┴──────────────────┘
```

#### 6.8.3 DB: `audit_logs`

---

## 7. 모바일·반응형 (V1.5)

### 7.1 정책

| 화면 | Mobile (<768px) | 비고 |
|------|-----------------|------|
| Dashboard | Hidden / simplified KPI only | PC 권장 |
| Live Monitor | **Read-only optional** | V1.5 scope |
| Consult List | Hidden | |
| AI Settings | Hidden | |
| Prompt / Failover | Hidden | |

### 7.2 Mobile Monitor ASCII (V1.5)

```
┌─────────────────────┐
│ ≡  Live Monitor  🔴 │
├─────────────────────┤
│ [Room ▼ CR-1024   ] │
├─────────────────────┤
│ 14:30 고객: ...     │
│ 14:31 agent: ...    │
│ 14:32 고객: ...     │
│ (scroll)            │
├─────────────────────┤
│ AI: neutral 67%     │
│ 계약: 72%           │
└─────────────────────┘
```

### 7.3 Breakpoints

| Name | Width | Sidebar |
|------|-------|---------|
| xl | ≥1440 | expanded 240px |
| lg | 1280-1439 | expanded |
| md | 1024-1279 | collapsed 64px |
| sm | 768-1023 | overlay drawer |
| xs | <768 | Admin 미지원 (Monitor only V1.5) |

---

## 8. DB·API 참조 매핑

### 8.1 화면 ↔ Table

| 화면 | Primary Tables | Secondary |
|------|----------------|-----------|
| Dashboard | `chat_rooms`, `messages`, `ai_recommendations` | `ai_logs`, `agents` |
| Live Monitor | `chat_rooms`, `messages` | `ai_logs`, `ai_recommendations` |
| Consult List | `chat_rooms`, `customers` | legacy `consults` |
| Agent Mgmt | `agents`, `chat_room_assignments` | `users` |
| AI Settings | `settings`, `ai_config` | — |
| Prompts | `ai_prompts` | `audit_logs` |
| Failover | `ai_failover_log` | `ai_logs` |
| Audit | `audit_logs` | `agents` |

### 8.2 화면 ↔ API (Admin Domain)

상세: [`04_Admin_API_및_권한_명세.md`](./04_Admin_API_및_권한_명세.md)

| 화면 | API |
|------|-----|
| Dashboard | `GET /api/v1/admin/stats/overview` |
| Dashboard charts | `GET /api/v1/admin/stats/sentiment`, `.../funnel`, `.../agents` |
| Live Monitor | `GET /api/v1/admin/monitor/rooms`, WS `admin:monitor` (V1.5) |
| Consult List | `GET /api/v1/admin/consults` |
| Agents | `GET/POST/PATCH /api/v1/admin/agents` |
| Prompts | `GET/POST/PATCH/DELETE /api/v1/admin/prompts` |
| Failover | `GET /api/v1/admin/failover-logs` |
| Audit | `GET /api/v1/admin/audit-logs` |

---

## 9. 비즈니스 규칙

### 9.1 접근 제어

| Rule ID | 규칙 |
|---------|------|
| BR-ADM-001 | Admin 콘솔 URL은 `require_role(['super','admin','operator'])` 필수 |
| BR-ADM-002 | `agent` 역할 JWT/세션으로 Admin URL 접근 시 HTTP 403 |
| BR-ADM-003 | operator는 모든 POST/PUT/PATCH/DELETE Admin API 403 |
| BR-ADM-004 | super만 API Key plaintext 조회·수정 가능 |
| BR-ADM-005 | admin은 prompt activate 가능, production prompt **delete** 불가 |

### 9.2 데이터 표시

| Rule ID | 규칙 |
|---------|------|
| BR-ADM-010 | 고객 PII: 목록에서 이름 마스킹 (김**), 상세는 admin+ unmask |
| BR-ADM-011 | operator Live Monitor: 메시지 read-only, copy allowed |
| BR-ADM-012 | Dashboard KPI: timezone Asia/Seoul, day boundary 00:00 KST |
| BR-ADM-013 | Legacy CRM row는 90일 후 read-only archive badge |

### 9.3 AI 운영

| Rule ID | 규칙 |
|---------|------|
| BR-ADM-020 | ai.php 설정 변경 시 `audit_logs` 필수 기록 |
| BR-ADM-021 | Prompt activate 시 이전 active version 자동 deactivate |
| BR-ADM-022 | Failover log retention: 90 days (configurable super) |
| BR-ADM-023 | AI test connection은 production key 사용 — rate limit 5/min/user |

### 9.4 상담·에이전트

| Rule ID | 규칙 |
|---------|------|
| BR-ADM-030 | Agent max concurrent rooms: 1-10, default 5 |
| BR-ADM-031 | Assignment release 시 `released_at` 필수, active room duplicate assign 금지 |
| BR-ADM-032 | Consult List ai_summary: closed room도 허용 (admin+) |

---

## 10. 테스트 케이스

### 10.1 Auth & RBAC

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-A01 | agent 계정으로 `/admin/` 접근 | 403 Forbidden |
| TC-ADM-A02 | operator로 AI Settings URL 직접 접근 | 403 |
| TC-ADM-A03 | admin으로 API Key edit 시도 | Field disabled, 403 on POST |
| TC-ADM-A04 | super logout 후 back button | Login redirect, no cache |
| TC-ADM-A05 | CSRF token missing on form POST | 419/403 error |

### 10.2 Dashboard

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-D01 | 오늘 KPI 로드 | 4 cards render, numeric values |
| TC-ADM-D02 | date range 7일 변경 | Charts refresh, API called with params |
| TC-ADM-D03 | 활성상담 card click | Consult List filtered active |
| TC-ADM-D04 | operator dashboard | No export CSV button |
| TC-ADM-D05 | API failure | Error banner + retry button |

### 10.3 Live Monitor

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-M01 | Room list load | Active rooms sorted by updated_at |
| TC-ADM-M02 | Room select | Messages stream read-only |
| TC-ADM-M03 | No message input visible | DOM assertion |
| TC-ADM-M04 | Polling 30s | Network tab shows interval request |
| TC-ADM-M05 | operator role | Same read view as admin |

### 10.4 Consult List

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-C01 | ACEP filter | Only chat_rooms rows |
| TC-ADM-C02 | ai_summary click | Summary modal/page, ai_logs entry |
| TC-ADM-C03 | Search customer name | Partial match masked names |
| TC-ADM-C04 | Pagination page 2 | Correct offset, total count |
| TC-ADM-C05 | operator actions column | View only, no summary button |

### 10.5 Agent Management

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-G01 | Create agent | agents row + audit log |
| TC-ADM-G02 | max concurrent > 10 | Validation error |
| TC-ADM-G03 | Assign room already assigned | 409 conflict |
| TC-ADM-G04 | Deactivate agent with active rooms | Warning modal, force option super |
| TC-ADM-G05 | operator access | 403 |

### 10.6 AI Settings (ai.php)

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-I01 | Load existing ai.php | Fields match DB, keys masked |
| TC-ADM-I02 | super save temperature | Success toast + audit |
| TC-ADM-I03 | Test connection success | Green check message |
| TC-ADM-I04 | Test connection fail | Red error with provider message |
| TC-ADM-I05 | Invalid temperature 3.0 | Client+server validation |

### 10.7 Prompt Management

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-P01 | Create draft prompt | version=1, is_active=false |
| TC-ADM-P02 | Activate prompt | Previous active deactivated |
| TC-ADM-P03 | admin delete prod prompt | 403 |
| TC-ADM-P04 | Template preview with vars | Rendered sample output |
| TC-ADM-P05 | Duplicate prompt_key | 422 validation |

### 10.8 Failover & Audit

| TC ID | 시나리오 | Expected |
|-------|----------|----------|
| TC-ADM-F01 | Failover list 24h filter | Correct row count |
| TC-ADM-F02 | Expand detail row | Full error payload |
| TC-ADM-U01 | Audit log after prompt save | Row with actor, action, resource |
| TC-ADM-U02 | Audit log tamper | Append-only, no delete UI |

---

## 11. 디자인 토큰 및 컴포넌트

### 11.1 Component Library (PHP Admin)

| Component | Class/File | Usage |
|-----------|------------|-------|
| AdminLayout | `includes/admin_layout.php` | Master shell |
| DataTable | `assets/js/datatable.js` | Sortable tables |
| KPICard | `components/kpi_card.php` | Dashboard |
| ChartWidget | Chart.js wrapper | Dashboard charts |
| StatusBadge | `components/badge.php` | active/closed/CRM/ACEP |
| ConfirmModal | Bootstrap modal | Destructive actions |
| Toast | `assets/js/toast.js` | Success/error feedback |

### 11.2 Icon Set

- Lucide icons (CDN) — sidebar, actions
- Role badges: super=purple, admin=blue, operator=gray

### 11.3 Accessibility

- WCAG 2.1 AA target (STEP 6 baseline)
- Focus ring visible on all interactive elements
- Table headers `scope="col"`
- Chart alternatives: data table toggle link
- Color not sole indicator (icon + text for status)

---

## 12. 부록

### 12.1 Figma ↔ Code Mapping

| Figma (future) | Code Path |
|----------------|-----------|
| Admin/Dashboard | `/admin/index.php` |
| Admin/Monitor | `/admin/monitor/index.php` |
| Admin/Consults | `/admin/consults/` |
| Admin/Agents | `/admin/agents/` |
| Admin/AI Settings | `/admin/settings/ai.php` |
| Admin/Prompts | `/admin/settings/prompts.php` |

### 12.2 관련 문서

- [02_Admin_Dashboard_구현명세.md](./02_Admin_Dashboard_구현명세.md)
- [03_Admin_모듈_구현명세.md](./03_Admin_모듈_구현명세.md)
- [04_Admin_API_및_권한_명세.md](./04_Admin_API_및_권한_명세.md)
- [_ADMIN_INDEX.md](./_ADMIN_INDEX.md)

### 12.3 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 6 초판 — 8 screens, RBAC, test cases |

---

*End of document*
