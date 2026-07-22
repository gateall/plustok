# ACEP (PlusTok Enterprise) — REST API 설계

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 2 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** System Architecture Team  
**Audience:** Backend/Frontend Developers, QA  

**적용 위치:** `www/` (E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 상위 문서 | [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md) |
| UI/UX 참조 | [01_상담채팅화면.fig.md](../02_UIUX/01_상담채팅화면.fig.md) |
| 컴포넌트 참조 | [UI_COMPONENTS_GUIDE.md](../02_UIUX/UI_COMPONENTS_GUIDE.md) |
| DB 설계 | [01_DB설계.md](01_DB설계.md) |
| 시스템 아키텍처 | [03_시스템아키텍처.md](03_시스템아키텍처.md) |
| Base URL | `https://{host}/api/v1` |
| REST 엔드포인트 수 | **30개** (WebSocket 별도) |

본 문서는 ACEP 상담채팅 플랫폼의 **30개 REST API** 명세와 **WebSocket 이벤트**를 정의한다. UI 문서 §6의 5개 핵심 API를 `/api/v1/` prefix로 포함한다.

---

## 1. 공통 규격

### 1.1 표준 응답 형식 (MASTER Rule-004)

**성공:**

```json
{
  "success": true,
  "data": { },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

**실패:**

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "상담방을 찾을 수 없습니다",
    "code": "ROOM_NOT_FOUND"
  },
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

### 1.2 HTTP Status Code

| Code | 용도 |
|------|------|
| 200 | OK (GET, PUT 성공) |
| 201 | Created (POST 생성) |
| 400 | Bad Request (검증 실패) |
| 401 | Unauthorized (JWT 없음/만료) |
| 403 | Forbidden (RBAC/room 소유권) |
| 404 | Not Found |
| 409 | Conflict (중복 배정 등) |
| 429 | Too Many Requests (Rule-005) |
| 500 | Internal Server Error |
| 503 | Service Unavailable (AI_ALL_FAILED) |

### 1.3 공통 Request Headers

| Header | 필수 | 설명 |
|--------|:----:|------|
| `Authorization` | Y* | `Bearer {accessToken}` (*public 제외) |
| `Content-Type` | Y | `application/json` |
| `X-Request-Id` | N | 추적 UUID (권장) |
| `Accept-Language` | N | `ko-KR` (default) |

### 1.4 인증 (JWT)

| Token | 유효기간 | 저장 |
|-------|----------|------|
| Access Token | 24h | Memory / Authorization header |
| Refresh Token | 7d | HttpOnly Cookie `acep_refresh` |

**JWT Payload:**

```json
{
  "sub": "agent-uuid",
  "role": "agent",
  "name": "김상담",
  "iat": 1721540100,
  "exp": 1721626500
}
```

### 1.5 Rate Limiting (Rule-005)

| 대상 | 제한 | Redis Key |
|------|------|-----------|
| IP | 100 req/min | `rl:ip:{ip}` |
| 사용자 (JWT sub) | 50 req/min | `rl:user:{sub}` |
| AI 호출 (room) | 10 req/min | `rl:ai:room:{roomId}` |

- 초과 시 **HTTP 429** + `Retry-After: 60` header
- Response body: `{ "code": "RATE_LIMIT_EXCEEDED" }`

### 1.6 페이지네이션

| Param | Type | Default | Max |
|-------|------|---------|-----|
| page | int | 1 | - |
| limit | int | 20 | 50 |
| before | ISO8601 | - | cursor (messages) |

### 1.7 Error Code 목록

| code | HTTP | 설명 |
|------|------|------|
| VALIDATION_ERROR | 400 | 입력 검증 실패 |
| UNAUTHORIZED | 401 | 인증 실패 |
| FORBIDDEN | 403 | 권한 없음 |
| ROOM_NOT_FOUND | 404 | 상담방 없음 |
| MESSAGE_NOT_FOUND | 404 | 메시지 없음 |
| CUSTOMER_NOT_FOUND | 404 | 고객 없음 |
| AGENT_NOT_FOUND | 404 | 상담원 없음 |
| FILE_TOO_LARGE | 400 | 10MB 초과 |
| INVALID_FILE_TYPE | 400 | MIME 불허 |
| RATE_LIMIT_EXCEEDED | 429 | Rule-005 |
| MSG_SEND_FAILED | 500 | 메시지 저장 실패 |
| AI_ALL_FAILED | 503 | Failover 전부 실패 |
| ACCOUNT_LOCKED | 403 | 로그인 3회 실패 |

---

## 2. 엔드포인트 목록 (30개)

| # | Method | Path | Domain | MVP |
|---|--------|------|--------|:---:|
| 1 | POST | `/auth/login` | Auth | ✅ |
| 2 | POST | `/auth/logout` | Auth | ✅ |
| 3 | POST | `/auth/refresh` | Auth | ✅ |
| 4 | GET | `/auth/me` | Auth | ✅ |
| 5 | GET | `/chats/rooms` | Chats | ✅ |
| 6 | GET | `/chats/{id}` | Chats | ✅ |
| 7 | POST | `/chats/rooms` | Chats | ✅ |
| 8 | PUT | `/chats/{id}/close` | Chats | ✅ |
| 9 | PUT | `/chats/{id}/read` | Chats | ✅ |
| 10 | PUT | `/chats/{id}/assign` | Chats | V1.5 |
| 11 | GET | `/chats/{id}/messages` | Messages | ✅ |
| 12 | POST | `/chats/{id}/messages` | Messages | ✅ |
| 13 | DELETE | `/chats/{id}/messages/{messageId}` | Messages | V1.5 |
| 14 | GET | `/ai/recommendations/{id}` | AI | ✅ |
| 15 | POST | `/ai/recommendations/{id}/retry` | AI | V1.5 |
| 16 | GET | `/ai/settings` | AI | V1.5 |
| 17 | PUT | `/ai/settings` | AI | V1.5 |
| 18 | GET | `/customers/{id}` | Customers | ✅ |
| 19 | PUT | `/customers/{id}` | Customers | ✅ |
| 20 | GET | `/customers` | Customers | V1.5 |
| 21 | GET | `/agents` | Agents | V1.5 |
| 22 | GET | `/agents/{id}` | Agents | V1.5 |
| 23 | PUT | `/agents/{id}/status` | Agents | ✅ |
| 24 | PUT | `/agents/me/profile` | Agents | ✅ |
| 25 | GET | `/admin/dashboard` | Admin | V1.5 |
| 26 | GET | `/admin/audit-logs` | Admin | V1.5 |
| 27 | PUT | `/admin/agents/{id}` | Admin | V1.5 |
| 28 | POST | `/files/upload` | Files | ✅ |
| 29 | GET | `/files/{id}` | Files | ✅ |
| 30 | GET | `/system/health` | System | ✅ |

---

## 3. Auth Domain (4)

### API-001 POST /api/v1/auth/login

| 항목 | 내용 |
|------|------|
| Auth | Public |
| UI | 로그인 페이지 (Header redirect 대상) |
| DB | `agents`, `audit_logs` |

**Request Body:**

```json
{
  "loginId": "agent01",
  "password": "********"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "accessToken": "eyJhbGciOiJIUzI1NiIs...",
    "expiresIn": 86400,
    "agent": {
      "id": "agent-uuid-1",
      "name": "김상담",
      "role": "agent",
      "status": "online"
    }
  },
  "error": null,
  "timestamp": "2026-07-21T09:00:00+09:00"
}
```

**Error Codes:**

| code | HTTP | 조건 |
|------|------|------|
| UNAUTHORIZED | 401 | ID/PW 불일치 |
| ACCOUNT_LOCKED | 403 | failed_login_count ≥ 3 |

**Business Rules:**

- bcrypt cost 12 검증
- 3회 연속 실패 → `locked_until` = NOW() + 30분
- 성공 시 `audit_logs.action = 'login'`, Refresh Token Cookie Set

---

### API-002 POST /api/v1/auth/logout

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | Header 로그아웃 |
| DB | `audit_logs` |

**Request Body:** (empty)

**Response 200:**

```json
{
  "success": true,
  "data": { "loggedOut": true },
  "error": null,
  "timestamp": "2026-07-21T18:00:00+09:00"
}
```

**Business Rules:**

- Refresh Token Cookie invalidate (Max-Age=0)
- Redis JWT blacklist (optional, TTL=access remaining)
- `audit_logs.action = 'logout'`

---

### API-003 POST /api/v1/auth/refresh

| 항목 | 내용 |
|------|------|
| Auth | HttpOnly Cookie `acep_refresh` |
| UI | 401 interceptor 자동 호출 |
| DB | `agents` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "accessToken": "eyJhbGciOiJIUzI1NiIs...",
    "expiresIn": 86400
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

**Error Codes:** UNAUTHORIZED 401 (refresh 만료/무효)

---

### API-004 GET /api/v1/auth/me

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | Header 프로필, ChatScreen init |
| DB | `agents` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "agent-uuid-1",
    "loginId": "agent01",
    "name": "김상담",
    "role": "agent",
    "status": "online",
    "avatarUrl": null,
    "lastLoginAt": "2026-07-21T09:00:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

## 4. Chats Domain (6)

### API-005 GET /api/v1/chats/rooms

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (agent, admin) |
| UI | **`ChatList`** |
| DB | `chat_rooms`, `customers`, `chat_read_status`, `ai_recommendations` |

**Query Parameters:**

| Param | Type | 설명 |
|-------|------|------|
| status | string | `new,active,closed` (comma-separated) |
| search | string | 고객명·문의유형 검색 |
| page | int | default 1 |
| limit | int | default 20, max 50 |
| sort | string | `updated_at:desc` (default), `priority:desc` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "rooms": [
      {
        "id": "room-uuid-1",
        "customer": {
          "id": "cust-1",
          "name": "홍길동",
          "phoneMasked": "010-1234-****"
        },
        "inquiryType": "인터넷 가입",
        "status": "new",
        "unreadCount": 2,
        "contractProbability": 87,
        "updatedAt": "2026-07-21T14:30:00+09:00"
      }
    ],
    "pagination": { "page": 1, "limit": 20, "total": 45 }
  },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

**Business Rules:**

- Agent: `agent_id = sub` OR `status = 'new'` (미배정)
- Admin: 전체 room
- 정렬: UI §4-2-A — status > contract_probability > updated_at
- `unreadCount`: chat_read_status WHERE read_at IS NULL

**UI 바인딩:** `ChatListProps.rooms[]`

---

### API-006 GET /api/v1/chats/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | room header, Footer 메모 |
| DB | `chat_rooms`, `customers`, `agents` |

**Path:** `{id}` = room_id

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "room-uuid-1",
    "customer": {
      "id": "cust-1",
      "name": "홍길동",
      "phoneMasked": "010-1234-****",
      "tags": ["신규", "긍정"]
    },
    "agent": { "id": "agent-1", "name": "김상담" },
    "inquiryType": "인터넷 가입",
    "status": "active",
    "channel": "web",
    "subject": "인터넷 가입 문의",
    "memo": null,
    "createdAt": "2026-07-21T14:00:00+09:00",
    "updatedAt": "2026-07-21T14:30:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

**Error Codes:** ROOM_NOT_FOUND 404, FORBIDDEN 403

---

### API-007 POST /api/v1/chats/rooms

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (agent, admin) / Customer widget token |
| UI | Reception (고객 위젯 V1.5+) |
| DB | `customers`, `chat_rooms`, `ai_recommendations` |

**Request Body:**

```json
{
  "customerName": "홍길동",
  "customerPhone": "01012345678",
  "inquiryType": "인터넷 가입",
  "channel": "web",
  "initialMessage": "안녕하세요. 인터넷 문의드립니다."
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "roomId": "room-uuid-new",
    "customerId": "cust-new",
    "status": "new",
    "createdAt": "2026-07-21T14:00:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:00:00+09:00"
}
```

**Business Rules:**

- phone_hash로 기존 customer 매칭 또는 신규 생성 (PII encrypt)
- initialMessage 있으면 chat_messages INSERT + AI 트리거
- PLUS톡 V2.0: external_crm_id webhook

---

### API-008 PUT /api/v1/chats/{id}/close

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (agent, admin) |
| UI | **`ActionButton`** action=close, TC-005 |
| DB | `chat_rooms`, `ai_recommendations`, `audit_logs` |

**Request Body:**

```json
{
  "reason": "상담 완료",
  "sendFeedbackRequest": false
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "roomId": "room-uuid-1",
    "status": "closed",
    "closedAt": "2026-07-21T15:00:00+09:00",
    "summary": "고객은 인터넷 500M 요금제에 관심. 설치 일정 협의 완료."
  },
  "error": null,
  "timestamp": "2026-07-21T15:00:00+09:00"
}
```

**Business Rules:**

- active → closed (BR-상담종료 §7.4)
- AI 상담요약 생성 (PROMPT_SUMMARY_v1.0)
- CRM webhook (V2.0)
- closed room: read API skip (BR-READ-004)

---

### API-009 PUT /api/v1/chats/{id}/read

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | **`MessageBubble`** read indicator, TC-003 |
| DB | `chat_read_status` |

**Request Body:**

```json
{
  "messageIds": ["msg-uuid-1", "msg-uuid-2"],
  "readerType": "agent"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": { "updatedCount": 2 },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

**Business Rules:**

- BR-READ-001~003: 사용자 액션( room focus) 시에만 read_at 설정
- Side Effect: WebSocket `read:update` broadcast
- closed room → 400 skip

**UI 바인딩:** MessageBubble `readStatus: 'read'`

---

### API-010 PUT /api/v1/chats/{id}/assign

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (agent self-assign, admin) |
| UI | ChatList room select → new→active |
| DB | `chat_rooms`, `chat_room_assignments` |

**Request Body:**

```json
{
  "agentId": "agent-uuid-1",
  "assignmentType": "manual"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "roomId": "room-uuid-1",
    "agentId": "agent-uuid-1",
    "status": "active",
    "assignedAt": "2026-07-21T14:10:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:10:00+09:00"
}
```

**Business Rules:**

- new → active 전환 (§7.5)
- chat_room_assignments INSERT, is_active=1
- 기존 active assignment → unassigned_at 설정

---

## 5. Messages Domain (3)

### API-011 GET /api/v1/chats/{id}/messages

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | **`MessageBubble`** list |
| DB | `chat_messages`, `chat_read_status`, `attachments` |

**Query:** `page`, `limit` (50), `before` (cursor ISO datetime)

**Response 200:**

```json
{
  "success": true,
  "data": {
    "messages": [
      {
        "id": "msg-uuid-1",
        "senderType": "customer",
        "senderId": "cust-1",
        "content": "안녕하세요. 인터넷 문의드립니다.",
        "attachmentUrl": null,
        "attachmentType": null,
        "source": "manual",
        "createdAt": "2026-07-21T14:30:00+09:00",
        "readStatus": "read"
      }
    ],
    "hasMore": true
  },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

**Business Rules:**

- room 소유권 검증 (IDOR 방지)
- infinite scroll: `before=createdAt` cursor
- readStatus: sent | delivered | read

**UI 바인딩:** `MessageBubbleProps`

---

### API-012 POST /api/v1/chats/{id}/messages

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | **`InputField`** + **`ActionButton`** send, TC-001 |
| DB | `chat_messages`, `chat_rooms`, `ai_recommendations` |

**Request Body:**

```json
{
  "content": "안녕하세요. 무엇을 도와드릴까요?",
  "attachmentUrl": null,
  "attachmentId": null,
  "source": "manual",
  "aiRecommendationId": null
}
```

| source | 설명 |
|--------|------|
| manual | 상담원 직접 입력 |
| ai_recommendation | **`RecommendationCard`** 클릭 후 전송 |

**Response 201:**

```json
{
  "success": true,
  "data": {
    "messageId": "msg-uuid-new",
    "createdAt": "2026-07-21T14:32:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:32:00+09:00"
}
```

**Side Effects:**

1. chat_rooms.updated_at 갱신
2. 고객 메시지 시 AI Router 트리거 → ai_recommendations pending
3. WebSocket `message:receive` broadcast
4. new room + agent 첫 응답 → status active

**Error Codes:** MSG_SEND_FAILED 500, ROOM_NOT_FOUND 404, VALIDATION_ERROR 400 (2000자 초과)

---

### API-013 DELETE /api/v1/chats/{id}/messages/{messageId}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin, 본인 agent 메시지) |
| UI | (V1.5+) 메시지 컨텍스트 메뉴 |
| DB | `chat_messages` (soft delete) |

**Response 200:**

```json
{
  "success": true,
  "data": { "messageId": "msg-uuid-1", "deleted": true },
  "error": null,
  "timestamp": "2026-07-21T16:00:00+09:00"
}
```

**Business Rules:**

- deleted_at 설정 (물리 삭제 금지)
- system 메시지 삭제 불가 → 403
- audit_logs INSERT

---

## 6. AI Domain (4)

### API-014 GET /api/v1/ai/recommendations/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (agent, admin) |
| UI | **`AIPanelCard`**, **`RecommendationCard`**, TC-002 |
| DB | `ai_recommendations` |

**Path:** `{id}` = room_id

**Response 200:**

```json
{
  "success": true,
  "data": {
    "roomId": "room-uuid-1",
    "contractProbability": 87,
    "contractLabel": "높음 - 우선 대응",
    "sentiment": "positive",
    "intent": "purchase",
    "customerTags": ["신규", "고가", "긍정"],
    "recommendations": [
      { "id": "rec-1", "text": "설치비는 무료입니다.", "confidence": 0.92 },
      { "id": "rec-2", "text": "3월까지 프로모션 적용 가능합니다.", "confidence": 0.87 }
    ],
    "faq": [
      { "question": "인터넷 설치비?", "answer": "기본 설치비는 무료입니다." }
    ],
    "aiModel": "claude-3.5-sonnet",
    "status": "completed",
    "updatedAt": "2026-07-21T14:35:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

| status | UI (AIPanelCard) |
|--------|------------------|
| pending | skeleton |
| processing | "분석 중..." |
| completed | RecommendationCard 렌더 |
| failed | "AI 분석 불가" + retry |

**Business Rules:**

- room 최신 ai_recommendations 1건 반환
- BR-AI-002: recommendations 최대 3개
- NFR-002: 2초 이내 completed 목표
- Rate limit: rl:ai:room:{id} 10/min

**UI 바인딩:** `AIPanelCard`, `RecommendationCard`, `CustomerCard.tags`

---

### API-015 POST /api/v1/ai/recommendations/{id}/retry

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | AIPanelCard error state **`onRetry`** |
| DB | `ai_recommendations`, `ai_failover_log`, `ai_logs` |

**Request Body:** (empty)

**Response 202:**

```json
{
  "success": true,
  "data": {
    "roomId": "room-uuid-1",
    "status": "processing",
    "recommendationId": "rec-new-uuid"
  },
  "error": null,
  "timestamp": "2026-07-21T14:40:00+09:00"
}
```

**Business Rules:**

- AI_ALL_FAILED 후 수동 재시도
- Failover chain 재실행 (Rule-001)
- WebSocket `ai:update` on complete

---

### API-016 GET /api/v1/ai/settings

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin, operator) |
| UI | Admin AI 설정 (V1.5) |
| DB | `ai_settings`, `ai_provider_config` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "settings": [
      { "key": "ai_room_rate_limit", "value": { "limit": 10, "windowSec": 60 } }
    ],
    "providers": [
      { "provider": "claude", "modelName": "claude-3.5-sonnet", "priority": 1, "isActive": true },
      { "provider": "openai", "modelName": "gpt-4o", "priority": 2, "isActive": true }
    ]
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

### API-017 PUT /api/v1/ai/settings

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin, operator) |
| UI | Admin AI 설정 저장 |
| DB | `ai_settings`, `audit_logs` |

**Request Body:**

```json
{
  "settings": [
    { "key": "ai_room_rate_limit", "value": { "limit": 15, "windowSec": 60 } }
  ]
}
```

**Response 200:**

```json
{
  "success": true,
  "data": { "updatedKeys": ["ai_room_rate_limit"] },
  "error": null,
  "timestamp": "2026-07-21T10:05:00+09:00"
}
```

**Business Rules:**

- Rule-005 Admin 임계값 조정
- audit_logs.action = 'ai.settings.update'

---

## 7. Customers Domain (3)

### API-018 GET /api/v1/customers/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | **`CustomerCard`** |
| DB | `customers` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "cust-1",
    "name": "홍길동",
    "phoneMasked": "010-1234-****",
    "emailMasked": "hong@****.com",
    "addressMasked": "서울시 강남구 ****",
    "tags": ["신규", "고가", "긍정"],
    "consultationCount": 2,
    "createdAt": "2026-06-01T10:00:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

**Business Rules:**

- PII 복호화 후 마스킹만 반환 (MASTER 9.3)
- room 접근 권한 연계 검증

---

### API-019 PUT /api/v1/customers/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (agent, admin) |
| UI | CustomerCard **`onEdit`** |
| DB | `customers`, `audit_logs` |

**Request Body:**

```json
{
  "name": "홍길동",
  "tags": ["신규", "VIP"],
  "memo": "프로모션 관심"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "cust-1",
    "name": "홍길동",
    "tags": ["신규", "VIP"],
    "updatedAt": "2026-07-21T15:00:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T15:00:00+09:00"
}
```

---

### API-020 GET /api/v1/customers

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin) |
| UI | Admin 고객 목록 (V1.5) |
| DB | `customers` |

**Query:** `search`, `page`, `limit`, `tag`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "customers": [
      { "id": "cust-1", "name": "홍길동", "phoneMasked": "010-****-5678", "tags": ["신규"] }
    ],
    "pagination": { "page": 1, "limit": 20, "total": 120 }
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

## 8. Agents Domain (4)

### API-021 GET /api/v1/agents

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin) |
| UI | Admin 상담원 목록 |
| DB | `agents` |

