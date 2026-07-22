# PlusTok V3.0 — AI 문서 인덱스

**프로젝트:** PlusTok Enterprise (ACEP)  
**Version:** 3.0  
**Status:** STEP 3 Complete  
**Last Updated:** 2026-07-21  
**적용 위치:** `www/04_AI/`

---

## 문서 목록 (SSOT — 신규 명명)

| # | 문서 | 설명 | 대상 |
|---|------|------|------|
| 1 | [01_AI전략.md](01_AI전략.md) | AI Router·Failover·비용 추적·Decision Tree·Business/AI Rule | Architect, Backend, DevOps |
| 2 | [02_Prompt설계.md](02_Prompt설계.md) | 12 AI 역할 Prompt ID, System/User, JSON Schema, Rule-002 | Prompt Engineer, Backend |
| 3 | [03_AI엔진구현.md](03_AI엔진구현.md) | `ai_call()`·캐싱·로깅·Chat 통합·PHP 구현 가이드 | Backend, Full-stack |

> **아카이브:** 이전 STEP 3 명명 (`01_AI_Prompt_설계.md`, `02_AI_Failover_구현명세.md`, `03_AI_Router_통합가이드.md`)은 본 3문서로 **대체·병합**되었다. 내용은 유실 없이 신규 파일에 통합.

---

## 상위·연관 문서

| 문서 | 경로 |
|------|------|
| MASTER (PART 5 AI, PART 7 Rule) | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| DB 설계 (ai_* 테이블) | [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) |
| API 설계 (AI Domain) | [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md) |
| UI/UX AI Rule | [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §8 |
| Admin AI Settings | [07_ADMIN/03_Admin_모듈_구현명세.md](../07_ADMIN/03_Admin_모듈_구현명세.md) |
| Test (Failover FO-001~007) | [08_TEST/03_E2E_시나리오_및_체크리스트.md](../08_TEST/03_E2E_시나리오_및_체크리스트.md) §8 |
| STEP 3 작업지시서 | [_작업지시서/03_STEP3_작업지시서_AI설계.md](../_작업지시서/03_STEP3_작업지시서_AI설계.md) |

---

## 구현 소스

| 파일 | 역할 |
|------|------|
| [includes/ai.php](../includes/ai.php) | `ai_call()`, Failover, `ai_mask_pii()`, logging |
| [config/ai.php](../config/ai.php) | timeout, max_tokens, api_url 폴백 |
| [admin/settings/ai.php](../admin/settings/ai.php) | Admin Provider 설정 UI |

---

## 문서 간 의존 관계

```
01_AI전략.md ──► Failover chain, 비용, DB ai_*
       │
       ├──► 02_Prompt설계.md ──► feature ↔ Prompt ID
       │
       └──► 03_AI엔진구현.md ──► ai_call(), Chat E2E
```

---

## STEP 로드맵

| STEP | 산출물 | 상태 |
|------|--------|------|
| STEP 3 | 04_AI/* (본 폴더) | ✅ 완료 |
| STEP 4 | 05_CHAT/*, AiRouterService | 예정 |
| STEP 5 | 06_FRONTEND/* AI 패널 | 예정 |

---

**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)
