<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

/**
 * sites 테이블 스키마 호환 — 레거시(status) / ACEP(use_yn).
 */
final class SiteSchema
{
    private static ?string $activeColumn = null;

    /** 활성 여부 컬럼명. 없으면 빈 문자열(필터 생략). */
    public static function activeColumn(PDO $pdo): string
    {
        if (self::$activeColumn !== null) {
            return self::$activeColumn;
        }
        if (acep_column_exists($pdo, 'sites', 'status')) {
            return self::$activeColumn = 'status';
        }
        if (acep_column_exists($pdo, 'sites', 'use_yn')) {
            return self::$activeColumn = 'use_yn';
        }
        return self::$activeColumn = '';
    }

    /** SQL WHERE fragment: "status = 1" | "use_yn = 1" | "1=1" */
    public static function activeSql(PDO $pdo, string $alias = ''): string
    {
        $col = self::activeColumn($pdo);
        if ($col === '') {
            return '1=1';
        }
        $prefix = $alias !== '' ? $alias . '.' : '';
        return $prefix . $col . ' = 1';
    }

    public static function isActive(array $site, PDO $pdo): bool
    {
        $col = self::activeColumn($pdo);
        if ($col === '') {
            return true;
        }
        return (int)($site[$col] ?? 0) === 1;
    }

    public static function hasDomain(PDO $pdo): bool
    {
        return acep_column_exists($pdo, 'sites', 'domain');
    }
}
