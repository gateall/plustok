# PlusTok ACEP — Test & QA Index (STEP 7)

> **프로젝트**: PlusTok AI Customer Engagement Platform (ACEP)  
> **STEP**: 7 · Testing + QA  
> **작성일**: 2026-07-21  
> **Archive:** 본 폴더는 **레거시 원본**입니다. QA Gate SSOT는 [`09_DEVELOPMENT/02_테스트시나리오.md`](../09_DEVELOPMENT/02_테스트시나리오.md) (STEP 8 통합).  
> **상위 문서**: [`00_PROJECT_MASTER.md`](../00_PROJECT_MASTER.md) PART 8 Quality

---

## 1. 문서 목록

| # | 문서 | 목적 | 대상 독자 | 예상 분량 |
|---|------|------|-----------|-----------|
| 01 | [테스트 전략 및 범위](01_테스트_전략_및_범위.md) | Test Pyramid, 커버리지, 환경, 역할, 회귀 정책 | QA Lead, Architect | ~600 lines |
| 02 | [단위·통합 테스트 명세](02_단위_통합_테스트_명세.md) | PHPUnit, Vitest, API/WS 통합, AI Mock | Developer, QA Automation | ~800 lines |
| 03 | [E2E 시나리오 및 체크리스트](03_E2E_시나리오_및_체크리스트.md) | 수동 QA, TC 통합, 보안·성능 스모크 | QA, Operator | ~900 lines |
| 04 | [QA 릴리스 게이트](04_QA_릴리스_게이트.md) | Pre-release, 배포 검증, 롤백, Known Issues | Release Manager, Operator | ~400 lines |

---

## 2. 작업지시서

| 문서 | 설명 |
|------|------|
| [`_작업지시서/07_STEP7_작업지시서_Test_QA.md`](../_작업지시서/07_STEP7_작업지시서_Test_QA.md) | STEP 7 개발·QA 태스크 체크리스트 |

---

## 3. 테스트 범위 요약 (V1.0 MVP)

### 3.1 In Scope

| 영역 | 테스트 유형 | 참조 |
|------|-------------|------|
| Customer/Agent React Chat | Unit + IT + E2E | [06_FRONTEND/04](../06_FRONTEND/04_ChatScreen_통합_구현가이드.md) |
| PHP Backend Services | Unit + Integration | [03_SYSTEM/02](../03_SYSTEM/02_API설계.md) |
| Node Chat Server | Unit + WS Integration | [05_CHAT/04](../05_CHAT/04_WebSocket_프로토콜_명세.md) |
| AI Failover | Unit + Manual | [04_AI/01](../04_AI/01_AI전략.md) |
| Admin Console | E2E + API | [07_ADMIN/01](../07_ADMIN/01_관리자화면_UIUX_설계.md) |
| REST API 30 + Admin 10 | Integration | [03_SYSTEM/02](../03_SYSTEM/02_API설계.md), [07_ADMIN/04](../07_ADMIN/04_Admin_API_및_권한_명세.md) |

### 3.2 Out of Scope (V1.0)

- Playwright/Cypress 자동 E2E (V1.5 — 수동 QA 우선)
- 1,000 WS 부하 테스트 (Staging 한정 스모크)
- 멀티테넌트·SSO·Public API
- 음성/화상 상담

---

## 4. 커버리지 목표 (MASTER PART 8.4)

| 유형 | 목표 | 측정 도구 |
|------|------|-----------|
| 단위 (Service, Repository) | **≥ 80%** | PHPUnit coverage, Vitest coverage |
| 통합 (REST + WS) | **100%** 엔드포인트·이벤트 | Postman/Newman, custom WS harness |
| E2E (핵심 플로우) | 접수→채팅→AI→종료→CRM | 수동 QA + 체크리스트 |
| UI TC | TC-001~007 + IT-01~72 + TC-ADM-* | [03_E2E](03_E2E_시나리오_및_체크리스트.md) |

