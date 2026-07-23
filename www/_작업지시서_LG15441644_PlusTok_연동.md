# 작업지시서 — LG15441644.kr → PlusTok CRM 연동

> **작성일:** 2026-07-23  
> **진단 결과:** LG15441644 상담폼은 **로컬 DB + 이메일만** 처리하며 PlusTok API를 **호출하지 않음**  
> **PlusTok 코드베이스:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_PLUS톡\www\`  
> **LG15441644 코드베이스:** `E:\0000000_AI Enterprise Framework\PROJECTS\000_www.LG15441644\www\`  
> **상태:** 설계·초안 완료 — **배포 전 (DO NOT DEPLOY without review)**

---

## 0. 진단 요약 (Root Cause)

| 항목 | 결과 |
|------|------|
| **근본 원인** | LG15441644 운영 상담폼(`ajax/lg_consult_write.php`)이 `g5_lg_consult` 테이블 INSERT + `lg_consult_send_mail()`만 수행. **PlusTok API 미호출** |
| **사용자 가설** | ✅ **확인됨** (테이블명은 `g5_write_online`이 아닌 `g5_lg_consult` 계열) |
| **PlusTok Dashboard** | `consults` 테이블 전체 COUNT — **필터 버그 아님**. 데이터가 PlusTok DB에 없으면 빈 화면 |
| **PlusTok API 자체** | `POST /api/v1/consult.php` 구현 완료. `embed/demo-chat.php`, `embed/form.php` 경로로는 정상 동작 가능 |
| **유일한 PlusTok 연동** | `content/plustok.php` — embed.js 테스트 페이지. **운영 상담폼과 분리** |

### 데이터 흐름 (현재 vs 목표)

```
[현재 — 운영]
lg15441644.kr/content/consult/*.php
  → POST /ajax/lg_consult_write.php
  → INSERT g5_lg_consult (+ 상품별 서브테이블 5종)
  → lg_consult_send_mail()
  → (끝) — PlusTok DB 접근 없음

[목표]
lg_consult_write.php (로컬 저장 성공 후)
  → POST https://plustok.mycafe24.com/api/v1/consult.php
  → PlusTok: customers upsert + consults INSERT + chat_rooms (선택)
  → admin/dashboard.php / admin/consults/index.php 에 표시
```

---

## 1. Step 1 — 로컬 저장 + 이메일 성공 후 PlusTok API 호출

### 1.1 수정 대상 파일

| 파일 | 역할 |
|------|------|
| `000_www.LG15441644/www/ajax/lg_consult_write.php` | **주 수정 대상** — step 9(메일) 직후 PlusTok 연동 추가 |
| `000_www.LG15441644/www/content/consult/_lg_consult_common.php` | 헬퍼 함수 `lg_consult_sync_plustok()` 추가 권장 |
| (선택) `000_www.LG15441644/www/ajax/consult_write.php` | 구형 통합폼 — 동일 패턴 적용 여부 PM 결정 |

### 1.2 호출 시점

```php
// lg_consult_write.php — step 9 메일 발송 try/catch 이후, step 10 JSON 응답 직전

try {
    lg_consult_send_mail($lc_id);
} catch (Exception $e) { /* 기존: 메일 실패해도 접수 성공 */ }

// ★ NEW: PlusTok 연동 (실패해도 LG 로컬 접수는 성공 유지)
lg_consult_sync_plustok($lc_id);  // 내부에서 큐/재시도/로그

// step 10 JSON 응답 (기존 유지)
lg_consult_json(true, '접수가 완료되었습니다.', ...);
```

### 1.3 API 엔드포인트 (정확한 URL)

| 항목 | 값 |
|------|-----|
| **URL** | `https://plustok.mycafe24.com/api/v1/consult.php` |
| **Method** | `POST` |
| **Content-Type** | `application/json` |
| **인증 헤더** | `X-API-KEY: {sites.api_key}` |

> ⚠️ `/api/v1/consults` (복수형)는 **존재하지 않음**. `router.php`의 `/consults/close`는 상담 **종료** 전용.

### 1.4 인증

- PlusTok `sites` 테이블에서 `site_code = 'lg15441644'` 행의 `api_key` 사용
- `includes/api_auth.php` → `require_site_by_apikey()` 가 헤더 검증
- `status = 1` (또는 `use_yn = 1`) 비활성 사이트는 403

**시드 API Key** (`admin/install.php`):

```
8a9925a7a9c29922e4cbb3e79b774812cf3c20b667056e68f2e2ab32c69765c6
```

> 운영 DB에서 재발급했을 수 있음 → HeidiSQL `SELECT api_key FROM sites WHERE site_code='lg15441644'` 확인 필수.

### 1.5 LG15441644 설정 상수 (신규)

