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

    public function test_customer_search_returns_masked_phone(): void
    {
        $token = $this->loginAdmin();
        $this->api('POST', '/chats/rooms', [
            'customerName'  => '마스킹고객',
            'customerPhone' => '01012349876',
            'inquiryType'   => '테스트',
        ], $token);

        $res = $this->api('GET', '/search/customers', null, $token, ['q' => '마스킹']);
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('data', $res->body);
        $this->assertArrayHasKey('results', $res->body['data']);
        $this->assertIsArray($res->body['data']['results']);

        $customers = $res->body['data']['results'];
        $this->assertNotEmpty($customers);

        $found = false;
        foreach ($customers as $customer) {
            if ($customer['name'] === '마스킹고객') {
                $found = true;
                $this->assertArrayHasKey('phoneMasked', $customer);
                $this->assertStringContainsString('****', $customer['phoneMasked']);
                $this->assertArrayNotHasKey('phone', $customer, 'Raw phone should not be exposed');
            }
        }
        $this->assertTrue($found, 'Customer should be found in search results');
    }
}
