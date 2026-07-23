<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

/**
 * products 테이블 스키마 호환 — 레거시(status) / install.php(use_yn).
 */
final class ProductSchema
{
    /** SQL WHERE fragment: "use_yn = 1" | "status = 1" | "1=1" */
    public static function activeSql(PDO $pdo, string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if (acep_column_exists($pdo, 'products', 'use_yn')) {
            return $prefix . 'use_yn = 1';
        }
        if (acep_column_exists($pdo, 'products', 'status')) {
            return $prefix . 'status = 1';
        }
        return '1=1';
    }

    /** site_id 컬럼 존재 여부 (V4.3.0 미적용 환경에서는 false로 안전 폴백). */
    public static function hasSiteScope(PDO $pdo): bool
    {
        return acep_column_exists($pdo, 'products', 'site_id');
    }

    /**
     * SQL WHERE fragment: "(site_id = :product_scope_site_id OR site_id IS NULL)" | "1=1".
     * site_id 컬럼이 없으면 무조건 '1=1'이므로, siteScopeParams()도 이때는 빈 배열을 반환해
     * 존재하지 않는 자리표시자를 바인딩하는 일이 없다.
     */
    public static function siteScopeSql(PDO $pdo, string $alias = ''): string
    {
        if (!self::hasSiteScope($pdo)) {
            return '1=1';
        }
        $prefix = $alias !== '' ? $alias . '.' : '';
        return '(' . $prefix . 'site_id = :product_scope_site_id OR ' . $prefix . 'site_id IS NULL)';
    }

    /** siteScopeSql()과 짝을 이루는 바인딩 파라미터. */
    public static function siteScopeParams(PDO $pdo, int $siteId): array
    {
        return self::hasSiteScope($pdo) ? [':product_scope_site_id' => $siteId] : [];
    }
}
