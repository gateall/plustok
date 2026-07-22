# 🎯 PlusTok V3.0 대시보드 & 개발·배포
## STEP 7, 8 작업지시서 (www 폴더 적용)

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**적용 위치:** E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www/  
**단계:** STEP 7 (고객 대시보드) + STEP 8 (개발·배포)  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  
**상태:** 작업 준비 완료  

---

## 📌 STEP 7, 8 목표

**4개 문서 작성 (www/08_DASHBOARD, 09_DEVELOPMENT 폴더 내)**

### STEP 7: 고객 대시보드 (1개 문서)
1. ✅ `www/08_DASHBOARD/01_고객대시보드.md` - 고객용 통계 및 분석

### STEP 8: 개발·배포 (3개 문서)
2. ✅ `www/09_DEVELOPMENT/01_개발WBS.md` - 작업 분해도 및 일정 계획
3. ✅ `www/09_DEVELOPMENT/02_테스트시나리오.md` - 테스트 계획 및 검수 기준
4. ✅ `www/09_DEVELOPMENT/03_배포운영.md` - 배포 가이드 및 운영 규칙

**산출물:** 약 40~50페이지 규모  
**예상 소요시간:** 4~5시간  
**전제조건:** STEP 1~6 완료

---

## 📂 폴더 구조 (www 기준)

```
www/
├── 07_ADMIN/                    ← STEP 6 참조
│
├── 08_DASHBOARD/                ← STEP 7 작성 대상
│   ├── 01_고객대시보드.md        ← 작성 대상 1
│   └── _DASHBOARD_INDEX.md
│
├── 09_DEVELOPMENT/              ← STEP 8 작성 대상
│   ├── 01_개발WBS.md            ← 작성 대상 2
│   ├── 02_테스트시나리오.md      ← 작성 대상 3
│   ├── 03_배포운영.md            ← 작성 대상 4
│   └── _DEVELOPMENT_INDEX.md
│
└── (기존 www 구조)
```

---

# 🔴 작업 1: www/08_DASHBOARD/01_고객대시보드.md 작성

## 목표
고객이 자신의 상담 기록과 서비스 사용 현황을 한눈에 볼 수 있는 개인 대시보드 설계

## 파일 위치
```
www/08_DASHBOARD/01_고객대시보드.md
```

## 작성 순서 (11개 섹션)

### 1. 목적(Purpose)
- 고객의 상담 기록 조회
- 상담 진행상황 추적
- 개인화된 정보 제공
- 만족도 피드백

### 2. 범위(Scope)
- 상담 기록 조회
- 상담 상태 추적 (진행중/완료/재상담대기)
- 개인정보 관리
- 피드백 작성
- 알림 수신 관리

### 3. 요구사항
- 반응형 UI (모바일/태블릿/PC)
- 접근성 (WCAG 2.1 Level AA)
- 페이지 로딩: 2초 이내
- 모든 상담 기록 검색 가능

### 4. 고객 대시보드 섹션

#### 4-1. 상담 요약
```
┌────────────────────────────────────────┐
│          고객 대시보드                  │
├────────────────────────────────────────┤
│                                        │
│ 1. 상담 요약 (카드)                    │
│    ├─ 총 상담 횟수: 12회              │
│    ├─ 진행중 상담: 1개                │
│    ├─ 미해결 상담: 0개                │
│    ├─ 평균 만족도: 4.5/5점            │
│    └─ 마지막 상담: 2026-07-20 14:30 │
│                                        │
│ 2. 상담 목록 (테이블)                 │
│    ├─ 일시, 상담원명, 주제            │
│    ├─ 상태 (완료/진행중)              │
│    └─ 평가 (별점/댓글)                │
│                                        │
│ 3. 상담 요청 (버튼)                   │
│    ├─ 새 상담 요청                    │
│    ├─ 재상담 요청                     │
│    └─ 일정 예약                       │
│                                        │
│ 4. 알림 (벨 아이콘)                  │
│    ├─ 상담 상태 변경 알림             │
│    ├─ 상담원 할당 알림                │
│    └─ 새 메시지 알림                  │
│                                        │
└────────────────────────────────────────┘
```

