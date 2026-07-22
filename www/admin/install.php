<?php
declare(strict_types=1);
/**
 * 일회용 DB 설치 스크립트. CRM 테이블(schema) + 시드(seed)를 plustok DB에 생성한다.
 * 안전장치: `sites` 테이블이 이미 있으면 아무것도 하지 않는다.
 * ⚠️ 설치 후 반드시 이 파일(admin/install.php)을 서버에서 삭제할 것.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/html; charset=utf-8');
$pdo = db();

// 이미 설치됐는지 확인
$already = false;
try {
    $already = (bool) $pdo->query("SHOW TABLES LIKE 'sites'")->fetch();
} catch (Throwable $e) { $already = false; }

if ($already) {
    echo '<meta charset="utf-8"><h2>이미 설치되어 있습니다.</h2>';
    echo '<p>보안을 위해 <b>admin/install.php</b> 파일을 서버에서 삭제하세요.</p>';
    echo '<p><a href="/admin/setup.php">→ 최초 관리자 생성</a></p>';
    exit;
}

/** 세미콜론 단위로 안전하게 실행 (주석/빈 줄 제거 후) */
function run_sql(PDO $pdo, string $sql): int {
    $lines = preg_split('/
/', $sql);
    $clean = [];
    foreach ($lines as $ln) {
        $t = trim($ln);
        if ($t === '' || strpos($t, '--') === 0) continue;
        $clean[] = $ln;
    }
    $joined = implode("
", $clean);
    $n = 0;
    foreach (explode(';', $joined) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $n++;
    }
    return $n;
}

$SCHEMA = <<<'SQLSCHEMA'
-- PlusTok 통합 CRM — V1.0 스키마
-- 적용: mysql -u <user> -p <dbname> < schema.sql
-- 규칙: utf8mb4 / PDO / snake_case. 상세는 DB.md.
-- 생성 순서(FK): sites, products, managers, customers → consults → consult_history, attachments, activity_log

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- 1. sites ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sites (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  site_code   VARCHAR(50)  NOT NULL UNIQUE,
  site_name   VARCHAR(100) NOT NULL,
  domain      VARCHAR(150) NOT NULL,
  brand       VARCHAR(50)  NOT NULL,
  division    VARCHAR(50)  NOT NULL,
  persona     VARCHAR(255) DEFAULT NULL,
  api_key     VARCHAR(64)  NOT NULL UNIQUE,
  status      TINYINT      NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sites_brand (brand),
  INDEX idx_sites_division (division)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. products ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  brand         VARCHAR(50)  NOT NULL,
  category      VARCHAR(60)  NOT NULL,
  product_name  VARCHAR(100) NOT NULL,
  sort_order    INT          NOT NULL DEFAULT 0,
  use_yn        TINYINT      NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_brand (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. managers ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS managers (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  login_id    VARCHAR(50)  NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  name        VARCHAR(60)  NOT NULL,
  phone       VARCHAR(20)  DEFAULT NULL,
  role        ENUM('super','admin','manager','sales','viewer') NOT NULL DEFAULT 'manager',
  status      TINYINT      NOT NULL DEFAULT 1,
  last_login  DATETIME     DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. customers -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  customer_no  VARCHAR(30)  NOT NULL UNIQUE,
  name         VARCHAR(60)  NOT NULL,
  phone        VARCHAR(20)  NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. consults ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consults (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_no   VARCHAR(20)  NOT NULL UNIQUE,
  customer_id  BIGINT       NOT NULL,
  site_id      BIGINT       NOT NULL,
  product_id   BIGINT       DEFAULT NULL,
  manager_id   BIGINT       DEFAULT NULL,
  category     VARCHAR(60)  DEFAULT NULL,
  product_name VARCHAR(100) DEFAULT NULL,
  status       ENUM('new','progress','consulting','quoted','contracted','installed','hold','canceled')
               NOT NULL DEFAULT 'new',
  detail_json  JSON         DEFAULT NULL,
  memo         TEXT         DEFAULT NULL,
  ai_summary   TEXT         DEFAULT NULL,
  referer      VARCHAR(255) DEFAULT NULL,
  device       VARCHAR(20)  DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_consults_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_consults_site     FOREIGN KEY (site_id)     REFERENCES sites(id),
  INDEX idx_consults_status (status),
  INDEX idx_consults_site (site_id),
  INDEX idx_consults_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. consult_history -----------------------------------------------------
CREATE TABLE IF NOT EXISTS consult_history (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       NOT NULL,
  from_status  VARCHAR(20)  DEFAULT NULL,
  to_status    VARCHAR(20)  NOT NULL,
  manager_id   BIGINT       DEFAULT NULL,
  note         VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_consult FOREIGN KEY (consult_id) REFERENCES consults(id),
  INDEX idx_history_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. attachments ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS attachments (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       DEFAULT NULL,
  file_type    VARCHAR(30)  DEFAULT NULL,
  orig_name    VARCHAR(255) NOT NULL,
  saved_path   VARCHAR(255) NOT NULL,
  mime         VARCHAR(100) DEFAULT NULL,
  size_bytes   INT          DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attach_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. activity_log --------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  manager_id  BIGINT       DEFAULT NULL,
  action      VARCHAR(50)  NOT NULL,
  target      VARCHAR(50)  DEFAULT NULL,
  detail      VARCHAR(255) DEFAULT NULL,
  ip          VARCHAR(45)  DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_manager (manager_id),
  INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
SQLSCHEMA;

$SEED = <<<'SQLSEED'
-- PlusTok 통합 CRM — V1.0 시드
-- 적용: mysql -u <user> -p <dbname> < seed.sql  (schema.sql 이후)
-- ⚠️ 아래 api_key는 초기 발급값이다. 운영 전/노출 시 관리자에서 재발급할 것.
-- ⚠️ 관리자 계정은 여기서 만들지 않는다. 서버에서 /admin/setup.php로 최초 super 계정을 생성한다.

SET NAMES utf8mb4;

-- 사이트(도메인) ---------------------------------------------------------
INSERT INTO sites (site_code, site_name, domain, brand, division, persona, api_key) VALUES
('smarttoktok','스마트톡톡','smarttoktok.com','SmartTokTok','통신사업','LG유플러스 대표번호 가입센터입니다.','8369d27c9826b1e1295b79d6e3992400b5e3f81c2b2c22fad641c1511b9f3094'),
('lg15441644','LG15441644','lg15441644.kr','LG15441644','통신가입','기업 인터넷·070·대표번호 가입센터입니다.','8a9925a7a9c29922e4cbb3e79b774812cf3c20b667056e68f2e2ab32c69765c6'),
('hompyshop','홈피샵','hompyshop.com','HompyShop','웹제작','AI 홈페이지 제작 상담입니다.','0e69d946a10a107a01cd18f33e6c4b737c0a5af2dbb6d6d7e1d8d316c113d5e8'),
('showform','쇼폼','showform.kr','ShowForm','AI 플랫폼','쇼폼 AI 랜딩 제작입니다.','f2b97801874692411f6e7bdbce0b859d793c8049428489dd8ec6b2e017f7ad11'),
('callmap','콜맵','callmap.kr','CallMap','광고플랫폼','지도·플레이스 상위노출 상담입니다.','bf0768d0b3cb536a87e8d5978c31141dfd85c14108ea71222363d60640343b0c'),
('hongpansa','홍판사','hongpansa.kr','HongPansa','판촉사업','판촉·홍보 상담입니다.','abae574511b2e1c24a526bde094d8197c0dae86a84249656c66d49ad4c1252fd'),
('oncap24','온캡24','oncap24.com','Oncap24','중개서비스','이사·공사 견적을 도와드립니다.','e440572c99d21f375d5b4e4ba6272c995c355bbe014f87b4bf65e2d9d4daf8e1'),
('nuguupso','누구업소','nuguupso.com','nuguupso','플랫폼 사업','원하는 공사·제품을 등록하면 업체가 견적을 제안합니다.','4a9b747e79f7ce428e0b3c2e4ec7e391e665f1307b8ddf8f2437a355610e9e9c');

-- 상품(브랜드별 기본 카테고리) ------------------------------------------
INSERT INTO products (brand, category, product_name, sort_order) VALUES
('SmartTokTok','통신','대표번호',1),
('SmartTokTok','통신','070전화',2),
('SmartTokTok','통신','기업인터넷',3),
('LG15441644','통신','기업인터넷',1),
('LG15441644','통신','070전화',2),
('LG15441644','통신','대표번호',3),
('LG15441644','통신','IPTV',4),
('LG15441644','통신','CCTV',5),
('LG15441644','통신','결합상품',6),
('HompyShop','웹','기업홈페이지',1),
('HompyShop','웹','쇼핑몰',2),
('HompyShop','웹','랜딩페이지',3),
('HompyShop','웹','SEO',4),
('HompyShop','웹','유지관리',5),
('ShowForm','웹','AI 랜딩페이지',1),
('ShowForm','웹','설문/폼',2),
('CallMap','광고','플레이스 등록',1),
('CallMap','광고','지도 상위',2),
('CallMap','광고','지역광고',3),
('HongPansa','판촉','판촉물',1),
('HongPansa','판촉','블로그체험단',2),
('HongPansa','판촉','상위노출',3),
('HongPansa','판촉','홍보',4),
('Oncap24','중개','가정이사',1),
('Oncap24','중개','사무실이사',2),
('Oncap24','중개','보관이사',3),
('Oncap24','중개','용달',4),
('Oncap24','중개','공사',5),
('nuguupso','중개','인테리어',1),
('nuguupso','중개','청소',2),
('nuguupso','중개','설비',3),
('nuguupso','중개','광고',4);
SQLSEED;

echo '<meta charset="utf-8"><h2>PlusTok CRM 설치</h2>';
try {
    $a = run_sql($pdo, $SCHEMA);
    $b = run_sql($pdo, $SEED);
    echo '<p style="color:#1e7e34">✔ 스키마 ' . $a . '문 + 시드 ' . $b . '문 실행 완료.</p>';
    echo '<p><b>다음 단계</b></p><ol>';
    echo '<li>보안을 위해 <b>admin/install.php</b>를 지금 삭제하세요.</li>';
    echo '<li><a href="/admin/setup.php">/admin/setup.php</a> 에서 최초 super 관리자 생성.</li>';
    echo '<li><a href="/admin/">/admin/</a> 로그인.</li>';
    echo '</ol>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p style="color:#c0392b">설치 실패: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
}
