<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class ChatApiTest extends ApiTestCase
{
    private function createRoom(string $token): string
    {
        $res = $this->api('POST', '/chats/rooms', [
            'customerName'  => '홍길동',
            'customerPhone' => '01012345678',
            'inquiryType'   => '설치문의',
            'channel'       => 'web',
        ], $token);
        $this->assertTrue($res->isSuccess(), $res->body['error']['message'] ?? '');
        return (string)$res->body['data']['roomId'];
    }

    public function test_list_rooms(): void
    {
        $token = $this->loginAdmin();
        $res = $this->api('GET', '/chats/rooms', null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('rooms', $res->body['data']);
    }

    public function test_create_and_get_room(): void
    {
        $token = $this->loginAdmin();
        $roomId = $this->createRoom($token);
        $res = $this->api('GET', '/chats/' . $roomId, null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertSame($roomId, $res->body['data']['id']);
    }

    public function test_close_room(): void
    {
        $token = $this->loginAdmin();
        $roomId = $this->createRoom($token);
        $res = $this->api('PUT', '/chats/' . $roomId . '/close', ['reason' => 'done'], $token);
        $this->assertTrue($res->isSuccess());
        $this->assertSame('closed', $res->body['data']['status']);
    }

    public function test_mark_read(): void
    {
        $token = $this->loginAdmin();
        $roomId = $this->createRoom($token);
        $res = $this->api('PUT', '/chats/' . $roomId . '/read', [
            'messageIds' => [],
            'readerType' => 'agent',
        ], $token);
        $this->assertTrue($res->isSuccess());
    }

    public function test_customer_can_access_own_room_only(): void
    {
        $adminToken = $this->loginAdmin();
        $roomA = $this->createRoom($adminToken);
        $roomB = $this->createRoomWithPhone($adminToken, '01099998888');

        $roomARes = $this->api('GET', '/chats/' . $roomA, null, $adminToken);
        $this->assertTrue($roomARes->isSuccess());
        $customerId = (string)$roomARes->body['data']['customer']['id'];

        $customerToken = $this->customerToken($customerId);

        $own = $this->api('GET', '/chats/' . $roomA, null, $customerToken);
        $this->assertTrue($own->isSuccess(), $own->body['error']['message'] ?? '');

        $other = $this->api('GET', '/chats/' . $roomB, null, $customerToken);
        $this->assertFalse($other->isSuccess());
        $this->assertSame(403, $other->http);
    }

    private function createRoomWithPhone(string $token, string $phone): string
    {
        $res = $this->api('POST', '/chats/rooms', [
            'customerName'  => '다른고객',
            'customerPhone' => $phone,
            'inquiryType'   => '설치문의',
            'channel'       => 'web',
        ], $token);
        $this->assertTrue($res->isSuccess(), $res->body['error']['message'] ?? '');
        return (string)$res->body['data']['roomId'];
    }

    private function customerToken(string $customerId): string
    {
        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';
        return \JwtHelper::encode([
            'sub'  => $customerId,
            'role' => 'customer',
            'name' => 'Test Customer',
        ], 3600);
    }
}