#### 4-2. 상담 기록 목록

| 항목 | 설명 |
|------|------|
| 날짜 | 상담 일시 |
| 상담원 | 담당 상담원 이름 |
| 주제 | 상담 내용 (요약) |
| 상태 | 완료/진행중/대기 |
| 만족도 | 별점 (1~5) |
| 액션 | 상세보기/재상담요청 |

#### 4-3. 상담 상세 정보

```
클릭 시 전체 상담 기록 표시:
- 상담 요약
- 메시지 히스토리 (최근 10개만, "더보기" 버튼)
- AI 추천 (있으면)
- 상담원 평가
- 고객 평가 & 댓글
- 관련 상담 목록 (같은 주제)
```

#### 4-4. 개인정보 관리

```
프로필 수정:
- 이름, 전화, 이메일, 주소
- 선호 상담원 설정
- 상담 가능 시간대
- 언어 선택
```

#### 4-5. 알림 설정

```
알림 유형:
- 상담 시작: On/Off
- 새 메시지: On/Off
- 상담 완료: On/Off
- 프로모션: On/Off

채널:
- 앱 푸시 알림
- 이메일
- SMS (선택)
```

### 5. DB 참조
- `customers` - 고객 정보
- `chat_rooms` - 상담방
- `chat_messages` - 메시지
- `customer_feedback` - 만족도 평가
- `customer_notifications` - 알림 설정

### 6. API
- GET /api/v1/customers/{id}/dashboard
- GET /api/v1/customers/{id}/consults
- POST /api/v1/customers/{id}/feedback
- PUT /api/v1/customers/{id}/profile

### 7. Business Rule

```
고객 대시보드 규칙:

1. 정보 공개 범위
   - 자신의 상담 기록만 조회 가능
   - 상담원 이름 표시
   - 전화/이메일 마스킹 (상담원 보이지 않음)

2. 상담 기록 조회
   - 완료된 상담: 모두 조회 가능
   - 진행중인 상담: 실시간 업데이트
   - 삭제된 상담: 표시 안 함

3. 평가 작성
   - 상담 완료 후 24시간 이내 작성 권장
   - 수정 가능 (최대 3회)
   - 삭제 불가 (관리자만 삭제)

4. 알림 정책
   - 고객 선택사항 존중
   - 필수 알림: 상담 시작/완료
   - 선택 알림: 프로모션

5. 데이터 보유 기간
   - 상담 기록: 3년 보관
   - 피드백: 영구 보관
   - 접근 로그: 90일 보관
```

### 8. AI Rule
- 추천 상품: AI 분석 기반 표시
- 맞춤형 콘텐츠: AI 분석 감정/카테고리 기반

### 9. Exception
- 상담 접근 권한 없음 → 표시 안 함
- 상담 데이터 로드 실패 → 캐시된 데이터 표시 + 새로고침 버튼
- 평가 작성 실패 → 재시도 + 로컬 저장

### 10. Test Case

```
TC-DASH-001: 대시보드 로드
- 고객 로그인 → 대시보드 표시
- 예상: 2초 이내 로드, 모든 정보 표시

TC-DASH-002: 상담 기록 조회
- 상담 목록 클릭 → 상세 정보 표시
- 예상: 메시지 히스토리 전체 로드 가능

TC-DASH-003: 평가 작성
- 평가 입력 → 저장
- 예상: 1초 이내 저장, 즉시 표시

TC-DASH-004: 모바일 반응형
- 모바일 화면 (375px) 에서 모든 기능 정상
- 예상: 터치 조작 쉬움, 텍스트 읽기 용이

TC-DASH-005: 개인정보 관리
- 프로필 수정 → 저장
- 예상: 검증 후 저장, 즉시 반영
```

### 11. Future
- V4.0: 상담 예약 캘린더
- V4.5: 챗봇 학습 피드백
- V5.0: 개인화된 추천 ML 모델

