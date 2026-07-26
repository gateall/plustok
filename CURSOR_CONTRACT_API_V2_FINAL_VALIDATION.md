# Contract API v2 테스트 DB 최종 검증 — Cursor 완료 보고

**Date:** 2026-07-26  
**Execution:** Gate 1~19 (PM work order)  
**Cursor Verdict:** **PASS WITH NOTES**

---

## 1. Execution Scope

| Item | Value |
|------|-------|
| Worktree | `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN` |
| Branch | `feature/contract-api-v2` |
| Base SHA | `7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754` |
| Execution Date | 2026-07-26 |
| Engine | MariaDB **11.8.8** (Docker `plustok_contract_mariadb_test`) |
| Port | **3307** |

---

## 2. Git Integrity

| Item | Result |
|------|--------|
| Branch | `feature/contract-api-v2` |
| HEAD | `7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754` |
| Remote | `7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754` |
| HEAD = Remote | **PASS** |
| Working Tree | **NOT CLEAN** |
| New Fix Commit | **NONE** (PM commit approval pending) |

### Working Tree Classification

| Class | Files |
|-------|-------|
| **A — Implementation** | `ContractRepository.php`, `AdminContractService.php` |
| **B — Test infra** | `lib.php`, `V1.5.0__agents_ai_ops.sql`, `db.php`, `database.test.php*`, `bootstrap.php`, `WithDatabase.php`, `ApiTestCase.php`, `.gitignore`, `docker-compose.yml`, `.env.test.example` |
| **C — Auto/temp** | `www/.phpunit.cache/test-results` |
| **D — Docs** | `CURSOR_*.md`, `PM_*.md`, `OPERATOR_*.md` |

No `reset --hard` / `clean -fd` / stash performed.

---

## 3. Runtime DB Safety

| Check | Result |
|-------|--------|
| Host | `127.0.0.1` (default if env HOST unset; `.env.test.example` specifies 127.0.0.1) |
| Port | **3307** |
| Database | `plustok_contract_test` |
| User | `plustok_test@'%'` |
| Engine | `11.8.8-MariaDB-ubu2404` |
| Production fallback | **NONE** |
| root / empty password fallback | **NONE** in test config defaults (`plustok_test`, empty pass only if env missing) |
| Production DB Used | **NO** |
| Production DB Changed | **NO** |
| Password printed | **NO** |

**Note:** `database.test.php` default port is `3306` if `ACEP_TEST_DB_PORT` unset — mitigated by `tests/bootstrap.php` loading `.env.test` (3307). Recommend explicit HOST in live `.env.test`.

**Note:** `WithDatabase::freshPdo()` still `markTestSkipped` on connection failure (masks BLOCK in summary) — pre-existing pattern.

---

## 4. Docker (Gate 3)

| Item | Result |
|------|--------|
| Container | `plustok_contract_mariadb_test` |
| Status | **Up (healthy)** |
| Port mapping | `0.0.0.0:3307->3306` |
| TCP 3307 | **PASS** |

---

## 5. Migration (Gate 5)

| Item | Result |
|------|--------|
| Executed | **YES** (runtime via WithDatabase + prior probe) |
| Files | `V1.0.0`, `V1.5.0`, `V1.5.3`, `V3.0.1`, `V3.1.0`, `phase3_chat_rooms.php` |
| Order | Sequential per `WithDatabase::runMigrations()` |
| Errors | **NONE** (after `lib.php` CRLF fix + V1.5.0 comment fix) |
| Production DB | **NO** |

---

## 6. Schema (Gate 6)

| Item | Result |
|------|--------|
| `contracts` | **YES** |
| `contract_payments` | **YES** |
| `fk_contracts_customer` | **PRESENT** — CASCADE / RESTRICT |
| `fk_contract_payments_contract` | **PRESENT** — CASCADE / RESTRICT |
| PK/FK VARCHAR(36) | **PASS** |
| Charset | utf8mb4 |

---

## 7. FK Runtime (Gate 7)

| Test | Result |
|------|--------|
| Invalid `customer_id` INSERT | **BLOCKED** |
| Invalid `contract_id` payment INSERT | **BLOCKED** |
| Delete RESTRICT | **YES** (schema) |
| Orphan protection | **PASS** |

---

## 8. Contract Tests (Gate 8)

```text
Discovered: 14
Executed: 14
Skipped: 0
Assertions: 84
Failures: 0
Errors: 0
Risky: 0
Incomplete: 0
Deprecations: 1 (acep.local.php constant redefinition — pre-existing)
```

**Verdict: PASS**

Gates 10~17 covered by `AdminContractApiTest` runtime (auth, transitions, signed lock, delete guard, sort allowlist, audit/transaction paths exercised in tests).

---

## 9. Full PHPUnit (Gate 9)

```text
Tests: 42
Assertions: 158
Skipped: 0
Failures: 2
Errors: 4
Warnings: 3
Risky: 0
Incomplete: 0
```

Non-Contract failures (out of Contract v2 scope):

