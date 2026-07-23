-- ACEP V4.0.0 — Enterprise CRM V2 Phase 1
-- SSOT: /implementation_plan_v2_phase1.md

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS consult_memos (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       NOT NULL,
  manager_id   BIGINT       NOT NULL,
  content_html MEDIUMTEXT   NOT NULL,
  memo_type    VARCHAR(20)  DEFAULT 'general' COMMENT 'general, vip, caution, competitor, recall, rep, special',
  is_private   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cmemos_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consult_files (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id    BIGINT       NOT NULL,
  file_category VARCHAR(50)  DEFAULT 'etc' COMMENT 'quote, contract, business_license, record, photo, etc',
  orig_name     VARCHAR(255) NOT NULL,
  saved_path    VARCHAR(500) NOT NULL,
  file_size     BIGINT       NOT NULL DEFAULT 0,
  file_ext      VARCHAR(20)  DEFAULT NULL,
  uploader_id   BIGINT       NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cfiles_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
