# 06_CRM — CRM 통합 모듈 인덱스

> **PlusTok ACEP** · STEP 5 · CRM Zero-Input  
> **SSOT**: 본 폴더(`06_CRM/`)가 상담→CRM 자동 저장의 단일 진실 공급원입니다.

---

## 1. 문서 목록

| # | 문서 | 목적 | 분량 |
|---|------|------|------|
| 01 | [01_CRM통합.md](./01_CRM통합.md) | 상담 종료→CRM 자동 저장, 필드 매핑, API, 후속 일정 | ~700 lines |

---

## 2. 관련 프로젝트 문서

| 영역 | 경로 | CRM 연관 |
|------|------|----------|
| 프로젝트 마스터 | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) | STEP 5 roadmap |
| DB 설계 | [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) | customers, chat_rooms |
| AI Prompt | [04_AI/02_Prompt설계.md](../04_AI/02_Prompt설계.md) | summarize, analyze |
| Chat API | [05_CHAT/02_Backend_Chat_API_구현명세.md](../05_CHAT/02_Backend_Chat_API_구현명세.md) | PUT close |
| Admin Dashboard | [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) | CRM KPI |
| 작업지시서 | [_작업지시서/05_STEP5_STEP6_작업지시서_CRM_Admin.md](../_작업지시서/05_STEP5_STEP6_작업지시서_CRM_Admin.md) | STEP 5-6 checklist |

---

## 3. 레거시 PHP 참조

| 경로 | 역할 |
|------|------|
| `admin/consults/index.php` | 상담 목록 |
| `admin/consults/ai_summary.php` | AI 요약 (→ auto) |
| `admin/consults/ai_analyze.php` | AI 분석 (→ auto) |
| `includes/functions.php` | consult_no 생성 |

---

## 4. STEP 5 범위

| 기능 | V1.0 | V4.0+ |
|------|------|-------|
| Auto CRM save on close | ✅ | |
| schedules follow-up | ✅ | |
| Email draft | ✅ manual send | auto send |
| SMS/Kakao | — | ✅ |

---

## 5. 변경 이력

| 날짜 | 버전 | 변경 |
|------|------|------|
| 2026-07-21 | 1.0.0 | STEP 5 CRM 문서 초판 |
