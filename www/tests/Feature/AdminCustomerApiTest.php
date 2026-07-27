<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class AdminCustomerApiTest extends ApiTestCase
{
    public function test_requires_authentication(): void
    {
        $res = $this->api('GET', '/admin/customers');
        $this->assertFalse($res->isSuccess());
        $this->assertSame(401, $res->http);
    }

    public function test_list_route_matches_before_id_route(): void
    {
        $token = $this->loginAdmin();
        $this->api('POST', '/chats/rooms', [
            'customerName'  => '목록테스트고객',
            'customerPhone' => '01077776666',
            'inquiryType'   => '견적',
        ], $token);

        $res = $this->api('GET', '/admin/customers', null, $token);
        $this->assertTrue($res->isSuccess(), json_encode($res->body));
        $this->assertArrayHasKey('items', $res->body['data']);
        $this->assertArrayHasKey('total', $res->body['data']);
        $this->assertArrayHasKey('page', $res->body['data']);
        $this->assertArrayHasKey('limit', $res->body['data']);
        $this->assertIsArray($res->body['data']['items']);
        $this->assertGreaterThanOrEqual(1, $res->body['data']['total']);
    }

    public function test_list_supports_query_params(): void
    {
        $token = $this->loginAdmin();
        $this->api('POST', '/chats/rooms', [
            'customerName'  => '필터고객Alpha',
            'customerPhone' => '01088889999',
            'inquiryType'   => '테스트',
        ], $token);

        $res = $this->api('GET', '/admin/customers', null, $token, [
            'page'  => '1',
            'limit' => '20',
            'q'     => '필터고객',
        ]);
        $this->assertTrue($res->isSuccess(), json_encode($res->body));
        $this->assertSame(1, $res->body['data']['page']);
        $this->assertSame(20, $res->body['data']['limit']);
        $this->assertNotEmpty($res->body['data']['items']);

        $found = false;
        foreach ($res->body['data']['items'] as $item) {
            if (($item['name'] ?? '') === '필터고객Alpha') {
                $found = true;
                $this->assertArrayHasKey('phone', $item);
                break;
            }
        }
        $this->assertTrue($found, 'Filtered customer should appear in list');
        foreach ($res->body['data']['items'] as $item) {
            $this->assertArrayHasKey('emailMasked', $item);
            $this->assertArrayNotHasKey('email', $item);
            if (isset($item['emailMasked']) && $item['emailMasked'] !== null) {
                $this->assertStringNotContainsString('/', (string)$item['emailMasked']);
            }
        }
    }

    public function test_detail_route_still_works_after_list_route(): void
    {
        $token = $this->loginAdmin();
        $room = $this->api('POST', '/chats/rooms', [
            'customerName'  => '상세유지고객',
            'customerPhone' => '01033334444',
            'inquiryType'   => '견적',
        ], $token);
        $roomId = (string)$room->body['data']['roomId'];
        $detail = $this->api('GET', '/chats/' . $roomId, null, $token);
        $customerId = (string)$detail->body['data']['customer']['id'];

        $get = $this->api('GET', '/admin/customers/' . $customerId, null, $token);
        $this->assertTrue($get->isSuccess(), json_encode($get->body));
        $this->assertSame($customerId, (string)$get->body['data']['id']);
    }
}
