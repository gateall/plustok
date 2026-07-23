<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

/**
 * site_field_schemas 조회 — 테이블/사이트별 스키마가 없으면 항상 빈 배열로 안전하게 폴백.
 */
final class SiteFieldSchema
{
    public static function tableExists(PDO $pdo): bool
    {
        return acep_table_exists($pdo, 'site_field_schemas');
    }

    /** 사이트의 필드 스키마 행 전체. schema_json은 fields 배열로 디코딩. */
    public static function forSite(PDO $pdo, int $siteId): array
    {
        if (!self::tableExists($pdo)) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT category, product_name, schema_json
             FROM site_field_schemas
             WHERE site_id = :sid
             ORDER BY sort_order, id'
        );
        $stmt->execute([':sid' => $siteId]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $fields = json_decode((string)$r['schema_json'], true);
            $out[] = [
                'category'     => (string)$r['category'],
                'product_name' => (string)$r['product_name'],
                'fields'       => is_array($fields) ? $fields : [],
            ];
        }
        return $out;
    }

    /**
     * product_name 정확 일치 → category 일치(product_name='') → 사이트 기본값(둘 다 '') 순으로 탐색.
     * @param array $rows forSite()가 반환한 배열
     */
    public static function resolve(array $rows, string $category, string $productName): array
    {
        foreach ($rows as $r) {
            if ($r['product_name'] !== '' && $r['product_name'] === $productName) {
                return $r['fields'];
            }
        }
        foreach ($rows as $r) {
            if ($r['product_name'] === '' && $r['category'] !== '' && $r['category'] === $category) {
                return $r['fields'];
            }
        }
        foreach ($rows as $r) {
            if ($r['product_name'] === '' && $r['category'] === '') {
                return $r['fields'];
            }
        }
        return [];
    }
}
