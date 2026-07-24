<?php
declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use Tests\Support\ApiTestCase;

final class AdminSiteApiTest extends ApiTestCase
{
    protected function migrationProfile(): string
    {
        return 'acep';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->upgradeSitesSchema($this->ensureSchema());
    }

    public function test_admin_can_create_list_get_update_and_toggle_site(): void
    {
        $pdo = $this->ensureSchema();
        $token = $this->adminToken();

        $created = $this->api('POST', '/admin/sites', [
            'siteCode' => 'plus-seoul',
            'siteName' => 'Plus Seoul',
            'domain' => 'seoul.example.test',
            'brand' => 'PlusTok',
            'division' => 'enterprise',
            'persona' => 'B2B',
            'status' => true,
        ], $token);
        $this->assertTrue($created->isSuccess());
        $siteId = (int)$created->body['data']['id'];
        $this->assertSame(64, strlen((string)$created->body['data']['apiKey']));

        $list = $this->api('GET', '/admin/sites', null, $token, ['q' => 'seoul']);
        $this->assertTrue($list->isSuccess());
        $this->assertSame('plus-seoul', $list->body['data']['data'][0]['siteCode']);
        $this->assertSame('enterprise', $list->body['data']['data'][0]['division']);

        $detail = $this->api('GET', '/admin/sites/' . $siteId, null, $token);
        $this->assertTrue($detail->isSuccess());
        $this->assertSame('Plus Seoul', $detail->body['data']['siteName']);
        $this->assertTrue($detail->body['data']['status']);

        $updated = $this->api('PATCH', '/admin/sites/' . $siteId, [
            'siteCode' => 'plus-seoul',
            'siteName' => 'Plus Seoul Updated',
            'domain' => 'seoul.example.test',
            'brand' => 'PlusTok',
            'division' => 'franchise',
            'persona' => 'VIP',
        ], $token);
        $this->assertTrue($updated->isSuccess());
        $this->assertSame('Plus Seoul Updated', $updated->body['data']['siteName']);
        $this->assertSame('franchise', $updated->body['data']['division']);

        $toggled = $this->api('POST', '/admin/sites/' . $siteId . '/toggle', [], $token);
        $this->assertTrue($toggled->isSuccess());
        $this->assertFalse($toggled->body['data']['status']);

        $row = $pdo->query('SELECT status, division, persona FROM sites WHERE id = ' . $siteId)->fetch();
        $this->assertSame(0, (int)$row['status']);
        $this->assertSame('franchise', $row['division']);
        $this->assertSame('VIP', $row['persona']);
    }

    public function test_admin_can_regenerate_key_and_read_stats(): void
    {
        $pdo = $this->ensureSchema();
        $siteId = $this->seedSite($pdo, ['site_code' => 'stats-site']);
        $this->seedConsult($pdo, $siteId, 'C202607240001');
        $this->seedConsult($pdo, $siteId, 'C202607240002');
        $token = $this->adminToken();

        $regen = $this->api('POST', '/admin/sites/' . $siteId . '/regen-key', [], $token);
        $this->assertTrue($regen->isSuccess());
        $this->assertSame(64, strlen((string)$regen->body['data']['apiKey']));

        $stats = $this->api('GET', '/admin/sites/' . $siteId . '/stats', null, $token);
        $this->assertTrue($stats->isSuccess());
        $this->assertSame(2, $stats->body['data']['todayConsultCount']);
        $this->assertSame(2, $stats->body['data']['totalConsultCount']);
        $this->assertNotNull($stats->body['data']['lastConsultedAt']);

        $health = $this->api('POST', '/admin/sites/' . $siteId . '/health-check', [], $token);
        $this->assertTrue($health->isSuccess());
        $this->assertTrue($health->body['data']['isHealthy']);

        $history = $this->api('GET', '/admin/sites/' . $siteId . '/health', null, $token);
        $this->assertTrue($history->isSuccess());
        $this->assertIsArray($history->body['data']['data']);
    }