## 검수 기준
- [ ] 대시보드 UI 설계 (5개 섹션)
- [ ] API 명세 (GET/POST/PUT)
- [ ] 반응형 디자인 (모바일/태블릿/PC)
- [ ] 접근성 고려 (WCAG)
- [ ] 보안 (개인정보 보호)
- [ ] 총 12~15페이지

---

# 🟢 작업 2~4: www/09_DEVELOPMENT/ 설계

## 작업 2: 01_개발WBS.md

### 목표
PlusTok V3.0 전체 개발 일정 및 작업 분해

### 포함 내용

#### 1. 목적(Purpose)
- 개발 작업 분해 (Work Breakdown Structure)
- 일정 계획 및 의존성 파악
- 팀 역할 분담
- 마일스톤 정의

#### 2. 범위(Scope)
- STEP 1~8 전체 구현
- DB 설계 및 DDL 생성
- API 개발
- WebSocket 구현
- Admin/Customer UI 개발

#### 3. WBS 계층

```
Level 1: Phase (단계)
├─ Phase 1: Platform Setup (STEP 1-2)
├─ Phase 2: Chat System (STEP 3-4)
├─ Phase 3: CRM Integration (STEP 5-6)
└─ Phase 4: Admin & Deployment (STEP 7-8)

Level 2: Category
├─ Backend (API/DB)
├─ Frontend (UI/Chat)
├─ DevOps (배포/모니터링)
└─ QA (테스트)

Level 3: Task
├─ 구체적인 구현 작업
├─ 예: "DB Schema 생성", "Login API 구현"
└─ 예상 시간, 담당자, 의존성
```

#### 4. 상세 WBS

```
[Phase 1] Platform Setup (2주)
├─ [Task 1.1] DB Schema 생성
│  ├─ 14개 테이블 DDL 작성
│  ├─ Index 생성
│  ├─ Foreign Key 설정
│  └─ 예상시간: 2일
│
├─ [Task 1.2] API Gateway 구축
│  ├─ Express 프로젝트 초기화
│  ├─ JWT 인증 미들웨어
│  ├─ 에러 핸들링
│  └─ 예상시간: 3일
│
├─ [Task 1.3] 기초 API (10개)
│  ├─ Auth: register, login, logout
│  ├─ Customer: get, update, delete
│  └─ 예상시간: 3일
│
└─ [Task 1.4] Frontend 프로젝트
   ├─ React 초기화
   ├─ UI 라이브러리 (Material-UI)
   ├─ 라우팅 설정
   └─ 예상시간: 2일

[Phase 2] Chat System (3주)
├─ [Task 2.1] WebSocket 서버
│  ├─ Socket.io 설치 및 설정
│  ├─ 연결 관리
│  ├─ 이벤트 정의 (15개)
│  └─ 예상시간: 5일
│
├─ [Task 2.2] Chat Frontend
│  ├─ 3-panel UI (좌: 채팅방 목록, 중: 메시지, 우: AI)
│  ├─ 메시지 입력/출력
│  ├─ 읽음표시
│  └─ 예상시간: 5일
│
├─ [Task 2.3] AI 통합
│  ├─ AI Router 구현
│  ├─ Failover 메커니즘
│  ├─ Prompt 관리
│  └─ 예상시간: 4일
│
└─ [Task 2.4] Chat API (15개)
   ├─ GET /api/v1/chat/rooms
   ├─ POST /api/v1/chat/messages
   ├─ PUT /api/v1/chat/messages/{id}/read
   └─ 예상시간: 4일

[Phase 3] CRM Integration (2주)
├─ [Task 3.1] CRM 자동화
│  ├─ 상담 종료 워크플로우
│  ├─ 후속 일정 생성
│  ├─ 이메일 템플릿
│  └─ 예상시간: 4일
│
└─ [Task 3.2] CRM API (10개)
   ├─ POST /api/v1/consults/close
   ├─ GET /api/v1/consults/{id}
   └─ 예상시간: 3일

[Phase 4] Admin & Deployment (2주)
├─ [Task 4.1] Admin Dashboard
│  ├─ 5개 섹션 UI 개발
│  ├─ 실시간 데이터 갱신
│  ├─ 차트 라이브러리 (Chart.js)
│  └─ 예상시간: 5일
│
├─ [Task 4.2] Customer Dashboard
│  ├─ 상담 기록 조회
│  ├─ 평가 작성
│  ├─ 프로필 관리
│  └─ 예상시간: 3일
│
├─ [Task 4.3] 배포 인프라
│  ├─ Docker 컨테이너화
│  ├─ CI/CD 파이프라인 (GitHub Actions)
│  ├─ 모니터링 설정 (Prometheus/Grafana)
│  └─ 예상시간: 4일
│
└─ [Task 4.4] QA & 배포
   ├─ 전체 테스트
   ├─ 성능 테스트
   ├─ 배포 리허설
   └─ 예상시간: 3일
```

