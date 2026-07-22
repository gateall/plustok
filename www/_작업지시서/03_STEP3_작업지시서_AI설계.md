# 🎯 PlusTok V3.0 AI 설계
## STEP 3 작업지시서 (www 폴더 적용)

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**적용 위치:** E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www/  
**단계:** STEP 3 - AI Strategy & Design  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  
**상태:** 작업 준비 완료  

---

## 📌 STEP 3 목표

**3개 문서 작성 (www/04_AI 폴더 내)**
1. ✅ `www/04_AI/01_AI전략.md` - AI Router & Failover 메커니즘
2. ✅ `www/04_AI/02_Prompt설계.md` - 기능별 프롬프트 정의
3. ✅ `www/04_AI/03_AI엔진구현.md` - 실제 구현 가이드

**산출물:** 약 40~50페이지 규모  
**예상 소요시간:** 4~6시간  
**전제조건:** STEP 1, 2 완료

---

## 📂 폴더 구조 (www 기준)

```
www/
├── 00_PROJECT_MASTER.md          ← STEP 1 참조
├── 02_UIUX/                      ← STEP 1 참조
├── 03_SYSTEM/                    ← STEP 2 참조
│
├── 04_AI/                        ← STEP 3 작성 대상
│   ├── 01_AI전략.md              ← 작성 대상 1
│   ├── 02_Prompt설계.md          ← 작성 대상 2
│   ├── 03_AI엔진구현.md          ← 작성 대상 3
│   └── _AI_INDEX.md
│
├── 05_CHAT/                      ← STEP 4 (향후)
├── 06_CRM/                       ← STEP 5 (향후)
├── 07_ADMIN/                     ← STEP 6 (향후)
├── 08_DASHBOARD/                 ← STEP 7 (향후)
├── 09_DEVELOPMENT/               ← STEP 8 (향후)
│
└── (기존 www 구조)
    ├── admin/
    ├── api/
    └── ...
```

---

# 🔴 작업 1: www/04_AI/01_AI전략.md 작성

## 목표
AI Router의 설계와 Failover 메커니즘 정의  
Claude → GPT → Gemini → Grok 자동 전환 로직

## 파일 위치
```
www/04_AI/01_AI전략.md
```

## 문서 헤더
```markdown
# ACEP (PlusTok Enterprise) — AI 전략 및 라우팅

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Design Phase (STEP 3)  
**Created:** 2026-07-21  
**Owner:** AI Architecture Team  

**적용 위치:** `www/` (E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 상위 문서 | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) (PART 5, 7) |
| 참조 문서 | [02_Prompt설계.md](../04_AI/02_Prompt설계.md), [03_AI엔진구현.md](../04_AI/03_AI엔진구현.md) |
| 기본 구현 | Claude (Anthropic) 우선, Failover 4단계 |
| 멀티 프로바이더 | OpenAI GPT, Google Gemini, xAI Grok |
| 로깅 | ai_logs, ai_failover_log (DB 설계 참조) |

---
```

## 작성 순서 (11개 섹션)

### 1. 목적(Purpose)
- AI Router를 통한 실시간 멀티프로바이더 지원
- 자동 Failover로 99.99% 가용성 확보
- 비용 최적화 (저비용 모델부터 시도)
- PLUS톡 V2.0 검증된 Failover 체인 정형화

### 2. 범위(Scope)
- 4개 AI 프로바이더 지원 (Claude, GPT, Gemini, Grok)
- Failover 메커니즘 (자동 전환)
- 프로바이더별 설정 관리 (API Key, 모델명)
- 응답 타입 통일 (표준화)
- 비용 추적 및 최적화

### 3. 요구사항
- AI 응답 시간: 2초 이내
- Failover 성공률: 99.99%
- 응답 형식 통일 (프로바이더별 상관없이)
- 개별 프로바이더 비활성화 지원
- 토큰 사용량 추적
- 프롬프트 버전 관리

### 4. AI 전략 구조

#### 4-1. AI Provider 우선순위

