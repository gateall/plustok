<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/../repositories/CustomerBridgeRepository.php';
require_once __DIR__ . '/../ws_publish.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../../config/acep.php';

final class ConsultChatService
{
    public function __construct(
        private PDO $pdo,
        private ChatRoomRepository $rooms,
        private CustomerRepository $customers,
        private CustomerBridgeRepository $bridge,
        private ChatMessageRepository $messages,
        private AiRecommendationRepository $aiRecs,
        private CustomerTokenService $tokens,
    ) {
    }

    public function chatTablesAvailable(): bool
    {
        if (!acep_table_exists($this->pdo, 'chat_rooms')) {
            return false;
        }
        if (acep_uses_legacy_chat_customers($this->pdo)) {
            return acep_table_exists($this->pdo, CrmSchema::legacyCustomerTable($this->pdo));
        }
        return CrmSchema::acepCustomersAvailable($this->pdo);
    }

    /**
     * 상담 접수 트랜잭션 내부에서 호출 — chat_room 생성 + 고객 JWT.
     * chat_rooms.customer_id는 ACEP customers(UUID)를 FK로 참조하므로,
     * 레거시 BIGINT 고객ID는 customer_bridge를 통해 ACEP UUID로 변환해서 사용한다.
     *
     * @return array{roomId:string,accessToken:string,expiresIn:int,wsUrl:string}|null
     */
    public function createRoomForConsult(
        int $legacyCustomerId,
        int $legacyConsultId,
        string $customerName,
        string $phone,
        ?string $email,
        string $inquiryType,
        ?string $memo = null,
        string $channel = 'web',
    ): ?array {
        if (!$this->chatTablesAvailable()) {
            return null;
        }

        if (acep_uses_legacy_chat_customers($this->pdo)) {
            $customerId = (string)$legacyCustomerId;
        } else {
            $customerId = $this->bridge->resolveAcepCustomer(
                $legacyCustomerId,
                $customerName,
                $phone,
                $email,
            );
        }

        $customer = $this->customers->findById($customerId);
        if (!$customer) {
            throw new RuntimeException('Customer not found for chat room: legacy=' . $legacyCustomerId);
        }

        $inquiry = $inquiryType !== '' ? $inquiryType : '상담신청';
        $initial = ($memo !== null && trim($memo) !== '') ? trim($memo) : '';
        $roomStatus = acep_resolve_enum_value(
            $this->pdo,
            'chat_rooms',
            'status',
            'new',
            'open',
            'active',
            'pending',
            'waiting',
        ) ?? 'new';

        $roomId = uuid_v4();
        $roomData = [
            ':id'           => $roomId,
            ':customer_id'  => $customerId,
            ':agent_id'     => null,
            ':inquiry_type' => $inquiry,
            ':status'       => $roomStatus,
            ':channel'      => $channel !== '' ? $channel : 'web',
            ':subject'      => $inquiry,
        ];
        if (acep_column_exists($this->pdo, 'chat_rooms', 'legacy_consult_id')) {
            $roomData[':legacy_consult_id'] = $legacyConsultId;
        }
        $this->rooms->create($roomData);

        if ($initial !== '' && acep_table_exists($this->pdo, 'chat_messages')) {
            $msgId = uuid_v4();
            $this->messages->create([
                ':id'                   => $msgId,
                ':room_id'              => $roomId,
                ':sender_type'          => 'customer',
                ':sender_id'            => $customerId,
                ':content'              => $initial,
                ':attachment_url'       => null,
                ':attachment_id'        => null,
                ':attachment_type'      => null,
                ':source'               => 'manual',
                ':ai_recommendation_id' => null,
            ]);
            if (acep_table_exists($this->pdo, 'ai_recommendations')) {
                $this->aiRecs->createPending(uuid_v4(), $roomId);
            }
        }

        $custPublic = $this->customers->maskRow($customer);
        acep_ws_publish_broadcast('room:update', [
            'roomId'              => $roomId,
            'status'              => $roomStatus,
            'customerName'        => $custPublic['name'],
            'inquiryType'         => $inquiry,
            'lastMessage'         => $initial !== '' ? $initial : null,
            'updatedAt'           => date('c'),
            'unreadCount'         => $initial !== '' ? 1 : 0,
            'agentId'             => null,
            'contractProbability' => 0,
        ]);

        $tokenData = $this->tokens->issue($customerId, $customerName);

        return [
            'roomId'      => $roomId,
            'accessToken' => $tokenData['accessToken'],
            'expiresIn'   => $tokenData['expiresIn'],
            'wsUrl'       => acep_chat_ws_url(),
        ];
    }
}