**Query:** `role`, `status`, `page`, `limit`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "agents": [
      { "id": "agent-1", "name": "김상담", "role": "agent", "status": "online", "activeRooms": 3 }
    ],
    "pagination": { "page": 1, "limit": 20, "total": 8 }
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

### API-022 GET /api/v1/agents/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin, self) |
| UI | Admin 상담원 상세 |
| DB | `agents`, `chat_room_assignments` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "agent-1",
    "loginId": "agent01",
    "name": "김상담",
    "role": "agent",
    "status": "online",
    "activeRoomCount": 3,
    "lastLoginAt": "2026-07-21T09:00:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

### API-023 PUT /api/v1/agents/{id}/status

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (self or admin) |
| UI | Header 근무상태 |
| DB | `agents` |

**Request Body:**

```json
{
  "status": "away"
}
```

| status | 설명 |
|--------|------|
| online | 접수 가능 |
| away | 자리비움 |
| offline | 오프라인 |

**Response 200:**

```json
{
  "success": true,
  "data": { "id": "agent-1", "status": "away" },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

### API-024 PUT /api/v1/agents/me/profile

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | Header 프로필 편집 |
| DB | `agents` |

**Request Body:**

```json
{
  "name": "김상담",
  "avatarUrl": "https://cdn.example.com/avatar.jpg"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "agent-1",
    "name": "김상담",
    "avatarUrl": "https://cdn.example.com/avatar.jpg"
  },
  "error": null,
  "timestamp": "2026-07-21T10:00:00+09:00"
}
```

---

## 9. Admin Domain (3)

### API-025 GET /api/v1/admin/dashboard

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin) |
| UI | Admin Dashboard (V1.5) |
| DB | `chat_rooms`, `ai_recommendations`, `ai_logs`, `agents` |

**Query:** `period` = `today` | `week` | `month`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "activeRooms": 12,
    "newRoomsToday": 45,
    "avgFirstResponseSec": 28,
    "aiAdoptionRate": 0.42,
    "aiFailoverRate": 0.003,
    "agentStats": [
      { "agentId": "agent-1", "name": "김상담", "handledRooms": 15, "avgResponseSec": 25 }
    ]
  },
  "error": null,
  "timestamp": "2026-07-21T18:00:00+09:00"
}
```

