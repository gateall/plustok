# STEP 3 작업지시서 — AI Prompt & Failover

> **레거시:** 본 파일은 이전 STEP 3 작업지시서이다. **현재 SSOT:** [_작업지시서/03_STEP3_작업지시서_AI설계.md](03_STEP3_작업지시서_AI설계.md)  
> 산출물 링크는 신규 04_AI 명명(`01_AI전략.md`, `02_Prompt설계.md`, `03_AI엔진구현.md`)으로 갱신되었다.

**프로젝트:** PlusTok Enterprise (ACEP)  
**STEP:** 3  
**상태:** ✅ **완료** (2026-07-21)  
**적용 위치:** `www/04_AI/`, `www/includes/ai.php`

---

## 목표

MASTER PART 5 (AI Strategy), PART 7 (AI Rule) 및 기존 `includes/ai.php` 구현을 기반으로 AI Prompt 설계·Failover 명세·Router 통합 문서를 작성한다.

---

## 산출물 체크리스트

| # | 산출물 | 상태 | 링크 |
|---|--------|:----:|------|
| 1 | AI Prompt 설계 (10 역할, 600+ lines) | ✅ | [04_AI/02_Prompt설계.md](../04_AI/02_Prompt설계.md) |
| 2 | AI Failover 구현명세 (ai.php 공식화) | ✅ | [04_AI/01_AI전략.md](../04_AI/01_AI전략.md) |
| 3 | AI Router 통합가이드 | ✅ | [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md) |
| 4 | AI 문서 인덱스 | ✅ | [04_AI/_AI_INDEX.md](../04_AI/_AI_INDEX.md) |
| 5 | MASTER PART 10 STEP 3 갱신 | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| 6 | MASTER Appendix B 04_AI 링크 | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §부록 B |
| 7 | MASTER §10.2.1 폴더 구조 04_AI/ | ✅ | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) §10.2.1 |

---

## 품질 기준

| 항목 | 기준 | 결과 |
|------|------|:----:|
| MASTER PART 5.1 10 AI 역할 | 각 Prompt ID + System/User + JSON Schema | ✅ |
| Rule-002 Prompt Versioning | ai_prompts 테이블 매핑 | ✅ |
| Rule-001 Failover | anthropic→openai→gemini→grok→deepseek | ✅ |
| ai.php 정합 | invent 금지, 구현 기준 명세 | ✅ |
| PII | ai_mask_pii() 참조 | ✅ |
| 한국어 Markdown | substantive | ✅ |
| 상대 링크 (www root) | 04_AI/*, 03_SYSTEM/*, 00_MASTER | ✅ |

---

## 참조 문서 (입력)

- [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 5, 7
- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md) ai_* 테이블
- [02_UIUX/01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) §8 AI Rule
- [includes/ai.php](../includes/ai.php)

---

## STEP 4 선행 과제 (다음 단계)

- [ ] `AiRouterService` PHP 클래스 구현
- [ ] `POST /api/v1/chats/{id}/messages` AI trigger hook
- [ ] Redis Rate Limit (Rule-005) — Session debounce 대체
- [ ] `ai_prompts` DB 시드 (10 Prompt INSERT)
- [ ] WebSocket `ai:update` Redis pub/sub 연동

---

## 완료 확인

```
STEP 3: AI Prompt, Failover 구현 문서화 — ✅ 2026-07-21
담당: AI Platform Team
다음 STEP: STEP 4 Chat Server, Backend
```

---

**상위:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) PART 10.2
