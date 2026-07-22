# 🚀 PlusTok V3.0 — Phase 1 구현 시작 명령어

**프로젝트:** PlusTok V1.0 → V3.0 AI Customer Engagement Platform  
**Phase:** 1 (DB & API)  
**기간:** 2주 (Day 1~10)  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  

---

## 📍 Cursor에 전달할 명령어

### Step 1: 프로젝트 컨텍스트 이해 (1시간)

```markdown
다음 문서들을 순서대로 읽어주세요:

1. E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www\INDEX.md
   └─ 프로젝트 전체 구조 이해 (Quick Start 섹션)

2. www/00_PROJECT_MASTER.md
   └─ 비즈니스 비전, 기능, 로드맵 (PART 1~5 읽기)

3. .cursor/rules/RULES.md
   └─ 개발 규칙 (필수, 구현 중 계속 참조)

4. .cursor/rules/AGENTS.md
   └─ 에이전트 역할 정의 (Code Implementer 역할)

읽고 난 후 "Phase 1 준비 완료"라고 보고해주세요.
```

---

### Step 2: DB 설계 & DDL 작성 (Day 1~2)

```markdown
📚 참고 문서:
   www/03_SYSTEM/01_DB설계.md (섹션 4: 테이블 정의)

🎯 Task: PostgreSQL DDL 스크립트 작성

목표:
1. 14개 테이블 DDL 생성
2. Primary Key & Foreign Key 정의
3. Index 생성
4. 제약조건 설정 (NOT NULL, UNIQUE 등)
5. UTF8MB4 인코딩 설정

출력물:
📄 db/schema/001_init_tables.sql
   ├─ customers (고객 테이블)
   ├─ agents (상담원 테이블)
   ├─ chat_rooms (상담방)
   ├─ chat_messages (메시지)
   ├─ chat_read_status (읽음 상태)
   ├─ ai_keys (AI 프로바이더 키)
   ├─ ai_logs (AI 호출 로그)
   ├─ ai_failover_log (Failover 기록)
   ├─ consults (상담 기록)
   ├─ customers_ext (고객 확장 정보)
   ├─ schedules (후속 일정)
   ├─ notifications (알림)
   ├─ audit_logs (감시 로그)
   └─ settings (시스템 설정)

📝 작성 기준:
   □ 각 테이블마다 주석 포함 (목적, 주요 필드)
   □ 타입 정확 (INT/BIGINT/VARCHAR/TIMESTAMP 등)
   □ 기본값 설정 (created_at DEFAULT now(), is_deleted DEFAULT false)
   □ 소프트 삭제 패턴 포함 (is_deleted, deleted_at)
   □ Index 최적화 (자주 쿼리되는 필드)
   □ FK 제약조건 (ON DELETE CASCADE 등)

🔍 검증:
   - 03_SYSTEM/01_DB설계.md 섹션 4와 100% 일치?
   - PK/FK 관계 다이어그램과 일치?
   - 모든 필드명이 영문 snake_case?
   - 14개 테이블 모두 포함?

✅ 체크리스트:
   [ ] 001_init_tables.sql 작성 완료
   [ ] 모든 테이블 PK/FK 정의
   [ ] Index 생성 포함
   [ ] 주석 추가
   [ ] DB설계 문서와 100% 일치 확인
   [ ] 문법 오류 없음 (psql 검증)
```

---

### Step 3: REST API 설계 & Express 서버 구축 (Day 3~7)

