<?php
declare(strict_types=1);
/**
 * 마이그레이션 실행 — CLI 또는 (관리자 로그인 시) 웹 브라우저로 실행 가능.
 *
 *   php migrations/migrate.php           # DDL only
 *   php migrations/migrate.php --seed    # DDL + V1.5.1 seed SQL
 *   php migrations/migrate.php --check   # legacy CRM 감지 + 상태 출력
 *
 *   웹(super 관리자 로그인 필요):
 *     /migrations/migrate.php?check=1
 *     /migrations/migrate.php?run=1&seed=1
 *
 * 레거시 CRM 공존 (install.php 스키마):
 *   mysql ... < migrations/V1.0.0__legacy_chat_bigint.sql   # chat only, no V0.0
 *   php migrations/migrate.php
 */

define('MIGRATE_IS_CLI', PHP_SAPI === 'cli');
define('MIGRATE_BUILD_MARKER', 'BUILD-20260723-closeCursor-v3');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/lib.php';

if (MIGRATE_IS_CLI) {
    $argv = $argv ?? [];
    $runSeed = in_array('--seed', $argv, true);
    $checkOnly = in_array('--check', $argv, true);
} else {
    // 웹 접근은 로그인한 관리자만 — 무인증 공개 URL로 실제 마이그레이션이 실행되는 것을 막는다.
    // (다른 admin 화면들의 민감 작업 — 사이트 키 재발급, 상품/상담 삭제 — 과 동일하게 super/admin 둘 다 허용)
    require_once __DIR__ . '/../includes/auth.php';
    require_login();
    require_role(['super', 'admin']);
    header('Content-Type: text/plain; charset=utf-8');

    $checkOnly = ($_GET['check'] ?? '') === '1';
    $runSeed = ($_GET['seed'] ?? '') === '1';
    if (!$checkOnly && ($_GET['run'] ?? '') !== '1') {
        echo "웹에서 실제 마이그레이션을 실행하려면 ?run=1 을 붙이세요. 상태만 보려면 ?check=1.\n";
        exit;
    }
}

echo '[marker] ' . MIGRATE_BUILD_MARKER . "\n";

$pdo = db();

if ($checkOnly) {
    report_environment($pdo);
    exit(0);
}

$legacyCrm = acep_is_legacy_crm($pdo);
$chatConflict = acep_legacy_chat_migration_conflict($pdo);
$chatMigration = $legacyCrm ? 'V1.0.0__legacy_chat_bigint.sql' : 'V1.0.0__mvp_core.sql';

$pdo->exec('CREATE TABLE IF NOT EXISTS acep_migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(64) NOT NULL,
    applied_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

// 채팅 스키마가 이미 다른(호환되는) 형태로 떠 있으면, 그 한 파일만 건너뛰고 나머지는 계속 진행한다.
// (기존에는 여기서 전체 스크립트를 abort — 이미 정상 동작 중인 채팅 기능과 무관한 신규
//  마이그레이션까지 막혀버리는 문제가 있었다.)
$files = [];
if ($chatConflict !== null) {
    echo "[skip] {$chatMigration} — 이미 호환되는 채팅 스키마가 적용되어 있어 건너뜁니다.\n";
    echo "       ({$chatConflict})\n";
} else {
    $files[] = $chatMigration;
}

$files = array_merge($files, [
    'V1.5.0__agents_ai_ops.sql',
    'V1.5.3__phase1_v15_tables.sql',
    'V3.0.1__phase3_crm.sql',
    'V3.1.0__contracts.sql',
    'V4.0.0__enterprise_crm_v2_phase1.sql',
    'V4.1.0__enterprise_crm_v2_phase2.sql',
    'V4.2.0__site_field_schema.sql',
    'V4.3.0__products_site_scope.sql',
    'V4.4.0__consult_meta.sql',
]);

foreach ($files as $file) {
    apply_migration_file($pdo, $file);
}

// V3.0.1__phase3_crm.sql에서 분리됨 — install.php 스키마의 sites 테이블엔 use_yn이 없다
// (status를 쓴다). ACEP 그린필드용 시드 row이므로 use_yn 컬럼이 실제로 있을 때만 넣는다.
if (acep_column_exists($pdo, 'sites', 'use_yn')) {
    $pdo->exec(
        "INSERT IGNORE INTO sites (id, site_code, site_name, brand, use_yn)
         VALUES (1, 'acep-default', 'PlusTok ACEP', 'PlusTok', 1)"
    );
    echo "[ok]  sites acep-default seed row\n";
} else {
    echo "[skip] sites acep-default seed row (install.php 스키마 — use_yn 컬럼 없음)\n";
}

// V1.5.3__phase1_v15_tables.sql에서 분리됨 — MySQL PREPARE/EXECUTE 동적 SQL이 PDO exec()와
// 충돌하는 조합이 있어 컬럼 존재 체크를 PHP 쪽에서 한다.
acep_add_column_if_missing(
    $pdo,
    'agents',
    'settings_json',
    "ALTER TABLE agents ADD COLUMN settings_json JSON NULL COMMENT 'Agent UI preferences' AFTER avatar_url"
);
echo "[ok]  agents.settings_json column\n";

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

/** CLI에서는 STDERR로, 웹(STDERR 상수 미정의)에서는 echo로 출력 후 종료. */
function migrate_fail(string $msg): never
{
    if (MIGRATE_IS_CLI) {
        fwrite(STDERR, $msg);
    } else {
        echo $msg;
    }
    exit(1);
}

function apply_migration_file(PDO $pdo, string $file): void
{
    $version = pathinfo($file, PATHINFO_FILENAME);
    if (migration_applied($pdo, $version)) {
        echo "[skip] {$version}\n";
        return;
    }

    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) {
        migrate_fail("Missing: {$path}\n");
    }

    echo "[run] {$version}...\n";
    try {
        acep_run_sql_file($pdo, (string)file_get_contents($path));
        apply_migration_record($pdo, $version);
        echo "[ok]  {$version}\n";
    } catch (Throwable $e) {
        migrate_fail("[fail] {$version}: {$e->getMessage()}\n");
    }
}

