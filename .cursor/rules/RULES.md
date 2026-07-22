# PlusTok V3.0 — Cursor Development Rules

**프로젝트:** PlusTok V1.0 → V3.0 AI Customer Engagement Platform  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**버전:** 1.0  
**적용일:** 2026-07-21  

---

## 📋 필수 규칙 (MUST)

### 1️⃣ 문서 우선 원칙 (Documentation First)

```markdown
구현 시작 전 항상 순서대로 확인:

1단계: www/INDEX.md 읽기
       └─ 프로젝트 전체 구조 이해

2단계: 00_PROJECT_MASTER.md 읽기
       └─ 비즈니스 컨텍스트, 기능, 로드맵

3단계: 해당 STEP 문서 읽기
       └─ DB / API / 구현 명세

4단계: Cross Reference 확인
       └─ 참조 문서 모두 읽음

5단계: 체크리스트 확인
       └─ 각 문서 끝의 검수 기준 확인

6단계: 구현 시작
       └─ 체크리스트 항목별로 진행
```

**위반 시 결과:**
- ❌ 일부 문서만 보고 구현 → 불일치 100% 확실
- ❌ 체크리스트 무시 → 테스트 실패 80% 이상
- ❌ Cross Reference 무시 → 중복 코드, 데이터 타입 불일치

---

### 2️⃣ Cross Reference 준수

**규칙:**
```markdown
한 문서가 다른 문서를 참조하면:
1. 링크 작동 여부 확인 (클릭 테스트)
2. 참조 내용이 최신인지 확인
3. 변경 발생 시 양쪽 모두 업데이트
```

**예시:**
```
❌ 잘못된 방법:
   05_CHAT/01_WebSocket설계.md 수정
   └─ 03_SYSTEM/01_DB설계.md 미업데이트

✅ 올바른 방법:
   05_CHAT/01_WebSocket설계.md 수정
   └─ 참조하는 문서 03_SYSTEM 확인
   └─ 필요시 DB 스키마도 함께 수정
   └─ 변경 사항 PR에 기록
```

---

### 3️⃣ 체크리스트 준수

**규칙:**
```markdown
각 문서 맨 아래의 검수 기준(체크리스트)는:

1. 구현 전: 모든 항목 이해했는지 확인
2. 구현 중: 항목별로 차례대로 진행
3. 구현 후: 모든 항목 ✅ 표시

체크리스트 예시:
[x] 목적(Purpose) 이해
[x] DB 스키마 확인
[x] API 명세 확인
[x] 예외 처리 정의
[ ] 테스트 케이스 작성 ← 다음 할 일
```

---

### 4️⃣ 코딩 기준 (Coding Standards)

#### 기술 스택
```yaml
Backend:
  Runtime: Node.js 18+
  Framework: Express.js
  Language: TypeScript (또는 JavaScript)
  
Database:
  Type: PostgreSQL 14+
  Encoding: UTF8MB4
  
Frontend:
  Framework: React 18+
  UI: Material-UI 또는 Tailwind CSS
  State: Context API 또는 Redux
  
Real-time:
  Protocol: WebSocket (Socket.io v4.x)
  
AI:
  Providers: Claude, OpenAI, Google Gemini, xAI Grok
  
DevOps:
  Container: Docker
  Orchestration: Docker Compose (dev) / Kubernetes (prod)
  CI/CD: GitHub Actions
  Cloud: AWS (ECS, RDS, ElastiCache)
```

#### 명명 규칙

```javascript
// 변수: camelCase
const chatRoomId = 123;
const isUserOnline = true;

// 상수: UPPER_SNAKE_CASE
const MAX_RETRY_COUNT = 3;
const CHAT_TIMEOUT_MS = 10000;

// 함수: camelCase + 동사
function createChatMessage(roomId, content) {}
function validateJwtToken(token) {}

// 클래스: PascalCase
class ChatRoomManager {}
class AIRouter {}

// DB 테이블: snake_case + 복수형
CREATE TABLE chat_rooms (...);
CREATE TABLE chat_messages (...);

// 파일명: snake_case (BE) / PascalCase (FE)
// BE: chat_service.js, ai_router.js
// FE: ChatScreen.jsx, MessageList.jsx

// API 엔드포인트: kebab-case
GET /api/v1/chat-rooms/{id}/messages
POST /api/v1/consults/close
```

#### 에러 처리

```javascript
// ✅ 올바른 방법
try {
  const response = await ai_call(prompt);
  return { success: true, data: response };
} catch (error) {
  logger.error('AI call failed', { error, prompt });
  return { 
    success: false, 
    error: 'AI_CALL_FAILED',
    message: '분석 중 오류가 발생했습니다.'
  };
}

// ❌ 금지
console.log(error); // 로그 X
throw new Error('뭔가 안 됨'); // 메시지 불명확 X
return null; // 타입 불일치 X
```

