# 07_ADMIN — 관리자·대시보드 모듈 인덱스

> **PlusTok ACEP** · STEP 6 · Admin + Dashboard  
> **SSOT**: 본 폴더(`07_ADMIN/`) · **2026-07-21 갱신**: STEP 6 SSOT 4종, 레거시 archive

---

## 1. SSOT 문서 (Primary)

| # | 문서 | 설명 | 분량 |
|---|------|------|------|
| 01 | [01_관리자대시보드.md](./01_관리자대시보드.md) | AI 운영 대시 KPI·차트·5 Blocks·stats API **UI SSOT** | ~720 lines |
| 02 | [02_상담원관리.md](./02_상담원관리.md) | agents, assignments, 스케줄, RBAC | ~580 lines |
| 03 | [03_설정관리.md](./03_설정관리.md) | AI/chat/CRM/알림, ai.php SSOT | ~560 lines |
| 04 | [04_Admin_API_및_권한_명세.md](./04_Admin_API_및_권한_명세.md) | REST ~10 endpoints, JWT | ~600 lines |

---

## 2. Archive (레거시 — 참고용)

> 아래 문서는 STEP 6 초기 산출물입니다. **신규 구현은 §1 SSOT를 따르세요.**

| Archive | Superseded By | 메모 |
|---------|---------------|------|
| [01_관리자화면_UIUX_설계.md](./01_관리자화면_UIUX_설계.md) | 01_관리자대시보드 + 02_에이전트관리 + 03_설정관리 | IA·ASCII still useful |
| [02_Admin_Dashboard_구현명세.md](./02_Admin_Dashboard_구현명세.md) | [01_관리자대시보드.md](./01_관리자대시보드.md) | KPI SQL retained |
| [03_Admin_모듈_구현명세.md](./03_Admin_모듈_구현명세.md) | 02 + 03 + 04 | PHP folder map |

---

## 3. 교차 참조

| 영역 | 링크 |
|------|------|
| Dashboard 아키텍처 | [08_DASHBOARD/01_대시보드설계.md](../08_DASHBOARD/01_대시보드설계.md) |
| CRM | [06_CRM/_CRM_INDEX.md](../06_CRM/_CRM_INDEX.md) |
| DB | [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) |
| AI | [04_AI/_AI_INDEX.md](../04_AI/_AI_INDEX.md) |
| 작업지시 | [_작업지시서/05_STEP5_STEP6_작업지시서_CRM_Admin.md](../_작업지시서/05_STEP5_STEP6_작업지시서_CRM_Admin.md) |

---

## 4. PHP 진입 경로

| Path | Role |
|------|------|
| admin/settings/ai.php | AI settings |
| admin/consults/ | CRM list + ai_* |
| admin/agents/ | Agent mgmt (STEP 6) |
| admin/index.php | Dashboard (UI SSOT: 01) |

---

## 5. 변경 이력

| 날짜 | 버전 | 내용 |
|------|------|------|
| 2026-07-21 | 2.0.0 | STEP 5-6 SSOT 4종, archive 분리 |
| 2026-07-21 | 1.0.0 | STEP 6 개편 |