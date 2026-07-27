# DEEPSEEK — CONTRACT API PRODUCTION DEPLOY + MIGRATION

**Priority:** P0  
**Owner:** DeepSeek  
**From:** Cursor (Contract List API Fix — code complete)  
**Date:** 2026-07-27  
**Repository:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_MERGE_MAIN\www`  
**Branch:** `merge/main-release`

---

## Context

Cursor fixed the React admin contracts list failure. Root cause on production:

| Item | Detail |
|------|--------|
| Symptom | `GET /api/v1/admin/contracts` → HTTP **500** `"서버 오류가 발생했습니다."` |
| Unauthenticated probe | HTTP **401** (route exists, not 404) |
| Root cause | `contracts` / `contract_payments` tables absent — **V3.1.0 migration not applied** |
| Cursor fix | `ContractRepository::contractsTableReady()` guard → empty list instead of 500 when tables missing |

**Code fix is PASS locally.** Production remains **BLOCK** until migration + targeted FTP deploy.

---

## Your Objectives

1. **Verify production DB state** (read-only)
2. **Apply migration** `migrations/V3.1.0__contracts.sql` on Cafe24 production DB
3. **Deploy backend file** after PM approval: `includes/repositories/ContractRepository.php`
4. **Post-deploy smoke** — authenticated `GET /api/v1/admin/contracts`
5. **Write report:** `CRM_Enterprise_PM/17_RELEASES/Release_3.4/CONTRACT_API_BACKEND_REPORT.md`

---

## Phase 1 — DB Investigation (Read-Only)

Run on production (read-only only):

```sql
SHOW TABLES LIKE '%contract%';
DESCRIBE contracts;
SELECT COUNT(*) FROM contracts;
```

Expected before migration: `contracts` table missing or incomplete schema.

**Forbidden:** DROP, TRUNCATE, DELETE, UPDATE, ALTER without approved migration script.

---

## Phase 2 — Migration

**File:** `www/migrations/V3.1.0__contracts.sql`

Creates:

- `contracts` — main ledger (UUID PK, status enum, soft delete `deleted_at`)
- `contract_payments` — payment/refund ledger

Apply using project migration runner or approved Cafe24 procedure. Document exact execution timestamp and result.

After migration, verify:

```sql
SHOW TABLES LIKE 'contract%';
DESCRIBE contracts;
DESCRIBE contract_payments;
SELECT COUNT(*) FROM contracts;
```

---

## Phase 3 — Backend Deploy (Operator Coordination)

Upload **only** (unless PM expands scope):

| File | Reason |
|------|--------|
| `includes/repositories/ContractRepository.php` | Schema guard + safe empty list |

**Do not** upload full `/www` unless PM explicitly approves.

Existing contract API stack should already be on production (401 confirms route):

- `api/v1/router.php` — `GET /admin/contracts`
- `includes/services/AdminContractService.php`
- `includes/controllers/AdminContractController.php` (if separate)

If 404 after migration, verify these files exist on server.

---

## Phase 4 — Authenticated Smoke Test

1. Admin login → obtain JWT
2. `GET /api/v1/admin/contracts?page=1&limit=20`
3. Expect **200** with:

```json
{
  "success": true,
  "data": {
    "items": [],
    "total": 0,
    "page": 1,
    "limit": 20
  }
}
```

4. Create test contract via `POST /admin/contracts` (staging or approved test customer) — optional
5. Confirm React page `/frontend/#/admin/contracts` loads list or empty state (not error)

---

## Phase 5 — Report Format

Write `CONTRACT_API_BACKEND_REPORT.md` with:

- Pre-migration DB evidence
- Migration execution log (no credentials)
- Post-migration table verification
- Authenticated API smoke (status + body summary)
- Deployed files list
- Verdict: PASS / BLOCK

---

## References

| Doc | Path |
|-----|------|
| Cursor work order | `16_AI_ASSIGNMENT/CURSOR_CONTRACT_LIST_API_FIX_WORK.md` |
| Cursor report | `17_RELEASES/Release_3.4/CONTRACT_LIST_API_FIX_REPORT.md` |
| Migration | `www/migrations/V3.1.0__contracts.sql` |
| PHPUnit | `tests/Feature/AdminContractApiTest.php` (14 tests PASS locally) |

---

## Handoff Chain

```text
Cursor (code fix PASS) → DeepSeek (migration + smoke) → Operator (FTP) → AntiGravity (responsive QA) → ChatGPT PM (RELEASE verdict)
```

**FTP:** HOLD until PM approves file list above + frontend `dist/` bundle.
