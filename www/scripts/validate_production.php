<?php
declare(strict_types=1);
/**
 * Production DB 검증 — SSOT MariaDB (Cafe24)
 *
 * Usage: php scripts/validate_production.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../migrations/lib.php';

$pdo = db();
$errors = [];

$requiredTables = [
    'customers', 'chat_rooms', 'chat_messages', 'ai_recommendations', 'chat_read_status',
    'agents', 'chat_room_assignments', 'attachments', 'ai_settings', 'ai_provider_config',
    'ai_prompts', 'ai_logs', 'ai_failover_log', 'audit_logs', 'agent_notifications',
    // Phase 3 CRM
    'sites', 'crm_customers', 'customer_bridge', 'consults', 'consult_history', 'schedules',
];

echo "=== ACEP Production Validation ===\n";
echo 'Database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

foreach ($requiredTables as $table) {
    $exists = acep_table_exists($pdo, $table);
    $count = 0;
    if ($exists) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    $status = $exists ? 'OK' : 'MISSING';
    echo sprintf("[%s] %-24s rows=%d\n", $status, $table, $count);
    if (!$exists) {
        $errors[] = "Missing table: {$table}";
    }
}

$orphaned = (int)$pdo->query(
    'SELECT COUNT(*) FROM chat_messages cm
     LEFT JOIN chat_rooms cr ON cr.id = cm.room_id
     WHERE cr.id IS NULL'
)->fetchColumn();
echo "\nOrphaned messages: {$orphaned}\n";
if ($orphaned > 0) {
    $errors[] = "Orphaned messages: {$orphaned}";
}

$migrationCount = 0;
if (acep_table_exists($pdo, 'acep_migrations')) {
    $migrationCount = (int)$pdo->query('SELECT COUNT(*) FROM acep_migrations')->fetchColumn();
}
echo "Applied migrations (acep_migrations): {$migrationCount}\n";

if ($errors === []) {
    echo "\n✅ Validation PASSED\n";
    exit(0);
}

echo "\n❌ Validation FAILED\n";
foreach ($errors as $e) {
    echo " - {$e}\n";
}
exit(1);
