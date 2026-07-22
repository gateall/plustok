<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

/**
 * chat_rooms CRM 컬럼 — MariaDB 버전별 IF NOT EXISTS 미지원 대비 PHP 마이그레이션.
 */
function acep_migrate_phase3_chat_rooms(PDO $pdo): void
{
    if (!acep_table_exists($pdo, 'chat_rooms')) {
        return;
    }
    if (!acep_column_exists($pdo, 'chat_rooms', 'legacy_consult_id')) {
        $pdo->exec(
            "ALTER TABLE chat_rooms
             ADD COLUMN legacy_consult_id BIGINT NULL COMMENT 'consults.id after CRM save'"
        );
    }
    if (!acep_column_exists($pdo, 'chat_rooms', 'crm_save_status')) {
        $pdo->exec(
            "ALTER TABLE chat_rooms
             ADD COLUMN crm_save_status ENUM('pending','saved','failed') NOT NULL DEFAULT 'pending'"
        );
    }
    if (!acep_column_exists($pdo, 'chat_rooms', 'crm_saved_at')) {
        $pdo->exec(
            'ALTER TABLE chat_rooms ADD COLUMN crm_saved_at DATETIME(3) NULL'
        );
    }
    if (!acep_column_exists($pdo, 'chat_rooms', 'crm_save_status')) {
        return;
    }
    try {
        $pdo->exec('CREATE INDEX idx_chat_rooms_crm ON chat_rooms (crm_save_status)');
    } catch (Throwable) {
        // index may already exist
    }
}
