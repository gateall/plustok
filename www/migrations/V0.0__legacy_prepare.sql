-- ACEP V3.0 — 레거시 PlusTok CRM 공존 준비 (선택)
-- ⚠️ 기존 customers / attachments 테이블이 install.php 스키마일 때만 1회 실행
-- 실행 후 V1.0.0 → V1.5.0 마이그레이션 진행

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- legacy customers → crm_customers (BIGINT PK 유지)
RENAME TABLE customers TO crm_customers;

-- legacy attachments → crm_attachments
RENAME TABLE attachments TO crm_attachments;

SET foreign_key_checks = 1;
