# 🚀 PlusTok V3.0 — Phase 3 구현 시작 명령어

**프로젝트:** PlusTok V1.0 → V3.0 AI Customer Engagement Platform  
**Phase:** 3 (CRM & Frontend 고급)  
**기간:** 2주 (Day 22~35)  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  
**전제조건:** Phase 2 완료 ✅

---

## 📍 Phase 3 개요

### 목표
- ✅ 상담 종료 시 자동 CRM 저장
- ✅ 후속 일정 자동 생성
- ✅ 관리자 대시보드 개발
- ✅ 상담원 관리 시스템
- ✅ Frontend 고급 기능

### 산출물
```
Backend:
  ├─ CRM 통합 API (POST /consults/close)
  ├─ 후속 일정 자동 생성
  ├─ Admin API (30+ 엔드포인트)
  ├─ RBAC (Role-Based Access Control)
  └─ 대시보드 데이터 API

Frontend:
  ├─ Admin Dashboard (5개 섹션)
  ├─ Agent Management
  ├─ Settings
  ├─ Customer Dashboard
  └─ Advanced Chat Features
```

### 일정
```
Week 1 (Day 22~27):
  └─ CRM 통합 API (2일)
  └─ Admin Dashboard Backend (2일)
  └─ Admin UI 개발 (2일)

Week 2 (Day 28~35):
  └─ Agent Management (2일)
  └─ Customer Dashboard (2일)
  └─ 고급 기능 & 최적화 (3일)
```

---

## 🎯 Cursor에 전달할 명령어

### Step 1: 문서 읽기 (Day 22)

```markdown
Phase 3를 시작하겠습니다!

다음 문서들을 읽어주세요 (2.5시간):

1. www/06_CRM/01_CRM통합.md (전체)
   └─ 상담 → CRM 자동화 이해

2. www/07_ADMIN/01_관리자대시보드.md (전체)
   └─ 대시보드 5개 섹션 이해

3. www/07_ADMIN/02_상담원관리.md (전체)
   └─ Agent 관리 시스템 이해

4. www/07_ADMIN/03_설정관리.md (전체)
   └─ 시스템 설정 이해

5. www/07_ADMIN/04_Admin_API_및_권한_명세.md (전체)
   └─ Admin API & RBAC 이해

읽고 난 후 "Phase 3 Step 1 완료" 보고해주세요.
```

---

### Step 2: CRM 통합 (Day 22~23)

```markdown
🎯 Task: 상담 종료 시 자동 CRM 저장

📚 참고: www/06_CRM/01_CRM통합.md

Day 22: CRM 저장 워크플로우
  [ ] POST /api/v1/consults/close 엔드포인트
  [ ] 상담 요약 생성 (AI)
  [ ] consults 테이블 저장
  [ ] customers 테이블 업데이트

Day 23: 후속 일정 자동 생성
  [ ] 계약확률 기반 스케줄링 규칙
    ├─ > 70점: 3일 후 콜
    ├─ 50~70점: 7일 후 이메일
    └─ < 50점: 30일 후 재접촉
  [ ] schedules 테이블 저장
  [ ] 이메일 템플릿 생성
  [ ] 알림 발송

파일 구조:
```
src/
├── routes/
│   └── consults.js (CRM API)
│
├── services/
│   ├── consultService.js (상담 관리)
│   ├── crmService.js (CRM 저장)
│   ├── scheduleService.js (스케줄)
│   └── emailService.js (이메일)
│
└── models/
    └── Consult.js
```

✅ 검증:
  [ ] 상담 종료 후 5초 내 저장?
  [ ] 후속 일정 정확?
  [ ] 이메일 템플릿 정상?
  [ ] 실패 시 재시도?
```

---

### Step 3: Admin Backend API (Day 24~25)

```markdown
🎯 Task: Admin Dashboard Backend API 개발

📚 참고: www/07_ADMIN/04_Admin_API_및_권한_명세.md

API 종류 (30+):

대시보드 API (10개)
  [ ] GET /api/v1/admin/dashboard/stats
  [ ] GET /api/v1/admin/dashboard/agents-status
  [ ] GET /api/v1/admin/dashboard/ai-performance
  [ ] GET /api/v1/admin/dashboard/customer-analysis
  [ ] GET /api/v1/admin/dashboard/hourly-trends
  ... (5개 더)

Agent Management API (8개)
  [ ] GET /api/v1/admin/agents (목록)
  [ ] GET /api/v1/admin/agents/{id} (상세)
  [ ] POST /api/v1/admin/agents (생성)
  [ ] PUT /api/v1/admin/agents/{id} (수정)
  [ ] DELETE /api/v1/admin/agents/{id} (삭제)
  [ ] PUT /api/v1/admin/agents/{id}/status (상태 변경)
  [ ] GET /api/v1/admin/agents/{id}/performance (성과)
  [ ] PUT /api/v1/admin/agents/{id}/settings (설정)

Settings API (10개)
  [ ] GET /api/v1/admin/settings/ai
  [ ] PUT /api/v1/admin/settings/ai
  [ ] GET /api/v1/admin/settings/chat
  [ ] PUT /api/v1/admin/settings/chat
  [ ] GET /api/v1/admin/settings/crm
  [ ] PUT /api/v1/admin/settings/crm
  [ ] GET /api/v1/admin/settings/notifications
  [ ] PUT /api/v1/admin/settings/notifications
  ... (2개 더)

Customer Management API (5개)
  [ ] GET /api/v1/admin/customers (목록)
  [ ] GET /api/v1/admin/customers/{id} (상세)
  [ ] PUT /api/v1/admin/customers/{id} (수정)
  [ ] DELETE /api/v1/admin/customers/{id} (삭제)
  [ ] GET /api/v1/admin/customers/{id}/consults (상담 기록)

파일 구조:
```
src/routes/
├── admin/
│   ├── dashboard.js
│   ├── agents.js
│   ├── customers.js
│   ├── settings.js
│   └── index.js