```
┌──────────────────────────────────────────────┐
│         AI Router Decision Tree               │
├──────────────────────────────────────────────┤
│                                              │
│ 1단계: 활성 프로바이더 결정                  │
│ → DB 또는 환경변수에서 읽음                  │
│ → active_provider = 'claude'                 │
│                                              │
│ 2단계: API Key 확인                          │
│ → ai_keys 테이블 조회                        │
│ → 없으면 다음 단계로                        │
│                                              │
│ 3단계: 첫 번째 프로바이더 호출              │
│ ┌─────────────────┐                         │
│ │ Claude (Opus)   │ timeout: 10s             │
│ │ cost: 가장 높음  │ max_tokens: 1024        │
│ └────────┬────────┘                         │
│          │                                  │
│          ├─ 성공 → 응답 반환 + 로그        │
│          │                                  │
│          └─ 실패 (timeout/error/429)       │
│                ↓                            │
│ 4단계: Failover 체인 시작                   │
│ ┌─────────────────┐                        │
│ │ OpenAI GPT-4    │ (2번째 시도)            │
│ │ cost: 중간      │                        │
│ └────────┬────────┘                        │
│          │                                 │
│          ├─ 성공 → 응답 반환 + Failover로그│
│          │                                 │
│          └─ 실패                          │
│                ↓                           │
│ ┌─────────────────┐                       │
│ │ Google Gemini   │ (3번째 시도)           │
│ │ cost: 저가      │                       │
│ └────────┬────────┘                       │
│          │                                │
│          ├─ 성공 → 응답 반환             │
│          │                                │
│          └─ 실패                         │
│                ↓                          │
│ ┌─────────────────┐                      │
│ │ xAI Grok        │ (4번째 시도)          │
│ │ cost: 가장 저가  │                      │
│ └────────┬────────┘                      │
│          │                               │
│          ├─ 성공 → 응답 반환             │
│          │                               │
│          └─ 모두 실패 → 에러 응답        │
│              "AI_UNAVAILABLE"            │
│                                          │
└──────────────────────────────────────────┘
```

#### 4-2. 프로바이더별 설정

```
Claude (Anthropic) — 기본값
- API: https://api.anthropic.com/v1/messages
- 모델: claude-3-5-sonnet-20241022 (기본)
- 대체: claude-3-opus-20240229, claude-3-haiku-20240307
- Max Tokens: 1024
- Temperature: 0.7
- 비용: $3-15/1M 토큰 (모델별)

OpenAI GPT
- API: https://api.openai.com/v1/chat/completions
- 모델: gpt-4-turbo (기본)
- 대체: gpt-4o, gpt-4o-mini
- Max Tokens: 1024
- Temperature: 0.7
- 비용: $0.01-0.03/1K 토큰

Google Gemini
- API: https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent
- 모델: gemini-1.5-pro (기본)
- 대체: gemini-1.5-flash, gemini-pro
- Max Tokens: 1024
- Temperature: 0.7
- 비용: $0.00075-0.003/1K 토큰

xAI Grok
- API: https://api.x.ai/v1/chat/completions
- 모델: grok-2 (기본)
- Max Tokens: 1024
- Temperature: 0.7
- 비용: $2-8/1M 토큰
```

### 5. DB 설계 참조
- `ai_keys` 테이블: 프로바이더별 API Key 저장
- `ai_logs` 테이블: 모든 호출 로그
- `ai_failover_log` 테이블: Failover 이벤트 추적
- `prompts` 테이블: 버전별 프롬프트 저장

### 6. API 통신

```
요청 (상담원 클릭 → AI 추천):
POST /api/v1/ai/recommend
{
  "room_id": 123,
  "message_id": 456,
  "request_type": "answer_suggestion",
  "content": "인터넷 설치비 얼마인가요?"
}

응답 (성공):
{
  "success": true,
  "data": {
    "provider": "claude",
    "recommendations": [
      { "type": "answer", "text": "설치비는 무료입니다" },
      { "type": "faq", "text": "FAQ: 설치비?" }
    ],
    "latency_ms": 1200
  },
  "timestamp": "2026-07-21T14:35:00Z"
}

응답 (Failover 성공):
{
  "success": true,
  "data": {
    "provider": "gemini",  ← Claude 실패, Gemini 성공
    "failover_chain": ["claude", "openai", "gemini"],
    "recommendations": [...]
  }
}

응답 (모두 실패):
{
  "success": false,
  "error": {
    "code": "AI_UNAVAILABLE",
    "message": "모든 AI 프로바이더 사용 불가",
    "failover_attempts": 4
  }
}
```

