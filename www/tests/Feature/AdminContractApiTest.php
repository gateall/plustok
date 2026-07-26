<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class AdminContractApiTest extends ApiTestCase
{
    private function seedCustomerId(string $token): string
    {
        $room = $this->api('POST', '/chats/rooms', [
            'customerName'  => '계약테스트고객',
            'customerPhone' => '01099998888',
            'inquiryType'   => '견적',
        ], $token);
        $detail = $this->api('GET', '/chats/' . (string)$room->body['data']['roomId'], null, $token);
        return (string)$detail->body['data']['customer']['id'];
    }

    private function createContract(string $token, string $customerId, array $overrides = []): array
    {
        $res = $this->api('POST', '/admin/contracts', array_merge([
            'title'       => '테스트 계약',
            'customerId'  => $customerId,
            'totalAmount' => 1000000,
        ], $overrides), $token);
        $this->assertTrue($res->isSuccess(), 'create contract failed: ' . json_encode($res->body));
        return $res->body['data'];
    }

    public function test_requires_authentication(): void
    {
        $res = $this->api('GET', '/admin/contracts');
        $this->assertFalse($res->isSuccess());
        $this->assertSame(401, $res->http);
    }

    public function test_list_route_matches_before_id_route(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $this->createContract($token, $customerId);

        // 정적 목록 Route(GET /admin/contracts)가 동적 {id} Route에 가로채이지 않는지 확인.
        $res = $this->api('GET', '/admin/contracts', null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertArrayHasKey('items', $res->body['data']);
        $this->assertArrayHasKey('total', $res->body['data']);
        $this->assertArrayHasKey('page', $res->body['data']);
        $this->assertArrayHasKey('limit', $res->body['data']);
    }

    public function test_create_and_get_contract(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);

        $this->assertSame('draft', $created['status']);
        $this->assertSame(0.0, $created['paidAmount']);
        $this->assertSame(1000000.0, $created['outstandingAmount']);
        $this->assertTrue($created['permissions']['canEdit']);
        $this->assertTrue($created['permissions']['canDelete']);

        $get = $this->api('GET', '/admin/contracts/' . $created['id'], null, $token);
        $this->assertTrue($get->isSuccess());
        $this->assertSame($created['contractNo'], $get->body['data']['contractNo']);
    }

    public function test_search_and_sort(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $this->createContract($token, $customerId, ['title' => '검색대상계약']);

        $res = $this->api('GET', '/admin/contracts', null, $token, ['q' => '검색대상']);
        $this->assertTrue($res->isSuccess());
        $this->assertGreaterThanOrEqual(1, count($res->body['data']['items']));

        $sorted = $this->api('GET', '/admin/contracts', null, $token, ['sort' => 'total_amount', 'order' => 'asc']);
        $this->assertTrue($sorted->isSuccess());
    }

    public function test_invalid_sort_falls_back_safely(): void
    {
        $token = $this->loginAdmin();
        // 화이트리스트에 없는 sort 값을 SQL에 그대로 붙이지 않고 기본값으로 안전하게 처리되는지 확인.
        $res = $this->api('GET', '/admin/contracts', null, $token, ['sort' => "id; DROP TABLE contracts;--"]);
        $this->assertTrue($res->isSuccess());
    }

    public function test_update_rejects_invalid_date(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);

        $res = $this->api('PUT', '/admin/contracts/' . $created['id'], ['startDate' => 'not-a-date'], $token);
        $this->assertFalse($res->isSuccess());
        $this->assertSame(400, $res->http);
    }

    public function test_status_transition_whitelist_blocks_arbitrary_update(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);

        // draft -> completed 는 화이트리스트에 없음 → 차단되어야 한다.
        $res = $this->api('PATCH', '/admin/contracts/' . $created['id'] . '/status', ['status' => 'completed'], $token);
        $this->assertFalse($res->isSuccess());
        $this->assertSame(409, $res->http);
    }

    public function test_status_transition_happy_path_to_signed_requires_signer(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);
        $id = $created['id'];

        $this->assertTrue($this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => 'review'], $token)->isSuccess());
        $this->assertTrue($this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => 'sent'], $token)->isSuccess());
        $this->assertTrue($this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => 'signature_pending'], $token)->isSuccess());

        $missingSigner = $this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => 'signed'], $token);
        $this->assertFalse($missingSigner->isSuccess());
        $this->assertSame(400, $missingSigner->http);

        $signed = $this->api('PATCH', "/admin/contracts/{$id}/status", [
            'status'     => 'signed',
            'signerName' => '홍길동',
        ], $token);
        $this->assertTrue($signed->isSuccess());
        $this->assertSame('signed', $signed->body['data']['status']);
        $this->assertNotNull($signed->body['data']['signedAt']);
    }

    public function test_signed_contract_blocks_core_field_edit(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);
        $id = $created['id'];

        foreach (['review', 'sent', 'signature_pending'] as $status) {
            $this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => $status], $token);
        }
        $this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => 'signed', 'signerName' => '홍길동'], $token);

        $blocked = $this->api('PUT', "/admin/contracts/{$id}", ['totalAmount' => 9999999], $token);
        $this->assertFalse($blocked->isSuccess());
        $this->assertSame(409, $blocked->http);
        $this->assertSame('CONTRACT_SIGNED_LOCKED', $blocked->body['error']['code']);

        // 비핵심 필드(notes)는 서명 후에도 수정 가능해야 한다.
        $allowed = $this->api('PUT', "/admin/contracts/{$id}", ['notes' => '내부 메모'], $token);
        $this->assertTrue($allowed->isSuccess());

        $deleteAttempt = $this->api('DELETE', "/admin/contracts/{$id}", null, $token);
        $this->assertFalse($deleteAttempt->isSuccess());
        $this->assertSame(409, $deleteAttempt->http);
        $this->assertSame('CONTRACT_DELETE_BLOCKED', $deleteAttempt->body['error']['code']);

        $detail = $this->api('GET', "/admin/contracts/{$id}", null, $token);
        $this->assertSame('SIGNED', $detail->body['data']['deleteBlockReason']);
        $this->assertFalse($detail->body['data']['permissions']['canDelete']);
    }

    public function test_cancel_requires_reason_and_flags_refund(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);
        $id = $created['id'];

        $missingReason = $this->api('POST', "/admin/contracts/{$id}/cancel", [], $token);
        $this->assertFalse($missingReason->isSuccess());
        $this->assertSame(400, $missingReason->http);

        $cancelled = $this->api('POST', "/admin/contracts/{$id}/cancel", [
            'reasonCode' => 'CUSTOMER_REQUEST',
            'reason'     => '고객 요청으로 취소',
        ], $token);
        $this->assertTrue($cancelled->isSuccess());
        $this->assertSame('cancelled', $cancelled->body['data']['status']);
        $this->assertFalse($cancelled->body['data']['refundRequired']);

        $doubleCancel = $this->api('POST', "/admin/contracts/{$id}/cancel", [
            'reasonCode' => 'X', 'reason' => 'Y',
        ], $token);
        $this->assertFalse($doubleCancel->isSuccess());
        $this->assertSame(409, $doubleCancel->http);
    }

    public function test_archive_after_cancel(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);
        $id = $created['id'];

        $this->api('POST', "/admin/contracts/{$id}/cancel", ['reasonCode' => 'X', 'reason' => 'Y'], $token);
        $archived = $this->api('POST', "/admin/contracts/{$id}/archive", [], $token);
        $this->assertTrue($archived->isSuccess());
        $this->assertSame('archived', $archived->body['data']['status']);

        $reArchive = $this->api('POST', "/admin/contracts/{$id}/archive", [], $token);
        $this->assertFalse($reArchive->isSuccess());
        $this->assertSame(409, $reArchive->http);
    }

    public function test_delete_allowed_for_draft_without_payments(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);

        $res = $this->api('DELETE', '/admin/contracts/' . $created['id'], null, $token);
        $this->assertTrue($res->isSuccess());
        $this->assertTrue($res->body['data']['deleted']);

        $getAfter = $this->api('GET', '/admin/contracts/' . $created['id'], null, $token);
        $this->assertFalse($getAfter->isSuccess());
        $this->assertSame(404, $getAfter->http);
    }

    public function test_delete_blocked_when_not_draft(): void
    {
        $token = $this->loginAdmin();
        $customerId = $this->seedCustomerId($token);
        $created = $this->createContract($token, $customerId);
        $id = $created['id'];

        $this->api('PATCH', "/admin/contracts/{$id}/status", ['status' => 'review'], $token);

        $res = $this->api('DELETE', "/admin/contracts/{$id}", null, $token);
        $this->assertFalse($res->isSuccess());
        $this->assertSame(409, $res->http);
        $this->assertSame('CONTRACT_DELETE_BLOCKED', $res->body['error']['code']);
    }

    public function test_not_found_returns_404(): void
    {
        $token = $this->loginAdmin();
        $res = $this->api('GET', '/admin/contracts/00000000-0000-4000-8000-000000000000', null, $token);
        $this->assertFalse($res->isSuccess());
        $this->assertSame(404, $res->http);
    }
}
