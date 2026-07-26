<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/PiiEncryptor.php';
require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../api_envelope.php';

final class ChatService
{
    public function __construct(
        private ChatRoomRepository $rooms,
        private CustomerRepository $customers,
        private AgentRepository $agents,
        private ChatMessageRepository $messages,
        private ReadStatusRepository $readStatus,
        private AiRecommendationRepository $aiRecs,
        private AuditService $audit,
        private ?object $crmClose = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function listRooms(string $agentId, string $role, array $query): array
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(50, max(1, (int)($query['limit'] ?? 20)));
        $sort = (string)($query['sort'] ?? 'updated_at:desc');
        $search = isset($query['search']) ? trim((string)$query['search']) : null;

        $statuses = null;
        if (!empty($query['status'])) {
            $statuses = array_filter(array_map('trim', explode(',', (string)$query['status'])));
        }

        $rows = $this->rooms->listForAgent($agentId, $role, $statuses, $search, $page, $limit, $sort);
        $total = $this->rooms->countForAgent($agentId, $role, $statuses, $search);

        $rooms = [];
        foreach ($rows as $row) {
            $cust = $this->customers->maskRow([
                'id'    => $row['customer_id'],
                'name'  => $row['customer_name'],
                'phone' => $row['customer_phone'],
                'tags'  => $row['customer_tags'],
            ]);
            $score = $this->rooms->latestContractScore((string)$row['id']);
            $rooms[] = [
                'id'                  => $row['id'],
                'customer'            => $cust,
                'inquiryType'         => $row['inquiry_type'],
                'status'              => $row['status'],
                'unreadCount'         => $this->rooms->unreadCount((string)$row['id'], $agentId, 'agent'),
                'contractProbability' => $score ?? (int)$row['priority_score'],
                'updatedAt'           => date('c', strtotime((string)$row['updated_at'])),
            ];
        }

        return [
            'rooms'      => $rooms,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ];
    }

    /** @return array<string,mixed> */
    public function getRoom(string $roomId, string $agentId, string $role): array
    {
        $room = $this->requireRoomAccess($roomId, $agentId, $role);
        $customer = $this->customers->findById((string)$room['customer_id']);
        if (!$customer) {
            acep_error('CUSTOMER_NOT_FOUND', '고객을 찾을 수 없습니다.', 404);
        }

        $agent = null;
        if (!empty($room['agent_id'])) {
            $a = $this->agents->findById((string)$room['agent_id']);
            if ($a) {
                $agent = ['id' => $a['id'], 'name' => $a['name']];
            }
        }

        $custPublic = $this->customers->maskRow($customer);

        return [
            'id'          => $room['id'],
            'customer'    => $custPublic,
            'agent'       => $agent,
            'inquiryType' => $room['inquiry_type'],
            'status'      => $room['status'],
            'channel'     => $room['channel'],
            'subject'     => $room['subject'],
            'memo'        => $room['memo'],
            'createdAt'   => date('c', strtotime((string)$room['created_at'])),
            'updatedAt'   => date('c', strtotime((string)$room['updated_at'])),
        ];
    }

    /** @param array<string,mixed> $body */
    public function createRoom(string $agentId, string $role, array $body): array
    {
        $name = trim((string)($body['customerName'] ?? ''));
        $phone = trim((string)($body['customerPhone'] ?? ''));
        $inquiry = trim((string)($body['inquiryType'] ?? ''));
        $channel = trim((string)($body['channel'] ?? 'web'));
        $initial = trim((string)($body['initialMessage'] ?? ''));

        if ($name === '' || $phone === '' || $inquiry === '') {
            acep_error('VALIDATION_ERROR', 'customerName, customerPhone, inquiryType가 필요합니다.', 400);
        }

        $hash = PiiEncryptor::phoneHash($phone);
        $customer = $this->customers->findByPhoneHash($hash);
        if (!$customer) {
            $custId = uuid_v4();
            $this->customers->create([
                ':id'          => $custId,
                ':name'        => $name,
                ':phone'       => PiiEncryptor::encrypt($phone),
                ':phone_hash'  => $hash,
                ':email'       => null,
                ':address'     => null,
                ':tags'        => json_encode(['신규']),
            ]);
            $customer = $this->customers->findById($custId);
        }

        $roomId = uuid_v4();
        $this->rooms->create([
            ':id'           => $roomId,
            ':customer_id'  => $customer['id'],
            ':agent_id'     => in_array($role, ['agent', 'admin'], true) ? $agentId : null,
            ':inquiry_type' => $inquiry,
            ':status'       => 'new',
            ':channel'      => $channel,
            ':subject'      => $inquiry,
        ]);

        if ($initial !== '') {
            $msgId = uuid_v4();
            $this->messages->create([
                ':id'                   => $msgId,
                ':room_id'              => $roomId,
                ':sender_type'          => 'customer',
                ':sender_id'            => $customer['id'],
                ':content'              => $initial,
                ':attachment_url'       => null,
                ':attachment_id'        => null,
                ':attachment_type'      => null,
                ':source'               => 'manual',
                ':ai_recommendation_id' => null,
            ]);
            $this->aiRecs->createPending(uuid_v4(), $roomId);
        }

        $this->audit->agentAction($agentId, 'room.create', 'chat_room', $roomId);

        return [
            'roomId'     => $roomId,
            'customerId' => $customer['id'],
            'status'     => 'new',
            'createdAt'  => date('c'),
        ];
    }

