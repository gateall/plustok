<?php
declare(strict_types=1);

namespace Tests\Unit;

require_once dirname(__DIR__, 2) . '/includes/util/ConsultSchema.php';

use ConsultSchema;
use PHPUnit\Framework\TestCase;
use Tests\Traits\WithDatabase;

final class ConsultSchemaTest extends TestCase
{
    use WithDatabase;

    protected function migrationProfile(): string
    {
        return 'legacy';
    }

    public function test_initial_status_prefers_new_when_enum_includes_it(): void
    {
        $pdo = $this->ensureSchema();
        $this->assertSame('new', ConsultSchema::initialStatus($pdo));
    }

    public function test_initial_status_falls_back_when_new_missing_from_enum(): void
    {
        $pdo = $this->ensureSchema();
        $pdo->exec(
            "ALTER TABLE consults MODIFY status
             ENUM('progress','consulting','quoted','contracted','installed','hold','canceled')
             NOT NULL DEFAULT 'consulting'"
        );

        $ref = new \ReflectionClass(ConsultSchema::class);
        $cache = $ref->getProperty('statusEnumCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);

        $this->assertSame('consulting', ConsultSchema::initialStatus($pdo));
    }

    public function test_build_insert_omits_missing_optional_columns(): void
    {
        $pdo = $this->ensureSchema();
        if (ConsultSchema::hasColumn($pdo, 'customers', 'region')) {
            $pdo->exec('ALTER TABLE customers DROP COLUMN region');
        }

        $insert = ConsultSchema::buildInsert($pdo, 'customers', [
            'customer_no' => 'M202607220099',
            'name'        => '테스트',
            'phone'       => '01099998888',
        ], [
            'region' => '서울',
        ]);

        $this->assertNotContains('region', $insert['columns']);
        $this->assertArrayNotHasKey(':region', $insert['params']);
    }
}
