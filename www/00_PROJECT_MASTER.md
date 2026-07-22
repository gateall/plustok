# PlusTok ACEP — Project Master Document

> **프로젝트**: PlusTok AI Customer Engagement Platform (ACEP)  
> **버전**: 2.1.0 · **최종 수정**: 2026-07-21 (STEP 1~8 문서화 완료)  
> **Base Path**: `www/`  
> **Navigation**: [INDEX.md](INDEX.md) · **Quality Gate**: [_검증리포트_문서품질.md](_검증리포트_문서품질.md)

---

## PART 1 — Project Overview

PlusTok ACEP는 LG U+ PlusTok 서비스를 위한 AI 기반 고객 상담·계약 전환 플랫폼이다.

| Layer | Stack |
|-------|-------|
| Backend | PHP 8.x, MySQL 8, Redis |
| Frontend | React (Customer/Agent), PHP Admin |
| AI | OpenAI GPT-4o + Anthropic Claude fallback |
| Real-time | WebSocket (V1.5+) |

---

## PART 2 — Business Roles

### 2.1 Role Definitions

| Role | Korean | Admin Console | Description |
|------|--------|---------------|-------------|
| Customer | 고객 | ❌ | 채팅 상담 요청, 계약 문의 |
| Agent | 상담원 | ❌ | 실시간 상담, AI 추천 활용 |
| Operator | 운영자 | ✅ read-only | Live Monitor, KPI 열람 |
| Admin | 관리자 | ✅ | 상담·AI·에이전트 운영 |
| Super Admin | 슈퍼관리자 | ✅ full | API 키, 프롬프트 삭제, 감사 |

### 2.2 Admin Role Capabilities (STEP 6)

- Dashboard KPI·차트 모니터링
- Live Chat Monitor (read-only)
- Consult List (CRM + ACEP 통합)
- Agent CRUD·배정
- AI Settings (`admin/settings/ai.php`)
- Prompt Management (`ai_prompts`)
- Failover Log 조회
- System Settings·Audit Logs

상세 RBAC: [`07_ADMIN/04_Admin_API_및_권한_명세.md`](07_ADMIN/04_Admin_API_및_권한_명세.md)

---

## PART 3 — System Architecture

```
Customer App (React) ──► API Gateway ──► chat_rooms / messages
Agent App (React)    ──► API Gateway ──► ai_recommendations
Admin Console (PHP)  ──► Admin API     ──► stats / prompts / audit_logs
                              │
                              ▼
                         AI Service (OpenAI / Claude failover)
```

---

## PART 4 — Module Map

| Module | Folder | STEP |
|--------|--------|------|
| UI/UX | `02_UIUX/` | 1, 4, 6 |
| System (DB/API) | `03_SYSTEM/` | 2, 3 |
| Backend | `04_BACKEND/` | 3 |
| AI Engine | `04_AI/` | 3 |
| Frontend | `06_FRONTEND/` | 5 |
| **CRM** | **`06_CRM/`** | **5** |
| **Admin** | **`07_ADMIN/`** | **6** |
| **Real-time Dashboard** | **`08_DASHBOARD/`** | **7** |
| **Development (WBS·QA·Deploy)** | **`09_DEVELOPMENT/`** | **8** |
| Test (archive) | `08_TEST/` | 7 legacy |
| Release (archive) | `09_RELEASE/` | 8 legacy |

---

## PART 5 — Dashboard Module

### 5.1 AI 운영 센터 Dashboard

| Component | STEP 6 | V2.5 (STEP 14) |
|-----------|--------|----------------|
| KPI Cards (4) | ✅ 설계·API | + latency, cost |
| Sentiment Chart | ✅ | + ML review |
| Contract Funnel | ✅ | + A/B prompt |
| Agent Performance | ✅ | + quality score |
| Live Widgets | ✅ polling | WS push |
| React SPA | 설계 only (V2.0) | 통합 Ops Center |

문서: [`07_ADMIN/01_관리자대시보드.md`](07_ADMIN/01_관리자대시보드.md) (UI SSOT), [`08_DASHBOARD/01_대시보드설계.md`](08_DASHBOARD/01_대시보드설계.md) (실시간 파이프라인 SSOT)