`content/consult/_lg_consult_common.php` 또는 별도 `data/plustok_config.php`:

```php
// LG15441644 서버에만 배치 — Git에 키 커밋 금지
define('PLUSTOK_API_URL',  'https://plustok.mycafe24.com/api/v1/consult.php');
define('PLUSTOK_SITE_CODE','lg15441644');
define('PLUSTOK_API_KEY',  '/* HeidiSQL에서 조회한 현행 api_key */');
define('PLUSTOK_SYNC_ENABLED', true);  // false면 연동 스킵 (롤백 스위치)
```

---

## 2. Step 2 — 전송 필드 매핑

### 2.1 PlusTok API 필수 필드 (`api/v1/consult.php`)

| PlusTok 필드 | 필수 | LG15441644 소스 | 비고 |
|--------------|:----:|-----------------|------|
| `customer_name` | ✅ | `lc_applicant_name` | |
| `phone` | ✅ | `lc_phone` | 숫자만, 9~11자리 (`normalize_phone`) |
| `email` | ✅ | `lc_email` | **LG는 선택 → 빈 값이면 `noreply@lg15441644.kr` 등 fallback 필수** |
| `agree` | ✅ | `lc_privacy == 1` | `true` |
| `company` | | `lc_company_name` | |
| `zipcode` | | `lc_zipcode` | |
| `address` | | `lc_addr1` + `lc_addr2` | 공백 결합 |
| `region` | | `lc_region` | |
| `memo` | | `lc_etc` + 접수번호 | |
| `category` | | `'통신'` | PlusTok products.brand=`LG15441644` 와 일치 |
| `product` | | `lc_main_product` → **한글 라벨** | 아래 매핑표 |
| `referer` | | `$_SERVER['HTTP_REFERER']` | |
| `device` | | User-Agent | `mobile` / `pc` |
| `detail` | | 상품별 서브테이블 JSON | object/array |

### 2.2 상품 코드 → PlusTok product_name 매핑

| LG 코드 (`lc_main_product`) | PlusTok `product` (products 테이블) |
|-----------------------------|-------------------------------------|
| `representative` | `대표번호` |
| `telephone070` | `070전화` |
| `internet` | `기업인터넷` |
| `iptv` | `IPTV` |
| `cctv` | `CCTV` |
| 결합 (`lc_receipt_type=bundle`) | `결합상품` (또는 메인 상품명 + memo에 add_products 기록) |

### 2.3 detail JSON 예시 (서브테이블 포함)

```json
{
  "lg_receipt_no": "LG202607230001",
  "lg_consult_id": 42,
  "receipt_type": "bundle",
  "main_product_code": "internet",
  "add_products": ["telephone070", "cctv"],
  "install_date": "2026-08-01",
  "consult_time": "오전",
  "est_price": 89000,
  "representative": { "lr_number_type": "1544", "lr_join_type": "신규" },
  "internet": { "li_speed": "500M", "li_biz_type": "일반기업" }
}
```

### 2.4 성공 응답

```json
{
  "success": true,
  "data": {
    "consult_no": "C202607230001",
    "roomId": "uuid-optional",
    "accessToken": "jwt-optional",
    "wsUrl": "wss://..."
  },
  "message": "상담 접수가 완료되었습니다."
}
```

`consult_no` → LG DB `g5_lg_consult`에 `lc_plustok_consult_no` 컬럼 추가 권장 (역추적용).

---

## 3. Step 3 — consults / customers / chat_rooms 자동 생성

PlusTok `consult.php` 처리 순서 (이미 구현됨 — **LG 측은 POST만 하면 됨**):

1. `X-API-KEY` → `sites` 조회 → `site_id` 결정
2. `customers` (또는 `crm_customers`) — phone 중복 시 reuse, 없으면 INSERT
3. `consults` INSERT — `site_id`, `customer_id`, `product_name`, `detail_json`
4. `consult_history` 초기 상태 기록
5. (별도 try) `chat_rooms` + 고객 JWT — `chat_rooms` 테이블 없으면 **스킵**(상담 접수는 성공)

### 3.1 사전 조건 (PlusTok DB)

HeidiSQL / phpMyAdmin:

```sql
-- 사이트 등록 확인
SELECT id, site_code, site_name, domain, brand, status, api_key
FROM sites WHERE site_code = 'lg15441644';

-- 상품 시드 확인
SELECT product_name FROM products WHERE brand = 'LG15441644' AND use_yn = 1;

-- chat_rooms (채팅 연동 시)
SHOW TABLES LIKE 'chat_rooms';
```

없으면 `admin/install.php` 시드 또는 수동 INSERT (과거 대화 참조).

---

## 4. Step 4 — Dashboard 반영 확인

