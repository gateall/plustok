<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class CustomerApiTest extends ApiTestCase
{
    public function test_get_and_update_customer(): void
    {
        $token = $this->loginAdmin();
        $room = $this->api('POST', '/chats/rooms', [
            'customerName'  => '이고객',
            'customerPhone' => '01055556666',
            'inquiryType'   => '견적',
        ], $token);
        $roomId = (string)$room->body['data']['roomId'];
        $detail = $this->api('GET', '/chats/' . $roomId, null, $token);
        $customerId = (string)$detail->body['data']['customer']['id'];

        $get = $this->api('GET', '/customers/' . $customerId, null, $token);
        $this->assertTrue($get->isSuccess());

        $put = $this->api('PUT', '/customers/' . $customerId, [
            'tags' => ['VIP', '재문의'],
        ], $token);
        $this->assertTrue($put->isSuccess());
    }
}
