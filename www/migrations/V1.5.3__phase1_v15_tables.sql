-- ACEP V1.5 Phase1 completion — notifications + agent settings
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS agent_notifications (
    id          VARCHAR(36)     NOT NULL,
    agent_id    VARCHAR(36)     NOT NULL,
    type        VARCHAR(50)     NOT NULL DEFAULT 'system',
    title       VARCHAR(255)    NOT NULL,
    body        TEXT            NULL,
    payload     JSON            NULL,
    read_at     DATETIME(3)     NULL,
    created_at  DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_notifications_agent_read (agent_id, read_at, created_at DESC),
    CONSTRAINT fk_notifications_agent
        FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='상담원 알림';

-- agents.settings_json (idempotent)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'agents' AND column_name = 'settings_json'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE agents ADD COLUMN settings_json JSON NULL COMMENT ''Agent UI preferences'' AFTER avatar_url',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
