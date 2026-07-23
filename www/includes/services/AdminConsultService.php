<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/CrmSchema.php';

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

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return $this->getAcepDetail($id);
        }

        return $this->getCrmDetail($id);
    }

    /** @return array<string,mixed> */
    private function getAcepDetail(string $roomId): array
    {
        $room = $this->rooms->findById($roomId);
        if (!$room || !empty($room['deleted_at'])) {
            acep_error('NOT_FOUND', '상담을 찾을 수 없습니다.', 404);
        }

        $customer = $this->customers->findById((string)$room['customer_id']);
        if (!$customer) {
            acep_error('NOT_FOUND', '고객을 찾을 수 없습니다.', 404);
        }

        $agent = null;
        if (!empty($room['agent_id'])) {
            $a = $this->agents->findById((string)$room['agent_id']);
            if ($a) {
                $agent = ['id' => $a['id'], 'displayName' => $a['name']];
            }
        }

        $cust = $this->customers->maskRow($customer);
        $consultNo = $roomId;
        $crmRow = $this->consults->findByRoomId($roomId);
        if ($crmRow) {
            $consultNo = (string)$crmRow['consult_no'];
        }

        return [
            'id'                  => $roomId,
            'source'              => 'acep',
            'consultNo'           => $consultNo,
            'status'              => (string)$room['status'],
            'customerNameMasked'  => $cust['name'],
            'phoneMasked'         => $cust['phoneMasked'] ?? null,
            'email'               => null,
            'siteName'            => null,
            'productName'         => (string)($room['subject'] ?? $room['inquiry_type'] ?? ''),
            'memo'                => (string)($room['memo'] ?? ''),
            'agent'               => $agent,
            'roomId'              => $roomId,
            'aiEnabled'           => true,
            'contractProbability' => (float)($room['priority_score'] ?? 0),
            'aiSummary'           => null,
            'createdAt'           => date('c', strtotime((string)$room['created_at'])),
            'updatedAt'           => date('c', strtotime((string)$room['updated_at'])),
        ];
    }

    /** @return array<string,mixed> */
    private function getCrmDetail(string $consultNo): array
    {
        if (!acep_table_exists($this->pdo, 'consults')) {
            acep_error('NOT_FOUND', '상담을 찾을 수 없습니다.', 404);
        }

        $custTable = CrmSchema::legacyCustomerTable($this->pdo);
        $sql = "SELECT c.*, cu.name AS cust_name, cu.phone, cu.email,
                       s.site_name
                FROM consults c
                LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
                LEFT JOIN sites s ON s.id = c.site_id
                WHERE c.consult_no = :no LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':no' => $consultNo]);
        $row = $st->fetch();
        if (!$row) {
            acep_error('NOT_FOUND', '상담을 찾을 수 없습니다.', 404);
        }

        $roomId = $this->resolveRoomId($row);
        $phoneMasked = null;
        $customerName = '—';
        if (!empty($row['cust_name'])) {
            $masked = $this->customers->maskRow([
                'id'    => (string)($row['customer_id'] ?? '0'),
                'name'  => (string)$row['cust_name'],
                'phone' => (string)($row['phone'] ?? ''),
                'tags'  => '[]',
            ]);
            $customerName = $masked['name'];
            $phoneMasked = $masked['phoneMasked'] ?? null;
        }

        return [
            'id'                  => (string)$row['consult_no'],
            'source'              => 'crm',
            'consultNo'           => (string)$row['consult_no'],
            'status'              => (string)$row['status'],
            'customerNameMasked'  => $customerName,
            'phoneMasked'         => $phoneMasked,
            'email'               => !empty($row['email']) ? (string)$row['email'] : null,
            'siteName'            => !empty($row['site_name']) ? (string)$row['site_name'] : null,
            'productName'         => !empty($row['product_name']) ? (string)$row['product_name'] : null,
            'memo'                => !empty($row['memo']) ? (string)$row['memo'] : '',
            'agent'               => null,
            'roomId'              => $roomId,
            'aiEnabled'           => $row['lead_score'] !== null,
            'contractProbability' => $row['lead_score'] !== null ? (float)$row['lead_score'] : null,
            'aiSummary'           => !empty($row['ai_summary']) ? (string)$row['ai_summary'] : null,
            'createdAt'           => date('c', strtotime((string)$row['created_at'])),
            'updatedAt'           => date('c', strtotime((string)$row['updated_at'])),
        ];
    }

    /** @param array<string,mixed> $consult */
    private function resolveRoomId(array $consult): ?string
    {
        $detail = !empty($consult['detail_json'])
            ? json_decode((string)$consult['detail_json'], true)
            : null;
        if (is_array($detail) && !empty($detail['room_id'])) {
            return (string)$detail['room_id'];
        }

        if (!acep_column_exists($this->pdo, 'chat_rooms', 'legacy_consult_id')) {
            return null;
        }

        $st = $this->pdo->prepare(
            'SELECT id FROM chat_rooms
             WHERE legacy_consult_id = :cid AND deleted_at IS NULL
             LIMIT 1'
        );
        $st->execute([':cid' => (int)$consult['id']]);
        $rid = $st->fetchColumn();

        return $rid ? (string)$rid : null;
    }
}