#### 코드 리뷰 기준

```markdown
구현 후 다음 확인:

□ 문서 준수도
  ├─ API 응답 형식: INDEX의 표준 형식?
  ├─ DB 필드명: 스키마와 일치?
  ├─ 예외 처리: 문서의 Exception 섹션 포함?
  └─ 성능: 문서의 목표값 충족?

□ 테스트
  ├─ 단위 테스트: 함수별로 작성?
  ├─ 통합 테스트: 다른 모듈과 호환?
  ├─ E2E 테스트: 09_DEVELOPMENT/02_테스트시나리오.md 참조
  └─ 커버리지: 80% 이상?

□ 보안
  ├─ SQL Injection 대비: 파라미터화?
  ├─ XSS 대비: 입력 이스케이프?
  ├─ CSRF 대비: 토큰 검증?
  └─ 인증/인가: JWT + RBAC?

□ 성능
  ├─ API 응답시간: < 200ms?
  ├─ WebSocket: < 100ms?
  ├─ DB 쿼리: Index 포함?
  └─ 메모리: 누수 없음?
```

---

## 📌 금지사항 (MUST NOT)

```markdown
✗ 문서 없이 구현 시작
  → 후회의 지름길, 100% 재작업 예상

✗ 일부 문서만 보고 구현
  → 데이터 타입, API 형식 불일치 확실

✗ Cross Reference 무시
  → 다른 팀의 기대와 다른 결과

✗ 체크리스트 무시
  → 테스트 실패, 배포 지연

✗ 환경 변수 하드코딩
  → 보안 사고, 릴리스 불가능

✗ 기존 코드 무단 변경
  → 팀 충돌, 머지 컨플릭트

✗ 성능 확인 없이 배포
  → 프로덕션 장애, 롤백 필요

✗ 에러 핸들링 없이 구현
  → 사용자에게 "Cannot read property" 노출

✗ 레거시 기술 사용
  → 팀의 기술 스택과 불일치
  예: jQuery, Lodash 등 (React 사용 중)
```

---

## 🎯 Task별 진행 방식

### AI 기능 구현 (STEP 3)

```markdown
1. 문서 읽기
   └─ 04_AI/01_AI전략.md (Failover 체인 이해)
   └─ 04_AI/02_Prompt설계.md (프롬프트 템플릿)
   └─ 04_AI/03_AI엔진구현.md (ai_call() 구현)

2. DB 스키마 확인
   └─ ai_keys 테이블 (API 키 저장)
   └─ ai_logs 테이블 (호출 기록)
   └─ ai_failover_log 테이블 (Failover 이력)

3. API 정의
   └─ POST /api/v1/ai/call (AI 호출)
   └─ GET /api/v1/ai/logs (기록 조회)

4. 구현 단계
   ├─ ai_router.js 생성 (프로바이더 선택)
   ├─ claude_adapter.js (Claude 호출)
   ├─ openai_adapter.js (OpenAI 호출)
   ├─ gemini_adapter.js (Gemini 호출)
   └─ grok_adapter.js (Grok 호출)

5. Failover 테스트
   ├─ Claude 타임아웃 → OpenAI로 전환
   ├─ OpenAI Rate Limit → Gemini로 전환
   ├─ 모든 프로바이더 실패 → 에러 반환

6. 성능 확인
   ├─ 응답시간: 5초 이내
   ├─ Failover 전환: 1초 이내
   ├─ 캐시 히트율: 30% 이상

7. 테스트
   ├─ 09_DEVELOPMENT/02_테스트시나리오.md 섹션 3 참조
   ├─ TC-AI-001: AI 답변 생성
   ├─ TC-AI-002: AI Failover
   └─ 모든 테스트 PASS
```

### WebSocket 구현 (STEP 4)

```markdown
1. 문서 읽기
   └─ 05_CHAT/01_WebSocket설계.md
   └─ 05_CHAT/02_실시간동기화.md

2. 이벤트 정의 확인
   └─ 15개 이벤트 명세서 이해

3. DB 스키마
   └─ chat_rooms, chat_messages, chat_read_status

4. 구현 순서
   ├─ Socket.io 서버 초기화
   ├─ JWT 인증 미들웨어
   ├─ 15개 이벤트 핸들러
   ├─ 메시지 저장 로직
   ├─ 읽음표시 동기화
   └─ 오프라인 메시지 큐

5. 성능 확인
   ├─ 메시지 지연: 0.1~0.3초
   ├─ 동시 연결: 1000명+
   └─ 메모리: 연결당 50KB 이하

6. 테스트
   ├─ TC-WS-001: 정상 연결
   ├─ TC-WS-002: 메시지 송수신
   ├─ TC-WS-003: 네트워크 끊김 복구
   └─ TC-WS-004: 동시 1000명 연결
```