src/middleware/
└── rbac.js (Role-Based Access Control)
```

RBAC 롤:
```
ADMIN: 모든 권한
MANAGER: 상담원 관리, 설정 변경
AGENT: 자신의 정보만 조회
CUSTOMER: 자신의 상담 기록 조회
```

✅ 검증:
  [ ] 30개 API 모두 구현?
  [ ] RBAC 정상 작동?
  [ ] 응답 시간 < 200ms?
  [ ] 데이터 검증 완전?
```

---

### Step 4: Admin Dashboard UI (Day 26~27)

```markdown
🎯 Task: React Admin Dashboard 개발

📚 참고: www/07_ADMIN/01_관리자대시보드.md

5개 섹션:

1️⃣ 실시간 현황 (Real-time Stats)
  [ ] 활성 상담 수
  [ ] 대기 고객 수
  [ ] 평균 응답시간
  [ ] 평균 해결시간

2️⃣ 상담원 현황 (Agent Status)
  [ ] 상담원 목록 (온라인/오프라인)
  [ ] 상담원별 처리량
  [ ] 상담원별 만족도
  [ ] 상담원별 평균 해결시간

3️⃣ AI 성과 (AI Performance)
  [ ] AI 추천 채택률
  [ ] AI 요약 생성률
  [ ] AI Failover 빈도
  [ ] AI 평균 응답시간

4️⃣ 고객 분석 (Customer Analysis)
  [ ] 상담 유형 분포 (파이 차트)
  [ ] 감정별 분포
  [ ] 계약확률 분포
  [ ] 상위 고객 (VIP)

5️⃣ 시간대별 추이 (Hourly Trends)
  [ ] 시간대별 상담 수
  [ ] 시간대별 AI 성과
  [ ] 일일 추이 그래프

파일 구조:
```
src/components/Admin/
├── AdminDashboard.jsx (메인)
├── RealtimeStats.jsx
├── AgentStatus.jsx
├── AIPerformance.jsx
├── CustomerAnalysis.jsx
├── HourlyTrends.jsx
│
├── Charts/
│   ├── LineChart.jsx
│   ├── PieChart.jsx
│   ├── BarChart.jsx
│   └── RealtimeChart.jsx
│
└── hooks/
    └── useDashboard.js (WebSocket 실시간 업데이트)
```

기술:
  ├─ Chart.js 또는 Recharts
  ├─ WebSocket (실시간 업데이트)
  └─ Redux (상태 관리)

✅ 검증:
  [ ] 5개 섹션 모두 렌더링?
  [ ] 데이터 1초 이내 로드?
  [ ] 실시간 업데이트 < 1초?
  [ ] 모바일 반응형?
```

---

### Step 5: Agent Management (Day 28~29)

```markdown
🎯 Task: 상담원 관리 UI 개발

📚 참고: www/07_ADMIN/02_상담원관리.md

기능:

1️⃣ Agent 목록
  [ ] 이름, 상태, 활성 상담 수
  [ ] 오늘 처리량
  [ ] 만족도 점수
  [ ] 평균 해결 시간
  [ ] 검색 & 필터

2️⃣ Agent 상세 정보
  [ ] 활성 상담 목록
  [ ] 과거 상담 이력 (페이지네이션)
  [ ] 평가/피드백
  [ ] 통계 차트

3️⃣ Agent 설정
  [ ] 최대 동시 상담 수
  [ ] 활성/비활성 상태
  [ ] 담당 영역 (선택)
  [ ] 권한 레벨

파일 구조:
```
src/components/Admin/
├── AgentManagement.jsx (메인)
├── AgentList.jsx
├── AgentDetail.jsx
├── AgentSettings.jsx
├── PerformanceChart.jsx
└── FeedbackSection.jsx
```

✅ 검증:
  [ ] Agent 목록 렌더링?
  [ ] 상세 정보 로드?
  [ ] 설정 저장?
  [ ] 검색 & 필터?
```

