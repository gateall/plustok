# CONTRACT API V2 — PM HOLD CONFIRMATION

**Date:** 2026-07-26  
**Status:** ACCEPTED — Cursor HOLD confirmed

---

## 1. PM 최종 상태

```text
PM VERDICT: BLOCK
CURSOR STATUS: HOLD
BLOCKER OWNER: Operator / DBA
IMPLEMENTATION DEFECT CONFIRMED: NO
TEST DB READY: NO
MERGE APPROVED: NO
FTP APPROVED: NO
```

---

## 2. PM 접수 판정

| Item | Result |
|------|--------|
| Operator/DBA 작업지시서 저장 | ACCEPTED |
| Cursor 검증 보고서 PM 판정 반영 | ACCEPTED |
| Git 무결성 | PASS |
| 구현 코드 정적 검토 | PASS WITH NOTES |
| 독립 테스트 DB | BLOCK |
| Contract API 테스트 | NOT VALIDATED |
| 전체 PHPUnit | NOT RUN |
| Merge | NOT APPROVED |
| FTP | HOLD |
| Cursor 상태 | HOLD — 정상 |

---

## 3. 현재 유일한 P0 블로커

다음 항목이 아직 준비되지 않았습니다.

- 독립 테스트 DB: `plustok_contract_test`
- 테스트 전용 계정: `plustok_test`
- 테스트 DB 전용 권한
- `ACEP_TEST_DB_*` 환경변수
- 실제 Migration 및 Contract API 런타임 검증

`14 skipped / 0 assertions`는 PASS가 아니며, 테스트 DB 연결 실패로 테스트가 실행되지 않은 상태입니다.

**현재 추가 코드 수정은 지시하지 않습니다.**

---

## 4. Operator/DBA 완료 통보 형식

비밀번호를 포함하지 않고 다음 문구만 전달합니다.

```text
테스트 DB 준비 완료

DB: plustok_contract_test
Dedicated User: plustok_test
Production DB Used: NO
ACEP_TEST_DB_* Configured: YES
Password Shared in Chat: NO
Cursor Re-run Ready: YES
```

---

## 5. 준비 완료 후 Cursor 실행 순서

### Gate 1 — 환경변수 확인

다음 변수의 존재만 확인하며 실제 비밀번호는 출력하지 않습니다.

```text
ACEP_TEST_DB_HOST
ACEP_TEST_DB_NAME
ACEP_TEST_DB_USER
ACEP_TEST_DB_PASS
```

`ACEP_TEST_DB_PASS` 값은 로그와 보고서에 출력하지 않습니다.

### Gate 2 — DB 식별 확인

테스트 실행 전 실제 연결 DB가 다음과 일치하는지 확인합니다.

```text
plustok_contract_test
```

운영 DB 이름이 확인되면 즉시 중단합니다.

### Gate 3 — Migration 실행

다음을 실제 DB에서 검증합니다.

- contracts 관련 테이블 생성
- contract_payments 관련 테이블 생성
- `fk_contracts_customer`
- `fk_contract_payments_contract`
- PK/FK `VARCHAR(36)`
- `ON UPDATE CASCADE`
- `ON DELETE RESTRICT`

### Gate 4 — Contract 테스트

```powershell
cd "E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN\www"

& "C:\tools\php85\php.exe" `
  vendor/phpunit/phpunit/phpunit `
  --filter AdminContractApiTest `
  --testdox
```

필수 완료 기준:

```text
Discovered: 14
Executed: 14
Skipped: 0
Failures: 0
Errors: 0
Assertions: 1 이상
```

### Gate 5 — 전체 PHPUnit

Contract 테스트 통과 후 실행합니다.

```powershell
& "C:\tools\php85\php.exe" `
  vendor/phpunit/phpunit/phpunit `
  --testdox
```

### Gate 6 — 런타임 검증

다음을 실제 DB 트랜잭션으로 검증합니다.

- 허용된 상태 전환 성공
- 금지된 상태 transition 거부
- 상태 변경 audit 기록
- 계약 서명 후 잠금
- 서명된 계약 수정 차단
- 삭제 보호
- FK 제약 동작
- 트랜잭션 실패 시 rollback
- 정렬값 allowlist 적용
- SQL prepared statement 적용
- 운영 DB 미사용

### Gate 7 — Git 상태

테스트 완료 후 확인합니다.

```text
Branch: feature/contract-api-v2
HEAD SHA: 7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754
Working Tree: clean
```

테스트 과정에서 코드 수정이 발생하면 새 커밋 SHA와 수정 이유를 별도로 보고합니다.

---

## 6. Cursor 재보고 필수 형식

```text
Contract API v2 테스트 DB 재검증 — Cursor 완료 보고

Test DB Connection: PASS / FAIL
Actual DB Name:
Production DB Used: NO
Migration Executed: YES / NO

Contract Tests
Discovered:
Executed:
Skipped:
Assertions:
Failures:
Errors:

FK Runtime
fk_contracts_customer:
fk_contract_payments_contract:
ON UPDATE CASCADE:
ON DELETE RESTRICT:
VARCHAR(36) Compatibility:

Runtime Rules
Allowed Transitions:
Invalid Transition Guard:
Transaction Rollback:
Audit Logging:
Signed Contract Lock:
Delete Guard:
Sort Allowlist:
Prepared Statements:

Full Test Suite
Executed:
Skipped:
Assertions:
Failures:
Errors:

Git
Branch:
HEAD SHA:
Remote SHA:
Working Tree:
New Fix Commit:

Production DB Changed: NO
FTP Uploaded: NO
Cursor Verdict: PASS / BLOCK
Next Reviewer: Claude Code
```

---

## 7. Claude Code 검토 착수 조건

다음 조건을 모두 만족해야 Claude Code 검토로 이동합니다.

- [ ] 테스트 DB 연결 성공
- [ ] Migration 실제 실행 성공
- [ ] Contract 테스트 Skip 0
- [ ] Contract 테스트 Failure 0
- [ ] Contract 테스트 Error 0
- [ ] 전체 테스트 실행 완료
- [ ] 런타임 FK 검증 완료
- [ ] 운영 DB 미사용
- [ ] Cursor 재보고서 제출

---

## 8. 최종 Release Chain

```text
Operator / DBA
→ Test DB Unblock

Cursor
→ Contract 14 Tests
→ Full PHPUnit
→ Runtime FK and Transaction Verification

Claude Code
→ Independent Code and Runtime Review
→ PASS / BLOCK Verdict

ChatGPT Chief PM
→ Merge Approval or Rework Order

AntiGravity
→ Integration / Regression QA

ChatGPT Chief PM
→ Production Deployment Approval
```

---

## 9. 현재 금지사항

```text
NO PRODUCTION DB TEST
NO ROOT EMPTY-PASSWORD FALLBACK
NO TEST SKIP ACCEPTANCE
NO MERGE
NO FTP
NO PRODUCTION DEPLOYMENT
NO PASSWORD IN CHAT OR DOCUMENTS
NO CODE CHANGES UNTIL TEST DB UNBLOCK
```

---

## 10. 관련 문서

| Document | Path |
|----------|------|
| Operator work order | `OPERATOR_CONTRACT_API_V2_TEST_DB_UNBLOCK.md` |
| Cursor validation report | `CURSOR_CONTRACT_API_V2_TEST_DB_VALIDATION_REPORT.md` |

**Next valid trigger:** Operator/DBA `테스트 DB 준비 완료` 통보