#### 5. 일정 계획

```
Total Duration: 9주

Timeline:
Week 1-2:   Phase 1 (Platform Setup)
Week 3-5:   Phase 2 (Chat System)
Week 6-7:   Phase 3 (CRM)
Week 8-9:   Phase 4 (Admin & Deploy)

Milestones:
- Week 2 End: DB & API Gateway Ready
- Week 5 End: Chat & AI Integration Complete
- Week 7 End: CRM & Admin UI Complete
- Week 9 End: Go Live!
```

#### 6. 팀 구성

```
Backend Team (4명):
- Lead: Backend Architect
- API: 2명 (Chat API, CRM API)
- DevOps: 1명 (배포, 모니터링)

Frontend Team (3명):
- Lead: UI/UX Designer
- React: 2명 (Chat UI, Admin UI)

QA Team (2명):
- Test Automation
- Performance Testing

의존성:
- DB Schema 완료 → API 개발 시작
- API 개발 완료 → Frontend 개발 시작
- 모든 개발 완료 → QA & 배포
```

#### 7. 위험 요소 & 완화 방안

```
Risk 1: AI API 지연
- Impact: Chat 시스템 지연
- Mitigation: Mock AI 서버로 조기 테스트

Risk 2: 동시 1000명+ 연결 성능
- Impact: 배포 전 발견 시 재설계 필요
- Mitigation: Week 5 말 성능 테스트 완료

Risk 3: WebSocket 안정성
- Impact: 채팅 끊김
- Mitigation: 자동 재연결 로직 + 로컬 큐
```

## 검수 기준
- [ ] WBS 계층 명확
- [ ] 각 Task 예상시간 명시
- [ ] 의존성 파악
- [ ] 마일스톤 정의
- [ ] 팀 역할 분담
- [ ] 위험 요소 및 완화 방안
- [ ] 총 12~15페이지

---

## 작업 3: 02_테스트시나리오.md

### 목표
전체 기능 테스트 계획 및 검수 기준

### 포함 내용

#### 1. 목적(Purpose)
- 기능별 테스트 시나리오 정의
- 테스트 기준 명확화
- 버그 검출 최대화
- 배포 품질 보증

#### 2. 테스트 레벨

```
Unit Tests (개발 단계):
- API 로직 단위 테스트
- 각 함수/메서드 테스트
- 커버리지: 80% 이상

Integration Tests (모듈 단계):
- API + DB 통합 테스트
- WebSocket + Chat UI 통합
- 외부 API (AI) 통합

E2E Tests (전체 시스템):
- 로그인 → 채팅 → 평가 전체 플로우
- 대시보드 전체 기능
- 관리자 기능 전체
```

#### 3. 테스트 시나리오 (예시)

