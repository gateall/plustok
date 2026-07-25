<?php
declare(strict_types=1);
/**
 * CLI 마이그레이션 실행
 *
 *   php migrations/migrate.php           # DDL only
 *   php migrations/migrate.php --seed    # DDL + V1.5.1 seed SQL
 *   php migrations/migrate.php --check   # legacy CRM 감지 + 상태 출력
 *
 * 레거시 CRM 공존 (install.php 스키마):
 *   mysql ... < migrations/V0.0__legacy_prepare.sql   # 1회, 백업 필수
 *   php migrations/migrate.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/lib.php';

$argv = $argv ?? [];
$runSeed = in_array('--seed', $argv, true);
$checkOnly = in_array('--check', $argv, true);

$pdo = db();

if ($checkOnly) {
    report_environment($pdo);
    exit(0);
}

if (acep_is_legacy_crm($pdo)) {
    fwrite(STDERR, "[abort] Legacy CRM detected (customers.customer_no).\n");
    fwrite(STDERR, "        Run V0.0__legacy_prepare.sql first (backup required).\n");
    exit(1);
}

$pdo->exec('CREATE TABLE IF NOT EXISTS acep_migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(64) NOT NULL,
    applied_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$files = [
    'V1.0.0__mvp_core.sql',
    'V1.5.0__agents_ai_ops.sql',
    'V1.5.3__phase1_v15_tables.sql',
    'V3.0.1__phase3_crm.sql',
    'V3.1.0__contracts.sql',
];

foreach ($files as $file) {
    apply_migration_file($pdo, $file);
}

require_once __DIR__ . '/phase3_chat_rooms.php';
acep_migrate_phase3_chat_rooms($pdo);
echo "[ok]  phase3_chat_rooms columns\n";

apply_fk_constraints($pdo);

if ($runSeed) {
    require __DIR__ . '/seed.php';
} else {
    echo "Tip: run with --seed for AI settings + admin account.\n";
}

echo "Done.\n";

function apply_migration_file(PDO $pdo, string $file): void
{
    $version = pathinfo($file, PATHINFO_FILENAME);
    if (migration_applied($pdo, $version)) {
        echo "[skip] {$version}\n";
        return;
    }

    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing: {$path}\n");
        exit(1);
    }

    echo "[run] {$version}...\n";
    try {
        acep_run_sql_file($pdo, (string)file_get_contents($path));
        apply_migration_record($pdo, $version);
        echo "[ok]  {$version}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "[fail] {$version}: {$e->getMessage()}\n");
        exit(1);
    }
}

function apply_fk_constraints(PDO $pdo): void
{
    if (migration_applied($pdo, 'V1.5.0__fk_constraints')) {
        echo "[skip] V1.5.0__fk_constraints\n";
        return;
    }

    echo "[run] V1.5.0__fk_constraints...\n";
    try {
        acep_add_fk_if_missing(
            $pdo,
            'chat_rooms',
            'fk_chat_rooms_agent',
            'ALTER TABLE chat_rooms ADD CONSTRAINT fk_chat_rooms_agent
             FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL'
        );
        acep_add_fk_if_missing(
            $pdo,
            'chat_messages',
            'fk_chat_messages_attachment',
            'ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_attachment
             FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE SET NULL'
        );
        apply_migration_record($pdo, 'V1.5.0__fk_constraints');
        echo "[ok]  V1.5.0__fk_constraints\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "[fail] V1.5.0__fk_constraints: {$e->getMessage()}\n");
        exit(1);
    }
}

function migration_applied(PDO $pdo, string $version): bool
{
    $chk = $pdo->prepare('SELECT 1 FROM acep_migrations WHERE version = :v LIMIT 1');
    $chk->execute([':v' => $version]);
    return (bool)$chk->fetchColumn();
}

function apply_migration_record(PDO $pdo, string $version): void
{
    $pdo->prepare('INSERT IGNORE INTO acep_migrations (version) VALUES (:v)')
        ->execute([':v' => $version]);
}

function report_environment(PDO $pdo): void
{
    echo "=== ACEP Migration Check ===\n";
    echo 'Legacy CRM (customers.customer_no): ' . (acep_is_legacy_crm($pdo) ? 'YES — run V0.0 first' : 'NO') . "\n";
    echo 'crm_customers exists: ' . (acep_table_exists($pdo, 'crm_customers') ? 'YES' : 'NO') . "\n";
    echo 'crm_attachments exists: ' . (acep_table_exists($pdo, 'crm_attachments') ? 'YES' : 'NO') . "\n";

    $acepTables = [
        'customers', 'chat_rooms', 'chat_messages', 'ai_recommendations', 'chat_read_status',
        'agents', 'chat_room_assignments', 'attachments', 'ai_settings', 'ai_provider_config',
        'ai_prompts', 'ai_logs', 'ai_failover_log', 'audit_logs',
    ];
    $found = 0;
    foreach ($acepTables as $t) {
        if (acep_table_exists($pdo, $t)) {
            $found++;
        }
    }
    echo "ACEP SSOT tables present: {$found}/14\n";

    if (acep_table_exists($pdo, 'acep_migrations')) {
        $rows = $pdo->query('SELECT version, applied_at FROM acep_migrations ORDER BY id')->fetchAll();
        echo "Applied migrations:\n";
        foreach ($rows as $r) {
            echo "  - {$r['version']} @ {$r['applied_at']}\n";
        }
    }
}