### 5.2 Data Sources

- `chat_rooms` — active count, conversion
- `messages` — response time
- `ai_logs` — sentiment, contract_probability
- `ai_recommendations` — adoption rate
- `ai_failover_log` — failover widget
- `agents` — performance dimension

---

## PART 6 — STEP Roadmap

| STEP | Name | Status | Deliverable |
|------|------|--------|-------------|
| 1 | Project Setup + UI/UX Index | ✅ | `02_UIUX/`, folder structure |
| 2 | DB Design + API Architecture | ✅ | `03_SYSTEM/` |
| 3 | AI Strategy & Design | ✅ | `04_AI/` |
| 4 | WebSocket + Real-time Chat | ✅ | `05_CHAT/01`, `05_CHAT/02` |
| 5 | CRM Integration | ✅ | `06_CRM/01_CRM통합.md` |
| 6 | Admin Dashboard + Agent Mgmt | ✅ | `07_ADMIN/01~03` SSOT |
| 7 | Real-time Stats Dashboard | ✅ | `08_DASHBOARD/01_대시보드설계.md` |
| 8 | Development WBS + QA + Deploy | ✅ | `09_DEVELOPMENT/01~03` |
| 9–13 | (reserved) | ☐ | |
| 14 | AI Ops Center V2.5 | ☐ | Dashboard ML extension |

### STEP 3 Deliverables (✅ 2026-07-21)

| File | Lines | Description |
|------|-------|-------------|
| `04_AI/01_AI전략.md` | ~580 | AI Router, Failover 5-chain, 비용 추적, Decision Tree |
| `04_AI/02_Prompt설계.md` | ~1400 | 12 AI roles, Prompt ID, JSON Schema, Rule-002 |
| `04_AI/03_AI엔진구현.md` | ~450 | ai_call(), 캐싱, 로깅, Chat E2E 통합 |
| `04_AI/_AI_INDEX.md` | index | AI folder SSOT |
| `_작업지시서/03_STEP3_작업지시서_AI설계.md` | guide | STEP 3 task checklist |


### STEP 5 Deliverables (✅ 2026-07-21) — CRM 통합

| File | Lines | Description |
|------|-------|-------------|
| `06_CRM/01_CRM통합.md` | 770 | 상담 종료→CRM auto-save, 필드 매핑, API, schedules |
| `06_CRM/_CRM_INDEX.md` | index | CRM folder SSOT |
| `_작업지시서/05_STEP5-6_작업지시서_CRM_Admin.md` | guide | STEP 5-6 task checklist |

### STEP 6 Deliverables (✅ 2026-07-21) — Admin SSOT 개정

| File | Lines | Description |
|------|-------|-------------|
| `07_ADMIN/01_관리자대시보드.md` | 1049 | 5 Blocks dashboard, stats API, real-time (SSOT) |
| `07_ADMIN/02_상담원관리.md` | 487 | agents, chat_room_assignments, RBAC |
| `07_ADMIN/03_설정관리.md` | 534 | AI/chat/CRM/알림, ai.php ref |
| `07_ADMIN/04_Admin_API_및_권한_명세.md` | ~600 | Admin REST, JWT (unchanged) |
| `07_ADMIN/_ADMIN_INDEX.md` | index | SSOT + legacy archive |
| `07_ADMIN/01_관리자화면_UIUX_설계.md` | archive | → 01/02/03 |
| `07_ADMIN/02_Admin_Dashboard_구현명세.md` | archive | → 01_관리자대시보드 |
| `07_ADMIN/03_Admin_모듈_구현명세.md` | archive | → 02/03 |

### STEP 6 Deliverables (ARCHIVE — superseded)

