<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class MessageApiTest extends ApiTestCase
{
    private function createRoom(string $token): string
    {
        $res = $this->api('POST', '/chats/rooms', [
            'customerName'  => '김테스트',
            'customerPhone' => '01099998888',
            'inquiryType'   => 'AS',
        ], $token);
        return (string)$res->body['data']['roomId'];
    }

    public function test_post_and_list_messages(): void
    {
        $token = $this->loginAdmin();
        $roomId = $this->createRoom($token);

        $post = $this->api('POST', '/chats/' . $roomId . '/messages', [
            'content' => '안녕하세요',
            'source'  => 'manual',
        ], $token);
        $this->assertSame(201, $post->http);
        $this->assertArrayHasKey('messageId', $post->body['data']);

        $list = $this->api('GET', '/chats/' . $roomId . '/messages', null, $token);
        $this->assertTrue($list->isSuccess());
        $this->assertNotEmpty($list->body['data']['messages']);
    }

    public function test_v15_chat_messages_alias(): void
    {
        $token = $this->loginAdmin();
        $roomId = $this->createRoom($token);
        $res = $this->api('POST', '/chat/messages', [
            'roomId'  => $roomId,
            'content' => 'V1.5 alias',
        ], $token);
        $this->assertSame(201, $res->http);
    }
}
