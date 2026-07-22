<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class SystemApiTest extends ApiTestCase
{
    public function test_system_health(): void
    {
        $res = $this->api('GET', '/system/health');
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('status', $res->body['data']);
    }

    public function test_v15_health_alias(): void
    {
        $res = $this->api('GET', '/health');
        $this->assertTrue($res->isSuccess());
        $this->assertSame('1.5', $res->body['data']['version']);
    }
}