```
카테고리: 로그인 & 인증
TC-AUTH-001: 정상 로그인
- 입력: 유효한 이메일, 비밀번호
- 예상: 로그인 성공, 토큰 발급
- 검증: JWT 토큰 유효성, 쿠키 설정

TC-AUTH-002: 잘못된 비밀번호
- 입력: 유효한 이메일, 잘못된 비밀번호
- 예상: 로그인 실패, 에러 메시지
- 검증: 에러 코드 401

TC-AUTH-003: 존재하지 않는 계정
- 입력: 없는 이메일, 임의 비밀번호
- 예상: 로그인 실패
- 검증: 에러 메시지 표시

카테고리: 채팅
TC-CHAT-001: 메시지 송수신
- 입력: "안녕하세요" 메시지 전송
- 예상: 0.1초 이내 상대방 수신
- 검증: 메시지 표시, 타임스탬프

TC-CHAT-002: 오프라인 메시지
- 입력: 네트워크 끊김 상태에서 메시지 전송
- 예상: 로컬 저장, 네트워크 복구 후 전송
- 검증: 메시지 전송 상태 변경

카테고리: AI
TC-AI-001: AI 답변 생성
- 입력: "인터넷이 안 돼요" 질문
- 예상: 5초 이내 AI 답변
- 검증: 관련성 있는 답변 제시

TC-AI-002: AI Failover
- 입력: Claude API 타임아웃 시뮬레이션
- 예상: 자동으로 OpenAI로 전환
- 검증: 답변 수신 (다른 모델)

카테고리: 대시보드
TC-DASH-001: 통계 조회
- 입력: 관리자 로그인, 대시보드 오픈
- 예상: 2초 이내 모든 통계 로드
- 검증: 실시간 데이터 일치
```

#### 4. 테스트 커버리지

```
Backend API Coverage: 85%
- Auth: 90%
- Chat: 85%
- CRM: 80%
- Admin: 80%

Frontend UI Coverage: 80%
- Chat UI: 85%
- Admin Dashboard: 80%
- Customer Dashboard: 75%
- Settings: 80%

E2E Coverage: 100% 주요 플로우
- 회원가입 → 첫 상담 → 평가
- 상담원 로그인 → 상담 처리 → CRM 저장
- 관리자 로그인 → 통계 조회 → 설정 변경
```

#### 5. 성능 테스트

```
부하 테스트:
- 동시 사용자: 1000명
- 목표: 응답시간 < 500ms, CPU < 80%

메모리 테스트:
- 메모리 누수 여부 확인
- 목표: 안정적 메모리 사용

응답시간 테스트:
- API 응답시간: < 200ms
- WebSocket 메시지: < 100ms
- UI 렌더링: < 1s
```

#### 6. 보안 테스트

```
SQL Injection:
- 모든 API 입력 검증

XSS (Cross-Site Scripting):
- 사용자 입력 이스케이프 확인

CSRF (Cross-Site Request Forgery):
- CSRF 토큰 검증

인증/인가:
- 다른 사용자 데이터 접근 차단
- 권한 없는 API 접근 차단
```

## 검수 기준
- [ ] 테스트 시나리오 100개+
- [ ] 각 시나리오 예상 결과 명시
- [ ] 테스트 커버리지 정의
- [ ] 성능/보안 테스트 포함
- [ ] 자동화 테스트 계획
- [ ] 총 15~20페이지

---

## 작업 4: 03_배포운영.md

### 목표
배포 전략, 배포 절차, 운영 가이드

### 포함 내용

#### 1. 목적(Purpose)
- 안전한 배포 절차
- 무중단 배포 (Zero Downtime)
- 배포 이후 모니터링
- 긴급 롤백 계획

#### 2. 배포 환경

```
Development:
- URL: dev.plusok.local
- DB: PostgreSQL (로컬)
- AI: Mock API

Staging:
- URL: staging.plusok.io
- DB: PostgreSQL (staging)
- AI: 실제 API (테스트 키)

Production:
- URL: app.plusok.io
- DB: PostgreSQL (RDS)
- AI: 실제 API (프로덕션 키)
```

#### 3. 배포 파이프라인

```
GitHub Push (develop)
  ↓
CI 실행 (GitHub Actions)
  - Unit Tests (5분)
  - Lint (2분)
  - Build (10분)
  - 커버리지 리포트
  ↓
Staging 배포 (자동)
  - Docker 이미지 빌드
  - ECR에 푸시
  - ECS 업데이트
  - 스모크 테스트
  ↓
QA 검증 (2일)
  - 수동 테스트
  - 성능 테스트
  ↓
Master 머지 (승인 필요)
  ↓
Production 배포 (수동)
  - 일정: 월/수/금 15:00 KST
  - 사전 백업
  - 무중단 배포 (Blue-Green)
  - 헬스 체크
  ↓
배포 후 모니터링 (1시간)
  - 에러 로그 모니터링
  - 성능 지표 확인
  - 사용자 피드백
```