```markdown
📚 참고 문서:
   www/03_SYSTEM/02_API설계.md (섹션 4: 30+ 엔드포인트)

🎯 Task: Node.js + Express 서버 & API 구현

기술 스택:
  └─ Node.js 18+
  └─ Express.js
  └─ TypeScript (또는 JavaScript)
  └─ PostgreSQL
  └─ JWT 인증

프로젝트 구조:
```
plusok-backend/
├── src/
│   ├── controllers/
│   │   ├── authController.js
│   │   ├── chatController.js
│   │   ├── aiController.js
│   │   ├── consultController.js
│   │   └─ adminController.js
│   │
│   ├── routes/
│   │   ├── auth.js
│   │   ├── chat.js
│   │   ├── ai.js
│   │   ├── consults.js
│   │   └── admin.js
│   │
│   ├── middleware/
│   │   ├── auth.js (JWT 검증)
│   │   ├── errorHandler.js
│   │   └─ logger.js
│   │
│   ├── models/
│   │   ├── User.js
│   │   ├── ChatMessage.js
│   │   ├── Consult.js
│   │   └─ ...
│   │
│   ├── services/
│   │   ├── authService.js
│   │   ├── chatService.js
│   │   ├── aiService.js
│   │   └─ consultService.js
│   │
│   ├── db/
│   │   ├── connection.js
│   │   ├── schema/
│   │   │   └─ 001_init_tables.sql
│   │   └─ migrations/
│   │
│   └─ app.js (Express 앱)
│
├── .env.example
├── package.json
└─ server.js
```

🚀 개발 단계:

Day 3: Express 서버 초기화
  [ ] npm init / package.json 작성
  [ ] Express 설치 & 기본 구조
  [ ] PostgreSQL 연결
  [ ] JWT 미들웨어 구현
  [ ] 에러 핸들링 미들웨어

Day 4~6: API 30개 구현
  
  **인증 (4개)**
  [ ] POST /api/v1/auth/register - 회원가입
  [ ] POST /api/v1/auth/login - 로그인
  [ ] POST /api/v1/auth/refresh - 토큰 갱신
  [ ] POST /api/v1/auth/logout - 로그아웃

  **고객 (5개)**
  [ ] GET /api/v1/customers - 목록
  [ ] GET /api/v1/customers/{id} - 상세
  [ ] POST /api/v1/customers - 생성
  [ ] PUT /api/v1/customers/{id} - 수정
  [ ] DELETE /api/v1/customers/{id} - 삭제

  **상담원 (4개)**
  [ ] GET /api/v1/agents - 목록
  [ ] GET /api/v1/agents/{id} - 상세
  [ ] POST /api/v1/agents - 생성
  [ ] PUT /api/v1/agents/{id} - 상태 변경

  **채팅방 (4개)**
  [ ] GET /api/v1/chat/rooms - 채팅방 목록
  [ ] GET /api/v1/chat/rooms/{id} - 상세
  [ ] POST /api/v1/chat/rooms - 생성
  [ ] DELETE /api/v1/chat/rooms/{id} - 종료

  **메시지 (4개)**
  [ ] GET /api/v1/chat/rooms/{id}/messages - 히스토리
  [ ] POST /api/v1/chat/messages - 메시지 저장
  [ ] PUT /api/v1/chat/messages/{id}/read - 읽음표시
  [ ] DELETE /api/v1/chat/messages/{id} - 삭제

  **상담 (4개)**
  [ ] GET /api/v1/consults - 상담 목록
  [ ] GET /api/v1/consults/{id} - 상담 상세
  [ ] POST /api/v1/consults/close - 상담 종료
  [ ] GET /api/v1/consults/{id}/feedback - 평가 조회

  **AI (2개)**
  [ ] POST /api/v1/ai/call - AI 호출
  [ ] GET /api/v1/ai/logs - 호출 로그

  **파일 (2개)**
  [ ] POST /api/v1/files/upload - 파일 업로드
  [ ] GET /api/v1/files/{id} - 파일 다운로드

Day 7: 테스트 & 최적화
  [ ] 단위 테스트 작성 (각 엔드포인트)
  [ ] 통합 테스트 (엔드포인트 간)
  [ ] 성능 테스트 (응답시간 < 200ms)
  [ ] 에러 케이스 테스트

📝 작성 기준:
  □ 요청/응답 형식: 03_SYSTEM/02_API설계.md 섹션 5 준수
  
    표준 응답:
    {
      "success": true,
      "data": { /* payload */ },
      "error": null,
      "timestamp": "2026-07-21T12:34:56Z"
    }
  
  □ HTTP 상태 코드:
    - 200: 성공
    - 201: 생성됨
    - 400: 잘못된 요청
    - 401: 인증 필요
    - 403: 권한 없음
    - 404: 찾을 수 없음
    - 500: 서버 오류

  □ JWT 인증: Authorization: Bearer <token>
  □ 에러 처리: try-catch + 로깅
  □ 입력 검증: 모든 파라미터 검증
  □ 성능: 응답시간 < 200ms

✅ 체크리스트:
  [ ] Express 서버 실행 가능 (npm start)
  [ ] 30개 엔드포인트 모두 구현
  [ ] API 응답 형식 표준 준수
  [ ] JWT 인증 동작
  [ ] 에러 처리 완전
  [ ] 단위 테스트 작성 (커버리지 >= 80%)
  [ ] 통합 테스트 통과
  [ ] 성능 목표 달성 (응답시간 < 200ms)
  [ ] 문서와 코드 100% 일치
```

---

### Step 4: 테스트 & 검증 (Day 8~10)

