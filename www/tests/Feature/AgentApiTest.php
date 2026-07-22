<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class AgentApiTest extends ApiTestCase
{
    public function test_update_status_and_profile(): void
    {
        $token = $this->loginAdmin();
        $id = $this->admin['id'];

        $status = $this->api('PUT', '/agents/' . $id . '/status', ['status' => 'away'], $token);
        $this->assertTrue($status->isSuccess());
        $this->assertSame('away', $status->body['data']['status']);

        $profile = $this->api('PUT', '/agents/me/profile', ['name' => 'Updated Admin'], $token);
        $this->assertTrue($profile->isSuccess());
        $this->assertSame('Updated Admin', $profile->body['data']['name']);
    }
}
