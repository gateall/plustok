<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class AdminMonitorService
{
    public function __construct(
        private ChatRoomRepository $rooms,
        private ChatMessageRepository $messages,
        private CustomerRepository $customers,
        private AgentRepository $agents,
        private AiRecommendationRepository $aiRecs,
        private PDO $pdo,
    ) {
    }

    /** @param array<string,mixed> $query */
    public function listRooms(array $query): array
    {
        $limit = min(100, max(1, (int)($query['limit'] ?? 100)));
        $agentFilter = isset($query['agent_id']) ? (string)$query['agent_id'] : null;

        $where = ["cr.status IN ('new','active')", 'cr.deleted_at IS NULL'];
        $params = [];
        if ($agentFilter) {
            $where[] = 'cr.agent_id = :aid';
            $params[':aid'] = $agentFilter;
        }
        $sql = 'SELECT cr.*, c.name AS customer_name, c.phone AS customer_phone
                FROM chat_rooms cr
                JOIN customers c ON c.id = cr.customer_id AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY cr.updated_at DESC LIMIT ' . (int)$limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $rooms = [];
        foreach ($st->fetchAll() ?: [] as $row) {
            $agent = null;
            if (!empty($row['agent_id'])) {
                $a = $this->agents->findById((string)$row['agent_id']);
                if ($a) {
                    $agent = ['id' => $a['id'], 'displayName' => $a['name']];
                }
            }
            $lastMsg = $this->messages->listByRoom((string)$row['id'], 1, null)[0] ?? null;
            $cust = $this->customers->maskRow([
                'id' => $row['customer_id'], 'name' => $row['customer_name'],
                'phone' => $row['customer_phone'], 'tags' => '[]',
            ]);
            $rooms[] = [
                'id'                  => $row['id'],
                'status'              => $row['status'],
                'customerNameMasked'  => $cust['name'],
                'agent'               => $agent,
                'updatedAt'           => date('c', strtotime((string)$row['updated_at'])),
                'lastMessagePreview'  => $lastMsg ? mb_substr((string)$lastMsg['content'], 0, 80) : '',
                'aiEnabled'           => true,
                'contractProbability' => (int)$row['priority_score'],
            ];
        }
        return ['rooms' => $rooms, 'generatedAt' => date('c')];
    }

    public function roomInsight(string $roomId): array
    {
        $room = $this->rooms->findById($roomId);
        if (!$room) {
            acep_error('ROOM_NOT_FOUND', '상담방을 찾을 수 없습니다.', 404);
        }
        $st = $this->pdo->prepare(
            "SELECT sentiment, contract_probability FROM ai_recommendations
             WHERE room_id = :rid AND status = 'completed'
             ORDER BY created_at DESC LIMIT 1"
        );
        $st->execute([':rid' => $roomId]);
        $latest = $st->fetch() ?: [];

        $cnt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN source = 'ai_recommendation' THEN 1 ELSE 0 END) AS used
             FROM chat_messages WHERE room_id = :rid AND sender_type = 'agent' AND deleted_at IS NULL"
        );
        $cnt->execute([':rid' => $roomId]);
        $usage = $cnt->fetch() ?: ['total' => 0, 'used' => 0];

        return [
            'sentiment' => [
                'label' => $latest['sentiment'] ?? 'neutral',
                'score' => 0.72,
            ],
            'contractProbability' => isset($latest['contract_probability'])
                ? (float)$latest['contract_probability'] : (float)$room['priority_score'],
            'recommendations' => [
                'total' => (int)($usage['total'] ?? 0),
                'used'  => (int)($usage['used'] ?? 0),
            ],
            'failoverCount' => 0,
        ];
    }
}
