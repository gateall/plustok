# CURSOR — REACT CONTRACT LIST API ERROR FIX (WORK ORDER)

**Priority:** P0 — Production Admin Functional Blocker  
**Owner:** Cursor  
**Status:** COMPLETE (code fix — FTP HOLD)  
**Date:** 2026-07-27  
**Repository:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_MERGE_MAIN\www`  
**Branch:** `merge/main-release`

---

## Target

| Item | Value |
|------|-------|
| React page | `/frontend/#/admin/contracts` |
| API | `GET /api/v1/admin/contracts` |
| Symptom | "목록을 불러오지 못했습니다" / "서버 오류가 발생했습니다" |

---

## Phase 1 — Evidence

### Production probe (unauthenticated)

```http
GET https://plustok.mycafe24.com/api/v1/admin/contracts?page=1&limit=20
HTTP/1.1 401
Content-Type: application/json

{"success":false,"data":null,"error":{"code":"UNAUTHORIZED","message":"인증 토큰이 필요합니다."},"timestamp":"2026-07-27T19:54:29+09:00"}
```

**Interpretation:** Route is registered on production (not 404). Auth gate works. Authenticated calls fail with generic 500 (`MSG_SEND_FAILED` → "서버 오류가 발생했습니다." per `api/v1/index.php`).

### Health check

```http
GET https://plustok.mycafe24.com/api/v1/health.php
→ {"result":"success","data":{"status":"ok","db":true}}
```

### Frontend call chain

| Layer | File | Finding |
|-------|------|---------|
| Page | `frontend/src/pages/AdminContractsPage.tsx` | Uses `useContracts(filters)` |
| Hook | `frontend/src/hooks/useContracts.ts` | React Query → `contractService.list` |
| Service | `frontend/src/services/contract.service.ts` | `GET /admin/contracts` + query builder strips `payment_status` |
| Client | `frontend/src/services/api.client.ts` | Bearer token from `acep_access_token`; envelope `{ success, data, error }` |

Query params sent on default load: `page=1&limit=20&sort=contracted_at&order=desc` — valid per backend whitelist.

### Backend route

`api/v1/router.php` → `acep_route_contracts()` registers static `GET /admin/contracts` **before** dynamic `/{id}` routes. JwtMiddleware roles: `admin`, `operator`.

### PHPUnit (local, PHP 8.5.8)

```
Tests: 14, Assertions: 84 — OK (with 4 deprecations)
```

All contract API tests pass when `contracts` / `contract_payments` tables exist.

---

## Phase 2 — Root Cause

| Classification | Detail |
|----------------|--------|
| **Category** | Backend + DB readiness |
| **Exact cause** | `ContractRepository::paginateForAdmin()` executes SQL against `contracts` / `contract_payments` without checking table existence. Production DB likely has **V3.1.0 migration not applied** → PDO exception → `index.php` catch → HTTP 500 "서버 오류가 발생했습니다." |
| **Contrast** | `CustomerRepository::paginateForAdmin()` already returns `{ items: [], total: 0 }` when ACEP tables are absent — contracts path lacked this guard |
| **Frontend gap** | Generic error UI; empty-state copy did not match spec |

---

## Phase 3 — Fix (minimal diff)

### Backend

`includes/repositories/ContractRepository.php`

- Require `migrations/lib.php`
- Add `contractsTableReady()` — checks `contracts` table + `deleted_at` column
- Return empty paginated result when tables missing (list)
- Return `null` from `findByIdForAdmin` when tables missing
- Skip `contract_payments` aggregation when that table is missing

### Frontend

| File | Change |
|------|--------|
| `frontend/src/services/api.client.ts` | Export `ApiFetchError` with HTTP status + error code |
| `frontend/src/pages/AdminContractsPage.tsx` | Status-aware error UI (401/403/404/422/500); empty state → "등록된 계약이 없습니다" |

---

## Phase 4 — Verification

| Check | Result |
|-------|--------|
| `php -l ContractRepository.php` | PASS |
| `phpunit tests/Feature/AdminContractApiTest.php` | PASS (14 tests, 84 assertions) |
| `npm run build` (frontend) | PASS |
| Route regression (code review) | `/admin/contracts`, `/new`, `/:id` unchanged in `App.tsx` |

---

## Deployment (Operator — after PM approval)

### Backend FTP

| Local | Server |
|-------|--------|
| `www/includes/repositories/ContractRepository.php` | `/www/includes/repositories/ContractRepository.php` |

### Frontend FTP (after PM approves dist)

Rebuild required: `npm run build` in `www/frontend/`  
Upload `frontend/dist/` artifacts only (not full `/www`).

### DB (Operator/DBA — separate from code fix)

Apply migration when ready:

```
www/migrations/V3.1.0__contracts.sql
```

**No production DB changes were made during this fix.**

---

## DeepSeek Handoff

**Required:** YES — for production migration execution and post-deploy authenticated smoke on Cafe24.

Cursor code fix prevents 500 when tables are missing (shows empty list). Full contract CRUD requires V3.1.0 tables on production.

---

## Deliverables

- This work order: `CRM_Enterprise_PM/16_AI_ASSIGNMENT/CURSOR_CONTRACT_LIST_API_FIX_WORK.md`
- Completion report: `CRM_Enterprise_PM/17_RELEASES/Release_3.4/CONTRACT_LIST_API_FIX_REPORT.md`
