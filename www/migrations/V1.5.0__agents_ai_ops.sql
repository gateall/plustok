-- ACEP V3.0 — V1.5 Agents + AI Ops (9 tables) + FK 보완
-- SSOT: 03_SYSTEM/01_DB설계.md §7.2
-- 실행 전: V1.0.0__mvp_core.sql

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- 6. agents -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agents (
    id                  VARCHAR(36)     NOT NULL,
    login_id            VARCHAR(50)     NOT NULL,
    password_hash       VARCHAR(255)    NOT NULL,
    name                VARCHAR(100)    NOT NULL,
    email               VARCHAR(512)    NULL COMMENT 'AES-256-GCM encrypted',
    phone               VARCHAR(512)    NULL COMMENT 'AES-256-GCM encrypted',
    role                ENUM('agent','admin','operator') NOT NULL DEFAULT 'agent',
    status              ENUM('online','away','offline') NOT NULL DEFAULT 'offline',
    avatar_url          VARCHAR(500)    NULL,
    failed_login_count  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until        DATETIME(3)     NULL,
    last_login_at       DATETIME(3)     NULL,
    created_at          DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at          DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    deleted_at          DATETIME(3)     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agents_login_id (login_id),
    KEY idx_agents_role_status (role, status),
    KEY idx_agents_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='상담원/관리자 — Auth';

-- 7. chat_room_assignments ---------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_room_assignments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id         VARCHAR(36)     NOT NULL,
    agent_id        VARCHAR(36)     NOT NULL,
    assignment_type ENUM('auto','manual','handoff') NOT NULL DEFAULT 'auto',
    assigned_by     VARCHAR(36)     NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    assigned_at     DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    unassigned_at   DATETIME(3)     NULL,
    PRIMARY KEY (id),
    KEY idx_assignments_room_active (room_id, is_active),
    KEY idx_assignments_agent_active (agent_id, is_active),
    CONSTRAINT fk_assignments_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    CONSTRAINT fk_assignments_agent
        FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='상담방 배정 이력';

-- 8. attachments (SSOT §5.8 — V0.0 legacy rename 후 greenfield) -----------
CREATE TABLE IF NOT EXISTS attachments (
    id              VARCHAR(36)     NOT NULL,
    room_id         VARCHAR(36)     NOT NULL,
    message_id      VARCHAR(36)     NULL,
    uploader_type   ENUM('customer','agent') NOT NULL,
    uploader_id     VARCHAR(36)     NOT NULL,
    file_name       VARCHAR(255)    NOT NULL,
    mime_type       VARCHAR(100)    NOT NULL,
    file_size       INT UNSIGNED    NOT NULL,
    storage_path    VARCHAR(500)    NOT NULL,
    public_url      VARCHAR(500)    NOT NULL,
    checksum_sha256 CHAR(64)        NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    deleted_at      DATETIME(3)     NULL,
    PRIMARY KEY (id),
    KEY idx_attachments_room (room_id),
    KEY idx_attachments_message (message_id),
    CONSTRAINT fk_attachments_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    CONSTRAINT fk_attachments_message
        FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='첨부파일 — FileUpload';

-- 9. ai_settings -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_settings (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    setting_key     VARCHAR(100)    NOT NULL,
    setting_value   JSON            NOT NULL,
    description     VARCHAR(255)    NULL,
    updated_by      VARCHAR(36)     NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI 전역 설정';

-- 10. ai_provider_config -----------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_provider_config (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    provider        ENUM('claude','openai','gemini','grok') NOT NULL,
    model_name      VARCHAR(50)     NOT NULL,
    priority        TINYINT UNSIGNED NOT NULL,
    timeout_ms      INT UNSIGNED    NOT NULL DEFAULT 10000,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    api_key_env     VARCHAR(100)    NOT NULL COMMENT 'env var name only',
    config_json     JSON            NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_priority (provider, priority),
    KEY idx_provider_active (is_active, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI Provider Failover';

-- 11. ai_prompts -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_prompts (
    id              VARCHAR(36)     NOT NULL,
    role            VARCHAR(50)     NOT NULL,
    version         VARCHAR(20)     NOT NULL,
    prompt_id       VARCHAR(50)     NOT NULL,
    content         MEDIUMTEXT      NOT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 0,
    changelog       TEXT            NULL,
    created_by      VARCHAR(36)     NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_prompts_prompt_id (prompt_id),
    KEY idx_ai_prompts_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI 프롬프트 버전';

-- 12. ai_logs ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_logs (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id             VARCHAR(36)     NULL,
    recommendation_id   VARCHAR(36)     NULL,
    provider            VARCHAR(30)     NOT NULL,
    model               VARCHAR(50)     NOT NULL,
    request_tokens      INT UNSIGNED    NULL,
    response_tokens     INT UNSIGNED    NULL,
    latency_ms          INT UNSIGNED    NULL,
    status              ENUM('success','error','timeout') NOT NULL,
    error_code          VARCHAR(50)     NULL,
    request_hash        CHAR(64)        NULL,
    created_at          DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_ai_logs_room_created (room_id, created_at DESC),
    KEY idx_ai_logs_provider_status (provider, status, created_at),
    KEY idx_ai_logs_recommendation (recommendation_id),
    CONSTRAINT fk_ai_logs_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE SET NULL,
    CONSTRAINT fk_ai_logs_recommendation
        FOREIGN KEY (recommendation_id) REFERENCES ai_recommendations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI API 호출 로그';

-- 13. ai_failover_log --------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_failover_log (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id             VARCHAR(36)     NOT NULL,
    recommendation_id   VARCHAR(36)     NULL,
    primary_model       VARCHAR(50)     NOT NULL,
    failover_model      VARCHAR(50)     NOT NULL,
    reason              VARCHAR(255)    NOT NULL,
    latency_ms          INT UNSIGNED    NULL,
    created_at          DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_failover_room_created (room_id, created_at DESC),
    KEY idx_failover_reason (reason, created_at),
    CONSTRAINT fk_failover_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI Failover 이력';

-- 14. audit_logs -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type      ENUM('agent','customer','system') NOT NULL,
    actor_id        VARCHAR(36)     NULL,
    action          VARCHAR(100)    NOT NULL,
    resource_type   VARCHAR(50)     NULL,
    resource_id     VARCHAR(36)     NULL,
    ip_address      VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,
    metadata        JSON            NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_audit_actor (actor_type, actor_id, created_at DESC),
    KEY idx_audit_action (action, created_at DESC),
    KEY idx_audit_resource (resource_type, resource_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='보안 감사 로그';

-- CRM 브릿지 (06_CRM/01 §5.3 — SSOT 14개 외 보조 테이블) --------------------
CREATE TABLE IF NOT EXISTS customer_bridge (
    acep_customer_id    VARCHAR(36) NOT NULL,
    legacy_customer_id  BIGINT      NOT NULL,
    created_at          DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (acep_customer_id),
    UNIQUE KEY uq_legacy (legacy_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACEP UUID ↔ Legacy CRM customers.id';

-- FK 보완은 migrate.php에서 idempotent 적용

SET foreign_key_checks = 1;
