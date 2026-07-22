# TASK — 현재 작업지시서 (V1.0 스프린트)

구현 에이전트(Codex / Antigravity)용. **순서대로** 진행한다. 각 단계는 완료 기준(DoD)을 만족해야 다음으로 넘어간다.
전제 규칙은 [`PROMPT.md`], 상세 명세는 [`SPEC.md`] · [`API.md`] · [`DB.md`] · [`STYLEGUIDE.md`].

---

## ✅ 현재 상태 (2026-07-17)

- STEP 1~6 **코드 작성 완료**(로컬). 파일은 모두 `www/` 하위에 배치됨.
- `www/`에 **그누보드5(+영카트5) 설치 완료**, DB `plustok` 존재. CRM은 **독립 공존**(같은 DB, 접두사 없는 CRM 테이블, 자체 로그인).
- `includes/db.php`가 그누보드 `data/dbconfig.php`를 자동 사용 → **CRM용 config/database.php 불필요.**
- 남은 일: **서버 배포 → 스키마 임포트 → 문법/동작 검증 → lg15441644.kr 실연동** (아래 "배포 절차").

### 배포 절차 (그누보드 공존 · Cafe24 수동)
1. `www/`의 **CRM 폴더만** FTP 업로드: `admin/ api/ embed/ config/ includes/ assets/ uploads/ logs/`
   (그누보드 파일은 이미 서버에 있으므로 덮어쓰지 않는다. `www/index.php` 등 그누보드 파일 유지)
2. phpMyAdmin(또는 mysql)로 `db/schema.sql` → `db/seed.sql` 임포트 (DB=`plustok`)
   - ⚠️ `db/`는 웹루트(www)에 올리지 않는다. seed.sql엔 API Key가 있음.
3. `https://plustok.mycafe24.com/api/v1/health.php` → `{"db":true}` 확인
4. `https://plustok.mycafe24.com/admin/setup.php` 에서 super 계정 생성 → **setup.php 삭제**
5. `https://plustok.mycafe24.com/admin/` 로그인 → 대시보드 확인
6. `https://plustok.mycafe24.com/embed/demo.php?site=lg15441644` 폼 제출 → 관리자 상담목록 반영 확인
7. lg15441644.kr에 임베드 삽입(STEP 7)

> 아래 STEP 0~3은 원래 계획 기록(참고). 코드는 이미 완료됨 — 실제로는 위 "배포 절차"를 따른다.

---

## STEP 0. 환경 확인 (사람이 먼저)

Cafe24 호스팅 관리자에서 확인:
- [ ] PHP 8.1 이상
- [ ] MySQL/MariaDB 생성 (DB명·사용자·비밀번호 확보)
- [ ] 무료 SSL 적용
- [ ] FTP 접속 가능
- [ ] `.htaccess` 사용 가능
- [ ] PDO / curl 사용 가능
- [ ] 외부 사이트에서 API 요청 수신 가능(CORS/방화벽)

> 확인 결과를 [`CHANGELOG.md`] 또는 이슈에 기록. 미충족 항목 있으면 여기서 중단.

---

## STEP 1. 기반 골격

- [ ] 디렉터리 생성 ([`PROJECT.md`] 구조 그대로)
- [ ] `config/database.sample.php`, `config/app.php`, `config/security.php`
- [ ] `includes/db.php` (PDO 팩토리, 옵션은 STYLEGUIDE 3항)
- [ ] `includes/response.php` (`json_response()`), `functions.php`
- [ ] `config/`,`logs/`,`uploads/`에 접근 차단 `.htaccess`
- **DoD:** `php -l` 통과, `includes/db.php`로 커넥션 성공.

## STEP 2. DB 스키마

- [ ] `/db/schema.sql` 작성 ([`DB.md`] DDL) — sites→products→managers→customers→consults→consult_history→attachments→activity_log
- [ ] `/db/seed.sql` — 8개 사이트 시드 + 초기 관리자 1명(`super`) + 브랜드별 기본 상품
- [ ] 서버 DB에 적용
- **DoD:** 8개 테이블 생성, `sites` 8행, `super` 계정 로그인 가능한 해시 삽입.

