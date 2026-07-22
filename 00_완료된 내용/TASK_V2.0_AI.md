# TASK_V2.0_AI.md — AI 상담 어시스턴트 Phase 2 (상담 업무 자동화)

- **완료일:** 2026-07-21 (STEP 5 완료, E2E 검증 건너뜀)

## 📌 목표
AI가 상담을 단순히 "도와주는(요약·초안)" 수준을 넘어, **상담 접수 즉시 종합 분석하여 업무를 자동화(분류·긴급도·점수·태그·감정)하고 우선순위 및 태스크를 스마트하게 배정하는 단계**입니다.

---

## ⭐ 구현 우선순위 및 STEP 9 (AI 자동 분석 엔진) 상세

### 1. AI 상담 자동 분류 (Auto Classification)
- 상담이 접수되거나 분석 요청 시 자동으로 다음 중 하나로 분류 및 DB 저장 (`consults.category_ai`):
  - `인터넷`, `대표번호`, `CCTV`, `TV`, `홈페이지`, `플레이스`, `쇼핑몰`, `기타`
- AI 확신도(`confidence`, 0~100) 함께 산출.

### 2. 긴급도 판단 (Priority)
- AI가 상담 내용(예: "긴급", "오늘 당장", "즉시 설치", "계약예정", "급함", 장애/불만 등)을 분석하여 자동 지정 (`consults.priority`):
  - `LOW` (일반 문의/여유)
  - `NORMAL` (보통)
  - `HIGH` (빠른 응대 필요/계약 육박)
  - `URGENT` (당일/즉시/긴급 장애/불만 이탈 위험)
- 관리자 화면(목록 및 상세)에 빨간색/주황색 등 직관적 배지로 표시.

### 3. 계약 가능성 점수 (Lead Score)
- AI가 고객의 `예산 확보 여부`, `구체적인 구매 의사`, `문의 수준`, `도입 일정 구체성`을 분석하여 0~100점(`consults.lead_score`) 자동 부여.
- 예: `92점` (★★★★☆ 고가망 고객, 즉시 영업 투입).

### 4. 자동 태그 생성 (Auto Tags)
- 상담의 핵심 키워드, 지역, 조건, 통신사 등을 추출하여 해시태그 목록(`consults.tags`) 자동 생성.
- 예: `#KT #인터넷 #1기가 #신규 #법인 #서울`
- 관리자 목록 및 상세에서 태그 클릭/검색 가능.

### 5. 감정 분석 (Sentiment)
- 고객 문의 뉘앙스 및 어조를 분석하여 자동 표시 (`consults.sentiment`):
  - `POSITIVE` / `😊` (칭찬/호의적/구매 열의)
  - `NEUTRAL` / `😐` (일반 문의/중립)
  - `NEGATIVE` / `😡` (화남/불만/급함/이탈 우려)

---

## 🛠️ DB 스키마 추가 (`consults` 테이블 확장)
```sql
ALTER TABLE consults
  ADD COLUMN category_ai    VARCHAR(50)  DEFAULT NULL AFTER ai_summary_at,
  ADD COLUMN lead_score     INT          DEFAULT 0    AFTER category_ai,
  ADD COLUMN priority       VARCHAR(20)  DEFAULT 'NORMAL' AFTER lead_score,
  ADD COLUMN sentiment      VARCHAR(20)  DEFAULT 'NEUTRAL' AFTER priority,
  ADD COLUMN tags           VARCHAR(255) DEFAULT NULL AFTER sentiment,
  ADD COLUMN ai_analyzed_at DATETIME     DEFAULT NULL AFTER tags;
```
- 인덱스 권장: `INDEX idx_consults_priority (priority)`, `INDEX idx_consults_score (lead_score)`

---

## ⚡ AI Prompt & 호출 최적화 (1회 호출 JSON 반환)
- **Claude Opus (`claude-opus-4-8`) 1회 호출로 5가지 분석(`category_ai`, `priority`, `lead_score`, `sentiment`, `tags`)을 동시에 JSON 형식으로 반환**받아 비용을 절감하고 응답 시간을 극대화.
- PII 마스킹(`ai_mask_pii()`) 거친 안전한 데이터만 전송.
- `ai_logs` 테이블에 `feature = 'analyze'`로 토큰 및 처리 상태 기록.

---

## 🖥️ 관리자 화면 개선 계획 (STEP 9 ~ STEP 14)

### STEP 9. AI 자동 분석 엔진 — ✅ 구현 완료
1. **상담 종합 분석 엔드포인트 (`admin/consults/ai_analyze.php`)** — ✅
2. **상담 상세 화면 (`admin/consults/view.php`)** — ✅
3. **상담 목록 화면 (`admin/consults/index.php`)** — ✅
4. **Dashboard 연동 (`admin/dashboard.php`)** — ✅

---

## 📅 향후 권장 개발 로드맵
- **STEP 9 (AI 자동 분석 엔진)** — ✅ 완료
- **STEP 10 (담당자 자동 추천/배정)**: AI 분류 및 전문성에 따른 자동 추천
- **STEP 11 (일정 자동 생성)**: 계약 가능성 80점 이상 고객 Google Calendar 자동 등록
- **STEP 12 (AI Follow-up)**: 24시간 미응답 고객 대상 자동 리마인드 메시지 초안 생성
- **STEP 13 (실시간 알림)**: 긴급/고가망 발생 시 카카오/이메일/Slack 즉시 알림
- **STEP 14 (AI 종합 대시보드)**: AI 운영 센터 업그레이드
