-- V4.5.0 — Admin "사이트 설정" (site title/logo/notification email) storage.
-- Single-row config table, mirrors the ai_settings/ai_provider_config pattern.
CREATE TABLE IF NOT EXISTS site_settings (
  id                 TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  site_title         VARCHAR(120) NOT NULL DEFAULT 'PlusTok 통합 CRM',
  logo_url           VARCHAR(500) NULL,
  admin_notify_email VARCHAR(150) NULL,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_site_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (id, site_title, admin_notify_email)
VALUES (1, 'PlusTok 통합 CRM', 'adfull@naver.com')
ON DUPLICATE KEY UPDATE id = id;
