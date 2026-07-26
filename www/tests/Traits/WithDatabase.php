<?php
declare(strict_types=1);

namespace Tests\Traits;

use PDO;

trait WithDatabase
{
    protected static bool $schemaReady = false;
    protected static string $schemaProfile = '';

    /** @return 'acep'|'legacy' */
    protected function migrationProfile(): string
    {
        return 'acep';
    }

    protected function ensureSchema(): PDO
    {
        $cfgPath = dirname(__DIR__, 2) . '/config/database.test.php';
        if (!is_file($cfgPath)) {
            $this->markTestSkipped('config/database.test.php 없음 — example 복사 후 테스트 DB 설정');
        }

        require_once dirname(__DIR__, 2) . '/migrations/lib.php';
        require_once dirname(__DIR__, 2) . '/includes/db.php';

        $profile = $this->migrationProfile();
        \db_reset();
        $pdo = $this->freshPdo();
        if (!self::$schemaReady || self::$schemaProfile !== $profile) {
            if (self::$schemaProfile !== '' && self::$schemaProfile !== $profile) {
                $this->resetSchemaForProfileSwitch($pdo);
            }
            $this->runMigrations($pdo, $profile);
            self::$schemaReady = true;
            self::$schemaProfile = $profile;
        }
        \db_reset();
        $pdo = \db();
        $this->truncateTables($pdo, $profile);
        return $pdo;
    }

    protected function freshPdo(): PDO
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.test.php';
        $port = (int) ($cfg['port'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $port, $cfg['name'], $cfg['charset']);
        try {
            return new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        } catch (\PDOException $e) {
            $this->markTestSkipped(
                '테스트 DB 연결 실패 (' . $cfg['host'] . '/' . $cfg['name'] . '): ' . $e->getMessage()
                . ' — config/database.test.php 및 acep_test DB를 확인하세요.'
            );
        }
    }

    protected function resetSchemaForProfileSwitch(PDO $pdo): void
    {
        $pdo->exec('SET foreign_key_checks = 0');
        foreach ([
            'contract_payments', 'contracts',
            'ai_failover_log', 'ai_logs', 'ai_recommendations', 'chat_read_status',
            'chat_messages', 'chat_room_assignments', 'attachments', 'chat_rooms',
            'customer_bridge', 'consult_history', 'consults', 'crm_customers', 'customers',
            'sites', 'products', 'schedules_dedup_guard', 'schedules',
        ] as $table) {
            if (acep_table_exists($pdo, $table)) {
                $pdo->exec('DROP TABLE `' . $table . '`');
            }
        }
        $pdo->exec('SET foreign_key_checks = 1');
    }

    protected function runMigrations(PDO $pdo, string $profile): void
    {
        $dir = dirname(__DIR__, 2) . '/migrations';
        if ($profile === 'acep'
            && acep_table_exists($pdo, 'customers')
            && !acep_column_exists($pdo, 'customers', 'phone_hash')) {
            $this->resetSchemaForProfileSwitch($pdo);
        }
        if ($profile === 'legacy'
            && acep_table_exists($pdo, 'customers')
            && !acep_column_exists($pdo, 'customers', 'customer_no')) {
            $this->resetSchemaForProfileSwitch($pdo);
        }
        $files = $profile === 'legacy'
            ? [
                'legacy_crm_bootstrap.sql',
                'V1.0.0__legacy_chat_bigint.sql',
                'V1.5.0__agents_ai_ops.sql',
                'V1.5.3__phase1_v15_tables.sql',
            ]
            : [
                'V1.0.0__mvp_core.sql',
                'V1.5.0__agents_ai_ops.sql',
                'V1.5.3__phase1_v15_tables.sql',
                'V3.0.1__phase3_crm.sql',
                'V3.1.0__contracts.sql',
            ];

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                acep_run_sql_file($pdo, file_get_contents($path));
            }
        }

        if ($profile === 'acep') {
            require_once $dir . '/phase3_chat_rooms.php';
            acep_migrate_phase3_chat_rooms($pdo);
            acep_add_column_if_missing(
                $pdo,
                'agents',
                'settings_json',
                "ALTER TABLE agents ADD COLUMN settings_json JSON NULL COMMENT 'Agent UI preferences' AFTER avatar_url"
            );
        }
    }

    protected function truncateTables(PDO $pdo, string $profile): void
    {
        $pdo->exec('SET foreign_key_checks = 0');
        $tables = [
            'contract_payments', 'contracts',
            'agent_notifications', 'ai_failover_log', 'ai_logs', 'ai_recommendations',
            'schedules_dedup_guard', 'schedules', 'consult_history', 'consults',
            'customer_bridge', 'sites',
            'chat_read_status', 'chat_messages', 'chat_room_assignments', 'attachments',
            'chat_rooms', 'customers', 'audit_logs', 'agents',
        ];
        if ($profile === 'acep') {
            $tables[] = 'crm_customers';
        }
        foreach ($tables as $table) {
            if (acep_table_exists($pdo, $table)) {
                $pdo->exec('TRUNCATE TABLE `' . $table . '`');
            }
        }
        if ($profile === 'legacy' && acep_table_exists($pdo, 'crm_customers')) {
            $pdo->exec('DROP TABLE `crm_customers`');
        }
        $pdo->exec('SET foreign_key_checks = 1');
        $this->restoreConsultsStatusEnum($pdo, $profile);
        $this->seedDefaultSite($pdo);
    }

    protected function restoreConsultsStatusEnum(PDO $pdo, string $profile): void
    {
        if (!acep_table_exists($pdo, 'consults')) {
            return;
        }
        require_once dirname(__DIR__, 2) . '/includes/util/ConsultSchema.php';
        \ConsultSchema::resetStatusEnumCache();
        $expected = ['new', 'progress', 'consulting', 'quoted', 'contracted', 'installed', 'hold', 'canceled'];
        if (\ConsultSchema::statusEnumValues($pdo) === $expected) {
            return;
        }
        $enum = "ENUM('new','progress','consulting','quoted','contracted','installed','hold','canceled') NOT NULL";
        $default = $profile === 'legacy' ? "'new'" : "'consulting'";
        $pdo->exec("ALTER TABLE consults MODIFY status {$enum} DEFAULT {$default}");
        \ConsultSchema::resetStatusEnumCache();
    }

    protected function seedDefaultSite(PDO $pdo): void
    {
        if (!acep_table_exists($pdo, 'sites')) {
            return;
        }
        if (acep_column_exists($pdo, 'sites', 'use_yn')) {
            $pdo->exec(
                "INSERT IGNORE INTO sites (id, site_code, site_name, brand, use_yn)
                 VALUES (1, 'acep-default', 'ACEP Default', 'PlusTok', 1)"
            );
            return;
        }
        if (acep_column_exists($pdo, 'sites', 'status')) {
            $pdo->exec(
                "INSERT IGNORE INTO sites (id, site_code, site_name, brand, status)
                 VALUES (1, 'acep-default', 'ACEP Default', 'PlusTok', 'active')"
            );
        }
    }

    protected function seedAdmin(PDO $pdo): array
    {
        $id = '11111111-1111-4111-8111-111111111111';
        $hash = password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 4]);
        $pdo->prepare('DELETE FROM agents WHERE id = :id')->execute([':id' => $id]);
        $pdo->prepare(
            'REPLACE INTO agents (id, login_id, password_hash, name, role, status)
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
