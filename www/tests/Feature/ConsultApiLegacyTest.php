<?php
declare(strict_types=1);

namespace Tests\Feature;

require_once dirname(__DIR__, 2) . '/includes/util/ConsultSchema.php';
require_once dirname(__DIR__, 2) . '/includes/util/CrmSchema.php';
require_once dirname(__DIR__, 2) . '/includes/util/ProductSchema.php';

use ConsultSchema;
use CrmSchema;
use PHPUnit\Framework\TestCase;
use ProductSchema;
use Tests\Traits\WithDatabase;

final class ConsultApiLegacyTest extends TestCase
{
    use WithDatabase;

    protected function migrationProfile(): string
    {
        return 'legacy';
    }

    public function test_initial_status_falls_back_when_new_not_in_enum(): void
    {
        $pdo = $this->ensureSchema();
        $pdo->exec(
            "ALTER TABLE consults MODIFY status ENUM('receipt','consulting','quoted') NOT NULL DEFAULT 'receipt'"
        );
        ConsultSchema::resetStatusEnumCache();

        $this->assertSame('receipt', ConsultSchema::initialStatus($pdo));
    }

    public function test_build_insert_skips_missing_columns(): void
    {
        $pdo = $this->ensureSchema();
        if (ConsultSchema::hasColumn($pdo, 'consults', 'detail_json')) {
            $pdo->exec('ALTER TABLE consults DROP COLUMN detail_json');
        }
        if (ConsultSchema::hasColumn($pdo, 'consults', 'referer')) {
            $pdo->exec('ALTER TABLE consults DROP COLUMN referer');
        }
        if (ConsultSchema::hasColumn($pdo, $legacyTable = CrmSchema::legacyCustomerTable($pdo), 'region')) {
            $pdo->exec('ALTER TABLE `' . $legacyTable . '` DROP COLUMN region');
        }

        $pdo->exec(
            "INSERT IGNORE INTO sites (id, site_code, site_name, brand, api_key, use_yn)
             VALUES (1, 'legacy-test', 'Legacy', 'LegacyBrand', 'test-key-legacy', 1)"
        );

        $cust = ConsultSchema::buildInsert($pdo, $legacyTable, [
            'customer_no' => 'M202607220099',
            'name'        => '레거시',
            'phone'       => '01011112222',
            'email'       => 'legacy@test.example',
        ], [
            'region' => '서울',
        ]);
        $pdo->prepare(
            'INSERT INTO `' . $legacyTable . '` (' . implode(', ', $cust['columns']) . ') VALUES ('
            . implode(', ', $cust['placeholders']) . ')'
        )->execute($cust['params']);
        $customerId = (int)$pdo->lastInsertId();

        $status = ConsultSchema::initialStatus($pdo);
        $consult = ConsultSchema::buildInsert($pdo, 'consults', [
            'consult_no'  => 'C202607220001',
            'customer_id' => $customerId,
            'site_id'     => 1,
            'status'      => $status,
            'memo'        => 'legacy insert test',
        ], [
            'detail_json' => '{"x":1}',
            'referer'     => 'https://example.com',
            'device'      => 'web',
        ]);
        $this->assertNotContains('detail_json', $consult['columns']);
        $this->assertNotContains('referer', $consult['columns']);
        $this->assertContains('device', $consult['columns']);

        $pdo->prepare(
            'INSERT INTO consults (' . implode(', ', $consult['columns']) . ') VALUES ('
            . implode(', ', $consult['placeholders']) . ')'
        )->execute($consult['params']);
        $consultId = (int)$pdo->lastInsertId();

        $pdo->exec('DROP TABLE consult_history');
        ConsultSchema::recordInitialHistory($pdo, $consultId, $status);

        $row = $pdo->query('SELECT status, memo FROM consults WHERE id = ' . $consultId)->fetch();
        $this->assertSame($status, $row['status']);
        $this->assertSame('legacy insert test', $row['memo']);
    }

    public function test_product_schema_active_sql_uses_status_column(): void
    {
        $pdo = $this->ensureSchema();
        require_once dirname(__DIR__, 2) . '/includes/util/ProductSchema.php';
        require_once dirname(__DIR__, 2) . '/includes/util/CrmSchema.php';
        if (!acep_table_exists($pdo, 'products')) {
            $pdo->exec(
                'CREATE TABLE products (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    use_yn TINYINT NOT NULL DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
        if (\acep_column_exists($pdo, 'products', 'use_yn')) {
            $pdo->exec('ALTER TABLE products CHANGE use_yn status TINYINT NOT NULL DEFAULT 1');
        }
        $this->assertSame('status = 1', ProductSchema::activeSql($pdo));
        $this->assertSame('customers', CrmSchema::legacyCustomerTable($pdo));
    }
}