## STEP 3. 상담 접수 API

- [ ] `api/v1/health.php` (DB 커넥션 포함 상태 반환)
- [ ] `includes/api_auth.php` (X-API-KEY 검증 → site 확인)
- [ ] `api/v1/consult.php` — [`SPEC.md`] E 처리 규칙: 검증→중복확인→고객등록/연결→상담번호→insert→history→응답
  - 트랜잭션 사용. `phone` 정규화. `detail_json` 화이트리스트.
- [ ] `api/v1/upload.php` — 확장자/용량 검증, 랜덤 저장명, `attachments` 연결
- **DoD:** `health.php` OK. curl로 `consult.php`에 샘플 JSON 전송 시 `consult_no` 반환, DB에 customers+consults+consult_history 생성.

```bash
# 테스트 예시
curl -X POST https://plustok.mycafe24.com/api/v1/consult.php \
 -H "X-API-KEY: <lg15441644 key>" -H "Content-Type: application/json" \
 -d '{"site_code":"lg15441644","customer_name":"홍길동","phone":"010-1234-5678","product":"기업인터넷","agree":true}'
```

## STEP 4. 관리자 CRM — 인증 & 상담

- [ ] `admin/index.php` 로그인(세션, `password_verify`, 실패 제한)
- [ ] `includes/auth.php` (로그인 검사, role 검사, CSRF 토큰)
- [ ] `admin/dashboard.php` 요약 카드 + 사이트별 현황 + 최근 상담
- [ ] `admin/consults/` 목록(필터: 사이트/상품/담당자/상태/기간) + 상세
- [ ] 상세에서 상태 변경(→ `consult_history` 기록) · 담당자 배정 · 메모
- **DoD:** STEP 3에서 접수된 상담이 목록/상세에 보이고, 상태 변경이 이력에 남는다.

## STEP 5. 관리자 CRM — 마스터 관리

- [ ] `admin/customers/` 고객 목록/상세(상담 이력)
- [ ] `admin/sites/` 사이트 등록/수정 + **API Key 발급/재발급**
- [ ] `admin/products/` 브랜드별 상품 관리
- [ ] `admin/users/` 담당자 관리(권한)
- [ ] `admin/stats/` 기본 통계, `admin/settings/` 설정·로그
- **DoD:** 새 사이트를 UI에서 등록하고 API Key를 발급받아 그 키로 접수 API 성공.

## STEP 6. 임베드 상담폼 & 첫 연동

- [ ] `embed/form.php` — `site_code`로 브랜드·상품·질문셋·persona JSON 반환 + 서버 프록시로 consult 호출
- [ ] `embed/embed.js` — `#plustok-form` 렌더, 5단계 흐름, 모바일 최적화([`SPEC.md`] A)
- [ ] **lg15441644.kr에 임베드 삽입 → 실제 접수 → 관리자 목록 확인**
- **DoD:** lg15441644.kr에서 사용자가 폼 제출 → CRM 상담 목록에 표시(README 5장 시나리오).

## STEP 7. 확대

- [ ] SmartTokTok → HompyShop → 나머지 순차 임베드 연동
- [ ] 사이트별 persona/상품 옵션 점검
- **DoD:** 최소 3개 사이트에서 정상 접수.

---

## 완료(Definition of Done) 공통
- `php -l` 무오류, [`STYLEGUIDE.md`] 보안 규칙 준수(PDO prepared, 접근차단, 해시, CSRF).
- 각 STEP 완료 시 [`CHANGELOG.md`]에 한 줄 기록, [`TODO.md`] 체크.
- 개인정보/키를 커밋하지 않았는지 확인(`config/database.php` 제외).

## 하지 말 것 (V1.0 범위 밖)
- OpenAI/AI 요약 로직 구현(자리만 유지) · 계약/정산 · JWT · 카톡/SMS 발송 · 모바일 앱.
  필요해지면 해당 버전 TASK로 별도 지시.
