<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class V15ApiTest extends ApiTestCase
{
    public function test_settings_get_and_put(): void
    {
        $token = $this->loginAdmin();
        $get = $this->api('GET', '/settings', null, $token);
        $this->assertTrue($get->isSuccess());
        $this->assertArrayHasKey('settings', $get->body['data']);

        $put = $this->api('PUT', '/settings', [
            'settings' => ['theme' => 'dark'],
        ], $token);
        $this->assertTrue($put->isSuccess());
        $this->assertSame('dark', $put->body['data']['settings']['theme']);
    }

    public function test_search_customers_and_chats(): void
    {
        $token = $this->loginAdmin();
        $this->api('POST', '/chats/rooms', [
            'customerName'  => '검색고객',
            'customerPhone' => '01011112222',
            'inquiryType'   => '검색',
        ], $token);

        $cust = $this->api('GET', '/search/customers', null, $token, ['q' => '검색']);
        $this->assertTrue($cust->isSuccess());

        $chats = $this->api('GET', '/search/chats', null, $token, ['q' => '검색']);
        $this->assertTrue($chats->isSuccess());
    }

    public function test_dashboard_stats(): void
    {
        $token = $this->loginAdmin();
        $res = $this->api('GET', '/dashboard/stats', null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('activeChats', $res->body['data']);
    }

    public function test_notifications_list_and_read(): void
    {
        $token = $this->loginAdmin();
        $pdo = $this->ensureSchema();
        $nid = '22222222-2222-4222-8222-222222222222';
        $pdo->prepare(
            'INSERT INTO agent_notifications (id, agent_id, type, title, body)
             VALUES (:id, :aid, :type, :title, :body)'
        )->execute([
            ':id'    => $nid,
            ':aid'   => $this->admin['id'],
            ':type'  => 'system',
            ':title' => 'Test',
            ':body'  => 'Hello',
        ]);

        $list = $this->api('GET', '/notifications', null, $token);
        $this->assertTrue($list->isSuccess());

        $read = $this->api('PUT', '/notifications/' . $nid . '/read', null, $token);
        $this->assertTrue($read->isSuccess());
    }
}
