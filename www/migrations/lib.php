<?php
declare(strict_types=1);
/**
 * ACEP migration 공통 유틸.
 */

function acep_run_sql_file(PDO $pdo, string $sql): void
{
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET foreign_key_checks = 0');
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(trim($line), ';')) {
            $stmt = trim($buffer);
            $buffer = '';
            if ($stmt !== '') {
                $pdo->exec($stmt);
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
    return (bool)$st->fetchColumn();
}

function acep_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1'
    );
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool)$st->fetchColumn();
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
    return (bool)$st->fetchColumn();
}

function acep_add_fk_if_missing(PDO $pdo, string $table, string $constraint, string $ddl): void
{
    if (!acep_fk_exists($pdo, $table, $constraint)) {
        $pdo->exec($ddl);
    }
}