    public function test_delete_is_blocked_when_consults_exist_and_allowed_otherwise(): void
    {
        $pdo = $this->ensureSchema();
        $blockedSiteId = $this->seedSite($pdo, ['site_code' => 'blocked-site']);
        $freeSiteId = $this->seedSite($pdo, ['site_code' => 'free-site']);
        $this->seedConsult($pdo, $blockedSiteId, 'C202607240010');
        $token = $this->adminToken();

        $blocked = $this->api('DELETE', '/admin/sites/' . $blockedSiteId, null, $token);
        $this->assertFalse($blocked->isSuccess());
        $this->assertSame('SITE_IN_USE', $blocked->body['error']['code']);

        $deleted = $this->api('DELETE', '/admin/sites/' . $freeSiteId, null, $token);
        $this->assertTrue($deleted->isSuccess());
        $this->assertTrue($deleted->body['data']['deleted']);
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sites WHERE id = ' . $blockedSiteId)->fetchColumn());
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sites WHERE id = ' . $freeSiteId)->fetchColumn());
    }

    private function upgradeSitesSchema(PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'sites', 'domain')) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN domain VARCHAR(150) NOT NULL DEFAULT '' AFTER site_name");
        }
        if (!$this->columnExists($pdo, 'sites', 'division')) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN division VARCHAR(50) NOT NULL DEFAULT '' AFTER brand");
        }
        if (!$this->columnExists($pdo, 'sites', 'persona')) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN persona VARCHAR(255) NULL AFTER division");
        }
        if (!$this->columnExists($pdo, 'sites', 'status')) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN status TINYINT(4) NOT NULL DEFAULT 1 AFTER api_key");
        }
        if ($this->columnExists($pdo, 'sites', 'use_yn')) {
            $pdo->exec('UPDATE sites SET status = use_yn');
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $st->execute([':table' => $table, ':column' => $column]);
        return (int)$st->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $override */
    private function seedSite(PDO $pdo, array $override = []): int
    {
        $data = array_merge([
            'site_code' => 'site-' . substr(bin2hex(random_bytes(4)), 0, 8),
            'site_name' => 'Seed Site',
            'domain' => 'seed.example.test',
            'brand' => 'PlusTok',
            'division' => 'enterprise',
            'persona' => 'seed persona',
            'api_key' => str_repeat('a', 64),
            'status' => 1,
        ], $override);

        $pdo->prepare(
            'INSERT INTO sites (site_code, site_name, domain, brand, division, persona, api_key, status)
             VALUES (:site_code, :site_name, :domain, :brand, :division, :persona, :api_key, :status)'
        )->execute($data);

        return (int)$pdo->lastInsertId();
    }

    private function seedConsult(PDO $pdo, int $siteId, string $consultNo): void
    {
        require_once dirname(__DIR__, 2) . '/includes/util/CrmSchema.php';
        $customerTable = \CrmSchema::legacyCustomerTable($pdo);
        $pdo->prepare("INSERT INTO {$customerTable} (customer_no, name, phone, email) VALUES (:no, :name, :phone, :email)")
            ->execute([
                ':no' => 'M' . substr($consultNo, 1),
                ':name' => 'Site Customer',
                ':phone' => '010' . substr($consultNo, -8),
                ':email' => strtolower($consultNo) . '@example.test',
            ]);
        $customerId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO consults (consult_no, customer_id, site_id, status, memo)
             VALUES (:consult_no, :customer_id, :site_id, :status, :memo)'
        )->execute([
            ':consult_no' => $consultNo,
            ':customer_id' => $customerId,
            ':site_id' => $siteId,
            ':status' => 'new',
            ':memo' => 'seed consult',
        ]);
        $consultId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO consult_history (consult_id, from_status, to_status, note)
             VALUES (:consult_id, NULL, :to_status, :note)'
        )->execute([
            ':consult_id' => $consultId,
            ':to_status' => 'new',
            ':note' => 'seed history',
        ]);
    }
}
