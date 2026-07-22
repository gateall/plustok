# API — REST API 명세 (V1.0)

- **Base URL:** `https://plustok.mycafe24.com/api/v1/` (추후 `https://crm.smarttoktok.com/api/v1/`)
- **형식:** 요청/응답 모두 JSON (`Content-Type: application/json`)
- **HTTPS 전용.** HTTP 요청은 거부.
- **버전 고정:** 경로에 `/v1/` 유지. 파괴적 변경은 `/v2/`로 분기.

---

## 인증

| 대상 | 방식 |
|---|---|
| 상담 접수/업로드 (사이트 → 서버) | **사이트별 API Key** (헤더 `X-API-KEY`) |
| 관리자 CRM | 세션 로그인 (`password_hash()`), API 아님 |
| (V1.5+) 매니저용 모바일/외부 | JWT Access/Refresh — **V1.0 미구현** |

### 브라우저에서의 API Key 취급 (중요)
클라이언트 JS에 API Key(시크릿)를 노출하면 안 된다. 두 가지 중 하나로 구현한다.

1. **(권장) 서버 프록시:** `embed/form.php`가 서버측에서 `site_code`에 맞는 키로 접수 API를 대신 호출.
   브라우저는 `site_code` + 폼 데이터만 `form.php`(같은 origin)로 보낸다.
2. **오리진 검증:** consult 엔드포인트가 `Origin`/`Referer`를 `sites.domain`과 대조하고,
   사이트별 도메인에서만 CORS 허용. (키를 공개키처럼 쓰되 도메인 잠금)

V1.0 기본은 **1번(서버 프록시)** 을 사용한다.

---

## 공통 응답 형식

성공:
```json
{ "result": "success", "data": { ... }, "message": "..." }
```
실패:
```json
{ "result": "error", "code": "INVALID_PARAM", "message": "휴대폰 번호를 확인해주세요." }
```

| HTTP | 의미 |
|---|---|
| 200 | 정상 |
| 400 | 필수값 누락/형식 오류 (`INVALID_PARAM`) |
| 401 | API Key 없음/불일치 (`UNAUTHORIZED`) |
| 403 | 사이트 비활성/권한 없음 (`FORBIDDEN`) |
| 413 | 업로드 용량 초과 |
| 429 | 요청 횟수 초과 (`RATE_LIMIT`) |
| 500 | 서버 오류 |

---

## 1. POST /consult.php — 상담 접수

**Headers:** `X-API-KEY: <site api_key>` (프록시 경유 시 서버가 부착)

**Request**
```json
{
  "site_code": "lg15441644",
  "category": "기업인터넷",
  "product": "기업인터넷",
  "customer_name": "홍길동",
  "phone": "01012345678",
  "company": "ABC회사",
  "email": "hong@abc.com",
  "zipcode": "12345",
  "region": "서울",
  "address": "서울시 강남구 ...",
  "preferred_time": "이번주",
  "memo": "070전화도 함께 문의",
  "detail": { "internet_speed": "1G", "line_count": 3, "rep_number": true },
  "referer": "google",
  "device": "mobile",
  "agree": true
}
```

**필수:** `site_code`, `customer_name`, `phone`, `agree=true`
**서버 처리:** [`SPEC.md`] E. 상담 접수 처리 규칙 순서대로.

**Response (200)**
```json
{
  "result": "success",
  "data": { "consult_no": "C202607170001" },
  "message": "상담 접수가 완료되었습니다."
}
```

**주의**
- `phone`은 서버에서 숫자만 남겨 저장. 중복 판단은 `phone` 기준.
- `detail`은 `consults.detail_json`에 그대로 저장(서버에서 화이트리스트 검증).
- `agree != true` → 400.

---

## 2. POST /upload.php — 파일 첨부

**Headers:** `X-API-KEY`
**형식:** `multipart/form-data`
**필드:** `site_code`, `file`, `file_type`(사업자등록증/사진/견적서/도면/PDF), `consult_no`(선택)

**제한 (STYLEGUIDE 준수)**
- 허용 확장자: `jpg jpeg png gif pdf hwp hwpx`
- 최대 용량: 10MB
- 저장 경로: `uploads/consult/YYYY/MM/{uuid}.ext` (원본명은 DB에만 보관)
- **업로드 폴더 PHP 실행 금지**(.htaccess)

**Response**
```json
{ "result": "success", "data": { "attachment_id": 45, "saved_path": "uploads/consult/2026/07/ab12.pdf" } }
```

---

## 3. GET /health.php — 작동 확인

**Request:** 인증 없음
**Response**
```json
{ "result": "success", "data": { "status": "ok", "time": "2026-07-17T14:20:00+09:00", "db": true } }
```
- DB 커넥션 여부(`db`)를 포함한다. 배포 직후 점검용.

---

## 4. (V1.5+ 예약) 확장 엔드포인트 — V1.0 미구현

| 메서드 | 경로 | 용도 |
|---|---|---|
| GET | `/customer` | 매니저용 고객 조회 |
| POST | `/customer` | 고객 등록 |
| PUT | `/consult` | 상담 내용 수정 |
| PATCH | `/consult/status` | 상태 변경 |
| POST | `/login` | 로그인(JWT 발급) |
| POST | `/refresh` | 토큰 갱신 |
| POST | `/contract` | 계약 접수 (V2.0) |

> 위 경로는 문서에만 예약. 구현은 해당 버전 TASK에서 지시한다.

---

## 보안 요약 (상세 STYLEGUIDE)
- 모든 쿼리는 **PDO Prepared Statement**.
- 입력값 화이트리스트 검증(SQL Injection / XSS 방어), 관리자 폼은 CSRF 토큰.
- API 요청 횟수 제한(Rate Limit): IP+site 기준(예: 분당 30건).
- 에러 메시지에 내부 정보(쿼리·경로·스택) 노출 금지. 상세는 `logs/`에만 기록.