#### 4. 배포 절차

```
Pre-Deployment (배포 전)
1. 배포 체크리스트 확인
   - DB 백업
   - 환경 변수 확인
   - API 키 유효성 확인
   - 이전 버전 정보 저장

2. 팀 공지
   - Slack #deployments 채널
   - "배포 시작합니다. 5분 소요"

Deployment (배포 중)
1. Blue-Green 배포
   - 구 서버 (Blue): 활성
   - 신 서버 (Green): 배포 시작
   - 신 서버 헬스 체크 (통과할 때까지)
   - 로드 밸런서 전환 (Blue → Green)
   - 구 서버 대기 (1시간)

2. 진행 상황 모니터링
   - 에러율 확인
   - 응답시간 확인
   - DB 연결 상태 확인

Post-Deployment (배포 후)
1. 스모크 테스트
   - 주요 기능 동작 확인
   - 로그인 → 채팅 → 평가

2. 성능 확인
   - Dashboard 로딩 시간
   - API 응답시간
   - DB 쿼리 성능

3. 1시간 모니터링
   - 에러 로그 확인
   - 사용자 리포트 모니터링

4. 보고서 작성
   - 배포 완료 시간
   - 발생한 이슈 및 해결
   - 성능 지표
```

#### 5. 환경 변수 관리

```
AWS Secrets Manager 사용:
- CLAUDE_API_KEY
- OPENAI_API_KEY
- GOOGLE_API_KEY
- XAI_API_KEY
- DATABASE_URL
- JWT_SECRET
- REDIS_URL

배포 시 자동 주입:
- ECS Task Definition에 참조
- 환경별 분리 (dev/staging/prod)
```

#### 6. 모니터링 & 알림

```
모니터링 도구:
- CloudWatch (AWS 로그)
- Prometheus (메트릭)
- Grafana (대시보드)
- DataDog (APM)

주요 메트릭:
- API 응답시간 (P95, P99)
- 에러율 (5xx, 4xx)
- DB 연결 수
- WebSocket 동시 연결 수
- AI API 사용량 & 비용

알림 규칙:
- 에러율 > 1% → Slack 알림
- 응답시간 P99 > 1초 → 경고
- DB CPU > 80% → 경고
- 디스크 사용률 > 90% → 경고
```

#### 7. 롤백 전략

```
긴급 롤백 시:
1. 즉시 로드 밸런서 전환 (Green → Blue)
2. 이전 버전 재검증
3. 원인 분석

일반 롤백:
1. 이전 Docker 이미지 태그 확인
2. Blue 서버에 이미지 배포
3. 단계적 트래픽 전환

자동 롤백:
- 배포 후 5분 이내 에러율 > 5%
- 자동 롤백 실행
- 팀 알림 발송
```

#### 8. 장애 대응 (On-Call)

```
On-Call 로테이션:
- Backend Lead: 월~목
- Frontend Lead: 금~일
- DevOps: 항상 대기

장애 대응 절차:
1. 즉시 모니터링 대시보드 확인
2. Slack #incidents 채널에 보고
3. 원인 파악 (3분)
4. 조치 (롤백/핫픽스/스케일링)
5. 사후 분석 (24시간 내)
```

## 검수 기준
- [ ] 배포 파이프라인 명확
- [ ] 배포 체크리스트
- [ ] Blue-Green 배포 상세
- [ ] 모니터링 설정
- [ ] 롤백 절차
- [ ] On-Call 가이드
- [ ] 총 12~15페이지

---

# 📋 작업 체크리스트

## STEP 7: 고객 대시보드
- [ ] 1~11. 모든 섹션 작성
- [ ] 5개 대시보드 섹션 상세
- [ ] 반응형 디자인
- [ ] 접근성 고려
- [ ] **최종 검수** (12~15페이지)

