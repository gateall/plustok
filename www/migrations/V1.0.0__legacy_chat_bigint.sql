-- ACEP Legacy Chat — chat tables referencing customers(id) BIGINT
-- Safe path: DO NOT run V0.0__legacy_prepare.sql or V1.0.0__mvp_core.sql on legacy CRM.
-- Prerequisites: legacy customers (BIGINT id, customer_no), agents (V1.5.0) optional for agent FK.
-- Room id stays VARCHAR(36) UUID for frontend/socket compatibility.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- 1. chat_rooms -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_rooms (
    id                  VARCHAR(36)      NOT NULL,
    customer_id         BIGINT           NOT NULL COMMENT 'legacy customers.id',
    agent_id            VARCHAR(36)      NULL,
    inquiry_type        VARCHAR(50)      NOT NULL,
    status              ENUM('new','active','closed') NOT NULL DEFAULT 'new',
    channel             VARCHAR(30)      NOT NULL DEFAULT 'web',
    subject             VARCHAR(200)     NULL,
    memo                TEXT             NULL,
    priority_score      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 contract cache',
    legacy_consult_id   BIGINT           NULL COMMENT 'consults.id link',
    crm_save_status     ENUM('pending','saved','failed') NOT NULL DEFAULT 'pending',
    crm_saved_at        DATETIME(3)      NULL,
    created_at          DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at          DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    closed_at           DATETIME(3)      NULL,
    deleted_at          DATETIME(3)      NULL,
    PRIMARY KEY (id),
    KEY idx_chat_rooms_status_updated (status, updated_at DESC),
    KEY idx_chat_rooms_customer (customer_id),
    KEY idx_chat_rooms_agent (agent_id),
    KEY idx_chat_rooms_priority (priority_score DESC, updated_at DESC),
    KEY idx_chat_rooms_legacy_consult (legacy_consult_id),
    KEY idx_chat_rooms_crm (crm_save_status),
    CONSTRAINT fk_chat_rooms_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='상담방 — legacy customers BIGINT FK';

-- 2. chat_messages ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
    id                      VARCHAR(36)     NOT NULL,
    room_id                 VARCHAR(36)     NOT NULL,
    sender_type             ENUM('customer','agent','system') NOT NULL,
    sender_id               VARCHAR(36)     NOT NULL COMMENT 'legacy customer id as string or agent UUID',
    content                 TEXT            NOT NULL,
    attachment_url          VARCHAR(500)    NULL,
    attachment_id           VARCHAR(36)     NULL,
    attachment_type         VARCHAR(20)     NULL,
    source                  ENUM('manual','ai_recommendation') NOT NULL DEFAULT 'manual',
    ai_recommendation_id    VARCHAR(36)     NULL,
    created_at              DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    deleted_at              DATETIME(3)     NULL,
    PRIMARY KEY (id),
    KEY idx_chat_messages_room_created (room_id, created_at DESC),
    KEY idx_chat_messages_sender (sender_type, sender_id),
    KEY idx_chat_messages_attachment (attachment_id),
    CONSTRAINT fk_chat_messages_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='채팅 메시지 — MessageBubble';

-- 3. ai_recommendations -------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_recommendations (
    id                      VARCHAR(36)     NOT NULL,
    room_id                 VARCHAR(36)     NOT NULL,
    type                    ENUM('answer','faq','analysis','contract') NOT NULL DEFAULT 'answer',
    content                 JSON            NOT NULL,
    contract_probability    TINYINT UNSIGNED NULL,
    sentiment               ENUM('positive','neutral','negative') NULL,
    intent                  VARCHAR(30)     NULL,
    status                  ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    ai_model                VARCHAR(50)     NULL,
    prompt_version          VARCHAR(20)     NULL,
    latency_ms              INT UNSIGNED    NULL,
    created_at              DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_ai_recommendations_room_created (room_id, created_at DESC),
    KEY idx_ai_recommendations_status (room_id, status),
    KEY idx_ai_recommendations_contract (contract_probability DESC),
    CONSTRAINT fk_ai_recommendations_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI 추천 — AIPanelCard';

-- 4. chat_read_status ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_read_status (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id         VARCHAR(36)     NOT NULL,
    message_id      VARCHAR(36)     NOT NULL,
    reader_type     ENUM('customer','agent') NOT NULL,
    reader_id       VARCHAR(36)     NOT NULL COMMENT 'legacy customer id as string or agent UUID',
    delivered_at    DATETIME(3)     NULL,
    read_at         DATETIME(3)     NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_read_status (message_id, reader_type, reader_id),
    KEY idx_read_status_room (room_id),
    KEY idx_read_status_reader (reader_type, reader_id, read_at),
    CONSTRAINT fk_read_status_room
        FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    CONSTRAINT fk_read_status_message
        FOREIGN KEY (message_id) REFERENCES chat_messages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='읽음 표시 — MessageBubble';

SET foreign_key_checks = 1;