---

## 5. 환경 매트릭스

| 환경 | URL | 용도 | AI 호출 |
|------|-----|------|---------|
| **local** | `http://localhost:8080` | 개발 단위·통합 | Mock (CI), Sandbox key (dev) |
| **staging** | `https://plustok.mycafe24.com` | QA E2E, 부하 스모크 | Sandbox / Mock |
| **production** | 운영 도메인 | Smoke only (배포 후) | Live keys |

---

## 6. TC ID 네임스페이스

| Prefix | 출처 | 문서 |
|--------|------|------|
| `TC-00x` | UI/UX 상담채팅 | [02_UIUX/01 §10](../02_UIUX/01_상담채팅화면.fig.md) |
| `IT-xx` | Frontend 통합 | [06_FRONTEND/04 §11](../06_FRONTEND/04_ChatScreen_통합_구현가이드.md) |
| `TC-ADM-*` | Admin UI | [07_ADMIN/01 §10](../07_ADMIN/01_관리자화면_UIUX_설계.md) |
| `TC-WS-Pxx` | WebSocket | [05_CHAT/04 §12](../05_CHAT/04_WebSocket_프로토콜_명세.md) |
| `FO-00x` | AI Failover | [04_AI/01 §10](../04_AI/01_AI전략.md) |
| `API-xxx` | REST Integration | [02_단위_통합 §4](02_단위_통합_테스트_명세.md) |
| `E2E-xx` | End-to-end | [03_E2E](03_E2E_시나리오_및_체크리스트.md) |

---

## 7. 선행 STEP 의존성

| STEP | 산출물 | 테스트 연계 |
|------|--------|-------------|
| STEP 2 | DB, API 설계 | Fixture 스키마, API-001~030 |
| STEP 3 | AI Prompt, Failover | FO-001~007, AI Mock |
| STEP 4 | Chat Server, WS | TC-WS-P01~10 |
| STEP 5 | React Frontend | IT-01~72, Vitest 11 components |
| STEP 6 | Admin | TC-ADM-*, A-01~A-10 |
| **STEP 7** | **본 폴더** | QA Sign-off → STEP 8 Release |

---

## 8. 릴리스 게이트 연계

STEP 7 완료 후 [04_QA_릴리스_게이트.md](04_QA_릴리스_게이트.md) 체크리스트 pass → STEP 8 배포 핸드오프.

```
STEP 7 QA Sign-off
    ↓
04_QA_릴리스_게이트 Pre-release Checklist
    ↓
STEP 8 Deployment (FTP, migration, smoke)
```

---

## 9. 관련 문서 Cross-Reference

| Topic | Primary | Test Doc |
|-------|---------|----------|
| Quality SLA | [MASTER PART 8](../00_PROJECT_MASTER.md) | [01 §3](01_테스트_전략_및_범위.md) |
| API 30 endpoints | [03_SYSTEM/02](../03_SYSTEM/02_API설계.md) | [02 §4](02_단위_통합_테스트_명세.md) |
| Admin API 10 | [07_ADMIN/04](../07_ADMIN/04_Admin_API_및_권한_명세.md) | [02 §5](02_단위_통합_테스트_명세.md) |
| UI Test Cases | [02_UIUX/01 §10](../02_UIUX/01_상담채팅화면.fig.md) | [03 §5](03_E2E_시나리오_및_체크리스트.md) |
| Failover | [04_AI/01](../04_AI/01_AI전략.md) | [03 §8](03_E2E_시나리오_및_체크리스트.md) |
| WebSocket | [05_CHAT/04](../05_CHAT/04_WebSocket_프로토콜_명세.md) | [02 §7](02_단위_통합_테스트_명세.md) |

---

## 10. 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 7 초판 — 08_TEST/ 4문서 + Index |

---

*End of Test Index — STEP 7 SSOT*
