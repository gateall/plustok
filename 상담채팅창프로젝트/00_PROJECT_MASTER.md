# AI Customer Engagement Platform (PlusTok Enterprise)

**Version:** 1.0  
**Type:** Enterprise Specification  
**Document:** MASTER DOCUMENT (Constitution)  
**Status:** Draft v1.0  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Project Master  
**Audience:** Architects, Developers, AI Models, QA  

---

## 문서 개요

본 문서는 **AI Customer Engagement Platform (ACEP)** — 상품명 **PlusTok Enterprise** — 의 최상위 헌장(Constitution) 문서이다. 모든 하위 설계 문서, API 명세, UI/UX 설계, AI 프롬프트, 테스트 케이스는 본 문서의 원칙과 용어를 따른다.

| 항목 | 내용 |
|------|------|
| 프로젝트 코드명 | ACEP |
| 상품명 | PlusTok Enterprise |
| 기존 제품 연계 | PLUS톡 V2.0 (PHP 8.4, MariaDB, 멀티 AI Failover) |
| 1차 MVP 화면 | [상담채팅화면 UI/UX](02_UIUX/01_상담채팅화면.fig.md) |
| UI 컴포넌트 | [UI Components Guide](02_UIUX/UI_COMPONENTS_GUIDE.md) |

---

## PART 1. Vision (비전)

### 1.1 프로젝트 목표

> **"AI가 상담원의 옆자리 직원처럼 동작하는 상담 플랫폼"**

PlusTok Enterprise는 고객과 상담원 사이의 실시간 채팅을 중심으로, AI가 상담 과정 전반에 걸쳐 **분석·추천·기록·판단**을 수행하는 B2B/B2B2C 고객 상담 플랫폼이다. 상담원은 별도 도구를 전환하지 않고 채팅 화면 하나에서 CRM 입력, 이메일 작성, FAQ 검색, 계약 확률 확인, 후속 일정 생성을 처리한다.

기존 PLUS톡 V2.0에서 검증된 **PHP 8.4 백엔드**, **MariaDB**, **Claude → GPT → Gemini → Grok Failover** 아키텍처를 Enterprise급으로 확장·정형화한다.

### 1.2 해결하고자 하는 문제

| 문제 영역 | 현재 Pain Point | ACEP 해결 방향 |
|-----------|-----------------|----------------|
| 상담원 수작업 | 상담 후 요약, CRM 기입, 이메일 작성에 30~40% 시간 소모 | AI 자동 요약 + CRM 자동 기록 |
| 응답 지연 | FAQ/가격표/상품 정보 검색으로 고객 대기 발생 | AI 옆자리 추천 (1클릭 전송) |
| 계약 확률 파악 | 상담원 경험·직관에 의존, 우선순위 판단 불일치 | AI 계약확률 0~100점 실시간 산출 |
| 후속관리 누락 | 상담 종료 후 일정·견적·계약 후속 누락 | AI 일정 생성 + CRM 워크플로우 |
| 도구 분산 | 채팅, CRM, 이메일, 통계가 별도 시스템 | 단일 상담채팅 화면 통합 |

### 1.3 경쟁 제품 분석

#### 카카오톡 / 네이버 톡톡
- **강점:** 실시간 채팅 UX, 높은 사용자 친숙도, 모바일 최적화
- **약점:** AI 상담 보조 없음, CRM/계약관리 미통합, B2B 상담 워크플로우 부재
- **ACEP 차별점:** 카카오톡 수준 UX + AI 옆자리 직원 + CRM 자동 연동

#### 그누보드 CRM / 국내 CRM 솔루션
- **강점:** 고객·계약·통계 기능 풍부, 커스터마이징 가능
- **약점:** UI 복잡, 실시간 채팅과 분리, AI 기능 제한적
- **ACEP 차별점:** 채팅 중심 단순 UX, AI가 CRM 데이터를 자동 채움

#### 타사 AI 상담 솔루션 (별도 AI 버튼형)
- **강점:** GPT/Claude 기반 답변 생성
- **약점:** AI가 별도 패널·버튼에 격리, 상담 흐름과 단절, Failover 미비
- **ACEP 차별점:** AI가 채팅 옆에서 **실시간** 추천, PLUS톡 V2.0 검증 Failover 체인

### 1.4 핵심 차별화 요소

1. **옆자리 AI (Side-by-Side AI):** 상담원이 채팅하는 동안 AI 패널에 추천답변·계약확률·FAQ가 자동 갱신
2. **1클릭 전송:** 추천답변 클릭 → 입력창 반영 → 전송 (복사·붙여넣기 불필요)
3. **멀티 AI Failover:** Claude → GPT → Gemini → Grok 자동 전환 (PLUS톡 V2.0 상속)
4. **CRM Zero-Input:** 상담 종료 시 요약·태그·일정이 CRM에 자동 저장
5. **Enterprise 확장성:** V1 MVP → 멀티테넌트 SaaS까지 단계적 로드맵

### 1.5 목표 시장 (Target Market)

