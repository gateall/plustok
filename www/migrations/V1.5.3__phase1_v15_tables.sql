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

-- agents.settings_json 컬럼 추가는 migrate.php의 acep_add_column_if_missing()에서 처리한다.
-- (MySQL의 PREPARE/EXECUTE 동적 SQL을 PDO exec()로 실행하면 "Cannot execute queries while
--  other unbuffered queries are active" 에러가 나는 조합이 있어, PHP 쪽 조건 체크로 옮김)
