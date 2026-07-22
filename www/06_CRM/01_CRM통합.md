# 01 — CRM 통합 (상담 → CRM 자동 저장)

> **PlusTok ACEP** · STEP 5 · CRM Zero-Input Automation  
> **버전**: 1.0.0 · **작성일**: 2026-07-21  
> **SSOT**: 본 문서(`06_CRM/01_CRM통합.md`)  
> **상위**: [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)  
> **연관**: [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md), [04_AI/02_Prompt설계.md](../04_AI/02_Prompt설계.md), [05_CHAT/02_Backend_Chat_API_구현명세.md](../05_CHAT/02_Backend_Chat_API_구현명세.md)

---

## 목차

1. [목적 (Purpose)](#1-목적-purpose)
2. [범위 (Scope)](#2-범위-scope)
3. [요구사항 (Requirements)](#3-요구사항-requirements)
4. [CRM 저장 흐름](#4-crm-저장-흐름)
5. [DB 설계 참조 및 필드 매핑](#5-db-설계-참조-및-필드-매핑)
6. [API 명세](#6-api-명세)
7. [Business Rule](#7-business-rule)
8. [AI Rule](#8-ai-rule)
9. [Exception 처리](#9-exception-처리)
10. [Test Case](#10-test-case)
11. [Future (로드맵)](#11-future-로드맵)
12. [부록 — 레거시 연동·구현 가이드](#12-부록--레거시-연동구현-가이드)

---

## 1. 목적 (Purpose)

### 1.1 핵심 목표

PlusTok ACEP는 실시간 채팅(`chat_rooms`)과 레거시 CRM(`consults`)이 병행 운영된다.  
본 문서는 **상담 종료 시 CRM에 데이터를 자동 저장**하는 End-to-End 워크플로우를 정의하여 다음을 달성한다.

| 목표 | 설명 | KPI |
|------|------|-----|
| **CRM Zero-Input** | 상담원이 별도 CRM 폼을 작성하지 않음 | 수동 입력 0건/종료 |
| **데이터 일관성** | ACEP AI 분석 결과가 CRM 단일 레코드에 반영 | 필드 누락률 < 1% |
| **후속 업무 자동화** | 계약확률·감정 기반 일정·메모 자동 생성 | 후속 일정 생성률 100% (조건 충족 시) |
| **감사 추적** | 모든 AI 호출·CRM 쓰기는 `ai_logs`·`activity_log`에 기록 | 100% 로깅 |

### 1.2 비즈니스 배경

LG U+ PlusTok 멀티 사이트(lg15441644, smarttoktok 등)는 기존 PHP CRM(`admin/consults/`)으로 상담·계약을 관리해 왔다.  
ACEP V3.0은 React Agent/Customer 채팅 + AI Router를 도입하면서, **채팅 종료 = CRM 상담 레코드 확정**이 되어야 영업·설치 후속 프로세스가 끊기지 않는다.

### 1.3 대상 독자

| 독자 | 활용 |
|------|------|
| Backend 개발자 | `ConsultCloseService`, API, 트랜잭션 구현 |
| Frontend (Agent) | 종료 UI, 저장 상태 표시, 재시도 UX |
| 운영/PM | 자동 저장 규칙, 후속 일정 정책 |
| QA | §10 테스트 케이스 기준 E2E |

---

## 2. 범위 (Scope)

### 2.1 In Scope (STEP 5)

| 항목 | 설명 |
|------|------|
| 상담 종료 트리거 | Agent UI "상담종료" + `PUT /chats/{id}/close` 연쇄 |
| AI 파이프라인 | 요약(`summarize`) + 종합분석(`analyze`) 순차/병렬 |
| CRM 쓰기 | `consults` INSERT/UPDATE, `customers` UPDATE |
| ACEP 동기화 | `chat_rooms` status=closed, `priority_score` 캐시 |
| 후속 일정 | `schedules` 자동 INSERT (신규 테이블) |
| 이메일 초안 | `consult_memo_drafts` 또는 consults.memo append (수동 발송) |
| 실패 재시도 | 지수 백오프 3회, Dead Letter Queue (Redis) |
| 레거시 Bridge | `admin/consults/ai_*.php` 로직을 서비스 계층으로 흡수 |

### 2.2 Out of Scope (STEP 5)

| 항목 | 담당 STEP |
|------|-----------|
| Admin Dashboard KPI | STEP 6 `07_ADMIN/01_관리자대시보드.md` |
| 이메일/SMS 자동 발송 | V4.0 |
| 외부 CRM(Salesforce) Webhook | V4.5 |
| 고객 Self-service CRM 열람 | 미정 |

### 2.3 시스템 경계

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        CRM Integration Boundary                          │
├─────────────────────────────────────────────────────────────────────────┤
│  Agent React App                                                         │
│    └── closeRoom() ──► PUT /api/v1/chats/{room_id}/close                │
│                              │                                           │
│                              ▼                                           │
│  ChatRoomService.closeRoom()                                             │
│    ├── validate (duration, messages, assignment)                         │
│    ├── dispatch CrmCloseJob (sync ≤5s SLA)                               │
│    └── return { room_id, status: closed }                                │
│                              │                                           │
│                              ▼                                           │
│  CrmCloseService.execute()                                               │
│    ├── AiPipeline: summarize + analyze                                   │
│    ├── CustomerMergeService (dedup)                                      │
│    ├── ConsultRepository.upsertFromRoom()                                │
│    ├── ScheduleService.createFollowUps()                                 │
│    └── NotificationService.agentToast()                                  │
│                              │                                           │
│                              ▼                                           │
│  MySQL: consults, customers, schedules, chat_rooms, ai_logs              │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. 요구사항 (Requirements)

### 3.1 기능 요구사항

| ID | 요구사항 | 기준 | 우선순위 |
|----|----------|------|----------|
| REQ-CRM-001 | 종료 후 CRM 저장 완료 | **≤ 5초** (P95) | P0 |
| REQ-CRM-002 | AI 요약 최소 길이 | **≥ 250자** (한글 기준) | P0 |
| REQ-CRM-003 | 분류·감정·lead_score 저장 | consults + ai_logs 양쪽 | P0 |
| REQ-CRM-004 | 고객 PII CRM 내부 저장 | **마스킹 해제** (DB AES 복호화 후 consults) | P0 |
| REQ-CRM-005 | 실패 재시도 | 최대 3회, 백오프 1s→2s→4s | P0 |
| REQ-CRM-006 | chat_rooms ↔ consults 링크 | `chat_rooms.external_consult_id` 또는 consults.detail_json.room_id | P0 |
| REQ-CRM-007 | 후속 일정 자동 생성 | lead_score 구간별 §7.3 | P1 |
| REQ-CRM-008 | 상담원 optional 피드백 | agent_rating 1-5, memo | P2 |

### 3.2 비기능 요구사항

| ID | 항목 | 값 |
|----|------|-----|
| NFR-CRM-001 | 트랜잭션 | consults + customers + schedules 단일 TX |
| NFR-CRM-002 | Idempotency | 동일 room_id close 2회 → 409 또는 no-op |
| NFR-CRM-003 | Timezone | Asia/Seoul (config/app.php) |
| NFR-CRM-004 | Audit | `log_activity('crm_auto_save', ...)` |
| NFR-CRM-005 | PII | 외부 AI 전송 시 `ai_mask_pii()`, CRM 저장 시 복호화 |

### 3.3 상담 종료 선행 조건

| 조건 | Rule ID | 검증 |
|------|---------|------|
| 최소 대화 시간 5분 | BR-CRM-001 | first_msg ~ close ≥ 300s |
| 최소 메시지 3건 | BR-CRM-002 | COUNT(messages) ≥ 3 |
| 배정된 agent 존재 | BR-CRM-003 | chat_room_assignments active OR agent_id |
| room status = active | BR-CRM-004 | else 422 |

---

## 4. CRM 저장 흐름

### 4.1 시퀀스 다이어그램

```
Agent          Chat API        CrmCloseSvc      AI Router       MySQL
  │                │                │               │              │
  │─[상담종료]────►│                │               │              │
  │                │─closeRoom()──►│               │              │
  │                │                │─validate─────┼─────────────►│
  │                │                │◄─room+msgs───┼──────────────│
  │                │                │               │              │
  │                │                │─summarize───►│              │
  │                │                │◄─summary─────│──ai_logs────►│
  │                │                │─analyze─────►│              │
  │                │                │◄─analysis────│──ai_logs────►│
  │                │                │               │              │
  │                │                │──── BEGIN TX ─────────────►│
  │                │                │  upsert consults             │
  │                │                │  update customers            │
  │                │                │  insert schedules            │
  │                │                │  update chat_rooms           │
  │                │                │──── COMMIT ─────────────────►│
  │                │                │               │              │
  │◄─toast─────────│◄─200 + payload─│               │              │
  │ "저장됨 [CRM]"  │                │               │              │
```

### 4.2 단계별 상세

#### Step 0: 트리거

| 트리거 | 경로 | 비고 |
|--------|------|------|
| Agent UI 버튼 | `PUT /api/v1/chats/{id}/close` | Primary |
| Admin 강제 종료 | `POST /api/v1/admin/consults/close` | super/admin |
| 자동 종료 (무응답) | Cron + `BR-CRM-010` | 30분 idle |

#### Step 1: AI 요약 생성

- **Feature**: `summarize` ([04_AI/02_Prompt설계.md](../04_AI/02_Prompt설계.md) Prompt ID `summary_v1`)
- **입력**: room 전체 messages (customer + agent), customer profile, inquiry_type
- **출력 JSON**:

```json
{
  "summary": "고객 김OO은 LG U+ 기업인터넷 100M 견적을 문의...",
  "keywords": ["기업인터넷", "100M", "설치희망", "강남"],
  "customer_updates": {
    "company": "(주)OO상사",
    "address_hint": "서울 강남구"
  }
}
```

- **저장**: `consults.ai_summary`, `consults.ai_summary_at`

#### Step 2: AI 종합 분석

- **Feature**: `analyze` — 레거시 [`admin/consults/ai_analyze.php`](../admin/consults/ai_analyze.php) 동일 스키마
- **출력**:

```json
{
  "category_ai": "인터넷",
  "confidence": 92,
  "priority": "HIGH",
  "lead_score": 78,
  "sentiment": "POSITIVE",
  "tags": ["#기업인터넷", "#LG유플러스", "#강남"]
}
```

- **저장**: consults 컬럼 + `chat_rooms.priority_score` = lead_score

#### Step 3: CRM 업데이트

- `consults` UPSERT (§5.3 필드 매핑)
- `customers` tags, consultation_count++, PII 필드 merge
- `consult_history` INSERT (to_status = progress 또는 consulting)

#### Step 4: 후속 업무

- `schedules` INSERT (§7.3)
- 이메일 초안: consults.memo 또는 별도 draft 테이블

#### Step 5: UI 확인

```typescript
// Agent App — close success handler
if (res.crm_saved_at) {
  toast.success(`상담이 CRM에 저장되었습니다. (${res.consult_no})`, {
    action: { label: 'CRM 보기', href: `/admin/consults/view.php?id=${res.consult_id}` }
  });
}
```

### 4.3 실패 시 UX

| 상태 | Agent UI | Admin |
|------|----------|-------|
| CRM 저장 중 | Spinner "CRM 저장 중..." | — |
| 재시도 중 | "재시도 2/3..." | — |
| 최종 실패 | Banner + [수동 저장] 버튼 | 알림 bell |
| 부분 성공 | room closed + CRM pending badge | DLQ viewer |

---

## 5. DB 설계 참조 및 필드 매핑

### 5.1 관련 테이블

| 테이블 | 역할 | SSOT |
|--------|------|------|
| `chat_rooms` | ACEP 상담 세션 | [03_SYSTEM/01_DB설계.md §5.2](../03_SYSTEM/01_DB설계.md) |
| `chat_messages` | 대화 원문 | §5.3 |
| `customers` | ACEP 고객 (UUID) | §5.1 |
| `consults` | 레거시 CRM 상담 (BIGINT) | `admin/install.php` |
| `customers` (legacy) | 레거시 고객 (BIGINT) | install.php — **별도 스키마** |
| `schedules` | 후속 일정 (STEP 5 신규) | 본 문서 §5.4 |
| `ai_logs` | AI 호출 감사 | §5.11 |
| `managers` | 레거시 상담원 | install.php |

> **이중 customers 주의**: ACEP `customers`(UUID)와 레거시 `customers`(BIGINT)는 마이그레이션 기간 병행.  
> CRM 저장 시 `customers.external_crm_id` ↔ legacy `customers.id` 매핑 필수.

### 5.2 chat_rooms → consults 필드 매핑

| chat_rooms / AI | consults 컬럼 | 변환 규칙 |
|-----------------|---------------|-----------|
| `id` (UUID) | `detail_json.room_id` | JSON 저장 |
| `customer_id` | `customer_id` | legacy customer FK via bridge table |
| `inquiry_type` | `category` / `category_ai` | inquiry_type → category_ai 우선 |
| `agent_id` | `manager_id` | agents ↔ managers 매핑 테이블 |
| `channel` | `device` | web→mobile, kakao→kakao |
| `subject` | `product_name` | fallback |
| `closed_at` | `updated_at` | sync |
| AI summary | `ai_summary` | TEXT |
| AI analyze | `category_ai`, `priority`, `lead_score`, `sentiment`, `tags` | JSON tags → VARCHAR |
| `site_id` | `site_id` | embed widget site_code → sites.id |

### 5.3 ACEP customers → Legacy customers

| ACEP customers | Legacy customers | 비고 |
|----------------|------------------|------|
| name | name | |
| phone (decrypted) | phone | UNIQUE uq_customers_phone |
| email | — | legacy optional |
| tags JSON | — | consults.tags VARCHAR |
| external_crm_id | id | 역방향 링크 |

**Bridge SQL (개념)**:

```sql
-- customer_bridge: ACEP UUID ↔ legacy BIGINT
CREATE TABLE customer_bridge (
  acep_customer_id  VARCHAR(36) NOT NULL,
  legacy_customer_id BIGINT NOT NULL,
  PRIMARY KEY (acep_customer_id),
  UNIQUE KEY uq_legacy (legacy_customer_id)
);
```

### 5.4 schedules 테이블 (신규 — STEP 5)

```sql
CREATE TABLE schedules (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id      BIGINT NOT NULL,
  schedule_type   ENUM('call','email','follow_up','info_collect','satisfaction') NOT NULL,
  scheduled_at    DATETIME NOT NULL,
  assigned_to     BIGINT NULL COMMENT 'managers.id',
  status          ENUM('pending','done','canceled') NOT NULL DEFAULT 'pending',
  title           VARCHAR(200) NOT NULL,
  memo            TEXT NULL,
  created_by      ENUM('system','agent','admin') NOT NULL DEFAULT 'system',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME NULL,
  CONSTRAINT fk_schedules_consult FOREIGN KEY (consult_id) REFERENCES consults(id),
  INDEX idx_schedules_due (status, scheduled_at),
  INDEX idx_schedules_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.5 chat_rooms 확장 컬럼 (권장)

```sql
ALTER TABLE chat_rooms
  ADD COLUMN legacy_consult_id BIGINT NULL COMMENT 'consults.id after CRM save',
  ADD COLUMN crm_save_status ENUM('pending','saved','failed') DEFAULT 'pending',
  ADD COLUMN crm_saved_at DATETIME(3) NULL,
  ADD INDEX idx_chat_rooms_crm (crm_save_status);
```

### 5.6 ai_logs CRM 연관

| ai_logs.feature | CRM 필드 |
|-----------------|----------|
| summarize | consults.ai_summary |
| analyze | category_ai, lead_score, sentiment, priority |
| (all) | consults.ai_analyzed_at |

---

## 6. API 명세

### 6.1 POST /api/v1/consults/close

> **Primary CRM Close Endpoint** — Chat close 후 내부 호출 또는 직접 호출

| Item | Value |
|------|-------|
| Auth | Bearer JWT, roles: `agent`, `admin`, `super` |
| Content-Type | application/json |
| Idempotency-Key | `room_id` (권장 header) |

**Request Body**:

```json
{
  "room_id": "550e8400-e29b-41d4-a716-446655440000",
  "agent_id": "agent-uuid-001",
  "summary_override": null,
  "feedback": {
    "rating": 4,
    "memo": "고객 설치 일정 조율 필요"
  },
  "force": false
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| room_id | UUID | ✅ | chat_rooms.id |
| agent_id | UUID | ✅ | 종료 수행 agent |
| summary_override | string | ❌ | AI 요약 수동 교체 (admin) |
| feedback.rating | int 1-5 | ❌ | 상담원 자체 평가 |
| feedback.memo | string | ❌ | consults.memo append |
| force | bool | ❌ | 5분 미만 강제 (admin only) |

**Response 200**:

```json
{
  "ok": true,
  "consult_id": 10482,
  "consult_no": "20260721-0042",
  "crm_saved_at": "2026-07-21T14:35:02+09:00",
  "schedule_ids": [901, 902],
  "ai": {
    "summary_length": 312,
    "lead_score": 78,
    "category_ai": "인터넷",
    "sentiment": "POSITIVE"
  },
  "room_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Error Responses**:

| HTTP | Code | Meaning |
|------|------|---------|
| 400 | CRM_ROOM_NOT_ACTIVE | status != active |
| 422 | CRM_MIN_DURATION | < 5 min |
| 422 | CRM_MIN_MESSAGES | < 3 messages |
| 409 | CRM_ALREADY_SAVED | idempotent duplicate |
| 503 | CRM_AI_UNAVAILABLE | AI failed after retries |
| 500 | CRM_SAVE_FAILED | DB error after retries |

### 6.2 PUT /api/v1/chats/{id}/close (연쇄)

기존 Chat API ([05_CHAT/02_Backend_Chat_API_구현명세.md](../05_CHAT/02_Backend_Chat_API_구현명세.md)) 확장:

```php
// ChatRoomService::closeRoom()
public function closeRoom(string $roomId, string $agentId, ?array $feedback): array
{
    $room = $this->rooms->findOrFail($roomId);
    $this->validateClose($room);
    $this->rooms->updateStatus($roomId, 'closed', now());

    try {
        $crm = $this->crmClose->execute($roomId, $agentId, $feedback);
        $this->rooms->markCrmSaved($roomId, $crm['consult_id']);
    } catch (CrmCloseException $e) {
        $this->queue->push('crm:retry', ['room_id' => $roomId, 'attempt' => 1]);
        $this->rooms->markCrmFailed($roomId);
    }

    return ['room_id' => $roomId, 'status' => 'closed', 'crm' => $crm ?? null];
}
```

### 6.3 GET /api/v1/consults/{consult_no}

CRM 저장 결과 조회 (Agent/Admin).

### 6.4 POST /api/v1/consults/close/retry (Internal)

Dead Letter Queue worker — admin cron or Redis consumer.

---

## 7. Business Rule

### 7.1 상담 종료 조건

| Rule ID | 규칙 | 예외 |
|---------|------|------|
| BR-CRM-001 | 대화 시간 ≥ 5분 | admin `force=true` |
| BR-CRM-002 | messages ≥ 3 | 스팸 suspected → hold |
| BR-CRM-003 | agent 배정 필수 | auto-assign 실패 시 422 |
| BR-CRM-004 | room status = active | waiting → active 전환 후 |
| BR-CRM-005 | 고객 동의 UX | Agent UI confirm modal (권장) |

### 7.2 자동 저장 항목

| 항목 | 필수 | 검증 |
|------|:----:|------|
| ai_summary | ✅ | len ≥ 250 |
| category_ai | ✅ | enum |
| lead_score | ✅ | 0-100 |
| sentiment | ✅ | POSITIVE/NEUTRAL/NEGATIVE |
| priority | ✅ | LOW/NORMAL/HIGH/URGENT |
| tags | △ | max 6 |
| customer phone/name | ✅ | legacy customers |
| consult_no | ✅ | 당일 시퀀스 ([functions.php](../includes/functions.php) `next_consult_no()`) |

### 7.3 후속 일정 자동 생성

| lead_score | schedule_type | scheduled_at | title �emplate |
|------------|---------------|--------------|-----------------|
| ≥ 70 | call | +3 days 10:00 | `[자동] 계약 follow-up 콜 — {consult_no}` |
| 50-69 | email | +7 days 09:00 | `[자동] 견적 follow-up 이메일 검토` |
| < 50 | follow_up | +30 days | `[자동] 재접촉 — {category_ai}` |
| sentiment=NEGATIVE | satisfaction | +1 day | `[자동] 만족도 확인` |
| customer incomplete | info_collect | +2 days | `[자동] 고객 정보 보완` |

**중복 방지**: 동일 consult_id + schedule_type + DATE(scheduled_at) UNIQUE.

### 7.4 이메일 자동 작성 (초안)

- **발신자**: `MAIL_FROM` (config/app.php)
- **Reply-To**: assigned manager email
- **템플릿 키**: `crm_followup_{category_ai}` (settings KV)
- **발송**: **수동** — Agent/Admin "발송 검토" UI (V4.0 auto)

### 7.5 실패 처리·재시도

```
Attempt 1: immediate
Attempt 2: +1s (AI transient)
Attempt 3: +2s
Attempt 4: +4s (max 3 retries = 4 total tries)

Still failing:
  → chat_rooms.crm_save_status = 'failed'
  → Redis DLQ key: crm:dlq:{room_id}
  → WebSocket admin:notify (V1.5)
  → Agent toast: "CRM 저장 실패 — 수동 저장 필요"
```

### 7.6 Idempotency

- 동일 `room_id` + `crm_save_status=saved` → 409 `CRM_ALREADY_SAVED`
- Body 동일 재전송 → 200 with existing consult_no (safe retry)

---

## 8. AI Rule

### 8.1 필수 AI 호출

| Order | Feature | Prompt Key | Fallback |
|-------|---------|------------|----------|
| 1 | summarize | `summary_v1` | template concat (no AI) |
| 2 | analyze | `analyze_v1` | rule-based category from inquiry_type |

### 8.2 프롬프트 버전

- Default: **v1.0** (`ai_prompts` active row)
- Rule-002: activate 시 이전 버전 deactivate ([04_AI/02_Prompt설계.md](../04_AI/02_Prompt설계.md))

### 8.3 PII 정책

| 단계 | 처리 |
|------|------|
| AI 요청 | `ai_mask_pii()` on messages |
| CRM 저장 | decrypt from ACEP customers → legacy plaintext |
| consults.memo | no mask (internal CRM) |

### 8.4 AI 실패

| 실패 유형 | 처리 |
|-----------|------|
| Primary+Failover 모두 실패 | retry CRM job; summary=manual placeholder |
| JSON parse fail | retry analyze 1x; else defaults |
| Rate limit 429 | queue delay 30s |

### 8.5 ai.php 연동

- `ai_call()` feature=`summarize|analyze`
- `ai_check_rate_limit('analyze', consult_id)` — 30s debounce (레거시 ai_analyze.php 동일)
- Log: `ai_logs.room_id`, `ai_logs.feature`

---

## 9. Exception 처리

### 9.1 API·네트워크

| Exception | Action |
|-----------|--------|
| OpenAI/Anthropic timeout | Failover chain → retry |
| DB deadlock | rollback + retry TX |
| consult_no collision | regenerate + retry |

### 9.2 데이터 검증

| Exception | Action |
|-----------|--------|
| summary < 250 chars | re-prompt AI with "extend" instruction |
| missing customer phone | block CRM; Agent modal "연락처 입력" |
| invalid category_ai | default "기타" |

### 9.3 고객 중복

```
phone_hash match found:
  1. If customer_bridge exists → use legacy_customer_id
  2. Else → show Agent merge confirm (name diff)
  3. Admin API POST /customers/merge
```

### 9.4 레거시 스키마 불일치

- `consults` column missing (migration 전) — fallback INSERT minimal columns ([consults/index.php](../admin/consults/index.php) 패턴)

### 9.5 Partial Failure Matrix

| chat_rooms | consults | schedules | UX |
|------------|----------|-----------|-----|
| closed | saved | saved | ✅ success |
| closed | saved | failed | ⚠ partial — retry schedules |
| closed | failed | — | ❌ DLQ |
| active | — | — | ❌ rollback close |

---

## 10. Test Case

### 10.1 E2E — 정상 종료

| TC ID | Steps | Expected |
|-------|-------|----------|
| TC-CRM-001 | 5min chat → Agent close | 200, consult_no, crm_saved_at ≤5s |
| TC-CRM-002 | Verify consults row | ai_summary≥250, lead_score set |
| TC-CRM-003 | Verify customers | consultation_count++ |
| TC-CRM-004 | lead_score=75 | schedule call +3d exists |
| TC-CRM-005 | chat_rooms | status=closed, crm_save_status=saved |

### 10.2 재시도·실패

| TC ID | Steps | Expected |
|-------|-------|----------|
| TC-CRM-010 | Mock AI 503 ×2 then OK | success on 3rd attempt |
| TC-CRM-011 | Mock DB fail all | crm_save_status=failed, DLQ |
| TC-CRM-012 | Duplicate close | 409 CRM_ALREADY_SAVED |
| TC-CRM-013 | <5min close | 422 CRM_MIN_DURATION |

### 10.3 데이터·매핑

| TC ID | Steps | Expected |
|-------|-------|----------|
| TC-CRM-020 | ACEP customer new phone | legacy customer created + bridge |
| TC-CRM-021 | Existing phone | bridge reused, no duplicate |
| TC-CRM-022 | inquiry_type=CCTV | category_ai=CCTV |
| TC-CRM-023 | NEGATIVE sentiment | satisfaction schedule +1d |

### 10.4 레거시 연동

| TC ID | Steps | Expected |
|-------|-------|----------|
| TC-CRM-030 | ai_analyze.php manual → auto close | same column values |
| TC-CRM-031 | admin/consults/view.php | room_id in detail_json link |
| TC-CRM-032 | Export CSV | new AI columns populated |

### 10.5 Integration Test (PHPUnit)

```php
public function test_close_room_creates_consult_and_schedules(): void
public function test_close_retries_on_ai_failure(): void
public function test_close_idempotent(): void
public function test_customer_bridge_on_duplicate_phone(): void
```

---

## 11. Future (로드맵)

### 11.1 V4.0 — 이메일 자동 발송

- SMTP queue, template engine, unsubscribe
- `schedules` status auto → done on send

### 11.2 V4.5 — SMS/카카오 알림톡

- Kakao BizMessage, SMS gateway
- 고객 동의(opt-in) 필수

### 11.3 V5.0 — Unified Customer Data Platform

- Single `customers` table (UUID)
- Legacy BIGINT deprecate
- Real-time CRM webhook to external ERP

---

## 12. 부록 — 레거시 연동·구현 가이드

### 12.1 admin/consults/ 파일 매핑

| 레거시 파일 | ACEP 대체 | 비고 |
|-------------|-----------|------|
| `ai_summary.php` | CrmCloseService → summarize | 수동 버튼 유지 |
| `ai_analyze.php` | CrmCloseService → analyze | JSON schema 동일 |
| `ai_reply.php` | 실시간 Agent UI | close flow 외 |
| `index.php` | + ACEP badge column | room_id filter |
| `view.php` | + chat timeline embed | |

### 12.2 CrmCloseService PHP Skeleton

```php
<?php
declare(strict_types=1);

final class CrmCloseService
{
    public function __construct(
        private ChatRoomRepository $rooms,
        private ConsultRepository $consults,
        private CustomerBridgeRepository $bridge,
        private ScheduleRepository $schedules,
        private AiPipeline $ai,
        private PDO $pdo,
    ) {}

    public function execute(string $roomId, string $agentId, ?array $feedback): array
    {
        $attempt = 0;
        $delays = [0, 1, 2, 4];
        $lastEx = null;

        while ($attempt < count($delays)) {
            if ($delays[$attempt] > 0) {
                sleep($delays[$attempt]);
            }
            try {
                return $this->pdo->transaction(function () use ($roomId, $agentId, $feedback) {
                    return $this->doClose($roomId, $agentId, $feedback);
                });
            } catch (Throwable $e) {
                $lastEx = $e;
                $attempt++;
            }
        }
        throw new CrmCloseException('CRM save failed', 0, $lastEx);
    }

    private function doClose(string $roomId, string $agentId, ?array $feedback): array
    {
        $room = $this->rooms->findActive($roomId);
        $messages = $this->rooms->getMessages($roomId);
        $summary = $this->ai->summarize($room, $messages);
        $analysis = $this->ai->analyze($room, $messages, $summary);
        $legacyCustomerId = $this->bridge->resolveLegacyCustomer($room->customer_id);
        $consultId = $this->consults->upsertFromRoom($room, $legacyCustomerId, $summary, $analysis, $feedback);
        $scheduleIds = $this->schedules->createFollowUps($consultId, $analysis);
        $this->rooms->linkConsult($roomId, $consultId);
        return [
            'consult_id' => $consultId,
            'consult_no' => $this->consults->getConsultNo($consultId),
            'schedule_ids' => $scheduleIds,
        ];
    }
}
```

### 12.3 config/app.php 상수

```php
// CRM auto-save (config/app.php additive)
const CRM_MIN_DURATION_SEC = 300;
const CRM_MIN_MESSAGE_COUNT = 3;
const CRM_SAVE_TIMEOUT_SEC = 5;
const CRM_RETRY_MAX = 3;
const ADMIN_NOTIFY_EMAIL = 'adfull@naver.com'; // 상담 접수 알림
```

### 12.4 CONSULT_STATUSES 매핑

| chat close | consults.status |
|------------|-----------------|
| normal close | `consulting` 또는 `progress` |
| quoted intent | `quoted` (AI intent) |
| contract signed flag | `contracted` |

### 12.5 관련 문서

- [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md) §9.5 레거시 매핑
- [07_ADMIN/01_관리자대시보드.md](../07_ADMIN/01_관리자대시보드.md) — CRM KPI
- [08_TEST/03_E2E_시나리오_및_체크리스트.md](../08_TEST/03_E2E_시나리오_및_체크리스트.md) E2E-01-S10

### 12.6 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 5 초판 — CRM auto-save SSOT |

---

*End of document*
