-- ACEP V3.0 — V1.0 MVP Core (5 tables)
-- SSOT: 03_SYSTEM/01_DB설계.md §7.1
-- agent_id FK, attachment_id FK 는 V1.5에서 추가

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- 1. customers (ACEP UUID) -------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id              VARCHAR(36)     NOT NULL,
    name            VARCHAR(100)    NOT NULL,
    phone           VARCHAR(512)    NOT NULL COMMENT 'AES-256-GCM encrypted',
    phone_hash      CHAR(64)        NULL COMMENT 'SHA-256 for lookup',
    email           VARCHAR(512)    NULL COMMENT 'AES-256-GCM encrypted',
    address         TEXT            NULL COMMENT 'AES-256-GCM encrypted',
    tags            JSON            NULL,
    consultation_count INT UNSIGNED NOT NULL DEFAULT 0,
    external_crm_id VARCHAR(100)    NULL,
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    deleted_at      DATETIME(3)     NULL,
    PRIMARY KEY (id),
    KEY idx_customers_phone_hash (phone_hash),
    KEY idx_customers_created_at (created_at),
    KEY idx_customers_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACEP 고객 — CustomerCard';

-- 2. chat_rooms (agent FK는 V1.5) ------------------------------------------
CREATE TABLE IF NOT EXISTS chat_rooms (
    id              VARCHAR(36)     NOT NULL,
    customer_id     VARCHAR(36)     NOT NULL,
    agent_id        VARCHAR(36)     NULL,
    inquiry_type    VARCHAR(50)     NOT NULL,
    status          ENUM('new','active','closed') NOT NULL DEFAULT 'new',
    channel         VARCHAR(30)     NOT NULL DEFAULT 'web',
    subject         VARCHAR(200)    NULL,
    memo            TEXT            NULL,
    priority_score  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 contract cache',
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    closed_at       DATETIME(3)     NULL,
    deleted_at      DATETIME(3)     NULL,
    PRIMARY KEY (id),
    KEY idx_chat_rooms_status_updated (status, updated_at DESC),
    KEY idx_chat_rooms_customer (customer_id),
    KEY idx_chat_rooms_agent (agent_id),
    KEY idx_chat_rooms_priority (priority_score DESC, updated_at DESC),
    CONSTRAINT fk_chat_rooms_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='상담방 — ChatList';

-- 3. chat_messages (attachment FK는 V1.5) ------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
    id                      VARCHAR(36)     NOT NULL,
    room_id                 VARCHAR(36)     NOT NULL,
    sender_type             ENUM('customer','agent','system') NOT NULL,
    sender_id               VARCHAR(36)     NOT NULL,
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

-- 4. ai_recommendations -----------------------------------------------------
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

-- 5. chat_read_status --------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_read_status (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id         VARCHAR(36)     NOT NULL,
    message_id      VARCHAR(36)     NOT NULL,
    reader_type     ENUM('customer','agent') NOT NULL,
    reader_id       VARCHAR(36)     NOT NULL,
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
