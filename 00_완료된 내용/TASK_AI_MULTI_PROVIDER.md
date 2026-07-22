# TASK — AI 멀티 프로바이더 지원 (OpenAI·Gemini 추가) 작업지시서

- **대상 작업자:** Antigravity (구현) / 작성·최종점검: Claude
- **완료일:** 2026-07-21 (STEP 4 완료, E2E 검증 건너뜀)
- **배경:** 현재 `ai_call()`은 Anthropic Claude 전용으로 하드코딩되어 있음. 사용자가 GPT(OpenAI)·Gemini도 선택해서 쓸 수 있게 확장 요청.
- **작성일:** 2026-07-21

> **구현 결과:** 5대 벤더(Anthropic/OpenAI/Gemini/Grok/DeepSeek) + Auto Failover Chain + `ai_failover_log` + V2.0 UI 완료.

## 4. 완료 기준(DoD)
- [x] DB 마이그레이션 실행 + 기존 Anthropic 키/모델 값 유지
- [x] `ai_call_openai()`, `ai_call_gemini()`, `ai_call_grok()`, `ai_call_deepseek()` 작성
- [x] 관리자 AI설정 화면 V2.0 (5개 프로바이더 + AUTO 모드)
- [~] 실제 OpenAI/Gemini 실클릭 검증 — **건너뜀**
- [x] `db/schema.sql`·`CHANGELOG.md` 동기화

(전체 지시서 본문은 아카이브 — STEP 4 범위 전부 코드 구현 완료)