    /** @param array<string,mixed> $body */
    public function closeRoom(string $roomId, string $agentId, string $role, array $body): array
    {
        $room = $this->requireRoomAccess($roomId, $agentId, $role);
        if ($room['status'] === 'closed') {
            acep_error('VALIDATION_ERROR', '이미 종료된 상담방입니다.', 400);
        }

        if (!$this->rooms->close($roomId)) {
            acep_error('ROOM_NOT_FOUND', '상담방을 찾을 수 없습니다.', 404);
        }

        $this->audit->agentAction($agentId, 'room.close', 'chat_room', $roomId, [
            'reason' => $body['reason'] ?? null,
        ]);

        $updated = $this->rooms->findById($roomId);
        $crm = null;
        if ($this->crmClose !== null) {
            try {
                $feedback = null;
                if (isset($body['feedback']) && is_array($body['feedback'])) {
                    $feedback = $body['feedback'];
                }
                $crm = $this->crmClose->execute(
                    $roomId,
                    $agentId,
                    $role,
                    $feedback,
                    (bool)($body['force'] ?? false),
                    isset($body['summaryOverride']) ? (string)$body['summaryOverride'] : null,
                    false,
                );
            } catch (AcepHttpResponse $e) {
                if ($e->httpCode >= 500) {
                    $this->rooms->markCrmFailed($roomId);
                }
                throw $e;
            } catch (Throwable) {
                $this->rooms->markCrmFailed($roomId);
            }
        }

        return [
            'roomId'   => $roomId,
            'status'   => 'closed',
            'closedAt' => date('c', strtotime((string)$updated['closed_at'])),
            'crm'      => $crm,
        ];
    }

    /** @param array<string,mixed> $body */
    public function markRead(string $roomId, string $agentId, string $role, array $body): array
    {
        $room = $this->requireRoomAccess($roomId, $agentId, $role);
        if ($room['status'] === 'closed') {
            acep_error('VALIDATION_ERROR', '종료된 상담방은 읽음 처리할 수 없습니다.', 400);
        }

        $messageIds = $body['messageIds'] ?? [];
        if (!is_array($messageIds)) {
            acep_error('VALIDATION_ERROR', 'messageIds 배열이 필요합니다.', 400);
        }

        $readerType = (string)($body['readerType'] ?? 'agent');
        if (!in_array($readerType, ['agent', 'customer'], true)) {
            acep_error('VALIDATION_ERROR', 'readerType은 agent 또는 customer입니다.', 400);
        }

        $readerId = $readerType === 'agent' ? $agentId : (string)($body['readerId'] ?? '');
        if ($readerId === '') {
            acep_error('VALIDATION_ERROR', 'readerId가 필요합니다.', 400);
        }

        $count = $this->readStatus->markRead($roomId, $readerType, $readerId, $messageIds);

        return ['updatedCount' => $count];
    }

    /** @return array<string,mixed> */
    public function requireRoomAccess(string $roomId, string $agentId, string $role): array
    {
        $room = $this->rooms->findById($roomId);
        if (!$room) {
            acep_error('ROOM_NOT_FOUND', '상담방을 찾을 수 없습니다.', 404);
        }
        if (!in_array($role, ['admin', 'operator'], true)) {
            if ($room['status'] !== 'new' && (string)($room['agent_id'] ?? '') !== $agentId) {
                acep_error('FORBIDDEN', '상담방 접근 권한이 없습니다.', 403);
            }
        }
        return $room;
    }
}
