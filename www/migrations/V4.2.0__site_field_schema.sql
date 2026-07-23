-- ACEP V4.2.0 — Site Field Schema (사이트별 임베드 폼 동적 필드 스키마)
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS site_field_schemas (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  site_id      BIGINT       NOT NULL,
  category     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT '빈 문자열 = 사이트 기본값(전체 적용)',
  product_name VARCHAR(100) NOT NULL DEFAULT '' COMMENT '빈 문자열 = category 단위 적용',
  schema_json  JSON         NOT NULL COMMENT '[{key,label,type,options,required}, ...]',
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_field_schema (site_id, category, product_name),
  CONSTRAINT fk_site_field_schemas_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  INDEX idx_sfs_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