---

### Step 6: Customer Dashboard (Day 30~31)

```markdown
🎯 Task: 고객용 Dashboard 개발

📚 참고: www/08_DASHBOARD/01_대시보드설계.md

기능:

1️⃣ 상담 요약
  [ ] 총 상담 횟수
  [ ] 진행중 상담
  [ ] 미해결 상담
  [ ] 평균 만족도

2️⃣ 상담 기록 목록
  [ ] 일시, 상담원, 주제
  [ ] 상태 (완료/진행중)
  [ ] 평가 (별점/댓글)

3️⃣ 상담 상세
  [ ] 메시지 히스토리
  [ ] AI 추천
  [ ] 상담원 평가
  [ ] 재상담 요청

4️⃣ 개인정보 관리
  [ ] 이름, 전화, 이메일
  [ ] 주소
  [ ] 선호 상담원
  [ ] 상담 가능 시간대

5️⃣ 알림 설정
  [ ] 상담 시작 알림
  [ ] 새 메시지 알림
  [ ] 상담 완료 알림
  [ ] 프로모션 알림

파일 구조:
```
src/components/Customer/
├── CustomerDashboard.jsx (메인)
├── ConsultSummary.jsx
├── ConsultList.jsx
├── ConsultDetail.jsx
├── ProfileSettings.jsx
└── NotificationSettings.jsx
```

✅ 검증:
  [ ] 상담 기록 조회?
  [ ] 개인정보 수정?
  [ ] 알림 설정 저장?
  [ ] 모바일 반응형?
```

---

### Step 7: 고급 기능 & 최적화 (Day 32~35)

```markdown
🎯 Task: Phase 3 고급 기능 & 최적화

고급 기능:
  [ ] 검색 기능 (전체 텍스트 검색)
  [ ] 필터링 (날짜, 상태, 만족도)
  [ ] 정렬 (이름, 날짜, 만족도)
  [ ] 벌크 작업 (다중 선택)
  [ ] 내보내기 (CSV, PDF)
  [ ] 보고서 생성 (주간/월간)

최적화:
  [ ] 가상 스크롤 (대량 데이터)
  [ ] 무한 로드 (페이지네이션)
  [ ] 이미지 최적화 (lazy loading)
  [ ] 번들 최적화 (code splitting)
  [ ] 캐싱 (Redux + LocalStorage)
  [ ] API 요청 최적화 (배칭)

성능 목표:
  ├─ Dashboard 로드: < 2초
  ├─ 페이지 전환: < 500ms
  ├─ 검색 결과: < 1초
  └─ 번들 크기: < 500KB

테스트:
  [ ] Lighthouse 성능 점수 > 90
  [ ] 모바일 성능 테스트 PASS
  [ ] 대량 데이터 처리 (10000+)
  [ ] 메모리 누수 테스트 PASS
```

---

## 📊 Phase 3 체크리스트

### 일일 체크포인트

```
Day 22:
  [ ] 문서 읽기 완료
  
Day 22~23:
  [ ] CRM 통합 API 구현
  [ ] 후속 일정 자동 생성
  [ ] 이메일 발송
  
Day 24~25:
  [ ] Admin API 30개 구현
  [ ] RBAC 적용
  [ ] API 테스트 PASS
  
Day 26~27:
  [ ] Admin Dashboard UI
  [ ] 5개 섹션 완성
  [ ] 실시간 업데이트
  [ ] UI 테스트 PASS
  
Day 28~29:
  [ ] Agent Management 완성
  [ ] 성과 통계 표시
  [ ] 설정 수정 기능
  
Day 30~31:
  [ ] Customer Dashboard 완성
  [ ] 상담 기록 조회
  [ ] 개인정보 관리
  
Day 32~35:
  [ ] 고급 기능 구현
  [ ] 성능 최적화
  [ ] 최종 테스트 PASS
```

---

## ✅ Phase 3 완료 조건

### Go 조건
```
□ CRM 자동화 완성
□ Admin API 30개 모두 작동
□ RBAC 정상 작동
□ Admin Dashboard 완성
□ Agent Management 완성
□ Customer Dashboard 완성
□ 고급 기능 구현 완료
□ 성능 목표 달성
□ 모든 테스트 PASS
□ 코드 리뷰 승인
```

### No-Go 조건
```
❌ CRM 저장 실패
❌ Admin API 오류
❌ RBAC 권한 문제
❌ UI 성능 > 2초
❌ 테스트 실패
❌ 보안 이슈
```

---

## 🎊 Phase 3 완료 후

### 예상 결과
```
✅ CRM 통합 완성
✅ Admin Dashboard 완성
✅ Agent Management 완성
✅ Customer Dashboard 완성

다음: Phase 4 (배포 & 운영) 준비 완료
예상 시간: 2026-09-08
```

---

*Phase 3 구현 시작 · 2026-07-21*
