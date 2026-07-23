-- ACEP V4.4.0 — Consult Meta (EAV) — detail_json을 검색/필터 가능하게 인덱싱한 파생 저장소
-- detail_json이 원본(full-fidelity), consult_meta는 조회 전용 파생 인덱스. 서로 대체 관계 아님.
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS consult_meta (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id  BIGINT       NOT NULL,
  meta_key    VARCHAR(60)  NOT NULL,
  meta_value  VARCHAR(255) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_consult_meta_consult FOREIGN KEY (consult_id) REFERENCES consults(id) ON DELETE CASCADE,
  INDEX idx_consult_meta_key_value (meta_key, meta_value),
  INDEX idx_consult_meta_consult (consult_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