### 7. Business Rule

```
AI Router 규칙:
1. 활성 프로바이더 선택
   - 우선순위: Claude > OpenAI > Gemini > Grok
   - 관리자가 변경 가능

2. Failover 조건
   - Timeout: 10초
   - HTTP Error: 429 (Rate Limit), 500, 503
   - Connection Error: 재시도
   - Invalid Response: 형식 오류

3. 재시도 정책
   - 최대 4회 (각 프로바이더별 1회)
   - Exponential Backoff: 1s, 2s, 4s, 8s
   - 타임아웃 후 즉시 다음 프로바이더

4. 비용 최적화
   - Gemini, Grok 우선 (저비용)
   - 성공률 추적 (일주일)
   - 저비용 프로바이더 먼저 시도 옵션 (미래)

5. Rate Limiting
   - Claude: 분당 100 요청
   - OpenAI: 분당 50 요청
   - Gemini: 분당 60 요청
   - Grok: 분당 40 요청
   - 초과 시 429 → Failover

6. 응답 캐싱
   - 동일 질문 1시간 캐시
   - Redis에 저장
   - 매장 고객 기반 (room_id별)
```

### 8. AI Rule

```
AI 호출 규칙:
1. 프롬프트 선택
   - request_type별 다른 프롬프트
   - 버전 지정 가능 (기본: latest)
   - DB prompts 테이블에서 로드

2. 개인정보 마스킹
   - 고객명: 마스킹 안 함
   - 전화: 010-****-5678
   - 이메일: user@****.com
   - 주소: 서울시 강남구 ****
   - AI 호출 전 마스킹 필수

3. 응답 정규화
   - 프로바이더별 응답 형식 다름
   - 표준 JSON으로 변환
   - Content Type 확인 (text/image/json)

4. 토큰 추적
   - 요청 토큰 + 응답 토큰 로그
   - 비용 계산 (provider별 요금표)
   - 예산 알림 (월 한도 설정)
```

### 9. Exception 처리

```
에러 상황과 대응:

1. 모든 프로바이더 실패 (Failover 완료)
   → API 응답: 503 Service Unavailable
   → 클라이언트: "AI 분석 불가. 나중에 다시"
   → 로그: ai_failover_log에 전체 실패 기록

2. API Key 없음
   → API 응답: 400 Bad Request
   → 로그: admin에 알림
   → 관리자가 설정 필요

3. Rate Limit (429)
   → 해당 프로바이더 스킵
   → 다음 프로바이더로 즉시 전환
   → 1시간 후 재시도

4. Timeout
   → 10초 내에 응답 없으면 fail
   → 다음 프로바이더로 전환

5. Invalid Response (형식 오류)
   → 파싱 실패
   → 다음 프로바이더로 전환
   → 로그: ai_logs에 오류 기록
```

### 10. Test Case

```
TC-AI-001: Claude 성공
- 시나리오: 상담원이 AI 추천 요청
- 예상: Claude 호출 → 1초 내 응답

TC-AI-002: Claude Timeout → Gemini 성공
- 시나리오: Claude 10초 이상 지연
- 예상: Gemini로 자동 전환 → 응답

TC-AI-003: 모든 프로바이더 실패
- 시나리오: 4개 모두 실패
- 예상: 503 Service Unavailable

TC-AI-004: Rate Limit 처리
- 시나리오: Claude 429 응답
- 예상: Gemini로 즉시 전환

TC-AI-005: 캐시 히트
- 시나리오: 동일 질문 2회
- 예상: 2번째는 Redis에서 1ms 내 반환
```

### 11. Future

```
V4.0:
- 프로바이더별 성공률 기반 동적 순서 변경
- 고객사별 선호 AI 설정
- 비용 기반 자동 최적화 (월별)
- AI 모델 자동 업그레이드

V4.5:
- 로컬 모델 지원 (Llama, Mistral)
- AI 자동선택 (각 기능별 최적 모델)
- 멀티 모달 지원 (이미지 입력)
```

