# DB — 데이터베이스 구조 (V1.0)

- **엔진:** MySQL / MariaDB, `utf8mb4` / `utf8mb4_unicode_ci`
- **접근:** PDO Prepared Statement만 사용 (문자열 결합 쿼리 금지)
- **시간:** 모든 `*_at`은 `DATETIME`, 앱 기준 시간대 `Asia/Seoul`
- **명명:** 테이블·컬럼 snake_case, PK는 `id BIGINT AUTO_INCREMENT`

> 상품별 가변 입력값은 개별 컬럼으로 만들지 않고 `consults.detail_json`에 JSON으로 저장한다.
> 스키마 변경 없이 상품이 늘어나도 대응하기 위함.

---

## 1. sites — 사이트(도메인) 관리

브랜드당 여러 도메인 가능 → **도메인 1개 = 1행**. `site_code`가 상담폼 로드/집계의 키.

```sql
CREATE TABLE sites (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  site_code   VARCHAR(50)  NOT NULL UNIQUE,      -- 예: lg15441644, lg15441644_b
  site_name   VARCHAR(100) NOT NULL,             -- 표시용 사이트명
  domain      VARCHAR(150) NOT NULL,             -- 예: lg15441644.kr
  brand       VARCHAR(50)  NOT NULL,             -- SmartTokTok, LG15441644 ...
  division    VARCHAR(50)  NOT NULL,             -- 통신사업, 통신가입 ...
  persona     VARCHAR(255) DEFAULT NULL,         -- 첫인사 문구
  api_key     VARCHAR(64)  NOT NULL UNIQUE,      -- 사이트별 발급
  status      TINYINT      NOT NULL DEFAULT 1,   -- 1=사용, 0=중지
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sites_brand (brand),
  INDEX idx_sites_division (division)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**시드 데이터 예시** (한 브랜드 여러 도메인 등록 방법 포함)

```sql
INSERT INTO sites (site_code, site_name, domain, brand, division, persona, api_key) VALUES
('smarttoktok','스마트톡톡','smarttoktok.com','SmartTokTok','통신사업','LG유플러스 대표번호 가입센터입니다.', 'KEY_REPLACE_1'),
('lg15441644','LG15441644','lg15441644.kr','LG15441644','통신가입','기업 인터넷·070·대표번호 가입센터입니다.','KEY_REPLACE_2'),
('hompyshop','홈피샵','hompyshop.com','HompyShop','웹제작','AI 홈페이지 제작 상담입니다.','KEY_REPLACE_3'),
('showform','쇼폼','showform.kr','ShowForm','AI 플랫폼','쇼폼 AI 랜딩 제작입니다.','KEY_REPLACE_4'),
('callmap','콜맵','callmap.kr','CallMap','광고플랫폼','지도·플레이스 상위노출 상담입니다.','KEY_REPLACE_5'),
('hongpansa','홍판사','hongpansa.kr','HongPansa','판촉사업','판촉·홍보 상담입니다.','KEY_REPLACE_6'),
('oncap24','온캡24','oncap24.com','Oncap24','중개서비스','이사·공사 견적을 도와드립니다.','KEY_REPLACE_7'),
('nuguupso','누구업소','nuguupso.com','nuguupso','플랫폼 사업','원하는 공사·제품을 등록하면 업체가 견적을 제안합니다.','KEY_REPLACE_8');
-- 같은 브랜드의 추가 도메인 예:
-- ('lg15441644_b','LG15441644(서브)','lg-second.kr','LG15441644','통신가입', ..., 'KEY_REPLACE_9');
```

> `api_key`는 `bin2hex(random_bytes(32))`로 생성하여 넣는다. 시드의 `KEY_REPLACE_*`는 반드시 교체.

---

## 2. customers — 고객

중복 판단 키는 `phone`. `customer_no` 형식은 `M{YYYYMMDD}{NNNN}` 권장(선택).

```sql
CREATE TABLE customers (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  customer_no  VARCHAR(30)  NOT NULL UNIQUE,
  name         VARCHAR(60)  NOT NULL,
  phone        VARCHAR(20)  NOT NULL,            -- 숫자만 저장(하이픈 제거)
  company      VARCHAR(120) DEFAULT NULL,
  email        VARCHAR(150) DEFAULT NULL,
  zipcode      VARCHAR(10)  DEFAULT NULL,
  address      VARCHAR(255) DEFAULT NULL,
  region       VARCHAR(50)  DEFAULT NULL,
  memo         TEXT         DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customers_phone (phone),
  INDEX idx_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> `phone`을 UNIQUE로 두면 중복 접수 시 기존 고객에 상담만 추가된다.
> 개인정보는 저장 시 최소화하고, 접근 권한(role)으로 제한한다. (암호화는 V1.5+ 검토)

---

## 3. products — 상품(카테고리)

```sql
CREATE TABLE products (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  brand         VARCHAR(50)  NOT NULL,           -- 소속 브랜드
  category      VARCHAR(60)  NOT NULL,           -- 대분류(예: 통신, 웹, 판촉)
  product_name  VARCHAR(100) NOT NULL,           -- 예: 기업인터넷, 대표번호
  sort_order    INT          NOT NULL DEFAULT 0,
  use_yn        TINYINT      NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_brand (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. managers — 담당자(관리자)

```sql
CREATE TABLE managers (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  login_id    VARCHAR(50)  NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,             -- password_hash() 결과
  name        VARCHAR(60)  NOT NULL,
  phone       VARCHAR(20)  DEFAULT NULL,
  role        ENUM('super','admin','manager','sales','viewer') NOT NULL DEFAULT 'manager',
  status      TINYINT      NOT NULL DEFAULT 1,
  last_login  DATETIME     DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. consults — 상담

```sql
CREATE TABLE consults (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_no   VARCHAR(20)  NOT NULL UNIQUE,     -- C20260717 0001
  customer_id  BIGINT       NOT NULL,
  site_id      BIGINT       NOT NULL,
  product_id   BIGINT       DEFAULT NULL,
  manager_id   BIGINT       DEFAULT NULL,        -- 배정 담당자
  category     VARCHAR(60)  DEFAULT NULL,        -- 접수 시 상품 카테고리(스냅샷)
  product_name VARCHAR(100) DEFAULT NULL,        -- 접수 시 상품명(스냅샷)
  status       ENUM('new','progress','consulting','quoted','contracted','installed','hold','canceled')
               NOT NULL DEFAULT 'new',
  detail_json  JSON         DEFAULT NULL,        -- 상품별 가변 입력값
  memo         TEXT         DEFAULT NULL,        -- 고객 추가 요청(상담 내용)
  ai_summary   TEXT         DEFAULT NULL,        -- V1.5에서 채움(V1.0은 NULL)
  referer      VARCHAR(255) DEFAULT NULL,
  device       VARCHAR(20)  DEFAULT NULL,        -- mobile / pc
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_consults_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_consults_site     FOREIGN KEY (site_id)     REFERENCES sites(id),
  INDEX idx_consults_status (status),
  INDEX idx_consults_site (site_id),
  INDEX idx_consults_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`detail_json` 예시(070전화):
```json
{ "line_count": 3, "rep_number": true, "recording": true, "device": "IP폰",
  "internet_speed": "1G", "preferred_time": "이번주" }
```

---

## 6. consult_history — 상태 변경 이력

```sql
CREATE TABLE consult_history (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       NOT NULL,
  from_status  VARCHAR(20)  DEFAULT NULL,
  to_status    VARCHAR(20)  NOT NULL,
  manager_id   BIGINT       DEFAULT NULL,
  note         VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_consult FOREIGN KEY (consult_id) REFERENCES consults(id),
  INDEX idx_history_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 7. attachments — 첨부파일

```sql
CREATE TABLE attachments (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       DEFAULT NULL,
  file_type    VARCHAR(30)  DEFAULT NULL,        -- 사업자등록증/사진/견적서/도면/PDF
  orig_name    VARCHAR(255) NOT NULL,
  saved_path   VARCHAR(255) NOT NULL,            -- uploads/consult/2026/07/xxx.ext
  mime         VARCHAR(100) DEFAULT NULL,
  size_bytes   INT          DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attach_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 8. activity_log — 관리자 작업 로그

```sql
CREATE TABLE activity_log (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  manager_id  BIGINT       DEFAULT NULL,
  action      VARCHAR(50)  NOT NULL,             -- login / update_status / assign / ...
  target      VARCHAR(50)  DEFAULT NULL,         -- consult:123 등
  detail      VARCHAR(255) DEFAULT NULL,
  ip          VARCHAR(45)  DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_manager (manager_id),
  INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 9. V2.0 예약 테이블 (V1.0에서는 생성만/미사용 가능)

- `contracts` — 계약번호·고객·상품·계약금액·진행상태
- `schedules` — 일정·담당자·방문·설치일
- `ai_logs` — 상담요약·답변초안·추천상품·분석결과 (V1.5)

> V1.0에서는 만들지 않아도 된다. 스키마 확장 시 이 문서에 먼저 추가한다.

---

## 10. 생성 순서 (마이그레이션)

1. `sites` → 2. `products` → 3. `managers` → 4. `customers`
→ 5. `consults` → 6. `consult_history` → 7. `attachments` → 8. `activity_log`

FK가 있으므로 참조 대상(customers, sites)을 먼저 만든다.
DDL은 `/db/schema.sql`, 시드는 `/db/seed.sql`로 분리해 커밋한다.