| File | Lines | Description |
|------|-------|-------------|
| `07_ADMIN/01_관리자화면_UIUX_설계.md` | ~800 | Admin UI/UX, ASCII, RBAC, tests |
| `07_ADMIN/02_Admin_Dashboard_구현명세.md` | ~700 | KPI, charts, stats API, hybrid |
| `07_ADMIN/03_Admin_모듈_구현명세.md` | ~900 | PHP admin modules, ai.php ref |
| `07_ADMIN/04_Admin_API_및_권한_명세.md` | ~600 | Admin 10 endpoints, JWT, audit |
| `07_ADMIN/_ADMIN_INDEX.md` | index | Admin folder SSOT |
| `02_UIUX/02_관리자화면.fig.md` | stub | → 07_ADMIN/01 link |
| `_작업지시서/06_STEP6_작업지시서_Admin_Dashboard.md` | WIP guide | Dev task checklist |

### STEP 4 Deliverables (✅ 2026-07-21) — WebSocket & Chat

| File | Lines | Description |
|------|-------|-------------|
| `05_CHAT/01_WebSocket설계.md` | ~1215 | Socket.io, 15+ events, reconnect, scale |
| `05_CHAT/02_실시간동기화.md` | ~970 | Message lifecycle, read receipts, offline queue |
| `05_CHAT/_CHAT_INDEX.md` | index | Chat folder SSOT |
| `_작업지시서/04_STEP4_작업지시서_WebSocket_Chat.md` | guide | STEP 4 checklist |

### STEP 7 Deliverables (✅ 2026-07-21) — Real-time Stats Dashboard

| File | Lines | Description |
|------|-------|-------------|
| `08_DASHBOARD/01_대시보드설계.md` | 724 | StatsAggregator, KPI pipeline, poll→WS, Chart.js, TC-DASH-* |
| `08_DASHBOARD/_DASHBOARD_INDEX.md` | index | Dashboard folder SSOT |
| `08_DASHBOARD/01_고객대시보드.md` | 284 | supplementary — Customer portal (optional) |

### STEP 8 Deliverables (✅ 2026-07-21) — Development·QA·Deploy (통합)

| File | Lines | Description |
|------|-------|-------------|
| `09_DEVELOPMENT/01_개발WBS.md` | 372 | 9-week WBS, RACI, sprint, STEP 1–14 |
| `09_DEVELOPMENT/02_테스트시나리오.md` | 3516 | Test pyramid, E2E, TC-ADM/WS, 08_TEST merged |
| `09_DEVELOPMENT/03_배포운영.md` | 2587 | Cafe24/Docker, runbook, 09_RELEASE merged |
| `09_DEVELOPMENT/_DEVELOPMENT_INDEX.md` | index | Development SSOT |
| `_작업지시서/07_STEP7-8_작업지시서_Dashboard_Development.md` | guide | STEP 7-8 checklist |

### STEP 7–8 Archive (legacy preserved)

| Folder | SSOT replaced by | Note |
|--------|------------------|------|
| `08_TEST/` | `09_DEVELOPMENT/02_테스트시나리오.md` | Original 4 docs + index |
| `09_RELEASE/` | `09_DEVELOPMENT/03_배포운영.md` | Original 5 docs + index |

---

## §10 Documentation Structure

### §10.1 Root Files

| File | Purpose |
|------|---------|
| `00_PROJECT_MASTER.md` | 본 문서 |
| `CHANGELOG.md` | Product changelog (Keep a Changelog) |
| `README.md` | Quick start |

### §10.2 Module Folders

#### §10.2.1 Complete Folder Map