```markdown
📚 참고 문서:
   www/09_DEVELOPMENT/02_테스트시나리오.md

🎯 Task: 테스트 작성 & 검증

테스트 종류:

1️⃣ 단위 테스트 (Unit Test)
   └─ 각 함수별로 테스트
   └─ 파일: src/tests/unit/
   └─ 예: authController.test.js

2️⃣ 통합 테스트 (Integration Test)
   └─ API 엔드포인트별 테스트
   └─ 파일: src/tests/integration/
   └─ 예: auth.test.js, chat.test.js
   └─ 포함: 요청 → 처리 → DB → 응답

3️⃣ E2E 테스트 (End-to-End)
   └─ 전체 플로우 테스트
   └─ 예: 회원가입 → 로그인 → 채팅방 생성 → 메시지 전송

📋 테스트 케이스 (09_DEVELOPMENT/02 참조):

인증 테스트
  TC-AUTH-001: 정상 로그인
  TC-AUTH-002: 잘못된 비밀번호
  TC-AUTH-003: 존재하지 않는 계정
  TC-AUTH-004: 토큰 만료
  TC-AUTH-005: 잘못된 토큰

API 테스트
  TC-API-001: 고객 생성
  TC-API-002: 고객 조회
  TC-API-003: 고객 수정
  TC-API-004: 고객 삭제
  TC-API-005: 없는 고객 조회 (404)
  ... (각 엔드포인트별 5개 이상)

에러 테스트
  TC-ERROR-001: 빈 요청 본문
  TC-ERROR-002: 잘못된 데이터 타입
  TC-ERROR-003: SQL Injection 시도
  TC-ERROR-004: 권한 없는 접근

성능 테스트
  TC-PERF-001: 응답시간 < 200ms
  TC-PERF-002: 100개 동시 요청
  TC-PERF-003: 메모리 누수 없음

✅ 체크리스트:
  [ ] 단위 테스트 80% 이상 커버리지
  [ ] 통합 테스트 30개 엔드포인트 모두 테스트
  [ ] E2E 테스트 5개 이상 플로우
  [ ] 모든 테스트 PASS
  [ ] 성능 목표 달성
  [ ] 보안 테스트 (SQL Injection, XSS 등)
```

---

## 📅 일정 (Day 1~10)

```
Week 1 (Day 1~5):
  └─ Day 1~2: DB 설계 & DDL (2일)
  └─ Day 3: Express 서버 초기화 (1일)
  └─ Day 4~5: API 30개 구현 (2일 - 하루 15개)

Week 2 (Day 6~10):
  └─ Day 6~7: API 30개 구현 완료 (2일)
  └─ Day 8~10: 테스트 & 최적화 (3일)
```

---

## 🎯 Phase 1 완료 조건 (Go/No-Go)

### ✅ Go 조건 (모두 만족)
```markdown
□ DB: 14개 테이블 DDL 작성 완료
□ API: 30개 엔드포인트 구현 완료
□ 테스트: 단위 테스트 80%+ 커버리지
□ 성능: 응답시간 < 200ms
□ 보안: SQL Injection/XSS 테스트 통과
□ 문서: API 설계와 100% 일치
□ 코드 리뷰: 승인 완료
```

### ❌ No-Go 조건 (하나라도 해당)
```markdown
❌ 테스트 실패
❌ 성능 목표 미달성
❌ 보안 이슈 발견
❌ 문서와 불일치 > 3개
❌ 코드 리뷰 승인 미획득
```

---

## 📞 막힐 때

### 질문할 때
```markdown
1단계: 이 파일(Phase1 명령어) 다시 읽기
2단계: www/03_SYSTEM/02_API설계.md 읽기
3단계: .cursor/rules/RULES.md 읽기
4단계: 팀에 질문 (문서에 없을 때만)

❌ 절대 금지: 문서 없이 임의로 구현
```

### 막혔을 때
```markdown
상황: "API 응답 형식이 뭔가요?"
→ www/03_SYSTEM/02_API설계.md 섹션 5 읽기
→ 표준 응답 형식 찾기
→ 예시 코드 구현

상황: "필드 타입은 뭐죠?"
→ www/03_SYSTEM/01_DB설계.md 섹션 4 읽기
→ 테이블 정의 찾기
→ 필드명, 타입 확인
```

---

## ✨ 성공 지표

### Phase 1 완료 후 이렇게 보고
```markdown
✅ DB: 14개 테이블 DDL 작성 완료
   ├─ 고객, 상담원, 상담방, 메시지 등
   └─ index, FK 포함

✅ API: 30개 엔드포인트 구현 완료
   ├─ 인증 4개
   ├─ 고객 5개
   ├─ 상담원 4개
   ├─ 채팅방 4개
   ├─ 메시지 4개
   ├─ 상담 4개
   └─ 기타 5개

✅ 테스트: 단위 테스트 85%, 통합 테스트 PASS
   ├─ 커버리지 85% (목표 80%)
   └─ 모든 시나리오 PASS

✅ 성능: 응답시간 < 200ms
   └─ P95: 150ms (목표 200ms)

✅ 보안: 모든 테스트 통과
   ├─ SQL Injection: ✅
   ├─ XSS: ✅
   └─ CSRF: ✅

✅ 다음: Phase 2 (Chat & AI) 시작 준비
```

---

## 🚀 Phase 1 시작!

**위 내용을 모두 읽고 이해했다면:**

```markdown
Cursor에게 이렇게 말하세요:

"위의 Phase 1 구현 시작 명령어를 읽었습니다.
이제 Step 1부터 시작하겠습니다.

Step 1: 프로젝트 컨텍스트 이해
- INDEX.md 읽기 시작합니다."
```

**좋은 운을 빕니다! 🎉**

---

*PlusTok V3.0 Phase 1 구현 시작 · 2026-07-21*
