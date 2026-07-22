<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

final class AdminConsultService
{
    public function __construct(
        private ChatRoomRepository $rooms,
        private CustomerRepository $customers,
        private AgentRepository $agents,
        private ConsultRepository $consults,
        private PDO $pdo,
    ) {
    }

    /** @param array<string,mixed> $query */
    public function list(array $query): array
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = isset($query['status']) ? (string)$query['status'] : null;
        $source = (string)($query['source'] ?? 'all');

        $data = [];
        if ($source === 'all' || $source === 'acep') {
            $data = array_merge($data, $this->listAcepRooms($status, $limit, $offset));
        }
        if (($source === 'all' || $source === 'crm') && acep_table_exists($this->pdo, 'consults')) {
            $data = array_merge($data, $this->listCrmConsults($limit));
        }

        return [
            'data' => array_slice($data, 0, $limit),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => count($data)],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function listAcepRooms(?string $status, int $limit, int $offset): array
    {
        $where = ['cr.deleted_at IS NULL'];
        $params = [];
        if ($status) {
            $where[] = 'cr.status = :st';
            $params[':st'] = $status;
        }
        $sql = 'SELECT cr.*, c.name AS customer_name, c.phone AS customer_phone
                FROM chat_rooms cr
                JOIN customers c ON c.id = cr.customer_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY cr.updated_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $agent = null;
            if (!empty($r['agent_id'])) {
                $a = $this->agents->findById((string)$r['agent_id']);
                if ($a) {
                    $agent = ['id' => $a['id'], 'displayName' => $a['name']];
                }
            }
            $cust = $this->customers->maskRow([
                'id' => $r['customer_id'], 'name' => $r['customer_name'],
                'phone' => $r['customer_phone'], 'tags' => '[]',
            ]);
            $rows[] = [
                'id'                   => $r['id'],
                'source'               => 'acep',
                'customerNameMasked'   => $cust['name'],
                'agent'                => $agent,
                'status'               => $r['status'],
                'aiEnabled'            => true,
                'aiAdoptionRate'       => 0.0,
                'contractProbability'  => (float)$r['priority_score'],
                'createdAt'            => date('c', strtotime((string)$r['created_at'])),
                'updatedAt'            => date('c', strtotime((string)$r['updated_at'])),
            ];
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function listCrmConsults(int $limit): array
    {
        $st = $this->pdo->query(
            'SELECT id, consult_no, status, lead_score, created_at, updated_at
             FROM consults ORDER BY updated_at DESC LIMIT ' . (int)$limit
        );
        $rows = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $rows[] = [
                'id'                  => (string)$r['consult_no'],
                'source'              => 'crm',
                'customerNameMasked'  => '—',
                'agent'               => null,
                'status'              => $r['status'],
                'aiEnabled'           => $r['lead_score'] !== null,
                'contractProbability' => $r['lead_score'] !== null ? (float)$r['lead_score'] : null,
                'createdAt'           => date('c', strtotime((string)$r['created_at'])),
                'updatedAt'           => date('c', strtotime((string)$r['updated_at'])),
            ];
        }
        return $rows;
    }
}
