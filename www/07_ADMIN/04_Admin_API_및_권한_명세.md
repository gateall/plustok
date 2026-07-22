# 04 — Admin API 및 권한 명세

> **PlusTok ACEP** · STEP 6 · Admin Domain Extension  
> **버전**: 1.0.0 · **작성일**: 2026-07-21  
> **Base API**: [`03_SYSTEM/02_API설계.md`](../03_SYSTEM/02_API설계.md) (Customer/Agent 30 endpoints)  
> **Extension**: Admin Domain **+10 endpoints** (본 문서)

---

## 목차

1. [개요](#1-개요)
2. [Admin Domain Endpoint Catalog](#2-admin-domain-endpoint-catalog)
3. [Endpoint 상세 명세](#3-endpoint-상세-명세)
4. [RBAC 매트릭스](#4-rbac-매트릭스)
5. [JWT Claims (Admin Routes)](#5-jwt-claims-admin-routes)
6. [Audit Logging](#6-audit-logging)
7. [Security Policies](#7-security-policies)
8. [Rate Limiting·Error Codes](#8-rate-limitingerror-codes)
9. [OpenAPI Extension](#9-openapi-extension)
10. [테스트 매트릭스](#10-테스트-매트릭스)
11. [부록](#11-부록)

---

## 1. 개요

### 1.1 Admin Domain Position

```
/api/v1/
├── customer/          # Customer Domain (STEP 4)
├── agent/             # Agent Domain (STEP 5)
├── ai/                # Shared AI invoke
└── admin/             # ★ Admin Domain (STEP 6) — 본 문서
    ├── stats/
    ├── consults/
    ├── monitor/
    ├── agents/
    ├── prompts/
    ├── failover-logs/
    └── audit-logs/
```

기존 30 endpoints에 **Admin 10 endpoints** 추가 → 총 **40 endpoints** (STEP 6 baseline).

### 1.2 Design Conventions (02_API설계 정합)

| Item | Convention |
|------|------------|
| Version | `/api/v1/` |
| Auth | Bearer JWT |
| Content-Type | `application/json; charset=utf-8` |
| Datetime | ISO8601 with timezone (`+09:00`) |
| IDs | UUID v4 for chat_rooms, prompts |
| Pagination | `{ data, meta: { page, limit, total } }` |
| Error | `{ error: { code, message, details? } }` |

### 1.3 Role Hierarchy

```
super > admin > operator > agent > customer
```

Admin Domain accessible: **super, admin, operator** (operator read-only subset).

---

## 2. Admin Domain Endpoint Catalog

| # | Method | Path | Description | Roles |
|---|--------|------|-------------|-------|
| A-01 | GET | `/api/v1/admin/stats/overview` | Dashboard KPI 4-pack | super, admin, operator |
| A-02 | GET | `/api/v1/admin/stats/sentiment` | 감정 분포 | super, admin, operator |
| A-03 | GET | `/api/v1/admin/stats/funnel` | 계약 확률 funnel | super, admin, operator |
| A-04 | GET | `/api/v1/admin/stats/agents` | 에이전트 성과 | super, admin, operator |
| A-05 | GET | `/api/v1/admin/consults` | 상담 통합 목록 | super, admin, operator |
| A-06 | GET | `/api/v1/admin/monitor/rooms` | Live monitor room list | super, admin, operator |
| A-07 | GET/POST/PATCH/DELETE | `/api/v1/admin/prompts` | 프롬프트 CRUD | super, admin (delete super) |
| A-08 | GET | `/api/v1/admin/failover-logs` | Failover log list | super, admin, operator |
| A-09 | GET/POST/PATCH | `/api/v1/admin/agents` | 에이전트 CRUD·배정 | super, admin |
| A-10 | GET | `/api/v1/admin/audit-logs` | 감사 로그 조회 | super, admin |

> **Note**: Stats 상세 request/response는 [`02_Admin_Dashboard_구현명세.md §6`](./02_Admin_Dashboard_구현명세.md#6-admin-stats-api) 참조.

---

## 3. Endpoint 상세 명세

### 3.1 A-05 GET /api/v1/admin/consults

**Description**: ACEP `chat_rooms` + Legacy CRM unified list

**Query Parameters**:

| Param | Type | Description |
|-------|------|-------------|
| page | int | default 1 |
| limit | int | default 20, max 100 |
| status | string | active, waiting, closed |
| agent_id | uuid | filter by assigned agent |
| source | enum | acep, crm, all |
| q | string | search customer name/id |
| period_start | datetime | created_at >= |
| period_end | datetime | created_at < |
| ai_enabled | bool | filter |

**Response 200**:

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "source": "acep",
      "customer_name_masked": "김**",
      "agent": { "id": "A001", "display_name": "김상담" },
      "status": "active",
      "ai_enabled": true,
      "ai_adoption_rate": 68.0,
      "contract_probability": 72.5,
      "created_at": "2026-07-21T10:00:00+09:00",
      "updated_at": "2026-07-21T14:30:00+09:00"
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 1247 }
}
```

**Permissions**: operator — read only; no `POST` actions via this endpoint.

---

### 3.2 A-06 GET /api/v1/admin/monitor/rooms

**Description**: Active/waiting rooms for Live Monitor

**Query**: `limit` (default 100), `agent_id` (optional)

**Response 200**:

```json
{
  "rooms": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "status": "active",
      "customer_name_masked": "김**",
      "agent": { "id": "A001", "display_name": "김상담" },
      "updated_at": "2026-07-21T14:32:00+09:00",
      "last_message_preview": "5G 결합 할인 있나요?",
      "ai_enabled": true
    }
  ],
  "generated_at": "2026-07-21T14:35:00+09:00"
}
```

**Sub-resource** (same auth):

```
GET /api/v1/admin/monitor/rooms/{room_id}/messages?limit=50&before={cursor}
GET /api/v1/admin/monitor/rooms/{room_id}/insight
```

`insight` response:

```json
{
  "sentiment": { "label": "neutral", "score": 0.72 },
  "contract_probability": 67.0,
  "recommendations": { "total": 3, "used": 2 },
  "failover_count": 0
}
```

---

### 3.3 A-07 /api/v1/admin/prompts

#### GET /api/v1/admin/prompts

List all prompt versions grouped by key.

**Query**: `type`, `is_active`, `q`

**Response**:

```json
{
  "data": [
    {
      "id": "prompt-uuid-1",
      "prompt_key": "sys_v3",
      "name": "System Base",
      "type": "system",
      "version": 3,
      "is_active": true,
      "updated_at": "2026-07-20T15:00:00+09:00",
      "updated_by": { "id": 1, "name": "super01" }
    }
  ]
}
```

#### POST /api/v1/admin/prompts

Create new prompt (version 1).

**Request**:

```json
{
  "prompt_key": "greet_v3",
  "name": "인사말 v3",
  "type": "greeting",
  "template": "안녕하세요 {{customer_name}}님, PlusTok입니다."
}
```

**Roles**: super, admin  
**Audit**: `prompt.create`

#### PATCH /api/v1/admin/prompts/{id}

- Save new version OR activate OR update metadata
- `prompt_key` **immutable** after create

**Activate request**:

```json
{ "action": "activate" }
```

**Audit**: `prompt.activate`

#### DELETE /api/v1/admin/prompts/{id}

**Roles**: **super only**  
**Constraint**: cannot delete `is_active=true` production prompt  
**Audit**: `prompt.delete`

---

### 3.4 A-08 GET /api/v1/admin/failover-logs

**Query**:

| Param | Type |
|-------|------|
| page, limit | pagination |
| period_start, period_end | datetime |
| provider | openai, anthropic |
| room_id | uuid |

**Response**:

```json
{
  "data": [
    {
      "id": "failover-uuid-1",
      "created_at": "2026-07-21T14:32:01+09:00",
      "chat_room_id": "550e8400-e29b-41d4-a716-446655440000",
      "from_provider": "openai",
      "from_model": "gpt-4o",
      "to_provider": "anthropic",
      "to_model": "claude-3-5-sonnet",
      "reason": "timeout",
      "latency_ms": 31200,
      "error_message": "OpenAI API timeout after 30000ms",
      "ai_log_id": "log-uuid-1"
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 14 },
  "summary": {
    "last_24h_count": 14,
    "primary_failure_rate_percent": 2.3
  }
}
```

**GET /api/v1/admin/failover-logs/{id}** — single row detail (expand row)

---

### 3.5 A-09 /api/v1/admin/agents

#### GET /api/v1/admin/agents

**Response**:

```json
{
  "data": [
    {
      "id": "A001",
      "display_name": "김상담",
      "email": "kim@plustok.kr",
      "role": "agent",
      "status": "active",
      "max_concurrent": 5,
      "active_assignments": 3,
      "stats_today": { "closed": 12, "avg_response_sec": 95 }
    }
  ]
}
```

#### POST /api/v1/admin/agents

Create agent account.

**Roles**: super, admin  
**Audit**: `agent.create`

#### PATCH /api/v1/admin/agents/{id}

Update profile, role, max_concurrent, status.

**Role change to super**: **super only**  
**Audit**: `agent.update`, `agent.role.update` if role changed

#### POST /api/v1/admin/agents/{id}/assignments

Assign room to agent.

**Request**: `{ "chat_room_id": "uuid" }`  
**Errors**: 409 already assigned, 422 at max concurrent  
**Audit**: `assignment.create`

#### DELETE /api/v1/admin/agents/{id}/assignments/{assignment_id}

Release assignment.  
**Audit**: `assignment.release`

---

### 3.6 A-10 GET /api/v1/admin/audit-logs

**Query**: `period_start`, `period_end`, `actor_id`, `action`, `resource`, `page`, `limit`

**Response**:

```json
{
  "data": [
    {
      "id": 1001,
      "created_at": "2026-07-21T14:00:01+09:00",
      "actor": { "id": 1, "name": "admin01", "role": "admin" },
      "action": "prompt.update",
      "resource": "ai_prompts/sys_v3",
      "ip_address": "203.0.113.1",
      "user_agent": "Mozilla/5.0 ..."
    }
  ],
  "meta": { "page": 1, "limit": 50, "total": 500 }
}
```

**Roles**: super, admin (operator **403**)  
**Immutable**: no DELETE endpoint — append-only table

---

## 4. RBAC 매트릭스

### 4.1 Role Definitions

| Role | Code | Admin Console | Primary Use |
|------|------|---------------|-------------|
| Customer | `customer` | ❌ | End user chat |
| Agent | `agent` | ❌ | Agent desk (STEP 5) |
| Operator | `operator` | ✅ read-only | Supervisor monitor |
| Admin | `admin` | ✅ operational | Day-to-day ops |
| Super Admin | `super` | ✅ full | Security, keys, delete |

### 4.2 Endpoint × Method Matrix

| Endpoint | super | admin | operator | agent | customer |
|----------|:-----:|:-----:|:--------:|:-----:|:--------:|
| stats/* GET | ✅ | ✅ | ✅ | ❌ | ❌ |
| consults GET | ✅ | ✅ | ✅ | ❌ | ❌ |
| monitor/* GET | ✅ | ✅ | ✅ | ❌ | ❌ |
| prompts GET | ✅ | ✅ | ❌ | ❌ | ❌ |
| prompts POST/PATCH | ✅ | ✅ | ❌ | ❌ | ❌ |
| prompts DELETE | ✅ | ❌ | ❌ | ❌ | ❌ |
| failover-logs GET | ✅ | ✅ | ✅ | ❌ | ❌ |
| agents GET | ✅ | ✅ | ❌ | ❌ | ❌ |
| agents POST/PATCH | ✅ | ✅ | ❌ | ❌ | ❌ |
| assignments POST/DELETE | ✅ | ✅ | ❌ | ❌ | ❌ |
| audit-logs GET | ✅ | ✅ | ❌ | ❌ | ❌ |
| ai settings (PHP) | ✅ full | ✅ partial | ❌ | ❌ | ❌ |

### 4.3 Sensitive Action Matrix

| Action | super | admin | operator |
|--------|:-----:|:-----:|:--------:|
| View API key plaintext | ✅ | ❌ | ❌ |
| Edit API key | ✅ | ❌ | ❌ |
| Activate production prompt | ✅ | ✅ | ❌ |
| Delete prompt | ✅ | ❌ | ❌ |
| Change agent role to admin/super | ✅ | ❌* | ❌ |
| Enable maintenance mode | ✅ | ❌ | ❌ |
| Export stats CSV | ✅ | ✅ | ❌ |
| View unmasked customer PII | ✅ | ✅ | ❌** |

\* admin may promote to `agent` or `operator` only  
\*\* operator sees masked names only

### 4.4 Data Scope Rules

| Role | Consult list scope | Monitor scope |
|------|-------------------|---------------|
| super | all rooms | all rooms |
| admin | all rooms | all rooms |
| operator | all rooms (read) | all rooms (read) |
| agent | assigned only (via /agent API, not admin) | N/A |

---

## 5. JWT Claims (Admin Routes)

### 5.1 Token Issuance

Admin JWT issued on:
- PHP session login → embedded in page for AJAX (`issue_admin_jwt()`)
- Future: dedicated `/api/v1/admin/auth/token` (STEP 6 optional)

### 5.2 Required Claims

```json
{
  "iss": "plustok-acep",
  "sub": "user-uuid-or-int",
  "aud": "admin",
  "role": "admin",
  "agent_id": "A001",
  "iat": 1721548800,
  "exp": 1721550600
}
```

| Claim | Required | Validation |
|-------|----------|------------|
| aud | yes | must equal `"admin"` for Admin Domain |
| role | yes | super, admin, or operator |
| sub | yes | maps to users/agents table |
| exp | yes | max TTL 15 minutes (AJAX token) |

### 5.3 Middleware Pseudocode

```php
function admin_api_middleware(Request $req): void {
    $jwt = extract_bearer_token($req);
    $claims = JwtVerifier::verify($jwt);

    if ($claims->aud !== 'admin') {
        throw ForbiddenException('ADMIN_FORBIDDEN');
    }

    if (!in_array($claims->role, ['super', 'admin', 'operator'], true)) {
        throw ForbiddenException('ADMIN_FORBIDDEN');
    }

    if ($req->method !== 'GET' && $claims->role === 'operator') {
        throw ForbiddenException('ADMIN_READ_ONLY');
    }

    RequestContext::setUser($claims);
}
```

### 5.4 Agent Domain vs Admin Domain JWT

| aud | Routes | Roles |
|-----|--------|-------|
| `agent` | `/api/v1/agent/*` | agent |
| `customer` | `/api/v1/customer/*` | customer |
| `admin` | `/api/v1/admin/*` | super, admin, operator |

**Cross-audience token rejected** — agent JWT cannot call admin stats.

---

## 6. Audit Logging

### 6.1 audit_logs Table

Reference: [`03_SYSTEM/01_DB설계.md`](../03_SYSTEM/01_DB설계.md)

```sql
CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NOT NULL,
  action VARCHAR(64) NOT NULL,
  resource VARCHAR(255) NOT NULL,
  payload_json JSON NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(512),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at DESC),
  INDEX idx_audit_actor (actor_id),
  INDEX idx_audit_action (action)
);
```

### 6.2 Mandatory Audit Events

| Event | action code | resource pattern | Roles triggering |
|-------|-------------|------------------|------------------|
| API key update | `ai_key.update` | `settings/ai` | super |
| AI config save | `ai_settings.update` | `settings/ai` | super, admin |
| Prompt create | `prompt.create` | `ai_prompts/{key}` | super, admin |
| Prompt version save | `prompt.update` | `ai_prompts/{key}/v{n}` | super, admin |
| Prompt activate | `prompt.activate` | `ai_prompts/{key}/v{n}` | super, admin |
| Prompt delete | `prompt.delete` | `ai_prompts/{id}` | super |
| Agent create | `agent.create` | `agents/{id}` | super, admin |
| Agent role change | `agent.role.update` | `agents/{id}` | super |
| Assignment create | `assignment.create` | `chat_room_assignments/{room}` | super, admin |
| Assignment release | `assignment.release` | `chat_room_assignments/{id}` | super, admin |
| Maintenance mode | `system.maintenance` | `settings/general` | super |
| Admin login fail (optional) | `auth.login_failed` | `admin/login` | — |

### 6.3 Audit Payload Example

```json
{
  "before": { "temperature": 0.7 },
  "after": { "temperature": 0.5 },
  "fields_changed": ["temperature"]
}
```

**Never log**: API key plaintext, customer full PII

### 6.4 Retention

| Setting | Default | Configurable by |
|---------|---------|-----------------|
| audit_logs retention | 365 days | super |
| ai_failover_log retention | 90 days | super |
| activity_log retention | 180 days | super |

---

## 7. Security Policies

### 7.1 Who Can Change Prompts

| Operation | super | admin | Notes |
|-----------|:-----:|:-----:|-------|
| Create draft | ✅ | ✅ | |
| Edit draft | ✅ | ✅ | |
| Activate | ✅ | ✅ | immediate effect on AI service |
| Delete inactive | ✅ | ❌ | |
| Delete active | ❌ | ❌ | must deactivate first |

**Production safeguard**: activate requires confirm modal + audit entry.

### 7.2 Who Can Change API Keys

| Operation | super | admin |
|-----------|:-----:|:-----:|
| View masked | ✅ | ✅ |
| Reveal full | ✅ | ❌ |
| Edit | ✅ | ❌ |
| Test connection | ✅ | ✅ (uses DB stored key) |

Implementation: [`admin/settings/ai.php`](../../admin/settings/ai.php) — admin POST strips key fields server-side.

### 7.3 Who Can Assign Agents

| Operation | super | admin | operator |
|-----------|:-----:|:-----:|:--------:|
| Assign room | ✅ | ✅ | ❌ |
| Release assignment | ✅ | ✅ | ❌ |
| Force release (active chat) | ✅ | △* | ❌ |
| Change max_concurrent | ✅ | ✅ | ❌ |

\* admin force release requires reason field + audit `assignment.force_release`

### 7.4 Additional Security Controls

| Control | Implementation |
|---------|----------------|
| HTTPS only | production TLS 1.2+ |
| Session fixation | regenerate on login |
| CSRF | all PHP POST forms |
| SQL injection | prepared statements |
| XSS | htmlspecialchars on output |
| IDOR | room access validated against role scope |
| IP allowlist (optional) | super config for admin URLs |

---

## 8. Rate Limiting·Error Codes

### 8.1 Rate Limits (Admin Domain)

| Endpoint group | Limit | Window |
|----------------|-------|--------|
| stats/* | 60 req | per user / minute |
| monitor/* | 120 req | per user / minute |
| prompts write | 20 req | per user / minute |
| ai test connection | 5 req | per user / minute |
| export CSV | 10 req | per user / hour |

Response **429**:

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Retry after 60 seconds.",
    "details": { "retry_after_sec": 60 }
  }
}
```

### 8.2 Admin Error Code Catalog

| Code | HTTP | Description |
|------|------|-------------|
| ADMIN_FORBIDDEN | 403 | Role or aud insufficient |
| ADMIN_READ_ONLY | 403 | operator attempted write |
| ADMIN_RESOURCE_NOT_FOUND | 404 | |
| ADMIN_CONFLICT | 409 | duplicate assign, active prompt delete |
| ADMIN_VALIDATION_ERROR | 422 | invalid input |
| PROMPT_KEY_IMMUTABLE | 422 | attempted key change |
| PROMPT_ACTIVE_DELETE | 422 | cannot delete active prompt |
| AGENT_MAX_CONCURRENT | 422 | assignment limit |
| STATS_INVALID_PERIOD | 422 | bad date range |
| RATE_LIMIT_EXCEEDED | 429 | |
| STATS_QUERY_TIMEOUT | 504 | |

---

## 9. OpenAPI Extension

### 9.1 Tag: Admin Stats

```yaml
tags:
  - name: Admin Stats
    description: Dashboard KPI and charts
  - name: Admin Consults
  - name: Admin Monitor
  - name: Admin Prompts
  - name: Admin Agents
  - name: Admin Audit
```

### 9.2 Security Scheme

```yaml
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: aud must be "admin"
```

### 9.3 Sample Path Registration

Append to [`03_SYSTEM/02_API설계.md`](../03_SYSTEM/02_API설계.md) OpenAPI appendix:

```yaml
/api/v1/admin/prompts:
  get:
    tags: [Admin Prompts]
    security: [{ bearerAuth: [] }]
    summary: List prompts
  post:
    tags: [Admin Prompts]
    security: [{ bearerAuth: [] }]
    summary: Create prompt
```

---

## 10. 테스트 매트릭스

### 10.1 Auth Tests

| ID | Test | Expected |
|----|------|----------|
| API-AUTH-01 | No token | 401 |
| API-AUTH-02 | Customer aud token | 403 ADMIN_FORBIDDEN |
| API-AUTH-03 | Agent aud token on stats | 403 |
| API-AUTH-04 | Expired admin token | 401 |
| API-AUTH-05 | operator POST prompts | 403 ADMIN_READ_ONLY |

### 10.2 Prompt Tests

| ID | Test | Expected |
|----|------|----------|
| API-PRM-01 | admin create prompt | 201 + audit |
| API-PRM-02 | admin delete prompt | 403 |
| API-PRM-03 | super delete inactive | 204 + audit |
| API-PRM-04 | super delete active | 422 PROMPT_ACTIVE_DELETE |
| API-PRM-05 | activate prompt | previous deactivated |

### 10.3 Agent Assignment Tests

| ID | Test | Expected |
|----|------|----------|
| API-ASG-01 | assign available room | 201 |
| API-ASG-02 | double assign same room | 409 |
| API-ASG-03 | assign at max concurrent | 422 |
| API-ASG-04 | operator assign | 403 |

### 10.4 Audit Tests

| ID | Test | Expected |
|----|------|----------|
| API-AUD-01 | prompt activate | audit_logs row |
| API-AUD-02 | operator GET audit-logs | 403 |
| API-AUD-03 | audit payload no API key | grep key in payload fails |

---

## 11. 부록

### 11.1 Endpoint Count Summary

| Domain | Endpoints | STEP |
|--------|-----------|------|
| Customer | ~10 | 4 |
| Agent | ~10 | 5 |
| AI Shared | ~10 | 3-4 |
| **Admin** | **10** | **6** |
| **Total** | **~40** | |

### 11.2 Related Documents

- [02_Admin_Dashboard_구현명세.md](./02_Admin_Dashboard_구현명세.md)
- [03_Admin_모듈_구현명세.md](./03_Admin_모듈_구현명세.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)
- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md)

### 11.3 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 6 Admin Domain 10 endpoints, RBAC, audit |

---

*End of document*