## 검수 기준

- [ ] AI 전략 구조도 (Decision Tree)
- [ ] 4개 프로바이더 설정 정의
- [ ] Failover 메커니즘 명확
- [ ] 비용 추적 계획
- [ ] 응답 형식 통일
- [ ] 에러 처리 명확
- [ ] 캐싱 전략 정의
- [ ] 보안 (개인정보 마스킹)
- [ ] 총 12~18페이지

---

# 🟢 작업 2: www/04_AI/02_Prompt설계.md 작성

## 목표
기능별 프롬프트 정의 및 버전 관리

## 파일 위치
```
www/04_AI/02_Prompt설계.md
```

## 포함할 프롬프트 (최소 8개)

1. **상담 요약** (Summarization)
   - 고객 대화를 한 문단으로 요약
   - 키워드/의도 추출

2. **답변 초안** (Answer Draft)
   - 고객 질문에 대한 답변 3개 제안
   - 각 50자 이내

3. **계약확률** (Lead Score)
   - 0~100점 산출
   - 근거 설명

4. **감정분석** (Sentiment)
   - 긍정/중립/부정 분류
   - 신뢰도 점수

5. **분류** (Classification)
   - 상담유형 분류 (인터넷/대표번호/CCTV/TV 등)
   - 신뢰도 점수

6. **상품추천** (Product Recommendation)
   - 고객에게 맞는 상품 추천
   - 이유 제시

7. **FAQ 매칭** (FAQ Matching)
   - 고객 질문과 유사한 FAQ 검색
   - 유사도 점수

8. **다음질문** (Next Question)
   - 상담원이 물어볼 다음 질문 제안
   - 계약 가능성 높이는 질문

## 각 프롬프트 정의 기준

```markdown
### 프롬프트명: {name}
**용도:** {기능}
**모델:** {Claude/GPT/Gemini}
**버전:** v1.0
**Max Tokens:** {숫자}

**System Prompt:**
{시스템 지시사항}

**User Prompt Template:**
{사용자 입력 템플릿}

**응답 형식:**
{JSON 예시}

**사용 예시:**
{실제 사용 사례}

**주의사항:**
{특별한 주의사항}
```

## 검수 기준

- [ ] 8개 이상 프롬프트 정의
- [ ] 각 프롬프트별 버전 관리
- [ ] 응답 형식 명확
- [ ] 예시 포함
- [ ] PII 마스킹 명시
- [ ] 총 15~20페이지

---

# 🔵 작업 3: www/04_AI/03_AI엔진구현.md 작성

## 목표
실제 AI 호출 로직 및 구현 가이드

## 파일 위치
```
www/04_AI/03_AI엔진구현.md
```

## 포함 내용

1. **AI 호출 함수**
   - `ai_call()` - 기본 호출 함수
   - 프로바이더 선택 로직
   - 에러 처리

2. **응답 파싱**
   - 프로바이더별 응답 형식 변환
   - 표준 JSON으로 정규화

3. **캐싱 전략**
   - Redis 연동
   - TTL 설정 (1시간)
   - 캐시 키 생성

4. **로깅**
   - ai_logs 저장
   - ai_failover_log 저장
   - 성공/실패 추적

5. **개인정보 마스킹**
   - 호출 전 마스킹
   - 응답 후 언마스킹

6. **성능 최적화**
   - 동시 요청 처리
   - 토큰 최적화
   - 응답 캐싱

7. **모니터링**
   - 응답 시간 추적
   - 성공률 통계
   - 비용 집계

## 검수 기준

- [ ] 프로바이더별 호출 코드 예제
- [ ] 응답 파싱 로직
- [ ] 에러 처리 상세
- [ ] 캐싱 구현
- [ ] 로깅 구현
- [ ] 성능 최적화 팁
- [ ] 총 12~15페이지

---

# 📋 작업 체크리스트

## 작업 1: 01_AI전략.md (www/04_AI)
- [ ] 1. 목적(Purpose) 작성
- [ ] 2. 범위(Scope) 작성
- [ ] 3. 요구사항 작성
- [ ] 4. AI 전략 구조:
  - [ ] Failover Decision Tree
  - [ ] 프로바이더별 설정
  - [ ] API 통신 규칙
