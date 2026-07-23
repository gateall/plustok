-- ACEP V4.1.0 — Enterprise CRM V2 Phase 2 (Communications & Sending History)
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS consult_communications (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       NOT NULL,
  manager_id   BIGINT       NOT NULL,
  comm_type    VARCHAR(20)  NOT NULL COMMENT 'EMAIL, SMS, KAKAO',
  subject      VARCHAR(255) DEFAULT NULL,
  content_html MEDIUMTEXT   NOT NULL,
  recipient    VARCHAR(255) NOT NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'SENT' COMMENT 'SENT, FAILED',
  error_msg    VARCHAR(500) DEFAULT NULL,
  sent_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ccomm_consult (consult_id),
  INDEX idx_ccomm_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