```
www/
├── 00_PROJECT_MASTER.md
├── 02_UIUX/
│   ├── _UIUX_INDEX.md
│   ├── 01_상담채팅화면.fig.md
│   └── 02_관리자화면.fig.md          ← stub (SSOT: 07_ADMIN/01)
├── 03_SYSTEM/
│   ├── 01_DB설계.md                  ← agents, audit_logs
│   └── 02_API설계.md                 ← 30 endpoints + Admin extension
├── 04_AI/                          ← ★ STEP 3 SSOT
│   ├── _AI_INDEX.md
│   ├── 01_AI전략.md
│   ├── 02_Prompt설계.md
│   └── 03_AI엔진구현.md
├── 04_BACKEND/
├── 05_CHAT/                          ← ★ STEP 4 SSOT
│   ├── _CHAT_INDEX.md
│   ├── 01_WebSocket설계.md
│   ├── 02_실시간동기화.md
│   └── (legacy: 01_ChatServer, 04_WebSocket_프로토콜 등)
├── 06_CRM/                           ← ★ STEP 5 SSOT
│   ├── _CRM_INDEX.md
│   └── 01_CRM통합.md
├── 06_FRONTEND/
│   └── _FRONTEND_INDEX.md
├── 07_ADMIN/                         ← ★ STEP 6 SSOT
│   ├── _ADMIN_INDEX.md
│   ├── 01_관리자대시보드.md          ← SSOT
│   ├── 02_상담원관리.md              ← SSOT
│   ├── 03_설정관리.md                ← SSOT
│   ├── 04_Admin_API_및_권한_명세.md
│   ├── 01_관리자화면_UIUX_설계.md    ← archive
│   ├── 02_Admin_Dashboard_구현명세.md ← archive
│   └── 03_Admin_모듈_구현명세.md     ← archive
├── 08_DASHBOARD/                     ← ★ STEP 7 SSOT
│   ├── _DASHBOARD_INDEX.md
│   ├── 01_대시보드설계.md            ← SSOT (실시간 통계)
│   └── 01_고객대시보드.md            ← supplementary (고객 포털)
├── 09_DEVELOPMENT/                   ← ★ STEP 8 SSOT
│   ├── _DEVELOPMENT_INDEX.md
│   ├── 01_개발WBS.md
│   ├── 02_테스트시나리오.md          ← 08_TEST 통합
│   └── 03_배포운영.md                ← 09_RELEASE 통합
├── 08_TEST/                          ← archive (STEP 7 legacy)
│   ├── _TEST_INDEX.md
│   └── 01~04 테스트 문서
├── 09_RELEASE/                       ← archive (STEP 8 legacy)
│   ├── _RELEASE_INDEX.md
│   └── 01~05 배포 문서
├── CHANGELOG.md
├── admin/
│   ├── consults/
│   ├── settings/ai.php
│   ├── monitor/
│   └── agents/
├── _작업지시서/
│   ├── 03_STEP3_작업지시서_AI설계.md
│   ├── 04_STEP4_작업지시서_WebSocket_Chat.md
│   ├── 05_STEP5-6_작업지시서_CRM_Admin.md
│   ├── 07_STEP7-8_작업지시서_Dashboard_Development.md
│   ├── 07_STEP7_작업지시서_Test_QA.md          ← legacy guide
│   └── 08_STEP8_작업지시서_Release_Deploy.md   ← legacy guide
└── includes/
    └── auth.php
```

#### §10.2.2 Documentation Complete (STEP 1~8)

| STEP | Folder / Docs | Status |
|------|---------------|:------:|
| STEP 1 | `02_UIUX/` | ✅ |
| STEP 2 | `03_SYSTEM/` — DB, API | ✅ |
| STEP 3 | `04_AI/` — 01_AI전략, 02_Prompt설계, 03_AI엔진구현 | ✅ |
| STEP 4 | `05_CHAT/` — 01_WebSocket설계, 02_실시간동기화 | ✅ |
| STEP 5 | `06_CRM/` — 01_CRM통합 + `06_FRONTEND/` | ✅ |
| STEP 6 | `07_ADMIN/` — 01~03 SSOT + legacy archive | ✅ |
| STEP 7 | `08_DASHBOARD/` — 01_대시보드설계 | ✅ |
| STEP 8 | `09_DEVELOPMENT/` — WBS, 테스트, 배포 (08_TEST·09_RELEASE 통합) | ✅ |

> **2026-07-21 문서화 완료:** ACEP Enterprise 로드맵 STEP 1~8 전체 ✅ — 약 110~140페이지 분량 SSOT + 레거시 archive.

---

## Appendix A — Glossary

| Term | Definition |
|------|------------|
| ACEP | AI Customer Engagement Platform |
| Failover | Primary AI → Fallback model switch |
| Adoption Rate | AI recommendations marked `used=true` |
| CRM | Legacy consult system pre-ACEP |

---

## Appendix B — Document Cross-Reference Index

