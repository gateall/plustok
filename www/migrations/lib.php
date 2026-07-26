<?php
declare(strict_types=1);
/**
 * ACEP migration 공통 유틸.
 */

function acep_run_sql_file(PDO $pdo, string $sql): void
{
    // Normalize Windows/old-Mac line endings before split (bare \r mid-file breaks comment lines).
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET foreign_key_checks = 0');
    $buffer = '';
    foreach (preg_split('/\R/u', $sql) as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(trim($line), ';')) {
            $stmt = trim($buffer);
            $buffer = '';
            if ($stmt !== '') {
                $pdo->query($stmt)->closeCursor();
            }
        }
    }
    $pdo->exec('SET foreign_key_checks = 1');
}

function acep_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
    );
    $st->execute([':t' => $table]);
    $result = (bool)$st->fetchColumn();
    $st->closeCursor();
    return $result;
}

function acep_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1'
    );
    $st->execute([':t' => $table, ':c' => $column]);
    $result = (bool)$st->fetchColumn();
    $st->closeCursor();
    return $result;
}

/** install.php 레거시 CRM 스키마 여부 (customers.customer_no) */
function acep_is_legacy_crm(PDO $pdo): bool
{
    return acep_table_exists($pdo, 'customers')
        && acep_column_exists($pdo, 'customers', 'customer_no');
}

function acep_fk_exists(PDO $pdo, string $table, string $constraint): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.table_constraints
         WHERE table_schema = DATABASE()
           AND table_name = :t
           AND constraint_name = :c
           AND constraint_type = \'FOREIGN KEY\'
         LIMIT 1'
    );
    $st->execute([':t' => $table, ':c' => $constraint]);
    $result = (bool)$st->fetchColumn();
    $st->closeCursor();
    return $result;
}

function acep_add_fk_if_missing(PDO $pdo, string $table, string $constraint, string $ddl): void
{
    if (!acep_fk_exists($pdo, $table, $constraint)) {
        $pdo->exec($ddl);
    }
}

/** ALTER TABLE ADD COLUMN을 PHP 쪽에서 조건 체크 후 실행 (MySQL PREPARE/EXECUTE 동적 SQL 대신). */
function acep_add_column_if_missing(PDO $pdo, string $table, string $column, string $ddl): void
{
    if (!acep_column_exists($pdo, $table, $column)) {
        $pdo->exec($ddl);
    }
}

/** chat_rooms.customer_id column data type (e.g. bigint, varchar) */
function acep_chat_room_customer_id_type(PDO $pdo): ?string
{
    if (!acep_table_exists($pdo, 'chat_rooms')) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT DATA_TYPE FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = \'chat_rooms\'
           AND column_name = \'customer_id\'
         LIMIT 1'
    );
    $st->execute();
    $type = $st->fetchColumn();
    $st->closeCursor();
    return $type !== false ? strtolower((string)$type) : null;
}

/** Legacy BIGINT chat schema expected; returns error message when UUID chat migration was applied. */
function acep_legacy_chat_migration_conflict(PDO $pdo): ?string
{
    $type = acep_chat_room_customer_id_type($pdo);
    if ($type === null) {
        return null;
    }
    if ($type === 'varchar' || $type === 'char') {
        return 'chat_rooms.customer_id is ' . strtoupper($type)
            . ' (UUID ACEP migration). Drop chat_rooms/chat_messages/chat_read_status/ai_recommendations'
            . ' or recreate with V1.0.0__legacy_chat_bigint.sql before using legacy consult chat.';
    }
    return null;
}

function acep_uses_legacy_chat_customers(PDO $pdo): bool
{
    if (acep_is_legacy_crm($pdo)) {
        return true;
    }
    $type = acep_chat_room_customer_id_type($pdo);
    return $type !== null && $type !== 'varchar' && $type !== 'char';
}

/** @return list<string>|null ENUM members, or null when column is not ENUM */
function acep_column_enum_values(PDO $pdo, string $table, string $column): ?array
{
    if (!acep_column_exists($pdo, $table, $column)) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :t AND column_name = :c LIMIT 1'
    );
    $st->execute([':t' => $table, ':c' => $column]);
    $type = $st->fetchColumn();
    $st->closeCursor();
    if (!is_string($type) || !preg_match("/^enum\\('(.+)'\\)$/i", $type, $m)) {
        return null;
    }
    return array_map(
        static fn(string $v): string => str_replace("''", "'", $v),
        explode("','", $m[1])
    );
}

/** Pick the first candidate present in an ENUM column (falls back to first ENUM member). */
function acep_resolve_enum_value(PDO $pdo, string $table, string $column, string ...$candidates): ?string
{
    $values = acep_column_enum_values($pdo, $table, $column);
    if ($values === null) {
        return $candidates[0] ?? null;
    }
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $values, true)) {
            return $candidate;
        }
    }
    return $values[0] ?? null;
}

/** NOT NULL column with no DEFAULT that must be supplied on INSERT. */
function acep_column_requires_insert_value(PDO $pdo, string $table, string $column): bool
{
    if (!acep_column_exists($pdo, $table, $column)) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :t AND column_name = :c LIMIT 1'
    );
    $st->execute([':t' => $table, ':c' => $column]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $st->closeCursor();
    if (!$row) {
        return false;
    }
    if (strtoupper((string)$row['IS_NULLABLE']) === 'YES') {
        return false;
    }
    if ($row['COLUMN_DEFAULT'] !== null) {
        return false;
    }
    if (stripos((string)$row['EXTRA'], 'auto_increment') !== false) {
        return false;
    }
    return true;
}
