# CURSOR — Contract API v2 Test DB Validation Report

**Date:** 2026-07-26 02:00 KST  
**Agent:** Cursor  
**Worktree:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN`  
**Verdict:** **BLOCK** (테스트 DB 접속 불가 — Operator/DBA 조치 필요)

---

## Git Verification

```yaml
Worktree: E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN
Branch: feature/contract-api-v2
HEAD SHA: 7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754
Remote SHA: 7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754
Working Tree: clean (after phpunit cache restore)
FK Fix Commit Present: YES (7b65c50e)
Implementation Commit: bab4e08c
```

---

## Test DB

```yaml
Test DB Name (target): plustok_contract_test
Production DB Used: NO
Connection Result: FAIL
Error Type: SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)
```

**원인:** 로컬 MariaDB는 실행 중이나 `config/database.test.php` 기본값(`root` / 빈 비밀번호)으로 접속 거부. Operator/DBA가 테스트 전용 계정·DB·비밀번호를 제공해야 함.

**설정 경로 (프로젝트 표준):**

| 항목 | 값 |
|------|-----|
| Config | `www/config/database.test.php` |
| Env vars | `ACEP_TEST_DB_HOST`, `ACEP_TEST_DB_NAME`, `ACEP_TEST_DB_USER`, `ACEP_TEST_DB_PASS` |
| PHPUnit bootstrap | `ACEP_TESTING=true` → `includes/db.php` → `database.test.php` |
| WithDatabase | 동일 파일 + 자동 migration 5 files |

**권장 Operator 작업:**

```sql
CREATE DATABASE plustok_contract_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'plustok_test'@'localhost' IDENTIFIED BY '<테스트전용비밀번호>';
GRANT CREATE, ALTER, DROP, INDEX, SELECT, INSERT, UPDATE, DELETE, REFERENCES
  ON plustok_contract_test.* TO 'plustok_test'@'localhost';
FLUSH PRIVILEGES;
```

PowerShell (비밀번호는 채팅·Git에 넣지 않음):

```powershell
$env:ACEP_TEST_DB_HOST = "127.0.0.1"
$env:ACEP_TEST_DB_NAME = "plustok_contract_test"
$env:ACEP_TEST_DB_USER = "plustok_test"
$env:ACEP_TEST_DB_PASS = "<테스트전용비밀번호>"
```

---

## Local PHP Environment (Cursor 조치 — 코드 변경 없음)

| Item | Before | After |
|------|--------|-------|
| CLI PHP | 5.2.12 (PATH) | **8.5.8** (`C:\tools\php85\php.exe`) |
| pdo_mysql | disabled | enabled |
| mbstring | disabled | enabled (PHPUnit 필수) |

**실행 시 PHP 8.5 사용:**

```powershell
& "C:\tools\php85\php.exe" vendor/phpunit/phpunit/phpunit --filter AdminContractApiTest --testdox
```

---

## Migration Pre-check (SQL 파일 — PASS)

`migrations/V3.1.0__contracts.sql` 확인:

```yaml
contracts.customer_id → customers.id: YES (VARCHAR(36))
contract_payments.contract_id → contracts.id: YES (VARCHAR(36))
ON UPDATE CASCADE: YES
ON DELETE RESTRICT: YES
fk_contracts_customer: YES
fk_contract_payments_contract: YES
```

**Migration 실행:** DB 접속 BLOCK으로 미실행.

---

## Contract Tests

```yaml
Discovered: 14
Executed: 14
Passed: 0 (assertions none)
Failed: 0
Errors: 0
Skipped: 14
Assertions: 0
Command: C:\tools\php85\php.exe vendor/phpunit/phpunit/phpunit --filter AdminContractApiTest --testdox
```

**테스트 목록 (14):**

1. test_requires_authentication  
2. test_list_route_matches_before_id_route  
3. test_create_and_get_contract  
4. test_search_and_sort  
5. test_invalid_sort_falls_back_safely  
6. test_update_rejects_invalid_date  
7. test_status_transition_whitelist_blocks_arbitrary_update  
8. test_status_transition_happy_path_to_signed_requires_signer  
9. test_signed_contract_blocks_core_field_edit  
10. test_cancel_requires_reason_and_flags_refund  
11. test_archive_after_cancel  
12. test_delete_allowed_for_draft_without_payments  
13. test_delete_blocked_when_not_draft  
14. test_not_found_returns_404  

`14 skipped / 0 assertions` → **완료 조건 미충족** (WithDatabase::freshPdo → markTestSkipped).

---

## Full Test Suite

```yaml
Status: NOT RUN (Contract DB BLOCK 선행 필요)
```

---

## Static Code Review (2차 — 코드만, PASS WITH NOTES)

### AdminContractService.php

| Check | Result |
|-------|--------|
| ALLOWED_TRANSITIONS | ✅ 화이트리스트 |
| beginTransaction/commit/rollBack | ✅ create/update/status/cancel/archive/delete |
| Signed lock | ✅ signedAt 기반 edit block |
| Delete guard | ✅ payments/signed/draft policy |
| Audit log | ✅ agentAction on mutations |
| generateContractNo | ✅ `CT` + date + random hex (COUNT+1 아님) — **동시성 P1: UNIQUE 충돌 재시도 없음, 낮은 확률** |

### ContractRepository.php

| Check | Result |
|-------|--------|
| prepare() | ✅ |
| SORT_MAP whitelist | ✅ |
| ORDER BY | ✅ mapped column only |
| contract_payments | ✅ |
| deleted_at soft delete | ✅ |
| customerJoinSql | ✅ |

---

## Security (Runtime — PENDING DB)

```yaml
Authentication: PENDING
Role Enforcement: PENDING
Sort Whitelist: CODE REVIEW PASS — runtime PENDING
Signed Lock: CODE REVIEW PASS — runtime PENDING
Payment Delete Guard: CODE REVIEW PASS — runtime PENDING
FK Enforcement: MIGRATION SQL PASS — runtime PENDING
Transaction: CODE REVIEW PASS — runtime PENDING
Audit Log: CODE REVIEW PASS — runtime PENDING
```

---

## Changed Files / Deploy

```yaml
Changed Files: NONE (Clean Worktree)
New Fix Commit: NONE
Remote Push: NONE
Production DB Changed: NO
FTP Uploaded: NO
```

---

## Findings

1. **BLOCK — 테스트 DB credentials 미설정** (1045 Access denied). Operator가 `plustok_contract_test` + `plustok_test` 계정 생성 후 env 설정 필요.
2. **BLOCK — Migration·FK·Assertion runtime 미검증** (DB 없음).
3. **NOTE — PHP PATH가 5.2.12** → PHPUnit/Contract 테스트는 **PHP 8.5**로 실행해야 함.
4. **P1 — contract_no UNIQUE 충돌 재시도 없음** (random 6 hex, 실무 위험 낮음).
5. **NOTE — acep.local.php constant redefinition warnings** (PHP 9 error 예정, 테스트와 무관).

---

## Verdict

```yaml
Verdict: BLOCK
Reason: Test DB connection failed — 14 skipped / 0 assertions
Next Owner: Operator/DBA (test DB + credentials) → Cursor re-run → Claude Code review
```

---

## Re-run Checklist (Operator 완료 후 Cursor)

```powershell
cd "E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN\www"