---

### API-026 GET /api/v1/admin/audit-logs

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin, operator) |
| UI | Admin 감사 로그 |
| DB | `audit_logs` |

**Query:** `action`, `actorId`, `from`, `to`, `page`, `limit`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 1001,
        "actorType": "agent",
        "actorId": "agent-1",
        "action": "login",
        "ipAddress": "203.0.113.1",
        "createdAt": "2026-07-21T09:00:00+09:00"
      }
    ],
    "pagination": { "page": 1, "limit": 50, "total": 500 }
  },
  "error": null,
  "timestamp": "2026-07-21T18:00:00+09:00"
}
```

---

### API-027 PUT /api/v1/admin/agents/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT (admin) |
| UI | Admin 상담원 CRUD |
| DB | `agents`, `audit_logs` |

**Request Body:**

```json
{
  "name": "김상담",
  "role": "agent",
  "isActive": true
}
```

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "agent-1",
    "name": "김상담",
    "role": "agent",
    "updatedAt": "2026-07-21T11:00:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T11:00:00+09:00"
}
```

---

## 10. Files Domain (2)

### API-028 POST /api/v1/files/upload

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | **`FileUpload`**, TC-007 |
| DB | `attachments` |

**Request:** `multipart/form-data`

| Field | Type | 설명 |
|-------|------|------|
| file | binary | jpg, png, pdf |
| roomId | string | 상담방 ID |

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": "att-uuid-1",
    "url": "https://cdn.example.com/files/att-uuid-1.jpg",
    "type": "image",
    "name": "photo.jpg",
    "size": 5242880
  },
  "error": null,
  "timestamp": "2026-07-21T14:33:00+09:00"
}
```

**Error Codes:**

| code | HTTP | 조건 |
|------|------|------|
| FILE_TOO_LARGE | 400 | > 10MB |
| INVALID_FILE_TYPE | 400 | MIME 불허 |

**Business Rules:**

- accept: image/jpeg, image/png, application/pdf
- max 5 files per message (UI)
- attachments INSERT, message 전송 시 attachmentId 연결

---

### API-029 GET /api/v1/files/{id}

| 항목 | 내용 |
|------|------|
| Auth | Bearer JWT |
| UI | MessageBubble **`onAttachmentClick`** |
| DB | `attachments` |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": "att-uuid-1",
    "fileName": "photo.jpg",
    "mimeType": "image/jpeg",
    "fileSize": 5242880,
    "publicUrl": "https://cdn.example.com/files/att-uuid-1.jpg",
    "createdAt": "2026-07-21T14:33:00+09:00"
  },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

---

## 11. System Domain (1)

### API-030 GET /api/v1/system/health

| 항목 | 내용 |
|------|------|
| Auth | Public (internal network) / Operator API Key |
| UI | — (모니터링) |
| DB | — |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "1.0.0",
    "components": {
      "database": { "status": "up", "latencyMs": 2 },
      "redis": { "status": "up", "latencyMs": 1 },
      "chatServer": { "status": "up", "connections": 342 },
      "aiRouter": { "status": "up", "primaryProvider": "claude" }
    },
    "uptimeSec": 864000
  },
  "error": null,
  "timestamp": "2026-07-21T18:00:00+09:00"
}
```

