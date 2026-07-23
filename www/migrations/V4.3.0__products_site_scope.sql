-- ACEP V4.3.0 — Products Site Scope (사이트 전용 상품, nullable = 기존처럼 brand 공유)
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

ALTER TABLE products
  ADD COLUMN site_id BIGINT NULL DEFAULT NULL COMMENT 'NULL=브랜드 공유, 값 있으면 해당 사이트 전용' AFTER brand,
  ADD INDEX idx_products_site (site_id);

ALTER TABLE products
  ADD CONSTRAINT fk_products_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL;

SET foreign_key_checks = 1;
