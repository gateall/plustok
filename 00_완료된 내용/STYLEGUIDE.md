# STYLEGUIDE — 코딩 · 보안 · 디자인 규칙

이 문서의 규칙은 **강제**다. 위반 코드는 리뷰에서 반려한다.

---

## 1. 언어/환경

- PHP **8.1+**, `declare(strict_types=1);` 권장.
- 프레임워크·Composer 의존성 없이 동작해야 한다(Cafe24 공유호스팅 기준). 외부 라이브러리는 최소화.
- Cafe24 전용 확장에 종속되지 않게 표준 PHP + PDO + curl만 사용(→ VPS 이전 대비).
- 문자셋 `utf8mb4`, 파일 인코딩 UTF-8(BOM 없음), 개행 LF.
- 시간대: `date_default_timezone_set('Asia/Seoul')`.

## 2. 파일/폴더 구조 규칙

- 진입점은 `api/v1/*.php`, `admin/**/*.php`만. 공통 로직은 `includes/`에 둔다.
- 설정값·비밀정보는 `config/`에만. **`config/`, `logs/`, `uploads/`는 웹에서 직접 접근 차단.**
  각 폴더에 `.htaccess`(`Deny from all` 또는 `Require all denied`) 배치. `uploads/`는 PHP 실행 금지.
- `config/database.php`는 저장소에 커밋하지 않는다(`.gitignore`). 대신 `config/database.sample.php` 제공.

## 3. DB 접근 (필수)

- **모든 쿼리는 PDO Prepared Statement.** 사용자 입력을 문자열로 이어붙인 쿼리 금지.
```php
$stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone');
$stmt->execute([':phone' => $phone]);
```
- PDO 옵션: `ERRMODE_EXCEPTION`, `DEFAULT_FETCH_MODE => FETCH_ASSOC`, `EMULATE_PREPARES => false`.
- 트랜잭션이 필요한 다중 insert(상담 접수: customers+consults+history)는 `beginTransaction/commit/rollBack`.

## 4. 입력 검증 / 보안

- **화이트리스트 검증**: 필드별 타입·길이·허용값을 명시적으로 확인. 예상 외 키는 버린다.
- `phone`은 숫자만 추출(`preg_replace('/\D/','',...)`) 후 저장.
- 출력 시 XSS 방어: HTML 출력은 `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
- 관리자 상태변경/등록 등 변경 요청은 **CSRF 토큰** 검증.
- 비밀번호는 `password_hash($pw, PASSWORD_DEFAULT)` 저장, 검증은 `password_verify`.
- API Key는 `bin2hex(random_bytes(32))`. 비교는 `hash_equals`.
- 파일 업로드: 확장자+MIME 이중 검증, 용량 제한(10MB), 저장명은 랜덤(uuid), 원본명은 DB에만.
- 에러는 사용자에게 일반 메시지, 상세는 `logs/`에만(쿼리/경로/스택 노출 금지).
- Rate Limit: IP+site 기준 카운트(파일/DB 기반 간이 구현 가능).

## 5. 응답/에러 규약

- API 응답은 항상 `{ "result": "success"|"error", ... }` ([`API.md`] 공통 형식).
- HTTP 상태코드를 의미에 맞게 세팅(400/401/403/413/429/500).
- 성공/실패 모두 JSON. `exit`로 흘려보내지 말고 공통 `json_response()` 헬퍼 사용.

## 6. 코드 스타일

- 들여쓰기 4 스페이스. 함수/변수 `snake_case` 또는 프로젝트 내 일관성 유지(혼용 금지).
- 함수는 한 가지 일만. 공통 로직은 `includes/functions.php`로 추출.
- 주석은 **왜(제약/이유)** 를 적는다. 코드가 하는 일을 그대로 옮긴 주석은 쓰지 않는다.
- 매직넘버/문자열(상태코드, 사이트코드 등)은 `config/app.php` 상수로.
- SQL은 대문자 키워드, 컬럼은 소문자.

## 7. 프론트(상담폼) 규칙

- **모바일 우선.** 터치 영역 ≥ 44px, 큰 입력창/버튼.
- 전화: `<input type="tel" inputmode="numeric">`. 이메일: `type="email"`.
- Step UI로 진행 표시. 필수값 미입력 시 다음 단계 진행 차단(클라이언트+서버 이중 검증).
- 색상/폰트는 사이트별 커스터마이즈 가능하되, 기본 테마는 접근성 대비(WCAG AA) 확보.
- 외부 스크립트 의존 최소화(가능하면 Vanilla JS). CDN 사용 시 무결성/대체 고려.
- `embed.js`는 하나의 `<div id="plustok-form">`에만 주입, 전역 오염 없이 IIFE로 감싼다.

## 8. 디자인 톤

- 신뢰감 있는 비즈니스 톤. 브랜드별 첫인사(persona)로 상단 헤드라인 구성.
- 첫 화면은 버튼 선택형(채팅형 장문 지양) — 전환율 우선.
- 완료 화면에 접수번호를 크게 표시.

## 9. 로깅

- 관리자 작업은 `activity_log`에 기록(로그인/상태변경/배정/삭제 등).
- 시스템 오류는 `logs/error-YYYYMMDD.log`. 개인정보(전화/주소 원문)는 로그에 남기지 않는다.

## 10. 배포 (Cafe24 수동)

- FTP 업로드 전 로컬에서 문법 확인(`php -l`).
- `config/database.php`는 서버에만 존재(커밋 금지). 배포 후 `health.php`로 점검.
- DB 스키마 변경은 `/db/schema.sql`에 반영 후 서버 적용, [`CHANGELOG.md`]에 기록.
