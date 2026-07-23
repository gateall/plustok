-- Cafe24 phpMyAdmin: 비밀번호 재설정 토큰 컬럼 추가
-- 이미 컬럼이 있으면 각 ALTER 문에서 에러가 나므로 그 줄만 무시하고 넘어가면 됨

ALTER TABLE agents ADD COLUMN reset_token_hash CHAR(64) NULL AFTER locked_until;
ALTER TABLE agents ADD COLUMN reset_token_expires_at DATETIME(3) NULL AFTER reset_token_hash;
ALTER TABLE agents ADD KEY idx_agents_reset_token (reset_token_hash);