**Response 503 (degraded):**

```json
{
  "success": false,
  "data": {
    "status": "degraded",
    "components": {
      "aiRouter": { "status": "down", "error": "All providers failed" }
    }
  },
  "error": { "message": "Service degraded", "code": "SERVICE_DEGRADED" },
  "timestamp": "2026-07-21T18:00:00+09:00"
}
```

---

## 12. WebSocket Events (별도)

> Chat Server (Node.js + Socket.io). REST 30개와 별도.

### 12.1 연결

| 항목 | 값 |
|------|-----|
| URL | `wss://{host}/socket.io` |
| Auth | handshake `auth: { token: accessToken }` |
| Transport | websocket, polling fallback |
| Reconnect | exponential backoff 1s→2s→4s max 30s |

### 12.2 Client → Server

| Event | Payload | Trigger |
|-------|---------|---------|
| `room:join` | `{ roomId, userId, role }` | room 선택 |
| `room:leave` | `{ roomId }` | room 전환 |
| `message:send` | `{ roomId, content, tempId }` | optimistic UI |
| `typing:start` | `{ roomId, userId }` | InputField onInput |
| `typing:stop` | `{ roomId, userId }` | 3s idle / send |

### 12.3 Server → Client

| Event | Payload | UI Effect |
|-------|---------|-----------|
| **`message:receive`** | `{ messageId, roomId, content, senderType, senderId, timestamp, tempId }` | **`MessageBubble`** append |
| **`typing:start`** | `{ roomId, userId, userName, userType }` | **`TypingIndicator`** show |
| **`typing:stop`** | `{ roomId, userId }` | **`TypingIndicator`** hide |
| **`ai:update`** | `{ roomId, recommendationId, status, contractProbability }` | **`AIPanelCard`** refresh |
| **`read:update`** | `{ roomId, messageId, readerType, readAt }` | **`MessageBubble`** ✓✓ |