---

## 📊 일일 체크포인트

### 매 STEP 시작 시
```markdown
[ ] INDEX.md 읽음?
[ ] 해당 STEP의 모든 문서 읽음?
[ ] Cross Reference 링크 확인?
[ ] 체크리스트 이해?
[ ] 기술 스택 정확?
[ ] 기존 코드 파악?
```

### 매 일일 종료 시
```markdown
[ ] 오늘의 Task 완료?
[ ] 테스트 작성?
[ ] 코드 리뷰 완료?
[ ] 문서와 코드 일치?
[ ] 내일 Task 명확?
[ ] 막힌 부분 문서화?
```

### 매 STEP 완료 시
```markdown
[ ] 모든 체크리스트 ✅ 표시?
[ ] 통합 테스트 통과?
[ ] 다음 STEP 문서 읽음?
[ ] PR 제출 전 확인?
[ ] 배포 준비 상태?
```

---

## 🎯 성능 목표 (SLA)

### Backend API
```markdown
■ 응답시간 (P95): < 200ms
■ 에러율: < 0.1%
■ 가용성: 99.9%

예외:
- AI 호출: < 5초
- DB 복잡 쿼리: < 1초
```

### WebSocket
```markdown
■ 메시지 지연: 0.1~0.3초
■ 동시 연결: 1000+
■ Reconnect: < 3초
```

### Frontend
```markdown
■ 페이지 로드: < 2초
■ 상호작용 반응: < 500ms
■ 번들 크기: < 500KB
```

---

## 🔄 변경 절차

### 코드 변경 시
```markdown
1. 해당 문서 최신 상태 확인
2. 변경 전 기존 동작 이해
3. 변경 사항 작은 단위로 커밋
4. PR에 이유와 영향 범위 작성
5. 체크리스트 항목별로 검토

예: chat_message 필드 추가
   └─ DB 스키마 문서 업데이트
   └─ API 응답 형식 문서 업데이트
   └─ WebSocket 메시지 타입 문서 업데이트
   └─ 테스트 시나리오 추가
   └─ 마이그레이션 스크립트 작성
```

### 문서 변경 시
```markdown
1. 각 섹션 변경 이유 명시
2. 참조하는 다른 문서 확인
3. Cross Reference 업데이트
4. 예시 코드 테스트
5. 팀에 공지
```

---

## 🚨 긴급 상황

### 버그 발견 시
```markdown
1. 재현 코드 작성
2. 근본 원인 파악
3. 문서 확인 (버그는 문서 오류일 수 있음)
4. 최소 단위로 수정
5. 테스트 케이스 추가
6. 관련 문서 업데이트
```

### 성능 이슈 발생 시
```markdown
1. 목표값 확인 (이 파일의 성능 목표 참조)
2. 병목 분석 (프로파일링)
3. 해결 방안 구현
4. 성능 재확인
5. 근본 원인 문서화
```

### 배포 실패 시
```markdown
1. 즉시 롤백 (09_DEVELOPMENT/03_배포운영.md)
2. 원인 분석
3. 해결
4. 재배포
5. 사후 분석 문서화
```

---

## 📞 질문 & 막힘

### 기술적 질문
```markdown
질문: "API 응답 형식은?"
1단계: 이 파일 읽기 (완료)
2단계: INDEX.md 읽기
3단계: 03_SYSTEM/02_API설계.md 섹션 5
4단계: 예시 코드 찾기

절대 금지: 문서 안 보고 임의로 정의
```

### 막혔을 때
```markdown
1. 관련 문서 다시 읽기 (처음에는 놓친 부분이 있을 수 있음)
2. 예시 코드 참조
3. 유사한 구현 찾기
4. 팀에 물어보기
5. 시간 초과 시: 일단 구현 후 나중에 리팩토링
```

---

## ✅ 최종 체크리스트

### 구현 완료 후
```markdown
[ ] 모든 테스트 PASS?
[ ] 코드 리뷰 완료?
[ ] 문서와 코드 일치?
[ ] PR 설명 명확?
[ ] 성능 목표 달성?
[ ] 보안 검토 완료?
[ ] 배포 준비?
```

---

**이 규칙은 프로젝트 성공을 위한 최소한의 기준입니다.**  
**의문점이 있으면 문서를 먼저 확인하고, 문서에 없으면 팀에 질문하세요.**

---

*Cursor Development Rules v1.0 · 2026-07-21*