### 4.1 Dashboard SQL (`admin/dashboard.php`)

- 전체: `SELECT COUNT(*) FROM consults` — **site 필터 없음**
- 최근 20건: `consults JOIN customers JOIN sites ORDER BY c.id DESC LIMIT 20`

### 4.2 상담 목록 (`admin/consults/index.php`)

- 기본: 전체 사이트, 최대 200건
- `?site=lg15441644` 로 LG15441644만 필터 가능

### 4.3 연동 후 검증 SQL

```sql
SELECT c.consult_no, c.product_name, c.created_at, cu.name, cu.phone, s.site_name
FROM consults c
JOIN customers cu ON cu.id = c.customer_id
JOIN sites s ON s.id = c.site_id
WHERE s.site_code = 'lg15441644'
ORDER BY c.id DESC LIMIT 10;
```

### 4.4 스모크 테스트 (curl)

```bash
curl -s -X POST "https://plustok.mycafe24.com/api/v1/consult.php" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: YOUR_API_KEY_HERE" \
  -d "{\"customer_name\":\"연동테스트\",\"phone\":\"01099998877\",\"email\":\"test@lg15441644.kr\",\"agree\":true,\"category\":\"통신\",\"product\":\"기업인터넷\",\"company\":\"테스트상호\",\"memo\":\"curl smoke test\"}"
```

---

## 5. Step 5 — 실패 큐 / 재시도 / 로깅

### 5.1 원칙

- **PlusTok API 실패 ≠ LG 접수 실패** (기존 메일 패턴과 동일)
- 모든 실패는 로그 + 재시도 큐에 기록
- 관리자가 수동 재전송 가능

### 5.2 LG 측 큐 테이블 (신규)

```sql
CREATE TABLE IF NOT EXISTS `g5_lg_consult_plustok_queue` (
  `lq_id`           int(11) NOT NULL AUTO_INCREMENT,
  `lc_id`           int(11) NOT NULL,
  `lq_payload`      mediumtext NOT NULL,
  `lq_http_code`    smallint DEFAULT NULL,
  `lq_error`        varchar(500) DEFAULT '',
  `lq_attempts`     tinyint NOT NULL DEFAULT 0,
  `lq_status`       enum('pending','sent','failed','dead') NOT NULL DEFAULT 'pending',
  `lq_plustok_no`   varchar(30) DEFAULT NULL,
  `lq_created_at`   datetime DEFAULT NULL,
  `lq_updated_at`   datetime DEFAULT NULL,
  PRIMARY KEY (`lq_id`),
  KEY `idx_lc_id` (`lc_id`),
  KEY `idx_status` (`lq_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.3 재시도 정책

| 시도 | 대기 | 처리 |
|------|------|------|
| 1 | 즉시 | lg_consult_write.php 내 동기 호출 |
| 2 | 60초 | cron `ajax/lg_consult_plustok_retry.php` |
| 3 | 5분 | cron |
| 4+ | — | `dead` 상태, adm 알림 |

### 5.4 로그 경로

| 위치 | 내용 |
|------|------|
| LG `data/log/plustok_sync_YYYYMMDD.log` | curl http_code, errno, response snippet |
| PlusTok `logs/` | `consult.php` server-side errors |
| LG 큐 테이블 | payload + attempts |

---

## 6. PHP 연동 스니펫 (초안 — 미배포)

> `_lg_consult_common.php`에 추가할 함수 초안. **배포 전 API Key·email fallback 검토 필수.**