function apply_fk_constraints(PDO $pdo): void
{
    if (migration_applied($pdo, 'V1.5.0__fk_constraints')) {
        echo "[skip] V1.5.0__fk_constraints\n";
        return;
    }

    echo "[run] V1.5.0__fk_constraints...\n";
    // 채팅 스키마가 여러 세대(레거시 BIGINT / ACEP UUID)를 거치며 컬럼 타입이 어긋나 FK 생성이
    // 실패할 수 있다. 이건 참조무결성 보강용 부가 제약일 뿐 기능 동작에 필수가 아니므로,
    // 하나 실패해도 전체 마이그레이션을 막지 않고 경고만 남긴다.
    try {
        acep_add_fk_if_missing(
            $pdo,
            'chat_rooms',
            'fk_chat_rooms_agent',
            'ALTER TABLE chat_rooms ADD CONSTRAINT fk_chat_rooms_agent
             FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL'
        );
    } catch (Throwable $e) {
        echo "[warn] fk_chat_rooms_agent skipped: {$e->getMessage()}\n";
    }
    try {
        acep_add_fk_if_missing(
            $pdo,
            'chat_messages',
            'fk_chat_messages_attachment',
            'ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_attachment
             FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE SET NULL'
        );
    } catch (Throwable $e) {
        echo "[warn] fk_chat_messages_attachment skipped: {$e->getMessage()}\n";
    }
    apply_migration_record($pdo, 'V1.5.0__fk_constraints');
    echo "[ok]  V1.5.0__fk_constraints (경고 있었으면 위 참고)\n";
}

function migration_applied(PDO $pdo, string $version): bool
{
    $chk = $pdo->prepare('SELECT 1 FROM acep_migrations WHERE version = :v LIMIT 1');
    $chk->execute([':v' => $version]);
    $result = (bool)$chk->fetchColumn();
    $chk->closeCursor();
    return $result;
}

function apply_migration_record(PDO $pdo, string $version): void
{
    $pdo->prepare('INSERT IGNORE INTO acep_migrations (version) VALUES (:v)')
        ->execute([':v' => $version]);
}

function report_environment(PDO $pdo): void
{
    echo "=== ACEP Migration Check ===\n";
    $legacy = acep_is_legacy_crm($pdo);
    echo 'Legacy CRM (customers.customer_no): ' . ($legacy ? 'YES — use V1.0.0__legacy_chat_bigint.sql' : 'NO') . "\n";
    echo 'crm_customers exists: ' . (acep_table_exists($pdo, 'crm_customers') ? 'YES' : 'NO') . "\n";
    echo 'crm_attachments exists: ' . (acep_table_exists($pdo, 'crm_attachments') ? 'YES' : 'NO') . "\n";
    $custType = acep_chat_room_customer_id_type($pdo);
    if ($custType !== null) {
        echo "chat_rooms.customer_id type: {$custType}\n";
    }
    $conflict = acep_legacy_chat_migration_conflict($pdo);
    if ($conflict !== null) {
        echo "CONFLICT: {$conflict}\n";
    }

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