# 1. env 설정 후
& "C:\tools\php85\php.exe" -r "... TEST_DB_CONNECTION_OK ..."

# 2. Contract tests
& "C:\tools\php85\php.exe" vendor/phpunit/phpunit/phpunit --filter AdminContractApiTest --testdox

# 3. Full suite
& "C:\tools\php85\php.exe" vendor/phpunit/phpunit/phpunit --testdox
```

**PASS 조건:** Skipped 0 · Failures 0 · Assertions ≥ 1 · FK SHOW CREATE 확인.

---

## PM Final Verdict (2026-07-26)

| Item | Result |
|------|--------|
| Git branch · remote SHA | PASS |
| Working Tree | PASS |
| FK fix commit | PASS |
| Migration SQL static review | PASS |
| Test DB connection | **BLOCK** |
| Contract API tests (14) | **NOT VALIDATED** — all skipped |
| Full test suite | NOT RUN |
| Production DB changed | NO |
| FTP deploy | NO |
| Merge | **PROHIBITED** |

```yaml
VERDICT: BLOCK
REASON: Independent test DB credentials not configured
P0 BLOCKER: Test DB + plustok_test account + env vars
IMPLEMENTATION: NOT AT FAULT
NEXT OWNER: Operator/DBA
```

**Operator work order:** `OPERATOR_CONTRACT_API_V2_TEST_DB_UNBLOCK.md`  
**PM canonical assignment:** `CRM_Enterprise_PM/16_AI_ASSIGNMENT/OPERATOR_CONTRACT_API_V2_TEST_DB_EXECUTION.md`

**Pipeline (post-unblock):**

```text
Operator/DBA → Cursor re-run → Claude Code runtime review → ChatGPT PM merge decision
```

**Cursor status:** HOLD — awaiting test DB unblock. `14 skipped / 0 assertions` is not a pass.

**PM HOLD confirmation (2026-07-26):** ACCEPTED — no code changes until Operator/DBA unblock. See `PM_CONTRACT_API_V2_HOLD_CONFIRMATION.md`.

**Cursor execution work order (2026-07-26):** ACCEPTED — Gate 1~20. See `CRM_Enterprise_PM/16_AI_ASSIGNMENT/CURSOR_CONTRACT_API_V2_FINAL_VALIDATION_WORK.md`.

---

## Final Re-validation (2026-07-26 — MariaDB 11.8.8 / Docker 3307)

**Verdict:** **PASS WITH NOTES**

| Item | Result |
|------|--------|
| Docker MariaDB 11.8.8 healthy | PASS |
| DB `plustok_contract_test` | PASS |
| Migration runtime | PASS |
| FK runtime | PASS |
| Contract tests 14/14 | PASS (84 assertions) |
| Full PHPUnit | NOT CLEAN (V15 non-Contract) |
| Working Tree | NOT CLEAN (fixes pending commit) |

**Report:** `CURSOR_CONTRACT_API_V2_FINAL_VALIDATION.md`  
**Next:** Claude Code

---

## Re-validation Attempt (2026-07-26 — Operator unblock notice)

**Operator notice:** `테스트 DB 준비 완료` 수신  
**Re-run Verdict:** **BLOCK**

| Check | Result |
|-------|--------|
| Gate 1 HEAD = Remote | PASS |
| Gate 1 Working Tree | FAIL (uncommitted changes) |
| Gate 2 Env vars | SET via `.env.test` |
| Gate 3 PDO connection | FAIL (3307 refused / 3306 access denied) |
| MySQL version | **5.1.41** — migration incompatible |
| Contract tests | NOT RUN |
| Full suite | NOT RUN |

**Critical:** SSOT requires MariaDB 10.6+; MySQL 5.1.41 lacks `utf8mb4`, `DATETIME(3)`, `JSON`.  
**Report:** `CURSOR_CONTRACT_API_V2_FINAL_VALIDATION.md`