```php
/**
 * LG15441644 로컬 접수(lc_id)를 PlusTok CRM으로 동기화.
 * 실패해도 false만 반환 — 호출측에서 접수 성공 JSON은 유지.
 */
function lg_consult_sync_plustok($lc_id)
{
    if (!defined('PLUSTOK_SYNC_ENABLED') || !PLUSTOK_SYNC_ENABLED) {
        return false;
    }
    if (!function_exists('curl_init')) {
        lg_consult_plustok_log($lc_id, 0, 'curl extension missing');
        return false;
    }

    $lc_id = (int)$lc_id;
    $row = lg_consult_get_row($lc_id); // 기존 조회 헬퍼 재사용
    if (empty($row)) {
        return false;
    }

    $product_map = array(
        'representative' => '대표번호',
        'telephone070'   => '070전화',
        'internet'       => '기업인터넷',
        'iptv'           => 'IPTV',
        'cctv'           => 'CCTV',
    );
    $main = (string)$row['lc_main_product'];
    $product_label = isset($product_map[$main]) ? $product_map[$main] : lg_consult_product_label($main);

    $email = trim((string)$row['lc_email']);
    if ($email === '') {
        $email = 'noreply@lg15441644.kr'; // PlusTok API email 필수
    }

    $phone = preg_replace('/[^0-9]/', '', (string)$row['lc_phone']);
    $addr = trim($row['lc_addr1'] . ' ' . $row['lc_addr2']);

    $detail = array(
        'lg_receipt_no'     => $row['lc_receipt_no'],
        'lg_consult_id'     => $lc_id,
        'receipt_type'      => $row['lc_receipt_type'],
        'main_product_code' => $main,
        'add_products'      => json_decode($row['lc_add_products'] ?: '[]', true),
        'install_date'      => $row['lc_install_date'],
        'consult_time'      => $row['lc_consult_time'],
        'est_price'         => (int)$row['lc_est_price'],
    );
    // 상품별 서브테이블은 lg_consult_build_detail($lc_id) 헬퍼로 merge 권장

    $payload = array(
        'customer_name' => $row['lc_applicant_name'],
        'phone'         => $phone,
        'email'         => $email,
        'agree'         => true,
        'company'       => $row['lc_company_name'],
        'zipcode'       => $row['lc_zipcode'],
        'address'       => $addr,
        'region'        => $row['lc_region'],
        'memo'          => '[LG15441644 ' . $row['lc_receipt_no'] . '] ' . $row['lc_etc'],
        'category'      => '통신',
        'product'       => ($row['lc_receipt_type'] === 'bundle') ? '결합상품' : $product_label,
        'referer'       => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        'device'        => preg_match('/Mobile|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? '') ? 'mobile' : 'pc',
        'detail'        => $detail,
    );

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch = curl_init(PLUSTOK_API_URL);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'X-API-KEY: ' . PLUSTOK_API_KEY,
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
    ));
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $http < 200 || $http >= 300) {
        lg_consult_plustok_queue_insert($lc_id, $json, $http, $err ?: (string)$resp);
        lg_consult_plustok_log($lc_id, $http, $err ?: $resp);
        return false;
    }

    $decoded = json_decode($resp, true);
    $consult_no = $decoded['data']['consult_no'] ?? null;
    if ($consult_no) {
        lg_consult_plustok_mark_sent($lc_id, $consult_no);
    }
    return true;
}
```

---

## 7. 대안 — embed.js 전환 (장기)

| 방식 | 장점 | 단점 |
|------|------|------|
| **A. Server-side curl (본 작업지시서)** | 기존 LG UX·상품별 상세폼 유지 | 필드 매핑·큐 유지보수 |
| **B. embed.js (`content/plustok.php` 패턴)** | API Key 서버 은닉, PlusTok UI | LG 맞춤 상품별 폼(대표번호/070/…) 재구현 필요 |

현재 `content/plustok.php` 주석:

> *"기존 상담 시스템(ajax/lg_consult_*, adm/lg_consult_*)과 **무관한 별도 페이지**"*

운영 상담 URL(`/content/consult/*.php`)은 **방식 A**가 적합.

---

## 8. 체크리스트 (배포 전)

- [ ] PlusTok `sites`에 `lg15441644` + `status=1` + 유효 `api_key`
- [ ] PlusTok `products`에 LG15441644 브랜드 6종
- [ ] curl 스모크 테스트 200 + `consult_no` 반환
- [ ] LG `PLUSTOK_API_KEY` 서버 config (Git 제외)
- [ ] `lg_consult_write.php` 연동 + 큐 테이블
- [ ] 테스트 접수 → PlusTok Dashboard / 상담관리 목록 확인
- [ ] PlusTok API 실패 시에도 LG 접수·완료 페이지 정상
- [ ] (선택) `lc_plustok_consult_no` 컬럼 + adm 목록 표시

---

## 9. 참조 파일 목록

### LG15441644

| 파일 | 설명 |
|------|------|
| `ajax/lg_consult_write.php` | 운영 상담 POST 핸들러 — **연동 삽입점** |
| `content/consult/_lg_consult_common.php` | 테이블·메일·상품 정의 |
| `content/plustok.php` | PlusTok embed **테스트 전용** |
| `ajax/consult_write.php` | 구형 consult 테이블 (별도 흐름) |

### PlusTok

| 파일 | 설명 |
|------|------|
| `api/v1/consult.php` | 상담 접수 API |
| `includes/api_auth.php` | X-API-KEY 검증 |
| `embed/form.php` | CORS + API Key 프록시 (embed.js) |
| `admin/dashboard.php` | Dashboard COUNT/최근 목록 |
| `admin/consults/index.php` | 상담 목록 + site 필터 |
| `admin/install.php` | sites/products 시드 |
| `admin/sites/index.php` | 사이트·API Key 관리 |

---

## 10. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-07-23 | 초판 — 진단 + Step 1~5 작업지시서 + PHP 스니펫 초안 |
