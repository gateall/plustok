<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class AdminPromptFailoverTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->ensureSchema();
        $this->seedPrompt($pdo);
    }

    private function seedPrompt(\PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'ai_prompts')) {
            return;
        }
        $pdo->exec("DELETE FROM ai_prompts");
        $pdo->prepare(
            'INSERT INTO ai_prompts (id, role, version, prompt_id, content, is_active, changelog)
             VALUES (:id, :role, :ver, :pid, :content, 0, :log)'
        )->execute([
            ':id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            ':role' => 'recommend',
            ':ver' => 'v1.0',
            ':pid' => 'TEST_PROMPT_v1',
            ':content' => 'test prompt body',
            ':log' => 'test seed',
        ]);
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        require_once dirname(__DIR__, 2) . '/migrations/lib.php';
        return acep_table_exists($pdo, $table);
    }

    public function test_operator_cannot_list_prompts(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';
        $token = \JwtHelper::encode([
            'sub'  => $this->admin['id'],
            'role' => 'operator',
            'name' => 'Op',
        ], 3600);

        $res = $this->api('GET', '/admin/prompts', null, $token);
        $this->assertSame(403, $res->http);
    }

    public function test_admin_lists_and_creates_prompt(): void
    {
        $token = $this->adminToken();

        $list = $this->api('GET', '/admin/prompts', null, $token);
        $this->assertTrue($list->isSuccess());
        $this->assertNotEmpty($list->body['data']['data']);

        $create = $this->api('POST', '/admin/prompts', [
            'role'      => 'greeting',
            'promptKey' => 'GREET_v2_test',
            'content'   => 'Hello {{name}}',
            'version'   => 'v2.0',
        ], $token);
        $this->assertTrue($create->isSuccess());
        $this->assertSame(201, $create->http);
        $this->assertSame('GREET_v2_test', $create->body['data']['promptKey']);
    }

    public function test_admin_gets_failover_logs(): void
    {
        $token = $this->adminToken();
        $res = $this->api('GET', '/admin/failover-logs', null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('summary', $res->body['data']);
        $this->assertArrayHasKey('last24hCount', $res->body['data']['summary']);
    }
}