| 세그먼트 | 대표 Use Case | ACEP 적용 시나리오 |
|----------|---------------|-------------------|
| 인터넷 판매사 | 가입·설치·요금 문의 | 실시간 견적, 프로모션 FAQ, 계약확률 |
| 통신사 (KT, SKT, LG U+) | 요금제·기기·해지 문의 | 상품추천, 감정분석, VIP 우선 대응 |
| 건설사 | 분양·모델하우스·계약 문의 | 일정생성, 고객분석, 계약확률 |
| 자동차 판매사 | 시승·견적·할부 문의 | 상품추천, Cross-selling, CRM 연동 |
| 보험사 | 가입·보장·청구 문의 | FAQ 검색, 문서검색, 규정 준수 AI |

### 1.6 성공 지표 (KPI)

| KPI | V1.0 목표 | Enterprise 목표 |
|-----|-----------|-----------------|
| 평균 첫 응답 시간 | 30초 이내 | 15초 이내 |
| AI 추천 채택률 | 40% 이상 | 60% 이상 |
| CRM 자동 기록률 | 80% 이상 | 95% 이상 |
| 상담원 1인당 처리량 | 기존 대비 +20% | 기존 대비 +50% |
| AI Failover 성공률 | 99.9% | 99.99% |

---

## PART 2. Business (비즈니스)

### 2.1 이해관계자 (Stakeholders)

ACEP는 5가지 핵심 역할을 정의한다. 각 역할은 RBAC(역할 기반 접근 제어)로 권한이 분리된다.

```
┌─────────────────────────────────────────────────────────────┐
│                    ACEP Stakeholder Map                      │
├──────────┬──────────────────────────────────────────────────┤
│ Customer │ 채팅 접수 → 실시간 응답 수신 → 계약/후속           │
│ Agent    │ 채팅 대응 → AI 추천 확인 → 1클릭 전송 → CRM      │
│ Admin    │ 모니터링 → 통계 → 상담원/AI 설정                   │
│ AI       │ 분석 → 추천 → 계약확률 → FAQ → CRM 기록            │
│ Operator │ 시스템 유지 → 프롬프트 관리 → 보안/백업             │
└──────────┴──────────────────────────────────────────────────┘
```

### 2.2 Customer (고객)

**정의:** ACEP 채팅 위젯 또는 전용 URL을 통해 상담을 요청하는 최종 사용자.

| 속성 | 설명 |
|------|------|
| 접수 채널 | 웹 채팅 위젯, 대표번호 연계 랜딩, CCTV/인터넷/계약 문의 페이지 |
| 기대 | 실시간 응답, 친절한 안내, 빠른 견적/일정 확정 |
| 데이터 | `customers` 테이블, `chat_rooms` (고객 1:N 상담방) |
| 권한 | 자신의 상담방·메시지만 조회 |

**고객 여정 (Customer Journey):**

```
문의 페이지 접속 → 채팅 위젯 클릭 → 이름/연락처 입력(선택)
    → 상담방 자동 생성 → AI 초기 분석 → 상담원 배정/응답
    → 실시간 대화 → 상담 종료 → (선택) 피드백
```

### 2.3 Agent (상담원)

**정의:** 실시간 채팅을 담당하는 CS/영업 담당자.

| 업무 | ACEP 지원 기능 |
|------|---------------|
| 실시간 채팅 대응 | WebSocket 기반 양방향 메시지 |
| AI 추천 확인 | 우측 AI Assistant 패널 (`AIPanelCard`, `RecommendationCard`) |
| 1클릭 답변 전송 | `RecommendationCard` 클릭 → `InputField` → `ActionButton` 전송 |
| CRM 자동 저장 | 상담 종료 시 AI 요약 → CRM API 연동 |
| 우선순위 판단 | `StatusBadge` + AI 계약확률 점수 |

**상담원 화면:** [상담채팅화면 UI/UX 설계](02_UIUX/01_상담채팅화면.fig.md)

### 2.4 Admin (관리자)

**정의:** 조직 내 상담 운영·통계·설정을 총괄하는 관리자.

| 기능 영역 | 상세 |
|-----------|------|
| 상담 현황 모니터링 | 실시간 대시보드, 상담원별 처리량 |
| 통계 분석 | AI 성공률, 계약 전환율, 평균 응답 시간 |
| 상담원 관리 | 계정 CRUD, 근무 상태, 상담 배정 규칙 |
| AI 설정 | 프롬프트 버전, Failover 순서, Rate Limit |

### 2.5 AI (Assistant)

**정의:** ACEP 내 AI Router를 통해 Claude/GPT/Gemini/Grok 중 활성 모델이 수행하는 논리적 역할.

AI는 독립 "사용자"가 아니라 **백엔드 서비스 레이어**로 동작한다. 상담원 화면에는 `ai_recommendations` 테이블 기반 추천 결과로 표시된다.

