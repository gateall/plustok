<?php
declare(strict_types=1);

namespace Tests\Traits;

use PDO;

trait WithDatabase
{
    protected static bool $schemaReady = false;

    protected function ensureSchema(): PDO
    {
        $cfgPath = dirname(__DIR__, 2) . '/config/database.test.php';
        if (!is_file($cfgPath)) {
            $this->markTestSkipped('config/database.test.php 없음 — example 복사 후 테스트 DB 설정');
        }

        require_once dirname(__DIR__, 2) . '/migrations/lib.php';

        $pdo = $this->freshPdo();
        if (!self::$schemaReady) {
            $this->runMigrations($pdo);
            self::$schemaReady = true;
        }
        $this->truncateTables($pdo);
        return $pdo;
    }

    protected function freshPdo(): PDO
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.test.php';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset']);
        try {
            return new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            $this->markTestSkipped(
                '테스트 DB 연결 실패 (' . $cfg['host'] . '/' . $cfg['name'] . '): ' . $e->getMessage()
                . ' — config/database.test.php 및 acep_test DB를 확인하세요.'
            );
        }
    }

    protected function runMigrations(PDO $pdo): void
    {
        $dir = dirname(__DIR__, 2) . '/migrations';
        foreach (['V1.0.0__mvp_core.sql', 'V1.5.0__agents_ai_ops.sql', 'V1.5.3__phase1_v15_tables.sql', 'V3.0.1__phase3_crm.sql', 'V3.1.0__contracts.sql'] as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                acep_run_sql_file($pdo, file_get_contents($path));
            }
        }
        require_once $dir . '/phase3_chat_rooms.php';
        acep_migrate_phase3_chat_rooms($pdo);
    }

    protected function truncateTables(PDO $pdo): void
    {
        $pdo->exec('SET foreign_key_checks = 0');
        foreach ([
            'contract_payments', 'contracts',
            'agent_notifications', 'ai_failover_log', 'ai_logs', 'ai_recommendations',
            'schedules_dedup_guard', 'schedules', 'consult_history', 'consults',
            'customer_bridge', 'crm_customers', 'sites',
            'chat_read_status', 'chat_messages', 'chat_room_assignments', 'attachments',
            'chat_rooms', 'customers', 'audit_logs', 'agents',
        ] as $table) {
            if (acep_table_exists($pdo, $table)) {
                $pdo->exec('TRUNCATE TABLE `' . $table . '`');
            }
        }
        $pdo->exec('SET foreign_key_checks = 1');
    }

    protected function seedAdmin(PDO $pdo): array
    {
        $id = '11111111-1111-4111-8111-111111111111';
        $hash = password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 4]);
        $pdo->prepare(
            'INSERT INTO agents (id, login_id, password_hash, name, role, status)
             VALUES (:id, :login, :hash, :name, :role, :status)'
        )->execute([
            ':id'     => $id,
            ':login'  => 'admin',
            ':hash'   => $hash,
            ':name'   => 'Test Admin',
            ':role'   => 'admin',
            ':status' => 'online',
        ]);
        return ['id' => $id, 'loginId' => 'admin', 'password' => 'Admin123!', 'role' => 'admin'];
    }
}