### B.1 Admin Domain (STEP 6)

| Topic | Primary Doc | Related |
|-------|-------------|---------|
| Admin UI/UX | [07_ADMIN/01](07_ADMIN/01_관리자화면_UIUX_설계.md) | [02_UIUX/02 stub](02_UIUX/02_관리자화면.fig.md) |
| Dashboard UI | [07_ADMIN/01](07_ADMIN/01_관리자대시보드.md) | [08_DASHBOARD/01](08_DASHBOARD/01_대시보드설계.md) |
| Dashboard (archive) | [07_ADMIN/02](07_ADMIN/02_Admin_Dashboard_구현명세.md) | → 01_관리자대시보드 |
| PHP Modules | [07_ADMIN/03](07_ADMIN/03_Admin_모듈_구현명세.md) | `admin/settings/ai.php` |
| API + RBAC | [07_ADMIN/04](07_ADMIN/04_Admin_API_및_권한_명세.md) | [03_SYSTEM/01 DB](03_SYSTEM/01_DB설계.md) |
| Work Order | [_작업지시서/06](_작업지시서/06_STEP6_작업지시서_Admin_Dashboard.md) | [07_ADMIN/_INDEX](07_ADMIN/_ADMIN_INDEX.md) |

### B.2 Admin Monitor V1.5 Cross-Ref

| Doc | Section |
|-----|---------|
| [02_UIUX/01_상담채팅화면.fig.md](02_UIUX/01_상담채팅화면.fig.md) | Admin Monitor View V1.5 |
| [07_ADMIN/01 §6.2](07_ADMIN/01_관리자화면_UIUX_설계.md) | Live Chat Monitor |
| [07_ADMIN/01 §7](07_ADMIN/01_관리자화면_UIUX_설계.md) | Mobile read-only Monitor |

### B.3 API Endpoint Count

| Domain | Count | STEP |
|--------|-------|------|
| Customer | ~10 | 4 |
| Agent | ~10 | 5 |
| AI Shared | ~10 | 3-4 |
| Admin | 10 | 6 |
| **Total** | **~40** | |

Admin endpoints: [`07_ADMIN/04 §2`](07_ADMIN/04_Admin_API_및_권한_명세.md)

### B.4 Test & QA Domain (STEP 8 — SSOT in 09_DEVELOPMENT)

| Topic | Primary Doc | Related |
|-------|-------------|---------|
| Test SSOT | [09_DEVELOPMENT/02](09_DEVELOPMENT/02_테스트시나리오.md) | [09_DEVELOPMENT/_INDEX](09_DEVELOPMENT/_DEVELOPMENT_INDEX.md) |
| Test Strategy (archive) | [08_TEST/01](08_TEST/01_테스트_전략_및_범위.md) | → 09_DEVELOPMENT/02 |
| E2E Checklists | [09_DEVELOPMENT/02 §E2E](09_DEVELOPMENT/02_테스트시나리오.md) | [02_UIUX/01 TC](02_UIUX/01_상담채팅화면.fig.md) |
| Release Gate | [09_DEVELOPMENT/02 §QA Gate](09_DEVELOPMENT/02_테스트시나리오.md) | [09_DEVELOPMENT/03](09_DEVELOPMENT/03_배포운영.md) |
| WebSocket QA | [05_CHAT/01 §12](05_CHAT/01_WebSocket설계.md) | TC-WS-P01~10 |
| Admin QA | [07_ADMIN/01 §10](07_ADMIN/01_관리자대시보드.md) | TC-ADM-* |
| Work Order | [_작업지시서/07-8](_작업지시서/07_STEP7-8_작업지시서_Dashboard_Development.md) | [08_TEST/_TEST_INDEX](08_TEST/_TEST_INDEX.md) (archive) |

### B.5 Release & Deploy Domain (STEP 8 — SSOT in 09_DEVELOPMENT)