### 12.4 message:receive Payload

```json
{
  "messageId": "msg-uuid-new",
  "roomId": "room-uuid-1",
  "content": "설치비는 무료입니다.",
  "senderType": "agent",
  "senderId": "agent-1",
  "attachmentUrl": null,
  "timestamp": "2026-07-21T14:32:00+09:00",
  "tempId": "temp-client-id-123"
}
```

### 12.5 ai:update Payload

```json
{
  "roomId": "room-uuid-1",
  "recommendationId": "rec-uuid-1",
  "status": "completed",
  "contractProbability": 87,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

### 12.6 read:update Payload

```json
{
  "roomId": "room-uuid-1",
  "messageId": "msg-uuid-1",
  "readerType": "agent",
  "readAt": "2026-07-21T14:36:00+09:00"
}
```

### 12.7 REST ↔ WebSocket 이중 전송 패턴

```
[상담원] ActionButton(send)
    ↓
POST /api/v1/chats/{id}/messages  (persist)
    +
socket.emit('message:send')       (optimistic)
    ↓
Chat Server → message:receive broadcast
    ↓
Backend AI Router → ai:update
```

---

## 13. API ↔ UI ↔ DB Cross-Reference

| UI Component | REST API | WebSocket | DB Tables |
|--------------|----------|-----------|-----------|
| ChatList | GET /chats/rooms | — | chat_rooms, customers, chat_read_status |
| MessageBubble | GET/POST /chats/{id}/messages | message:receive | chat_messages |
| MessageBubble (read) | PUT /chats/{id}/read | read:update | chat_read_status |
| RecommendationCard | GET /ai/recommendations/{id} | ai:update | ai_recommendations |
| AIPanelCard | GET /ai/recommendations/{id} | ai:update | ai_recommendations |
| CustomerCard | GET /customers/{id} | — | customers |
| FileUpload | POST /files/upload | — | attachments |
| TypingIndicator | — | typing:* | — |
| ActionButton (close) | PUT /chats/{id}/close | — | chat_rooms |
| Header (profile) | GET /auth/me, PUT /agents/me/profile | — | agents |

---

## 14. RBAC 매트릭스

| Endpoint | customer | agent | admin | operator |
|----------|:--------:|:-----:|:-----:|:--------:|
| POST /auth/login | ✓ | ✓ | ✓ | ✓ |
| GET /chats/rooms | own | assigned+new | ✓ | read |
| GET /chats/{id}/messages | own room | assigned | ✓ | read |
| POST /chats/{id}/messages | own | assigned | ✓ | — |
| GET /ai/recommendations/{id} | — | assigned | ✓ | read |
| PUT /chats/{id}/read | own | assigned | ✓ | — |
| GET /admin/* | — | — | ✓ | ✓ |
| PUT /ai/settings | — | — | ✓ | ✓ |

---

## 15. 구현 체크리스트 (V1.0 MVP)

| Priority | API | Component |
|----------|-----|-----------|
| P0 | API-001 login | — |
| P0 | API-005 rooms | ChatList |
| P0 | API-011 messages GET | MessageBubble |
| P0 | API-012 messages POST | InputField, ActionButton |
| P0 | API-014 ai/recommendations | AIPanelCard, RecommendationCard |
| P0 | API-009 read | MessageBubble |
| P1 | API-008 close | ActionButton |
| P1 | API-028 upload | FileUpload |
| P1 | API-030 health | DevOps |

---

## 부록 A. OpenAPI Tag Summary

```yaml
tags:
  - name: Auth
    description: 인증·세션
  - name: Chats
    description: 상담방 CRUD·배정·종료
  - name: Messages
    description: 메시지 송수신
  - name: AI
    description: AI 추천·설정
  - name: Customers
    description: 고객 CRM
  - name: Agents
    description: 상담원 프로필·상태
  - name: Admin
    description: 관리자·통계·감사
  - name: Files
    description: 첨부파일
  - name: System
    description: 헬스체크
```

---

## 부록 B. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 3.0 | 2026-07-21 | STEP 2 — 30 REST + WebSocket |

---

**문서 끝 — DB 스키마는 [01_DB설계.md](01_DB설계.md), 배포는 [03_시스템아키텍처.md](03_시스템아키텍처.md) 참조.**
