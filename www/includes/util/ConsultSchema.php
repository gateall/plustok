<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

/**
 * consults / consult_history 레거시 Cafe24 스키마 호환.
 */
final class ConsultSchema
{
    /** @var list<string>|null */
    private static ?array $statusEnumCache = null;

    public static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        return acep_column_exists($pdo, $table, $column);
    }

    public static function resetStatusEnumCache(): void
    {
        self::$statusEnumCache = null;
    }

    /** @return list<string> */
    public static function statusEnumValues(PDO $pdo): array
    {
        if (self::$statusEnumCache !== null) {
            return self::$statusEnumCache;
        }

        $st = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = \'consults\'
               AND column_name = \'status\'
             LIMIT 1'
        );
        $st->execute();
        $type = (string)$st->fetchColumn();
        if (preg_match('/^enum\((.+)\)$/i', $type, $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $vals);
            self::$statusEnumCache = $vals[1];
            return self::$statusEnumCache;
        }
        self::$statusEnumCache = ['new'];
        return self::$statusEnumCache;
    }

    /** 신규 접수 INSERT에 쓸 status — ENUM 정의에 맞춰 동적 선택. */
    public static function initialStatus(PDO $pdo): string
    {
        $values = self::statusEnumValues($pdo);
        foreach (['new', 'receipt', 'consulting', 'progress'] as $preferred) {
            if (in_array($preferred, $values, true)) {
                return $preferred;
            }
        }
        return $values[0] ?? 'new';
    }

    /**
     * 테이블에 실제 존재하는 컬럼만 포함한 INSERT 빌더.
     *
     * @param array<string,mixed> $required
     * @param array<string,mixed> $optional
     * @return array{columns: list<string>, placeholders: list<string>, params: array<string,mixed>}
     */
    public static function buildInsert(
        PDO $pdo,
        string $table,
        array $required,
        array $optional = [],
    ): array {
        $row = [];
        foreach ($required as $column => $value) {
            if (acep_column_exists($pdo, $table, $column)) {
                $row[$column] = $value;
            }
        }
        foreach ($optional as $column => $value) {
            if (acep_column_exists($pdo, $table, $column)) {
                $row[$column] = $value;
            }
        }

        $columns = array_keys($row);
        $placeholders = [];
        $params = [];
        foreach ($columns as $column) {
            $placeholder = ':' . $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $row[$column];
        }

        return [
            'columns'      => $columns,
            'placeholders' => $placeholders,
            'params'       => $params,
        ];
    }

    public static function recordInitialHistory(PDO $pdo, int $consultId, string $status, string $note = '상담 접수'): void
    {
        if (!acep_table_exists($pdo, 'consult_history')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO consult_history (consult_id, from_status, to_status, note)
             VALUES (:cid, NULL, :to, :note)'
        )->execute([
            ':cid'  => $consultId,
            ':to'   => $status,
            ':note' => $note,
        ]);
    }
}