| AI 출력 | 저장 위치 | UI 컴포넌트 |
|---------|-----------|-------------|
| 추천답변 | `ai_recommendations` | `RecommendationCard` |
| 계약확률 | `ai_recommendations` | `AIPanelCard` |
| FAQ | `ai_recommendations` | `AIPanelCard` |
| 고객분석 | `customers` + `ai_recommendations` | `CustomerCard` |

### 2.6 Operator (운영자)

**정의:** 인프라·보안·AI 프롬프트를 관리하는 DevOps/플랫폼 운영 담당.

| 책임 | 상세 |
|------|------|
| 시스템 유지보수 | Docker, Nginx, MariaDB, Redis |
| AI 프롬프트 관리 | Prompt 버전 관리, Rollback |
| 데이터 보안 | API Key 로테이션, 백업, 감사 로그 |
| Failover 모니터링 | `ai_failover_log` 분석, SLA 대응 |

### 2.7 비즈니스 프로세스 요약

```
[접수] 고객 채팅 시작
    ↓
[배정] 상담방 생성 (chat_rooms) + CRM 고객 레코드 (customers)
    ↓
[상담] 실시간 채팅 (chat_messages) + AI 추천 (ai_recommendations)
    ↓
[종료] AI 요약 + CRM 저장 + 후속일정 제안
    ↓
[분석] Dashboard 통계 반영
```

---

## PART 3. Platform (플랫폼)

### 3.1 플랫폼 정의

ACEP는 **5대 핵심 모듈**로 구성된 통합 상담 플랫폼이다.

```
┌─────────────────────────────────────────────────────────────┐
│                  AI Engagement Platform                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│ 1. Reception (접수)                                          │
│    ↓ 고객이 채팅 접수                                        │
│    ↓ 상담방 자동 생성 (chat_rooms)                           │
│    ↓ CRM 자동 생성 (customers)                               │
│    ↓ AI 초기 분석 (의도·감정·긴급도)                         │
│                                                               │
│ 2. Chat (상담)                                               │
│    ↓ 실시간 양방향 채팅 (WebSocket + chat_messages)          │
│    ↓ AI가 옆자리에서 추천 (ai_recommendations)               │
│    ↓ 상담원 1클릭 전송 (RecommendationCard)                  │
│                                                               │
│ 3. CRM (관리)                                                │
│    ↓ 상담 내용 자동 기록                                     │
│    ↓ 계약 확률 산출 (0~100)                                  │
│    ↓ 추천 상품 제시                                          │
│                                                               │
│ 4. AI (분석)                                                 │
│    ↓ 감정 분석 (긍정/중립/부정)                              │
│    ↓ 의도 파악 (구매/문의/불만)                             │
│    ↓ 계약 확률 (0~100)                                       │
│                                                               │
│ 5. Dashboard (통계)                                          │
│    ↓ 실시간 상담 현황                                        │
│    ↓ AI 성공률                                               │
│    ↓ 상담원별 통계                                           │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 모듈별 상세

#### 3.2.1 Reception (접수)

| 기능 | 설명 | API/테이블 |
|------|------|-----------|
| 채팅 위젯 | 고객 웹사이트 임베드 | `POST /api/chats/rooms` |
| 상담방 생성 | 고객·문의유형·채널 자동 기록 | `chat_rooms` |
| CRM 연동 | 신규/기존 고객 자동 매칭 | `customers` |
| AI 초기 분석 | 첫 메시지 기반 의도·감정 분석 | `ai_recommendations` |

#### 3.2.2 Chat (상담)

| 기능 | 설명 | API/테이블 |
|------|------|-----------|
| 실시간 메시지 | WebSocket 양방향 | `chat_messages` |
| 읽음 표시 | ✓ / ✓✓ | `chat_read_status` |
| 입력 중 표시 | TypingIndicator | WebSocket event |
| 파일 전송 | 이미지·PDF | `chat_messages.attachment_url` |

#### 3.2.3 CRM (관리)

| 기능 | 설명 |
|------|------|
| 자동 기록 | 상담 종료 시 AI 요약 → CRM 필드 매핑 |
| 계약 확률 | 실시간 0~100점, 우선순위 큐 결정 |
| 상품 추천 | Cross-selling 후보 제시 |

#### 3.2.4 AI (분석)

PART 5 AI Strategy 10역할 참조. 모든 AI 호출은 AI Router + Failover 체인을 통과한다.

#### 3.2.5 Dashboard (통계)

| 지표 | 집계 주기 |
|------|----------|
| 실시간 상담 수 | 1분 |
| AI 추천 채택률 | 1시간 |
| 상담원별 처리량 | 1일 |
| 계약 전환율 | 1일/1주 |

### 3.3 PLUS톡 V2.0 연계

| PLUS톡 V2.0 | ACEP Enterprise 확장 |
|-------------|---------------------|
| PHP 8.4 Backend | Repository/Service Layer 정형화 |
| MariaDB | 14+ 테이블 ERD 표준화 |
| Claude→GPT→Gemini→Grok Failover | Rule-001 공식화, `ai_failover_log` |
| 기본 채팅 | 3패널 UI + AI Assistant 패널 |
| 단일 테넌트 | Enterprise 멀티테넌트 SaaS |

---

## PART 4. Architecture (아키텍처)

### 4.1 시스템 아키텍처 다이어그램

```
┌──────────────────────────────────────────────────────────┐
│                      Client (브라우저)                    │
│  - HTML5 + React 18 + TypeScript                         │
│  - TailwindCSS + Socket.io Client                        │
│  - 컴포넌트: MessageBubble, ChatList, AIPanelCard 등     │
└──────────────────────────────────────────────────────────┘
                            ↓ WebSocket (Socket.io)
                            ↓ REST API (HTTPS)
