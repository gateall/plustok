# CONTRACT API V2 — TEST DB UNBLOCK 작업지시서

**Issued:** 2026-07-26  
**Target branch:** `feature/contract-api-v2`  
**Validation SHA:** `7b65c50e0cbcb4447df6a9e9fdcdbaf24b4f8754`  
**Current verdict:** `BLOCK`  
**Owner:** Operator / DBA

---

## 1. 작업 상태

| Item | Status |
|------|--------|
| 블로커 | 테스트 DB 및 테스트 전용 계정 미설정 |
| 운영 DB 사용 | 금지 |
| 운영 DB 변경 | 금지 |
| FTP 업로드 | 금지 |
| Merge | 금지 |

---

## 2. 담당자 — Operator / DBA

다음 작업만 수행합니다.

1. 독립 테스트 DB 생성
2. 테스트 전용 DB 계정 생성
3. 테스트 DB에만 권한 부여
4. 로컬 환경변수 설정
5. 비밀번호 비공개 유지
6. Cursor에 테스트 재실행 요청

---

## 3. 테스트 DB 생성

MariaDB 관리자 계정으로 접속한 뒤 실행합니다.

```sql
CREATE DATABASE IF NOT EXISTS plustok_contract_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

---

## 4. 테스트 전용 계정 생성

아래 비밀번호는 실제 강력한 비밀번호로 교체합니다.

```sql
CREATE USER IF NOT EXISTS 'plustok_test'@'localhost'
IDENTIFIED BY '<TEST_DB_PASSWORD>';
```

이미 계정이 존재하면 비밀번호를 새로 설정합니다.

```sql
ALTER USER 'plustok_test'@'localhost'
IDENTIFIED BY '<TEST_DB_PASSWORD>';
```

---

## 5. 테스트 DB에만 권한 부여

```sql
GRANT ALL PRIVILEGES
ON plustok_contract_test.*
TO 'plustok_test'@'localhost';

FLUSH PRIVILEGES;
```

운영 DB에 대한 권한은 부여하지 않습니다.

**금지 예시:**

```sql
GRANT ALL PRIVILEGES ON *.* TO 'plustok_test'@'localhost';
```

---

## 6. 접속 확인

```bash
mysql -u plustok_test -p plustok_contract_test
```

비밀번호 입력 후:

```sql
SELECT DATABASE();
```

예상 결과: `plustok_contract_test`

---

## 7. 환경변수 설정

**프로젝트 경로:**

```text
E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN\www
```

**필수 환경변수:**

```text
ACEP_TEST_DB_HOST=127.0.0.1
ACEP_TEST_DB_NAME=plustok_contract_test
ACEP_TEST_DB_USER=plustok_test
ACEP_TEST_DB_PASS=<TEST_DB_PASSWORD>
```

**PowerShell (현재 창만):**

```powershell
$env:ACEP_TEST_DB_HOST = "127.0.0.1"
$env:ACEP_TEST_DB_NAME = "plustok_contract_test"
$env:ACEP_TEST_DB_USER = "plustok_test"
$env:ACEP_TEST_DB_PASS = "<TEST_DB_PASSWORD>"
```

**비밀번호 기록 금지 위치:** Git · Markdown 보고서 · 작업지시서 본문(실값) · 채팅 · 커밋 · 스크린샷 · 운영 설정 파일

---

## 8. Cursor 재검증 지시

테스트 DB 연결 완료 후 Cursor 실행:

```powershell
cd "E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡_CONTRACT_CLEAN\www"

& "C:\tools\php85\php.exe" `
  vendor/phpunit/phpunit/phpunit `
  --filter AdminContractApiTest `
  --testdox
```

Contract 테스트 통과 후 전체 테스트:

```powershell
& "C:\tools\php85\php.exe" `
  vendor/phpunit/phpunit/phpunit `
  --testdox
```

---

## 9. Cursor 필수 검증 항목

1. 실제 연결 DB 이름
2. 운영 DB 미사용 확인
3. Migration 실제 실행 결과
4. `fk_contracts_customer` 생성 확인
5. `fk_contract_payments_contract` 생성 확인
6. PK/FK 타입 `VARCHAR(36)` 확인
7. `ON UPDATE CASCADE` 확인
8. `ON DELETE RESTRICT` 확인
9. Contract 테스트 실행 건수
10. Skip 건수
11. Assertion 건수
12. Failure 및 Error 건수
13. 전체 테스트 결과
14. 테스트 후 DB 정리 또는 격리 상태
15. Working Tree 상태
16. 새 수정 커밋 존재 여부

---

## 10. 완료 기준

- [ ] 테스트 DB 접속 성공
- [ ] 운영 DB 사용 없음
- [ ] Migration 실행 성공
- [ ] Contract 테스트 Skip 0
- [ ] Contract 테스트 Failure 0
- [ ] Contract 테스트 Error 0
- [ ] Assertion 1개 이상
- [ ] 전체 테스트 실행 완료
- [ ] 전체 테스트 Critical Failure 0
- [ ] Working Tree clean
- [ ] 테스트 결과 보고서 제출

---

## 11. 후속 검토 순서

```text
Operator/DBA
→ 테스트 DB·전용 계정 생성
→ Cursor Contract 테스트 재실행
→ Cursor 전체 테스트 실행
→ Claude Code 코드·런타임 검토
→ ChatGPT PM 최종 Merge 판정
```

---

## 12. 현재 금지사항

- Production DB로 테스트 금지
- 운영 DB 계정 재사용 금지
- root 빈 비밀번호 사용 금지
- 테스트 비밀번호 Git 저장 금지
- 테스트 Skip 상태에서 PASS 보고 금지
- Claude 검토 전 Merge 금지
- PM 승인 전 FTP 업로드 금지

---

## 13. PM 판정 (2026-07-26)

```yaml
VERDICT: BLOCK

IMPLEMENTATION SOURCE: PRESENT
GIT INTEGRITY: PASS
TEST DATABASE: BLOCKED
CONTRACT TESTS: NOT VALIDATED
FULL TEST SUITE: NOT RUN
PRODUCTION DB CHANGED: NO
FTP UPLOADED: NO
MERGE APPROVED: NO
```

**Cursor 검증 절차:** 정상 수행  
**BLOCK 판정:** 타당 — 구현 코드 문제 아님, 독립 테스트 DB 접속정보 부재가 유일한 P0 블로커

**참조:** `CURSOR_CONTRACT_API_V2_TEST_DB_VALIDATION_REPORT.md`