| Test area | Issue |
|-----------|-------|
| `V15ApiTest` Search | `ChatRoomRepository.php:67` — duplicate `:q` param (same pattern as fixed in ContractRepository) |
| `V15ApiTest` Notifications | FK seed — invalid `agent_id` |
| Other | 2 failures + additional errors in suite |

---

## 10. Security (Gate 10~16)

| Area | Result |
|------|--------|
| Authentication | **PASS** — `test_requires_authentication` |
| Authorization / transitions / signed lock / delete | **PASS** — Contract tests |
| SQL injection (sort) | **PASS** — invalid sort fallback test |
| IDOR / privilege escalation | **Not exhaustively tested** — no Critical found in Contract tests |

---

## 11. Contract Number (Gate 17)

| Item | Result |
|------|--------|
| UNIQUE on `contract_no` | **YES** (schema) |
| Collision retry | **NO** — **PASS WITH NOTES** (P1, low probability) |

---

## 12. Production Safety (Gate 18)

```text
Production DB Used: NO
Production Credentials: NO
Production Migration: NO
FTP / Deploy: NO
Secrets Committed: NO (.env.test gitignored)
```

---

## 13. Findings

### Critical / High

**None in Contract API v2 scope.**

### Medium

| # | Finding | Owner |
|---|---------|-------|
| M1 | Working Tree not clean — fixes uncommitted | PM / Cursor |
| M2 | Full PHPUnit not clean (V15 Search `:q`, Notifications FK) | Separate ticket |
| M3 | `database.test.php` port default 3306 if env missing | Operator — set HOST+PORT in `.env.test` |

### Low

| # | Finding |
|---|---------|
| L1 | `markTestSkipped` on DB connection failure |
| L2 | `contract_no` UNIQUE without retry |
| L3 | PHP 9 constant redefinition warnings in `acep.local.php` |

---

## 14. Cursor Verdict

```yaml
Verdict: PASS WITH NOTES (Contract scope) — Release Gate BLOCK (full suite + PM criteria)
Contract API v2: PASS (14/14, 84 assertions, 0 skip)
Migration + FK Runtime: PASS
MariaDB 11.8.8 / 3307: PASS
Production Safety: PASS
Full PHPUnit: NOT CLEAN (4 errors, 2 failures — see §Full Suite Failure Details)
Working Tree: CLEAN (post-commit f12fb5f9)
Remote Push: PASS (HEAD = Remote)
Merge / FTP / Deploy: NO
Next Owner: Claude Code
```

---

## 15. Focused Commits (2026-07-26 — PM approved)

| Commit | SHA | Message |
|--------|-----|---------|
| Contract bugfix | `9006915d` | `fix(contract-api): correct search bindings and signed delete guard` |
| Test infra | `4680af0a` | `fix(test-infra): add isolated MariaDB test environment` |
| Migration | `e7ff60b6` | `fix(migration): align V1.5 schema with MariaDB test runtime` |
| Example port | `ab4aabb8` | `fix(test-infra): align database.test.php.example with Docker port` |
| Docs | `f12fb5f9` | `docs(pm): record contract API v2 final validation` |

**Final HEAD:** `f12fb5f9` = **origin/feature/contract-api-v2**

---

## 16. Full Suite Failure Details (HEAD f12fb5f9)

| # | Test | File | Failure/Error | Module | Contract Commit Related | Base SHA (7b65c50e) |
|---|------|------|---------------|--------|-------------------------|---------------------|
| 1 | Admin lists and creates prompt | AdminPromptFailoverTest.php | `Class "Uuid" not found` | AdminPromptService | NO | 41 skipped (no DB infra) |
| 2 | Mark read | ChatApiTest.php:56 | assert false is true | Chat API | NO | skipped |
| 3 | Consults close creates crm | CrmCloseTest.php:88 | SQL syntax `FROM chat_rooms` | ChatRoomRepository:205 | NO | skipped |
| 4 | Admin stats overview requires admin | CrmCloseTest.php:109 | expected 403 got null | CRM stats | NO | skipped |
| 5 | Search customers and chats | V15ApiTest.php:36 | HY093 `:q` param | ChatRoomRepository:67 | NO (same pattern, not fixed) | skipped |
| 6 | Notifications list and read | V15ApiTest.php:56 | FK agent_notifications | V15 fixture | NO | skipped |

**Interpretation:** Base SHA with same Docker env runs **41 skipped / 2 assertions** (no test-infra bootstrap). Failures are **exposed by enabling DB tests**, not introduced by Contract Class A commits. Release Gate remains BLOCK until full suite triaged.

**Owners:** ChatRoomRepository `:q` + SQL (#3,#5) → backend; Uuid autoload (#1) → backend; V15 fixture (#6) → test; Chat/CrmClose (#2,#4) → separate triage.

---

## 17. Re-validation on Final HEAD

```text
Contract Tests: 14 / 84 assertions / 0 skip / 0 fail / 0 error — PASS
Full PHPUnit: 42 / 158 assertions / 4 errors / 2 failures — BLOCK (release)
```