| Topic | Primary Doc | Related |
|-------|-------------|---------|
| Deploy SSOT | [09_DEVELOPMENT/03](09_DEVELOPMENT/03_배포운영.md) | [09_DEVELOPMENT/_INDEX](09_DEVELOPMENT/_DEVELOPMENT_INDEX.md) |
| Deploy Architecture (archive) | [09_RELEASE/01](09_RELEASE/01_배포_아키텍처_및_환경.md) | → 09_DEVELOPMENT/03 |
| Docker / Nginx (archive) | [09_RELEASE/02](09_RELEASE/02_Docker_및_Nginx_구성.md) | [05_CHAT/_INDEX](05_CHAT/_CHAT_INDEX.md) |
| Cafe24 FTP (archive) | [09_RELEASE/03](09_RELEASE/03_FTP_Cafe24_배포_가이드.md) | [09_DEVELOPMENT/03](09_DEVELOPMENT/03_배포운영.md) |
| Release Runbook (archive) | [09_RELEASE/05](09_RELEASE/05_릴리스_런북.md) | [09_DEVELOPMENT/03](09_DEVELOPMENT/03_배포운영.md) |
| WBS | [09_DEVELOPMENT/01](09_DEVELOPMENT/01_개발WBS.md) | STEP 1–14 milestones |

### B.7 CRM Domain (STEP 5)

| Topic | Primary Doc | Related |
|-------|-------------|---------|
| CRM Auto-save | [06_CRM/01](06_CRM/01_CRM통합.md) | [05_CHAT/02](05_CHAT/02_Backend_Chat_API_구현명세.md) |
| Field mapping | [06_CRM/01 §5](06_CRM/01_CRM통합.md) | [03_SYSTEM/01 DB](03_SYSTEM/01_DB설계.md) |
| Work Order | [_작업지시서/05-6](_작업지시서/05_STEP5-6_작업지시서_CRM_Admin.md) | [06_CRM/_INDEX](06_CRM/_CRM_INDEX.md) |

### B.8 Dashboard Domain (STEP 7)

| Topic | Primary Doc | Related |
|-------|-------------|---------|
| Real-time pipeline | [08_DASHBOARD/01](08_DASHBOARD/01_대시보드설계.md) | [07_ADMIN/01](07_ADMIN/01_관리자대시보드.md) |
| Customer portal | [08_DASHBOARD/01_고객](08_DASHBOARD/01_고객대시보드.md) | supplementary |

### B.6 DB Tables (Admin-relevant)

| Table | Doc | Admin Usage |
|-------|-----|-------------|
| `agents` | 01_DB설계 | Agent mgmt |
| `audit_logs` | 01_DB설계 | Audit viewer |
| `ai_prompts` | 01_DB설계 | Prompt editor |
| `ai_failover_log` | 01_DB설계 | Failover viewer |
| `chat_rooms` | 01_DB설계 | Consult list, dashboard |
| `chat_room_assignments` | 01_DB설계 | Agent assignment |
| `ai_logs` | 01_DB설계 | Sentiment, analyze |
| `ai_recommendations` | 01_DB설계 | Adoption rate |

---

## Appendix C — Change Log

| Version | Date | Changes |
|---------|------|---------|
| 2.1.0 | 2026-07-21 | STEP 7-8 ✅ — 08_DASHBOARD/, 09_DEVELOPMENT/, legacy archive, §10.2.1 |
| 2.0.0 | 2026-07-21 | STEP 5-6 ✅ — 06_CRM/, 07_ADMIN SSOT 3종 |
| 1.9.0 | 2026-07-21 | STEP 8 ✅ — 09_RELEASE/ docs, CHANGELOG.md, §10.2.2, Appendix B.5 |
| 1.8.0 | 2026-07-21 | STEP 3 ✅ — 04_AI/ 신규 명명 (01_AI전략, 02_Prompt설계, 03_AI엔진구현) |
| 1.7.0 | 2026-07-21 | STEP 7 ✅ — 08_TEST/ docs, §10.2.1, Appendix B.4 |
| 1.6.0 | 2026-07-21 | STEP 6 ✅ — 07_ADMIN/ docs, §10.2.1, Appendix B |
| 1.5.0 | — | STEP 5 Frontend |
| 1.0.0 | — | Initial project master |

---

*End of Project Master Document*