- [ ] 5. DB 참조
- [ ] 6. API 엔드포인트
- [ ] 7. Business Rule
- [ ] 8. AI Rule
- [ ] 9. Exception 처리
- [ ] 10. Test Case (5개 이상)
- [ ] 11. Future 계획
- [ ] **최종 검수** (12~18페이지)

## 작업 2: 02_Prompt설계.md (www/04_AI)
- [ ] 8개 이상 프롬프트 정의:
  - [ ] 상담 요약
  - [ ] 답변 초안
  - [ ] 계약확률
  - [ ] 감정분석
  - [ ] 분류
  - [ ] 상품추천
  - [ ] FAQ 매칭
  - [ ] 다음질문
- [ ] 각 프롬프트별:
  - [ ] System Prompt
  - [ ] User Template
  - [ ] 응답 형식 (JSON)
  - [ ] 사용 예시
  - [ ] 주의사항
- [ ] 버전 관리 규칙
- [ ] **최종 검수** (15~20페이지)

## 작업 3: 03_AI엔진구현.md (www/04_AI)
- [ ] 1. 목적(Purpose)
- [ ] 2. 범위(Scope)
- [ ] 3. 요구사항
- [ ] 4. 구현 구조:
  - [ ] AI 호출 함수
  - [ ] 응답 파싱
  - [ ] 캐싱 전략
  - [ ] 로깅
  - [ ] 마스킹
  - [ ] 모니터링
- [ ] 5. DB 참조
- [ ] 6. API 통신
- [ ] 7. Business Rule
- [ ] 8. AI Rule
- [ ] 9. Exception 처리
- [ ] 10. Test Case (5개 이상)
- [ ] 11. Future 계획
- [ ] **최종 검수** (12~15페이지)

---

# 🎁 산출물

**예상 산출물:**
```
총 3개 문서 (모두 www/04_AI 폴더 내)
약 40~50페이지
Markdown 형식
개발자가 즉시 구현 가능한 수준
```

**구성:**
- www/04_AI/01_AI전략.md (12~18페이지)
  - AI Router 아키텍처
  - Failover 메커니즘
  - 프로바이더 설정

- www/04_AI/02_Prompt설계.md (15~20페이지)
  - 8개 이상 프롬프트
  - 각 프롬프트 상세 정의
  - 버전 관리

- www/04_AI/03_AI엔진구현.md (12~15페이지)
  - 구현 가이드
  - 코드 예제
  - 최적화 전략

---

# 🚀 Cursor 작업 방법

**Cursor에 다음과 같이 입력:**

```
STEP 3 작업지시서 기준으로 다음 3개 문서를 www/04_AI 폴더 내에 작성해줘:

1. www/04_AI/01_AI전략.md (12~18페이지)
   - AI Router의 설계와 Failover 메커니즘
   - Claude → OpenAI → Gemini → Grok 자동 전환
   - 비용 추적 및 최적화
   - 개인정보 마스킹

2. www/04_AI/02_Prompt설계.md (15~20페이지)
   - 상담요약, 답변초안, 계약확률, 감정분석, 분류, 상품추천, FAQ, 다음질문 등 8개 이상
   - 각 프롬프트별 System Prompt, User Template, 응답 형식
   - 버전 관리 규칙

3. www/04_AI/03_AI엔진구현.md (12~15페이지)
   - 실제 구현 가이드
   - AI 호출, 응답 파싱, 캐싱, 로깅
   - 에러 처리 및 모니터링

모든 내용을 11개 섹션 구조(Purpose/Scope/요구사항/화면/DB/API/Rule/AI Rule/Exception/Test/Future)로 작성하고,
STEP 1, 2 문서 형식과 일관성 있게 작성해줘.
```

---

# 🎯 다음 단계 (STEP 4)

STEP 3 완료 후:

**STEP 4 (WebSocket & Chat 설계):**
- [ ] 05_CHAT/01_WebSocket설계.md (실시간 통신 프로토콜)
- [ ] 05_CHAT/02_실시간동기화.md (메시지 동기, 읽음표시)

---

**STEP 3 준비 완료! Cursor에서 작업 시작하세요!** 🚀
