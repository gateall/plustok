-- ACEP V3.0 — Phase 3 CRM Integration
-- SSOT: 06_CRM/01_CRM통합.md
-- Prerequisites: V1.0.0 + V1.5.x

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS sites (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  site_code    VARCHAR(40)  NOT NULL UNIQUE,
  site_name    VARCHAR(100) NOT NULL,
  brand        VARCHAR(60)  DEFAULT NULL,
  api_key      VARCHAR(64)  DEFAULT NULL,
  use_yn       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 'acep-default' 시드 row는 migrate.php에서 sites.use_yn 컬럼 존재 여부를 확인한 뒤 조건부로 넣는다.
-- (install.php 스키마의 sites 테이블은 use_yn이 아니라 status를 쓰므로, 여기서 그대로 실행하면
--  "Unknown column 'use_yn'" 에러가 난다.)

CREATE TABLE IF NOT EXISTS crm_customers (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  customer_no  VARCHAR(20)  NOT NULL UNIQUE,
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
  UNIQUE KEY uq_crm_customers_phone (phone),
  INDEX idx_crm_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_bridge (
  acep_customer_id   VARCHAR(36) NOT NULL,
  legacy_customer_id BIGINT      NOT NULL,
  PRIMARY KEY (acep_customer_id),
  UNIQUE KEY uq_customer_bridge_legacy (legacy_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consults (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_no      VARCHAR(20)  NOT NULL UNIQUE,
  customer_id     BIGINT       NOT NULL,
  site_id         BIGINT       NOT NULL DEFAULT 1,
  product_id      BIGINT       DEFAULT NULL,
  manager_id      BIGINT       DEFAULT NULL,
  category        VARCHAR(60)  DEFAULT NULL,
  product_name    VARCHAR(100) DEFAULT NULL,
  status          ENUM('new','progress','consulting','quoted','contracted','installed','hold','canceled')
                  NOT NULL DEFAULT 'consulting',
  detail_json     JSON         DEFAULT NULL,
  memo            TEXT         DEFAULT NULL,
  ai_summary      TEXT         DEFAULT NULL,
  ai_summary_at   DATETIME     DEFAULT NULL,
  category_ai     VARCHAR(60)  DEFAULT NULL,
  lead_score      TINYINT UNSIGNED DEFAULT NULL,
  priority        ENUM('LOW','NORMAL','HIGH','URGENT') DEFAULT 'NORMAL',
  sentiment       ENUM('POSITIVE','NEUTRAL','NEGATIVE') DEFAULT 'NEUTRAL',
  tags            VARCHAR(500) DEFAULT NULL,
  ai_analyzed_at  DATETIME     DEFAULT NULL,
  referer         VARCHAR(255) DEFAULT NULL,
  device          VARCHAR(20)  DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_consults_status (status),
  INDEX idx_consults_created (created_at),
  INDEX idx_consults_lead_score (lead_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consult_history (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       NOT NULL,
  from_status  VARCHAR(20)  DEFAULT NULL,
  to_status    VARCHAR(20)  NOT NULL,
  manager_id   BIGINT       DEFAULT NULL,
  note         VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedules (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id      BIGINT NOT NULL,
  schedule_type   ENUM('call','email','follow_up','info_collect','satisfaction') NOT NULL,
  scheduled_at    DATETIME NOT NULL,
  assigned_to     BIGINT NULL,
  status          ENUM('pending','done','canceled') NOT NULL DEFAULT 'pending',
  title           VARCHAR(200) NOT NULL,
  memo            TEXT NULL,
  created_by      ENUM('system','agent','admin') NOT NULL DEFAULT 'system',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME NULL,
  INDEX idx_schedules_due (status, scheduled_at),
  INDEX idx_schedules_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedules_dedup_guard (
  consult_id      BIGINT NOT NULL,
  schedule_type   VARCHAR(30) NOT NULL,
  scheduled_date  DATE NOT NULL,
  schedule_id     BIGINT NOT NULL,
  PRIMARY KEY (consult_id, schedule_type, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
