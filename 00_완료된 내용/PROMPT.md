# PROMPT — AI 에이전트 상시 규칙

**구현을 시작하기 전에 이 문서를 먼저 읽는다.** 여기 규칙은 다른 문서보다 우선한다.

---

## 0. 역할 분담

- **작업지시서(이 MD들)** = 기획/점검 담당(Claude)이 작성·유지.
- **코드 구현** = 구현 에이전트(Codex / Antigravity)가 담당.
- 구현 후 최종 점검은 지시자가 수행. 임의로 범위를 넓히지 말 것.

## 1. 프로젝트 한 줄 정의

여러 사이트의 상담 신청을 **하나의 Cafe24 서버(`plustok.mycafe24.com`)로 수집**하는 통합 CRM.
각 사이트는 **접수만**, 데이터·관리는 **중앙 하나**.

## 2. 절대 기준 (바꾸지 말 것)

- 서버: `plustok.mycafe24.com` (Cafe24 웹호스팅, **VPS 아님**). 추후 `crm.smarttoktok.com` 연결.
- 스택: **서버 = PHP 8.4 / MariaDB 10.x / UTF-8**(코드는 8.1+ 기준, 8.4 호환). PDO만 사용, 프레임워크·Composer 없음. Cafe24 전용 확장 금지(→ VPS 이식성).
- 시스템명 **PlusTok 통합 CRM** / 외부명 **SmartTokTok CRM**.
- 데이터 모델: **사업부 → 브랜드 → 사이트(도메인, 여러 개 가능)**. `sites`는 도메인 1개 = 1행.
- **V1.0**은 완료·배포됨. 현재는 **V1.5 phase 1**(AI 상담 어시스턴트, [`TASK_V1.5_AI.md`] 참고) 진행 — 범위는 **②상담요약 + ③답변초안 두 기능만**(2026-07-18 승인). ①고객요약·④상품추천·⑤고객등급과 계약/정산/JWT/앱은 아직 **하지 않는다**(자리만 유지).
- **웹 루트 `www/`에 그누보드5(+영카트5)가 이미 설치됨. CRM은 독립 공존.**
  - CRM은 `www/admin` `www/api` `www/embed` `www/config` `www/includes` `www/assets` `www/uploads` `www/logs` 하위에만 둔다. **그누보드 파일(index.php·common.php·adm/·bbs/·shop/·data/·skin/·theme/…)은 절대 수정/삭제하지 않는다.**
  - DB는 그누보드와 **같은 `plustok` DB 공유**. CRM 테이블은 접두사 없음(그누보드 `g5_`/`yc5_`와 충돌 없음).
  - `includes/db.php`는 `config/database.php`가 없으면 그누보드 `data/dbconfig.php` 접속정보를 자동 사용 → **CRM용 config/database.php는 만들 필요 없음.**
  - CRM 관리자 로그인은 그누보드 회원과 **별개**(자체 `managers` 테이블·세션). 그누보드 로그인과 섞지 않는다.

## 3. 보안은 타협 없음 ([`STYLEGUIDE.md`] 준수)

- 모든 쿼리 **PDO Prepared Statement**. 입력값 화이트리스트 검증.
- 비밀번호 `password_hash()`, API Key `random_bytes(32)`+`hash_equals`.
- `config/`,`logs/`,`uploads/` 웹 접근 차단, `uploads/` PHP 실행 금지.
- 관리자 폼 CSRF, HTTPS 전용, Rate Limit, 에러 상세 비노출.
- 개인정보(전화·주소)는 최소 저장·권한 제한, 로그에 원문 금지.

## 4. 작업 순서

[`TASK.md`]의 STEP 0→7(V1.0)은 완료됨. 지금은 [`TASK_V1.5_AI.md`]의 §1→§2→②→③ 순서(STEP 8)를 진행.
새 기능/변경은 먼저 해당 MD(SPEC/API/DB)를 고치고, 그 다음 코드를 짠다. **문서가 소스 오브 트루스.**

## 5. 문서 우선순위

충돌 시: `PROMPT.md` > `SPEC.md`/`API.md`/`DB.md` > `TASK.md` > 나머지.
값(사이트코드·상태코드·엔드포인트)은 문서에 적힌 것을 그대로 쓴다. 임의 변경 금지.

## 6. 하지 말 것

- 문서에 없는 라이브러리/프레임워크 추가.
- V1.0 범위 밖 기능 선구현(AI·계약·SMS·앱·JWT).
- 클라이언트 JS에 API Key(시크릿) 노출 — 서버 프록시 사용([`API.md`] 인증).
- `config/database.php` 등 비밀정보 커밋.
- 개인정보를 URL 쿼리스트링·로그·에러 메시지에 노출.

## 7. 막히면

애매하면 추측하지 말고 **작업지시서에 질문 항목을 남기고 지시자에게 확인**을 요청한다.
Cafe24 제약(함수 비활성, 권한, 용량)에 막히면 우회 대신 제약 내용을 먼저 보고한다.

## 8. 완료 보고

각 STEP 완료 시: 무엇을·어떻게 했는지 + 테스트 결과(curl/화면) + [`CHANGELOG.md`] 한 줄.
성공/실패를 있는 그대로 보고(테스트 실패는 실패로).

## 9. 컨텍스트 파일 (항상 참조)

`README.md` 전체 그림 · `PROJECT.md` 구조 · `SPEC.md` 기능 · `API.md` API · `DB.md` 스키마 ·
`STYLEGUIDE.md` 규칙 · `TASK.md` 지금 할 일 · `TODO.md` 체크 · `CHANGELOG.md` 이력.
