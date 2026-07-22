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
}