## STEP 8: 개발·배포 (3개 문서)

### 01_개발WBS.md
- [ ] WBS 계층 (Phase/Category/Task)
- [ ] 상세 작업 분해
- [ ] 일정 계획 (9주)
- [ ] 팀 구성 및 역할
- [ ] 위험 요소 및 완화 방안
- [ ] **최종 검수** (12~15페이지)

### 02_테스트시나리오.md
- [ ] 테스트 레벨 (Unit/Integration/E2E)
- [ ] 테스트 시나리오 100개+
- [ ] 테스트 커버리지 정의
- [ ] 성능/보안 테스트
- [ ] 자동화 테스트 계획
- [ ] **최종 검수** (15~20페이지)

### 03_배포운영.md
- [ ] 배포 환경 (Dev/Staging/Prod)
- [ ] 배포 파이프라인 (CI/CD)
- [ ] 배포 절차 (Pre/During/Post)
- [ ] Blue-Green 배포 상세
- [ ] 모니터링 & 알림
- [ ] 롤백 & On-Call 가이드
- [ ] **최종 검수** (12~15페이지)

---

# 🎁 산출물

**예상 산출물:**
```
총 4개 문서 (08_DASHBOARD 1개, 09_DEVELOPMENT 3개)
약 40~50페이지
Markdown 형식
개발·배포 팀이 즉시 실행 가능한 수준
```

---

# 🚀 Cursor 작업 방법

**Cursor에 다음과 같이 입력:**

```
STEP 7, 8 최종 작업지시서 기준으로 다음 4개 문서를 작성해줘:

STEP 7 (1개):
1. www/08_DASHBOARD/01_고객대시보드.md (12~15페이지)
   - 고객용 상담 기록 조회 대시보드
   - 5개 섹션 (상담요약, 기록목록, 상세정보, 개인정보, 알림)
   - 반응형 UI (모바일/태블릿/PC)
   - 접근성 (WCAG 2.1 Level AA)
   - API: GET/POST/PUT 명세

STEP 8 (3개):
2. www/09_DEVELOPMENT/01_개발WBS.md (12~15페이지)
   - Phase 1~4 작업 분해도
   - 각 Task별 예상시간, 담당자, 의존성
   - 마일스톤 및 일정 (9주)
   - 팀 구성 (Backend 4, Frontend 3, QA 2)
   - 위험요소 및 완화방안

3. www/09_DEVELOPMENT/02_테스트시나리오.md (15~20페이지)
   - 테스트 레벨 (Unit/Integration/E2E)
   - 테스트 시나리오 100개+ 정의
   - 테스트 커버리지 (85~100%)
   - 성능 & 보안 테스트
   - 자동화 테스트 계획

4. www/09_DEVELOPMENT/03_배포운영.md (12~15페이지)
   - 배포 환경 (Dev/Staging/Prod)
   - CI/CD 파이프라인 상세
   - Blue-Green 무중단 배포
   - 배포 체크리스트
   - 모니터링 (CloudWatch/Prometheus/Grafana)
   - 롤백 절차 및 On-Call 가이드

모든 내용을 11개 섹션 구조로 작성하고,
STEP 1~6 문서 형식과 일관성 있게 작성해줘.
```

---

# 🎯 모든 STEP 완료!

**STEP 1~8 작업지시서 생성 완료:**

✅ STEP 1: 프로젝트 마스터  
✅ STEP 2: DB & API 시스템  
✅ STEP 3: AI 설계  
✅ STEP 4: WebSocket & 채팅  
✅ STEP 5: CRM 통합  
✅ STEP 6: 관리자 대시보드  
✅ STEP 7: 고객 대시보드  
✅ STEP 8: 개발·배포  

**총 30개 이상의 문서 작성**  
**약 200페이지 규모의 엔터프라이즈 스펙**  
**11개 섹션 구조로 AI 개발 친화적 설계**

---

**모든 준비 완료! Cursor에서 작업 시작하세요! 🚀**
