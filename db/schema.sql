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
  ai_summary_at DATETIME    DEFAULT NULL,
  category_ai  VARCHAR(50)  DEFAULT NULL,
  lead_score   INT          DEFAULT 0,
  priority     VARCHAR(20)  DEFAULT 'NORMAL',
  sentiment    VARCHAR(20)  DEFAULT 'NEUTRAL',
  tags         VARCHAR(255) DEFAULT NULL,
  ai_analyzed_at DATETIME   DEFAULT NULL,
  referer      VARCHAR(255) DEFAULT NULL,
  device       VARCHAR(20)  DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_consults_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_consults_site     FOREIGN KEY (site_id)     REFERENCES sites(id),
  INDEX idx_consults_status (status),
  INDEX idx_consults_site (site_id),
  INDEX idx_consults_created (created_at),
  INDEX idx_consults_priority (priority),
  INDEX idx_consults_score (lead_score),
  INDEX idx_consults_cat_ai (category_ai)
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

-- 9. ai_logs -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_logs (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  feature       VARCHAR(30)  NOT NULL,           -- customer_summary/consult_summary/reply_draft/recommend/grade
  target_id     BIGINT       DEFAULT NULL,       -- customers.id 또는 consults.id
  provider      VARCHAR(20)  NOT NULL DEFAULT 'anthropic',
  model         VARCHAR(40)  NOT NULL,
  status        VARCHAR(10)  NOT NULL,           -- ok/error
  input_tokens  INT          NOT NULL DEFAULT 0,
  output_tokens INT          NOT NULL DEFAULT 0,
  duration_ms   INT          NOT NULL DEFAULT 0,
  error         VARCHAR(500) DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_logs_feature (feature),
  INDEX idx_ai_logs_provider (provider),
  INDEX idx_ai_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. ai_settings (프로바이더별 키 및 모델 설정 - 5대 벤더 지원) ------------------
CREATE TABLE IF NOT EXISTS ai_settings (
  provider    VARCHAR(20)  PRIMARY KEY,          -- 'anthropic' | 'openai' | 'gemini' | 'grok' | 'deepseek'
  api_key     VARCHAR(200) DEFAULT NULL,
  model       VARCHAR(80)  DEFAULT NULL,
  updated_by  BIGINT       DEFAULT NULL,
  updated_at  DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ai_provider_config (전역 단일 활성 프로바이더 및 킬스위치) ---------------
CREATE TABLE IF NOT EXISTS ai_provider_config (
  id              TINYINT      PRIMARY KEY DEFAULT 1,
  active_provider VARCHAR(20)  NOT NULL DEFAULT 'anthropic', -- 'anthropic' | 'openai' | 'gemini' | 'grok' | 'deepseek' | 'auto'
  enabled         TINYINT      NOT NULL DEFAULT 0,
  CONSTRAINT chk_ai_provider_config_single CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ai_failover_log (Auto Failover 무정지 전환 및 장애 극복 감사 로그) ----------
CREATE TABLE IF NOT EXISTS ai_failover_log (
  id                BIGINT AUTO_INCREMENT PRIMARY KEY,
  feature           VARCHAR(30)  NOT NULL,
  target_id         BIGINT       DEFAULT NULL,
  failed_provider   VARCHAR(20)  NOT NULL,
  failed_model      VARCHAR(80)  NOT NULL,
  error_code        VARCHAR(20)  NOT NULL,
  error_message     VARCHAR(500) DEFAULT NULL,
  fallback_provider VARCHAR(20)  NOT NULL,
  fallback_model    VARCHAR(80)  NOT NULL,
  status            VARCHAR(10)  NOT NULL DEFAULT 'success',
  duration_ms       INT          NOT NULL DEFAULT 0,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_failover_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