┌──────────────────────────────────────────────────────────┐
│                   Chat Server (Node.js 20+)               │
│  - Socket.io (실시간 통신)                               │
│  - 메시지 라우팅 (room 단위)                             │
│  - 세션 관리 / TypingIndicator 브로드캐스트              │
└──────────────────────────────────────────────────────────┘
                            ↓ REST API (Internal)
┌──────────────────────────────────────────────────────────┐
│              Backend Server (PHP 8.4 / Node.js)           │
│  - 인증 & 권한 (JWT + RBAC)                              │
│  - DB 조회/저장 (Repository Pattern)                     │
│  - AI Router (Failover Chain)                            │
│  - CRM 연동 (REST/Webhook)                               │
│  - API: /api/v1/chats/*, /api/v1/ai/*                    │
└──────────────────────────────────────────────────────────┘
                    ↓ Database ↓ AI Router
        ┌───────────────────────────────────────┐
        │                                        │
    MariaDB 10.6+                    Claude → GPT → Gemini → Grok
    (InnoDB, UTF8MB4)                (Failover 자동 처리)
    
  - chat_rooms
  - chat_messages
  - customers
  - ai_recommendations
  - chat_read_status
  - ai_logs / ai_failover_log
  - (총 14개 테이블, STEP 2 ERD 참조)
```

### 4.2 기술 스택

| 계층 | 기술 | 버전 | 비고 |
|------|------|------|------|
| Frontend | React + TypeScript | 18.x | SPA, CSR |
| CSS | TailwindCSS | 3.x | [UI Components Guide](02_UIUX/UI_COMPONENTS_GUIDE.md) 팔레트 |
| Real-time Client | Socket.io Client | 4.x | 자동 재연결 |
| Chat Server | Node.js + Socket.io | 20+ | 실시간 전용 |
| Backend | PHP 8.4 또는 Node.js | 8.4 / 20+ | PLUS톡 V2.0 PHP 옵션 |
| Backend Framework | Express / Laravel-style | - | Service Layer |
| Database | MariaDB | 10.6+ | MySQL 8.0+ 호환 |
| Cache | Redis | 7.x | 선택, Rate Limit / AI Cache |
| AI Primary | Claude | 3.5+ | 기본 |
| AI Failover | GPT → Gemini → Grok | - | Rule-001 |
| Auth | JWT | - | Access 24h / Refresh 7d |
| API | REST + WebSocket | - | /api/v1/* |
| Deploy | Docker + Nginx | - | TLS 1.3 |

### 4.3 데이터 흐름 (메시지 전송)

```
[상담원] InputField 입력 → ActionButton 클릭
    ↓
[Client] POST /api/chats/{id}/messages (REST)
    ↓ 동시
[Client] socket.emit('message:send', payload)
    ↓
[Chat Server] room 브로드캐스트 → 고객 Client
    ↓
[Backend] chat_messages INSERT
    ↓
[Backend] AI Router → ai_recommendations INSERT
    ↓
[Client] GET /api/ai/recommendations/{id} → AIPanelCard 갱신
```

### 4.4 배포 아키텍처

```
                    ┌─────────────┐
                    │   Nginx     │
                    │  (TLS 1.3)  │
                    └──────┬──────┘
           ┌───────────────┼───────────────┐
           ↓               ↓               ↓
    ┌────────────┐  ┌────────────┐  ┌────────────┐
    │  Static    │  │  Backend   │  │ Chat Server│
    │  (React)   │  │  PHP/Node  │  │  Node.js   │
    └────────────┘  └─────┬──────┘  └─────┬──────┘
                          ↓               ↓
                    ┌────────────┐  ┌────────────┐
                    │  MariaDB   │  │   Redis    │
                    └────────────┘  └────────────┘
```

### 4.4 계층별 책임

| 계층 | 책임 | 금지 사항 |
|------|------|----------|
| Controller/Router | HTTP 라우팅, 입력 검증 | 비즈니스 로직 직접 작성 |
| Service | 비즈니스 로직, AI 호출 오케스트레이션 | SQL 직접 작성 |
| Repository | DB CRUD, Prepared Statement | AI API 호출 |
| AI Router | Failover, Timeout, Logging | UI 렌더링 |

---

## PART 5. AI Strategy (AI 전략)

### 5.1 AI 역할 10가지

```
1. 상담요약 (Summarization)
   └─ 고객 대화를 간단하게 요약
   └─ 상담원이 빠르게 상황 파악
   └─ 트리거: 상담 종료, N메시지마다

2. 고객분석 (Customer Analysis)
   └─ 고객 배경, 관심사, 구매 의사 분석
   └─ 고객 프로필 자동 작성 (customers 태그)
   └─ UI: CustomerCard

3. 감정분석 (Sentiment Analysis)
   └─ 고객 감정 파악 (긍정/중립/부정)
   └─ 불만 고객 즉시 발견 → Admin 알림
   └─ UI: AIPanelCard 감정 뱃지

4. 계약확률 (Contract Probability)
   └─ 고객의 구매 가능성 0~100점 산출
   └─ 우선순위 자동 결정 (ChatList 정렬)
   └─ UI: AIPanelCard 별점 + 점수

5. 답변추천 (Answer Recommendation)
   └─ 상담원이 답할 내용 3개 제시
   └─ 1클릭으로 InputField에 삽입
   └─ UI: RecommendationCard

6. FAQ 검색 (FAQ Search)
   └─ 질문과 유사한 FAQ 자동 검색 (임베딩)
   └─ 상위 3개 AIPanelCard에 표시

7. 문서검색 (Document Search)
   └─ 가격표, 상품 자료, 계약서 RAG 검색
   └─ RecommendationCard 또는 AIPanelCard 링크

8. 상품추천 (Product Recommendation)
   └─ 고객 문의에 맞는 상품 추천
   └─ Cross-selling 기회 제시

9. 일정생성 (Appointment Generation)
   └─ 상담 후 후속 일정 자동 생성
   └─ 캘린더 API 연동

10. CRM 기록 (CRM Recording)
    └─ 상담 내용 자동으로 CRM에 저장
    └─ 수작업 제거, 필드 매핑 규칙 적용
```

### 5.2 AI 실행 흐름

```
고객 메시지 (chat_messages INSERT)
    ↓
AI Router (활성 AI 선택 — 환경변수 AI_PRIMARY)
    ↓
Claude 호출 (기본, timeout 10s)
    ↓ 실패 (timeout / rate limit / 5xx)
GPT 호출
    ↓ 실패
Gemini 호출
    ↓ 실패
Grok 호출
    ↓
응답 반환 (성공) → ai_recommendations INSERT
    ↓ 실패 (4회 모두)
Fallback 응답 { success: false, code: "AI_ALL_FAILED" }
    ↓
상담원 화면 AIPanelCard / RecommendationCard 갱신
    ↓
상담원 클릭 → InputField → POST messages → 고객 전송
    ↓
CRM 자동 저장 (역할 10)
```

### 5.3 AI 출력 스키마 (표준 JSON)

```json
{
  "success": true,
  "data": {
    "roomId": "uuid",
    "recommendations": [
      { "type": "answer", "text": "설치비는 무료입니다.", "confidence": 0.92 },
      { "type": "answer", "text": "3월까지 프로모션 적용 가능합니다.", "confidence": 0.87 }
    ],
    "contractProbability": 87,
    "sentiment": "positive",
    "intent": "purchase",
    "faq": [
      { "question": "인터넷 설치비?", "answer": "..." }
    ],
    "customerTags": ["신규", "고가", "긍정"],
    "aiModel": "claude-3.5-sonnet",
    "timestamp": "2026-07-21T14:35:00+09:00"
  }
}
```

### 5.4 프롬프트 관리 원칙

- 모든 Prompt는 `prompts` 테이블에 버전 관리 (Rule-002)
- 역할별 Prompt ID: `PROMPT_SUMMARY_v1.0`, `PROMPT_RECOMMEND_v1.0` 등
- A/B 테스트: Admin이 Prompt 버전 전환 가능
- PLUS톡 V2.0 기존 Prompt 마이그레이션 후 ACEP 네이밍 통일

### 5.5 AI 캐싱 정책

| AI 역할 | 캐시 TTL | 키 |
|---------|----------|-----|
| 답변추천 | 1시간 | roomId + lastMessageHash |
| FAQ 검색 | 24시간 | questionEmbedding |
| 계약확률 | 실시간 (캐시 없음) | - |
| 상담요약 | 상담 종료까지 | roomId |

---

## PART 6. Coding Rule (코딩 규칙)

### 6.1 언어 & 프레임워크

| 영역 | 규칙 |
|------|------|
| Backend | PHP 8.4 (PLUS톡 호환) 또는 Node.js 20+ |
| Frontend | React 18 + TypeScript (strict mode) |
| Database | MariaDB 10.6+, UTF8MB4, InnoDB |
| Real-time | Socket.io 4.x |

### 6.2 코딩 표준

| 항목 | 규칙 | 예시 |
|------|------|------|
| 변수/함수 | camelCase | `getChatRooms`, `messageId` |
| 클래스/컴포넌트 | PascalCase | `ChatService`, `MessageBubble` |
| 상수 | UPPER_SNAKE_CASE | `AI_TIMEOUT_MS` |
| DB 테이블/컬럼 | snake_case | `chat_rooms`, `created_at` |
| API 경로 | kebab-case | `/api/chats/rooms` |
| 인코딩 | UTF-8 | BOM 없음 |
| 들여쓰기 | 4 spaces | 탭 금지 |
| 줄 길이 | 120자 이내 권장 | - |

### 6.3 아키텍처 패턴

```
Controller → Service → Repository → MariaDB
                ↓
            AI Router → External AI APIs
```

| 패턴 | 적용 |
|------|------|
| Repository Pattern | 모든 DB 접근 |
| Service Layer | 비즈니스 로직, 트랜잭션 |
| Dependency Injection | Service ↔ Repository |
| MVC / MVVM | Frontend: React 컴포넌트 + hooks |

### 6.4 API 규칙

| 규칙 | 상세 |
|------|------|
| 스타일 | REST (GET/POST/PUT/DELETE) |
| 버전 | `/api/v1/*` (향후 v2 병행) |
| 요청/응답 | JSON, `Content-Type: application/json` |
| 성공 | `{ "success": true, "data": {...}, "timestamp": "..." }` |
| 실패 | `{ "success": false, "error": "...", "code": "..." }` |
| HTTP Status | 200 OK, 201 Created, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 429 Too Many Requests, 500 Internal Server Error |

**핵심 엔드포인트 (상담채팅):**

| Method | Path | 설명 |
|--------|------|------|
| GET | `/api/chats/rooms` | 상담목록 |
| GET | `/api/chats/{id}/messages` | 메시지 목록 |
| POST | `/api/chats/{id}/messages` | 메시지 전송 |
| GET | `/api/ai/recommendations/{id}` | AI 추천 |
| PUT | `/api/chats/{id}/read` | 읽음 표시 |

### 6.5 WebSocket 규칙

| 이벤트 | 방향 | Payload |
|--------|------|---------|
| `room:join` | C→S | `{ roomId, userId, role }` |
| `message:receive` | S→C | `{ messageId, content, sender, timestamp }` |
| `typing:start` | C→S→C | `{ roomId, userId }` |
| `typing:stop` | C→S→C | `{ roomId, userId }` |
| `read:update` | S→C | `{ roomId, messageId, readAt }` |

- 재연결: exponential backoff (1s, 2s, 4s, max 30s)
- 인증: JWT를 handshake query 또는 auth header로 전달

### 6.6 데이터베이스 규칙

- UTF8MB4 + `utf8mb4_unicode_ci`
- InnoDB only
- FK 제약: `chat_messages.room_id → chat_rooms.id`, `chat_messages.sender_id → customers/agents`
- 인덱스: `chat_messages(room_id, created_at)`, `chat_rooms(status, updated_at)`
- Soft delete: `deleted_at` 컬럼 (물리 삭제 금지, GDPR 요청 시 anonymize)

### 6.7 Git & PR 규칙

| 항목 | 규칙 |
|------|------|
| Branch | `feature/ACEP-{ticket}-{desc}` |
| Commit | `[ACEP] feat: ...` / `fix:` / `docs:` |
| PR | 1 PR = 1 기능, 리뷰 1명 이상 |
| 문서 | API/DB 변경 시 STEP 2 문서 동시 갱신 |

---

## PART 7. AI Rule (AI 규칙)

### Rule-001: AI Router Failover

```
┌──────────────────────────────────────────┐
│ 활성 AI가 실패하면 자동으로 다음 AI로  │
├──────────────────────────────────────────┤
│ 1단계: Claude (기본)                     │
│ 2단계: OpenAI GPT (Claude 실패 시)      │
│ 3단계: Google Gemini (GPT 실패 시)      │
│ 4단계: xAI Grok (Gemini 실패 시)        │
│ 5단계: 실패 응답 (모두 실패 시)         │
│                                          │
│ 타이아웃: 10초 (모델당)                 │
│ 재시도: 최대 4회 (모델 수)              │
│ 로그: ai_failover_log에 기록            │
└──────────────────────────────────────────┘
```

**Failover 트리거 조건:**
- HTTP timeout (10s)
- HTTP 429 (Rate Limit)
- HTTP 5xx
- 응답 JSON 파싱 실패
- `success: false` from AI provider

**ai_failover_log 스키마 (요약):**

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | BIGINT | PK |
| room_id | VARCHAR(36) | 상담방 |
| primary_model | VARCHAR(50) | claude-3.5-sonnet |
| failover_model | VARCHAR(50) | gpt-4o |
| reason | VARCHAR(255) | timeout / 429 / 500 |
| latency_ms | INT | 응답 시간 |
| created_at | DATETIME | - |

### Rule-002: Prompt Versioning

- 모든 Prompt는 버전 관리 (`v1.0`, `v1.1`, `v1.2`, ...)
- 변경 이력: 날짜, 변경자, 변경 내용, diff
- `prompts` 테이블 저장: `id`, `role`, `version`, `content`, `is_active`
- Rollback: `is_active` 플래그 전환 (즉시 반영)

### Rule-003: API Key Management

- 환경변수: `.env` (gitignore 필수)
- 변수명: `AI_CLAUDE_API_KEY`, `AI_OPENAI_API_KEY`, `AI_GEMINI_API_KEY`, `AI_GROK_API_KEY`
- 코드 하드코딩 **절대 금지**
- 로테이션: 분기 1회, Operator 책임
- 키 유출 시: 즉시 폐기 + ai_failover_log + audit_log 기록

### Rule-004: Response Handling

```json
// 성공
{ "success": true, "data": { ... }, "timestamp": "2026-07-21T14:35:00+09:00" }

// 실패
{ "success": false, "error": "AI analysis unavailable", "code": "AI_ALL_FAILED", "timestamp": "..." }
```

- 모든 AI 응답은 Backend에서 표준 스키마로 정규화 후 Client 전달
- Client는 `success: false` 시 UI Exception 규칙 적용 ([상담채팅화면 §9](02_UIUX/01_상담채팅화면.fig.md))

### Rule-005: Rate Limiting

| 대상 | 제한 | 저장 |
|------|------|------|
| IP | 100 req/min | Redis |
| 사용자 (JWT sub) | 50 req/min | Redis |
| AI 호출 (room) | 10 req/min | Redis |

- 초과 시 HTTP 429 + `Retry-After` header
- Admin 대시보드에서 임계값 조정 가능 (Enterprise)

---

## PART 8. Quality (품질)

### 8.1 성능 SLA

| 지표 | 목표 | 측정 방법 |
|------|------|----------|
| 메시지 전송 (WebSocket) | 0.1초 이내 | Socket.io latency |
| AI 추천 표시 | 2초 이내 | API `GET /api/ai/recommendations/{id}` |
| REST API 응답 | 1초 이내 | p95 latency |
| 페이지 초기 로드 | 3초 이내 | Lighthouse |

### 8.2 오류율

| 항목 | 목표 |
|------|------|
| 상담 중 연결 끊김 | 0.1% 이하 |
| AI 실패 (Failover 후) | 0.01% 이하 |
| DB 에러 | 0% (자동 재시도 3회) |

### 8.3 가용성

| 항목 | 목표 |
|------|------|
| 시스템 가동률 | 99.5% 이상 |
| 계획 점검 | 월 1회, 1시간 이내 |
| RTO (복구 목표) | 4시간 |
| RPO (데이터 손실) | 24시간 (일일 백업) |

### 8.4 테스트

| 유형 | 커버리지/범위 |
|------|-------------|
| 단위 테스트 | 80% 이상 (Service, Repository) |
| 통합 테스트 | 모든 REST API + WebSocket 이벤트 |
| E2E 테스트 | 접수→채팅→AI추천→종료→CRM |
| UI 테스트 | [상담채팅화면 Test Cases](02_UIUX/01_상담채팅화면.fig.md) TC-001~ |

### 8.5 성능 목표

| 항목 | 목표 |
|------|------|
| 동시 WebSocket 연결 | 1,000+ |
| 메시지 처리량 | 10,000건/분 |
| MariaDB QPS | 5,000 (read-heavy 최적화) |

---

## PART 9. Security (보안)

### 9.1 인증 (Authentication)

| 항목 | 규칙 |
|------|------|
| Access Token | JWT, 유효기간 24시간 |
| Refresh Token | 유효기간 7일, HttpOnly Cookie |
| 로그인 실패 | 3회 연속 실패 → 계정 30분 잠금 |
| 비밀번호 | bcrypt, cost 12 |

### 9.2 권한 (Authorization — RBAC)

| 역할 | chat_rooms | chat_messages | ai_recommendations | Admin |
|------|-----------|---------------|-------------------|-------|
| Customer | 본인 room | 본인 room | - | - |
| Agent | 할당 room | 할당 room | 할당 room | - |
| Admin | 전체 | 전체 | 전체 | ✓ |
| Operator | read-only | read-only | read-only | 시스템 |

### 9.3 개인정보 보호

| 데이터 | 저장 | 표시 |
|--------|------|------|
| 전화번호 | AES-256 암호화 | `010-****-5678` |
| 이메일 | AES-256 | `user@****.com` |
| 주소 | AES-256 | `서울시 강남구 ****` |
| 채팅 내용 | 평문 (DB ACL) | 역할별 마스킹 |

### 9.4 데이터 통신

- HTTPS 필수 (TLS 1.3+)
- HSTS enabled
- CORS: 허용 origin whitelist
- WebSocket: wss:// only

### 9.5 OWASP 대응

| 위협 | 대응 |
|------|------|
| SQL Injection | Prepared Statement only |
| XSS | 출력 이스케이프, CSP header |
| CSRF | SameSite Cookie + CSRF token (state-changing) |
| IDOR | room 소유권 검증 (JWT + room_id) |

### 9.6 감사 로그 (Audit Log)

| 이벤트 | 보관 |
|--------|------|
| 로그인/로그아웃 | 90일 |
| 상담 내용 접근 | 90일 |
| AI API 호출 | 90일 |
| CRM 수정 | 1년 |
| Admin 설정 변경 | 1년 |

### 9.7 백업

- 일일 자동 백업 (MariaDB dump → S3/별도 스토리지)
- 주간 offsite 백업
- 복구 테스트: 월 1회 (Operator)

---

## PART 10. Roadmap (로드맵)

### 10.1 버전별 로드맵

```
V1.0 (MVP) — 2026년 8월 말
├── ✅ 기본 채팅 (WebSocket, chat_messages)
├── ✅ 3패널 UI (ChatList + MessageBubble + AIPanelCard)
├── ✅ 기본 AI 추천 (답변 3개, RecommendationCard)
├── ✅ 기본 CRM 연동 (customers 자동 생성)
├── ✅ 상담원 대시보드 (ChatList, StatusBadge)
└── 문서: 본 MASTER + [UI/UX](02_UIUX/01_상담채팅화면.fig.md)

V1.5 — 2026년 9월 말
├── ✅ AI Failover (Claude→GPT→Gemini→Grok, Rule-001)
├── ✅ ai_failover_log
├── ✅ 상세 감정분석 (AIPanelCard)
├── ✅ 고객분석 (CustomerCard 태그)
└── ✅ FAQ 검색 (임베딩)

V2.0 — 2026년 10월 말
├── ✅ 계약확률 정교화 (0~100, 실시간)
├── ✅ 상품추천 AI
├── ✅ 일정 자동생성
├── ✅ 고급 통계 Dashboard
└── PLUS톡 V2.0 기능 parity 달성

V3.0 — 2026년 11월 말
├── ✅ 음성 메시지
├── ✅ 비디오 상담 (선택)
├── ✅ 화면 공유
└── ✅ AI Agent 자동상담 (1차 챗봇)

Enterprise — 2026년 12월 말
├── ✅ 멀티테넌트 SaaS
├── ✅ 고급 RBAC + 커스텀 필드
├── ✅ Public API + Webhook
├── ✅ SSO (SAML/OIDC)
└── ✅ SLA 99.9% + 전담 Support
```

### 10.2 STEP별 문서 로드맵

| STEP | 산출물 | 상태 |
|------|--------|------|
| STEP 1 | 00_PROJECT_MASTER, UI/UX, Components | ✅ 본 문서 |
| STEP 2 | [DB설계](03_SYSTEM/01_DB설계.md), [API설계](03_SYSTEM/02_API설계.md), [시스템아키텍처](03_SYSTEM/03_시스템아키텍처.md) | ✅ 완료 |
| STEP 3 | AI Prompt, Failover 구현 | 예정 |
| STEP 4 | Chat Server, Backend | 예정 |
| STEP 5 | Frontend React | 예정 |
| STEP 6 | Admin, Dashboard | 예정 |
| STEP 7 | 테스트, QA | 예정 |
| STEP 8 | 릴리스, 배포 | 예정 |

### 10.3 V1.0 MVP 범위 (In/Out)

| In Scope | Out of Scope (V2+) |
|----------|-------------------|
| PC 3패널 + 모바일 탭 | 음성/화상 |
| 5 REST API + WebSocket | Public API |
| AI 답변추천 + 계약확률 | AI 자동상담 |
| chat_rooms 등 5테이블 | 14테이블 전체 |
| Claude 단일 (+ Failover V1.5) | 멀티테넌트 |

### 10.4 마일스톤

| 날짜 | 마일스톤 | Deliverable |
|------|----------|-------------|
| 2026-07-21 | STEP 1 완료 | MASTER + UI/UX 3문서 |
| 2026-08-07 | STEP 2 완료 | ERD + API 명세 |
| 2026-08-21 | Alpha | 내부 상담원 테스트 |
| 2026-08-31 | V1.0 Release | MVP 프로덕션 |

---

## 부록 A. 용어 사전

| 용어 | 정의 |
|------|------|
| ACEP | AI Customer Engagement Platform |
| PlusTok Enterprise | ACEP 상품명 |
| Side-by-Side AI | 채팅 옆 AI Assistant 패널 UX |
| Failover | AI Provider 장애 시 다음 모델 자동 전환 |
| room | chat_rooms 1레코드 = 1 상담방 |

## 부록 B. 관련 문서

- [상담채팅화면 UI/UX 설계](02_UIUX/01_상담채팅화면.fig.md)
- [UI Components Guide](02_UIUX/UI_COMPONENTS_GUIDE.md)
- STEP 2: [01_DB설계.md](03_SYSTEM/01_DB설계.md), [02_API설계.md](03_SYSTEM/02_API설계.md), [03_시스템아키텍처.md](03_SYSTEM/03_시스템아키텍처.md)

---

**문서 끝 — 본 MASTER DOCUMENT는 ACEP 프로젝트의 최상위 기준 문서이다.**
