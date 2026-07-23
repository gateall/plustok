-- Legacy CRM bootstrap for tests / greenfield dev without V0.0 rename.
-- Matches admin/install.php customers + consults shape.

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
                  NOT NULL DEFAULT 'new',
  detail_json     JSON         DEFAULT NULL,
  memo            TEXT         DEFAULT NULL,
  referer         VARCHAR(255) DEFAULT NULL,
  device          VARCHAR(20)  DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_consults_status (status),
  INDEX idx_consults_created (created_at)
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

SET foreign_key_checks = 1;
