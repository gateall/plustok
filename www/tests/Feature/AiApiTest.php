<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class AiApiTest extends ApiTestCase
{
    public function test_get_recommendations_empty(): void
    {
        $token = $this->loginAdmin();
        $room = $this->api('POST', '/chats/rooms', [
            'customerName'  => 'AI테스트',
            'customerPhone' => '01077778888',
            'inquiryType'   => 'AI',
        ], $token);
        $roomId = (string)$room->body['data']['roomId'];

        $res = $this->api('GET', '/ai/recommendations/' . $roomId, null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertSame('pending', $res->body['data']['status']);
    }
}
