<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/../util/ConsultMeta.php';
require_once __DIR__ . '/../util/ConsultSchema.php';

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
            $data = array_merge($data, $this->listAcepRooms($query, $status, $limit, $offset));
        }
        $crmTotal = 0;
        if (($source === 'all' || $source === 'crm') && acep_table_exists($this->pdo, 'consults')) {
            $crmRows = $this->listCrmConsults($query, $limit, $offset);
            $crmTotal = $this->countCrmConsults($query);
            $data = array_merge($data, $crmRows);
        }

        return [
            'data' => array_slice($data, 0, $limit),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => max(count($data), $crmTotal)],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function listAcepRooms(array $query, ?string $status, int $limit, int $offset): array
    {
        $where = ['cr.deleted_at IS NULL'];
        $params = [];
        // 이미 CRM consults 테이블에 저장된 방(legacy_consult_id 연결)은 listCrmConsults()가
        // 사이트명/상품명 등 완전한 데이터로 이미 보여주므로 여기서 중복 표시하지 않는다.
        if (acep_column_exists($this->pdo, 'chat_rooms', 'legacy_consult_id')) {
            $where[] = 'cr.legacy_consult_id IS NULL';
        }
        if ($status) {
            $where[] = 'cr.status = :st';
            $params[':st'] = $status;
        }

        $agentId = trim((string)($query['agentId'] ?? $query['agent_id'] ?? $query['assigneeId'] ?? $query['assignee_id'] ?? ''));
        if ($agentId !== '') {
            $where[] = 'cr.agent_id = :agent_id';
            $params[':agent_id'] = $agentId;
        }

        $from = mb_substr(trim((string)($query['from'] ?? '')), 0, 10);
        $to = mb_substr(trim((string)($query['to'] ?? '')), 0, 10);
        if ($from !== '') {
            $where[] = 'cr.created_at >= :acep_from';
            $params[':acep_from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'cr.created_at <= :acep_to';
            $params[':acep_to'] = $to . ' 23:59:59';
        }

        $q = mb_substr(trim((string)($query['q'] ?? '')), 0, 50);
        if ($q !== '') {
            $where[] = '(c.name LIKE :acep_q OR c.phone LIKE :acep_q OR cr.subject LIKE :acep_q)';
            $params[':acep_q'] = '%' . $q . '%';
        }

        $unreadOnly = filter_var($query['unreadOnly'] ?? $query['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sql = 'SELECT cr.*, c.name AS customer_name, c.phone AS customer_phone,
                       (SELECT COUNT(*)
                        FROM chat_messages cm
                        WHERE cm.room_id = cr.id
                          AND cm.sender_type = \'customer\'
                          AND cm.deleted_at IS NULL
                          AND NOT EXISTS (
                            SELECT 1 FROM chat_read_status rs
                            WHERE rs.message_id = cm.id
                              AND rs.reader_type = \'agent\'
                              AND rs.read_at IS NOT NULL
                          )) AS unread_count
                FROM chat_rooms cr
                JOIN customers c ON c.id = cr.customer_id
                WHERE ' . implode(' AND ', $where)
                . ($unreadOnly ? ' HAVING unread_count > 0' : '') . '
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
                'unreadCount'          => (int)($r['unread_count'] ?? 0),
                'aiEnabled'            => true,
                'aiAdoptionRate'       => 0.0,
                'contractProbability'  => (float)$r['priority_score'],
                'createdAt'            => date('c', strtotime((string)$r['created_at'])),
                'updatedAt'            => date('c', strtotime((string)$r['updated_at'])),
            ];
        }
        return $rows;
    }

    /** @param array<string,mixed> $query */
    /** @return list<array<string,mixed>> */
    private function listCrmConsults(array $query, int $limit, int $offset): array
    {
        [$sqlWhere, $params] = $this->buildCrmConsultFilter($query);
        $custTable = CrmSchema::legacyCustomerTable($this->pdo);

        $sql = "SELECT c.id, c.consult_no, c.status, c.product_name, c.lead_score, c.manager_id, c.tags, c.detail_json, c.created_at, c.updated_at,
                       cu.name AS cust_name, cu.phone, s.site_name
                FROM consults c
                LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
                LEFT JOIN sites s ON s.id = c.site_id
                {$sqlWhere}
                ORDER BY c.updated_at DESC
                LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $rows = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $customerName = 'Unknown';
            $phoneMasked = null;
            if (!empty($r['cust_name'])) {
                $masked = $this->customers->maskRow([
                    'id'    => '0',
                    'name'  => (string)$r['cust_name'],
                    'phone' => (string)($r['phone'] ?? ''),
                    'tags'  => '[]',
                ]);
                $customerName = $masked['name'];
                $phoneMasked = $masked['phoneMasked'] ?? null;
            }

            $rows[] = [
                'id'                  => (string)$r['consult_no'],
                'source'              => 'crm',
                'consultNo'           => (string)$r['consult_no'],
                'customerNameMasked'  => $customerName,
                'phoneMasked'         => $phoneMasked,
                'siteName'            => !empty($r['site_name']) ? (string)$r['site_name'] : null,
                'productName'         => !empty($r['product_name']) ? (string)$r['product_name'] : null,
                'agent'               => $this->formatCrmAgent($r),
                'tags'                => $this->splitTagText((string)($r['tags'] ?? '')),
                'unreadCount'         => $this->unreadCountForRoom($this->resolveRoomId($r)),
                'status'              => (string)$r['status'],
                'aiEnabled'           => $r['lead_score'] !== null,
                'contractProbability' => $r['lead_score'] !== null ? (float)$r['lead_score'] : null,
                'createdAt'           => date('c', strtotime((string)$r['created_at'])),
                'updatedAt'           => date('c', strtotime((string)$r['updated_at'])),
            ];
        }

        return $rows;
    }

    /** @param array<string,mixed> $query */
    private function countCrmConsults(array $query): int
    {
        [$sqlWhere, $params] = $this->buildCrmConsultFilter($query);
        $custTable = CrmSchema::legacyCustomerTable($this->pdo);
        $sql = "SELECT COUNT(*) FROM consults c
                LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
                LEFT JOIN sites s ON s.id = c.site_id
                {$sqlWhere}";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int)$st->fetchColumn();
    }

    /**
     * CRM consult list filters ??parity with admin/consults/index.php.
     *
     * @param array<string,mixed> $query
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildCrmConsultFilter(array $query): array
    {
        $where = [];
        $params = [];

        $fSite = mb_substr(trim((string)($query['site'] ?? '')), 0, 50);
        $fStatus = mb_substr(trim((string)($query['status'] ?? '')), 0, 20);
        $fFrom = mb_substr(trim((string)($query['from'] ?? '')), 0, 10);
        $fTo = mb_substr(trim((string)($query['to'] ?? '')), 0, 10);
        $q = mb_substr(trim((string)($query['q'] ?? '')), 0, 50);
        $fMetaKey = mb_substr(trim((string)($query['meta_key'] ?? '')), 0, 60);
        $fMetaValue = mb_substr(trim((string)($query['meta_value'] ?? '')), 0, 255);
        $fAgent = trim((string)($query['agentId'] ?? $query['agent_id'] ?? $query['assigneeId'] ?? $query['assignee_id'] ?? ''));
        $fTag = mb_substr(trim((string)($query['tag'] ?? '')), 0, 40);
        $unreadOnly = filter_var($query['unreadOnly'] ?? $query['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasConsultMeta = ConsultMeta::tableExists($this->pdo);

        if ($fSite !== '') {
            $where[] = 's.site_code = :site';
            $params[':site'] = $fSite;
        }
        if ($fStatus !== '') {
            $where[] = 'c.status = :st';
            $params[':st'] = $fStatus;
        }
        if ($fFrom !== '') {
            $where[] = 'c.created_at >= :from';
            $params[':from'] = $fFrom . ' 00:00:00';
        }
        if ($fTo !== '') {
            $where[] = 'c.created_at <= :to';
            $params[':to'] = $fTo . ' 23:59:59';
        }
        if ($q !== '') {
            $where[] = '(cu.name LIKE :q OR cu.phone LIKE :q OR c.consult_no LIKE :q OR c.tags LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($fAgent !== '') {
            if (ctype_digit($fAgent)) {
                $where[] = 'c.manager_id = :manager_id';
                $params[':manager_id'] = $fAgent;
            } else {
                $where[] = "JSON_UNQUOTE(JSON_EXTRACT(c.detail_json, '$.assigned_agent_id')) = :manager_id";
                $params[':manager_id'] = $fAgent;
            }
        }
        if ($fTag !== '') {
            $where[] = 'c.tags LIKE :tag';
            $params[':tag'] = '%' . $fTag . '%';
        }
        if ($unreadOnly) {
            $where[] = "EXISTS (SELECT 1 FROM chat_rooms cr JOIN chat_messages cm ON cm.room_id = cr.id WHERE JSON_UNQUOTE(JSON_EXTRACT(c.detail_json, '$.room_id')) = cr.id AND cm.sender_type = 'customer' AND cm.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM chat_read_status rs WHERE rs.message_id = cm.id AND rs.reader_type = 'agent' AND rs.read_at IS NOT NULL))";
        }
        if ($hasConsultMeta && $fMetaKey !== '') {
            if ($fMetaValue !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM consult_meta cm WHERE cm.consult_id = c.id AND cm.meta_key = :mkey AND cm.meta_value = :mval)';
                $params[':mval'] = $fMetaValue;
            } else {
                $where[] = 'EXISTS (SELECT 1 FROM consult_meta cm WHERE cm.consult_id = c.id AND cm.meta_key = :mkey)';
            }
            $params[':mkey'] = $fMetaKey;
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        return [$sqlWhere, $params];
    }

    /** @return list<array{metaKey: string, metaValue: string}> */
    private function fetchConsultMeta(int $consultId): array
    {
        if (!ConsultMeta::tableExists($this->pdo)) {
            return [];
        }

        $st = $this->pdo->prepare(
            'SELECT meta_key, meta_value FROM consult_meta WHERE consult_id = :cid ORDER BY meta_key'
        );
        $st->execute([':cid' => $consultId]);
        $rows = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $rows[] = [
                'metaKey'   => (string)$r['meta_key'],
                'metaValue' => (string)$r['meta_value'],
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
    public function customer(string $id): array
    {
        $row = $this->customers->findById($id);
        if (!$row) {
            acep_error('NOT_FOUND', 'Customer not found.', 404);
        }
        $masked = $this->customers->maskRow($row);
        return [
            'id' => (string)$row['id'],
            'customerNameMasked' => (string)($masked['name'] ?? $row['name'] ?? ''),
            'phoneMasked' => $masked['phoneMasked'] ?? null,
            'tags' => $masked['tags'] ?? [],
            'consultationCount' => isset($row['consultation_count']) ? (int)$row['consultation_count'] : null,
            'createdAt' => !empty($row['created_at']) ? date('c', strtotime((string)$row['created_at'])) : null,
            'updatedAt' => !empty($row['updated_at']) ? date('c', strtotime((string)$row['updated_at'])) : null,
        ];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function customerConsultHistory(string $id, array $query = []): array
    {
        $limit = min(100, max(1, (int)($query['limit'] ?? 20)));
        $data = [];

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)
            && acep_table_exists($this->pdo, 'chat_rooms')) {
            $st = $this->pdo->prepare(
                'SELECT id, status, subject, priority_score, created_at, updated_at
                 FROM chat_rooms
                 WHERE customer_id = :cid AND deleted_at IS NULL
                 ORDER BY updated_at DESC LIMIT ' . (int)$limit
            );
            $st->execute([':cid' => $id]);
            foreach ($st->fetchAll() ?: [] as $row) {
                $data[] = [
                    'id' => (string)$row['id'],
                    'source' => 'acep',
                    'status' => (string)$row['status'],
                    'subject' => (string)($row['subject'] ?? ''),
                    'contractProbability' => (float)($row['priority_score'] ?? 0),
                    'unreadCount' => $this->unreadCountForRoom((string)$row['id']),
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                    'updatedAt' => date('c', strtotime((string)$row['updated_at'])),
                ];
            }
        }

        if (ctype_digit($id) && acep_table_exists($this->pdo, 'consults')) {
            $st = $this->pdo->prepare(
                'SELECT consult_no, status, product_name, lead_score, tags, created_at, updated_at
                 FROM consults
                 WHERE customer_id = :cid
                 ORDER BY updated_at DESC LIMIT ' . (int)$limit
            );
            $st->execute([':cid' => (int)$id]);
            foreach ($st->fetchAll() ?: [] as $row) {
                $data[] = [
                    'id' => (string)$row['consult_no'],
                    'source' => 'crm',
                    'consultNo' => (string)$row['consult_no'],
                    'status' => (string)$row['status'],
                    'productName' => !empty($row['product_name']) ? (string)$row['product_name'] : null,
                    'contractProbability' => $row['lead_score'] !== null ? (float)$row['lead_score'] : null,
                    'tags' => $this->splitTagText((string)($row['tags'] ?? '')),
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                    'updatedAt' => date('c', strtotime((string)$row['updated_at'])),
                ];
            }
        }

        usort($data, static fn(array $a, array $b): int => strcmp((string)$b['updatedAt'], (string)$a['updatedAt']));
        return ['id' => $id, 'data' => array_slice($data, 0, $limit), 'meta' => ['limit' => $limit, 'total' => count($data)]];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function unreadCount(array $query = []): array
    {
        $agentId = trim((string)($query['agentId'] ?? $query['agent_id'] ?? ''));
        $where = ["cm.sender_type = 'customer'", 'cm.deleted_at IS NULL'];
        $params = [];
        if ($agentId !== '') {
            $where[] = '(cr.agent_id = :agent_id OR cr.agent_id IS NULL)';
            $params[':agent_id'] = $agentId;
        }
        $sql = 'SELECT cm.room_id, COUNT(*) AS unread_count
                FROM chat_messages cm
                JOIN chat_rooms cr ON cr.id = cm.room_id
                WHERE ' . implode(' AND ', $where) . '
                  AND cr.deleted_at IS NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM chat_read_status rs
                    WHERE rs.message_id = cm.id
                      AND rs.reader_type = \'agent\'
                      AND rs.read_at IS NOT NULL
                  )
                GROUP BY cm.room_id
                ORDER BY unread_count DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $byRoom = [];
        $total = 0;
        foreach ($st->fetchAll() ?: [] as $row) {
            $count = (int)$row['unread_count'];
            $byRoom[] = ['roomId' => (string)$row['room_id'], 'unreadCount' => $count];
            $total += $count;
        }
        return ['total' => $total, 'byRoom' => $byRoom];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function activityFeed(array $query = []): array
    {
        $limit = min(100, max(1, (int)($query['limit'] ?? 30)));
        $items = [];

        if (acep_table_exists($this->pdo, 'consult_history')) {
            $sql = 'SELECT h.id, h.consult_id, h.from_status, h.to_status, h.manager_id, h.note, h.created_at,
                           c.consult_no
                    FROM consult_history h
                    LEFT JOIN consults c ON c.id = h.consult_id
                    ORDER BY h.created_at DESC LIMIT ' . (int)$limit;
            foreach (($this->pdo->query($sql)->fetchAll() ?: []) as $row) {
                $items[] = [
                    'id' => 'history-' . (string)$row['id'],
                    'type' => 'consult_history',
                    'resourceId' => (string)($row['consult_no'] ?? $row['consult_id']),
                    'fromStatus' => $row['from_status'] !== null ? (string)$row['from_status'] : null,
                    'toStatus' => (string)$row['to_status'],
                    'managerId' => $row['manager_id'] !== null ? (int)$row['manager_id'] : null,
                    'note' => (string)($row['note'] ?? ''),
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                ];
            }
        }

        if (acep_table_exists($this->pdo, 'audit_logs')) {
            $sql = 'SELECT id, actor_type, actor_id, action, resource_type, resource_id, created_at
                    FROM audit_logs
                    ORDER BY created_at DESC LIMIT ' . (int)$limit;
            foreach (($this->pdo->query($sql)->fetchAll() ?: []) as $row) {
                $items[] = [
                    'id' => 'audit-' . (string)$row['id'],
                    'type' => 'audit',
                    'actorType' => (string)$row['actor_type'],
                    'actorId' => $row['actor_id'] !== null ? (string)$row['actor_id'] : null,
                    'action' => (string)$row['action'],
                    'resourceType' => $row['resource_type'] !== null ? (string)$row['resource_type'] : null,
                    'resourceId' => $row['resource_id'] !== null ? (string)$row['resource_id'] : null,
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                ];
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$b['createdAt'], (string)$a['createdAt']));
        return ['data' => array_slice($items, 0, $limit), 'meta' => ['limit' => $limit, 'total' => count($items)]];
    }

    /** @param array<string,mixed> $body */
    public function bulkDelete(array $body): array
    {
        $ids = $this->normalizeIds($body);
        $result = ['requested' => count($ids), 'deleted' => 0, 'notFound' => []];

        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $target = $this->resolveTarget($id);
                if ($target === null) {
                    $result['notFound'][] = $id;
                    continue;
                }

                if ($target['kind'] === 'acep') {
                    $this->pdo->prepare(
                        'UPDATE chat_rooms
                         SET deleted_at = CURRENT_TIMESTAMP(3), updated_at = CURRENT_TIMESTAMP(3)
                         WHERE id = :id AND deleted_at IS NULL'
                    )->execute([':id' => $target['roomId']]);
                    $result['deleted']++;
                    continue;
                }

                $this->pdo->prepare('DELETE FROM consults WHERE id = :id')
                    ->execute([':id' => $target['consultId']]);
                $result['deleted']++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    /** @param array<string,mixed> $body */
    public function updateStatus(string $id, array $body): array
    {
        $status = trim((string)($body['status'] ?? ''));
        if ($status === '') {
            acep_error('VALIDATION_ERROR', 'status is required.', 400);
        }

        $result = $this->bulkUpdateStatus(['ids' => [$id], 'status' => $status]);
        return ['id' => $id, 'status' => $status, 'updated' => $result['updated']];
    }

    /** @param array<string,mixed> $body */
    public function bulkUpdateStatus(array $body): array
    {
        $ids = $this->normalizeIds($body);
        $status = trim((string)($body['status'] ?? ''));
        if ($status === '') {
            acep_error('VALIDATION_ERROR', 'status is required.', 400);
        }

        $result = ['requested' => count($ids), 'updated' => 0, 'notFound' => [], 'skipped' => []];
        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $target = $this->resolveTarget($id);
                if ($target === null) {
                    $result['notFound'][] = $id;
                    continue;
                }

                if ($target['kind'] === 'acep') {
                    $roomStatus = $this->mapAcepStatus($status);
                    $this->pdo->prepare(
                        'UPDATE chat_rooms
                         SET status = :status, updated_at = CURRENT_TIMESTAMP(3)
                         WHERE id = :id AND deleted_at IS NULL'
                    )->execute([':status' => $roomStatus, ':id' => $target['roomId']]);
                    $result['updated']++;
                    continue;
                }

                $crmStatus = $this->normalizeCrmStatus($status);
                if ($crmStatus === null) {
                    $result['skipped'][] = ['id' => $id, 'reason' => 'invalid_crm_status'];
                    continue;
                }
                $from = (string)($target['row']['status'] ?? '');
                $this->pdo->prepare(
                    'UPDATE consults
                     SET status = :status, updated_at = NOW()
                     WHERE id = :id'
                )->execute([':status' => $crmStatus, ':id' => $target['consultId']]);
                $this->recordHistory((int)$target['consultId'], $from, $crmStatus, null, 'Admin status update');
                $result['updated']++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    /** @param array<string,mixed> $body */
    public function updateAssignee(string $id, array $body): array
    {
        $result = $this->bulkUpdateAssignee(['ids' => [$id]] + $body);
        return ['id' => $id, 'updated' => $result['updated']];
    }

    /** @param array<string,mixed> $body */
    public function bulkUpdateAssignee(array $body): array
    {
        $ids = $this->normalizeIds($body);
        $agentId = $body['agentId'] ?? $body['agent_id'] ?? $body['managerId'] ?? $body['manager_id'] ?? null;
        $agentId = $agentId === null ? null : trim((string)$agentId);
        if ($agentId === '') {
            $agentId = null;
        }

        $result = ['requested' => count($ids), 'updated' => 0, 'notFound' => []];
        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $target = $this->resolveTarget($id);
                if ($target === null) {
                    $result['notFound'][] = $id;
                    continue;
                }

                if ($target['kind'] === 'acep') {
                    $this->pdo->prepare(
                        'UPDATE chat_rooms
                         SET agent_id = :agent, updated_at = CURRENT_TIMESTAMP(3)
                         WHERE id = :id AND deleted_at IS NULL'
                    )->execute([':agent' => $agentId, ':id' => $target['roomId']]);
                    $result['updated']++;
                    continue;
                }

                $managerId = $agentId !== null && ctype_digit($agentId) ? (int)$agentId : null;
                $detail = $this->withDetailValue((array)$target['row'], 'assigned_agent_id', $agentId);
                $this->pdo->prepare(
                    'UPDATE consults
                     SET manager_id = :manager, detail_json = :detail, updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    ':manager' => $managerId,
                    ':detail'  => $detail,
                    ':id'      => $target['consultId'],
                ]);
                $this->recordHistory(
                    (int)$target['consultId'],
                    (string)($target['row']['status'] ?? ''),
                    (string)($target['row']['status'] ?? ''),
                    $managerId,
                    'Admin assignee update'
                );
                $result['updated']++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    /** @param array<string,mixed> $body */
    public function updateTags(array $body): array
    {
        $ids = $this->normalizeIds($body);
        $tags = $this->normalizeTags($body['tags'] ?? []);
        $tagText = implode(' ', $tags);

        $result = ['requested' => count($ids), 'updated' => 0, 'notFound' => []];
        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $target = $this->resolveTarget($id);
                if ($target === null) {
                    $result['notFound'][] = $id;
                    continue;
                }

                if ($target['kind'] === 'acep') {
                    $this->pdo->prepare(
                        'UPDATE customers c
                         JOIN chat_rooms cr ON cr.customer_id = c.id
                         SET c.tags = :tags, c.updated_at = CURRENT_TIMESTAMP(3)
                         WHERE cr.id = :id AND cr.deleted_at IS NULL'
                    )->execute([
                        ':tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
                        ':id'   => $target['roomId'],
                    ]);
                    $result['updated']++;
                    continue;
                }

                $this->pdo->prepare('UPDATE consults SET tags = :tags, updated_at = NOW() WHERE id = :id')
                    ->execute([':tags' => $tagText, ':id' => $target['consultId']]);
                $this->recordHistory(
                    (int)$target['consultId'],
                    (string)($target['row']['status'] ?? ''),
                    (string)($target['row']['status'] ?? ''),
                    null,
                    'Admin tag update'
                );
                $result['updated']++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function timeline(string $id): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null) {
            acep_error('NOT_FOUND', 'Consult not found.', 404);
        }

        $items = [];
        if ($target['kind'] === 'crm' && acep_table_exists($this->pdo, 'consult_history')) {
            $st = $this->pdo->prepare(
                'SELECT id, from_status, to_status, manager_id, note, created_at
                 FROM consult_history
                 WHERE consult_id = :id
                 ORDER BY created_at ASC, id ASC'
            );
            $st->execute([':id' => $target['consultId']]);
            foreach ($st->fetchAll() ?: [] as $row) {
                $items[] = [
                    'id' => 'history-' . (string)$row['id'],
                    'type' => 'status',
                    'fromStatus' => $row['from_status'] !== null ? (string)$row['from_status'] : null,
                    'toStatus' => (string)$row['to_status'],
                    'managerId' => $row['manager_id'] !== null ? (int)$row['manager_id'] : null,
                    'note' => (string)($row['note'] ?? ''),
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                ];
            }
        }

        $roomId = $target['roomId'] ?? null;
        if ($roomId !== null && acep_table_exists($this->pdo, 'chat_messages')) {
            $st = $this->pdo->prepare(
                'SELECT id, sender_type, content, created_at
                 FROM chat_messages
                 WHERE room_id = :rid AND deleted_at IS NULL
                 ORDER BY created_at ASC'
            );
            $st->execute([':rid' => $roomId]);
            foreach ($st->fetchAll() ?: [] as $row) {
                $items[] = [
                    'id' => 'message-' . (string)$row['id'],
                    'type' => 'message',
                    'senderType' => (string)$row['sender_type'],
                    'content' => (string)$row['content'],
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                ];
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['createdAt'], (string)$b['createdAt']));

        return ['id' => $id, 'data' => $items];
    }

    /** @return array<string,mixed> */
    public function attachments(string $id): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null) {
            acep_error('NOT_FOUND', 'Consult not found.', 404);
        }

        $items = [];
        if (($target['kind'] ?? '') === 'crm' && acep_table_exists($this->pdo, 'consult_files')) {
            $st = $this->pdo->prepare(
                'SELECT id, file_category, orig_name, saved_path, file_size, file_ext, uploader_id, created_at
                 FROM consult_files
                 WHERE consult_id = :id
                 ORDER BY created_at DESC, id DESC'
            );
            $st->execute([':id' => $target['consultId']]);
            foreach ($st->fetchAll() ?: [] as $row) {
                $items[] = $this->formatCrmFile($row);
            }
        }

        $roomId = $target['roomId'] ?? null;
        if ($roomId !== null && acep_table_exists($this->pdo, 'attachments')) {
            $st = $this->pdo->prepare(
                'SELECT id, file_name, mime_type, file_size, storage_path, public_url, uploader_id, created_at
                 FROM attachments
                 WHERE room_id = :rid AND deleted_at IS NULL
                 ORDER BY created_at DESC'
            );
            $st->execute([':rid' => $roomId]);
            foreach ($st->fetchAll() ?: [] as $row) {
                $items[] = [
                    'id' => (string)$row['id'],
                    'source' => 'acep',
                    'fileName' => (string)$row['file_name'],
                    'mimeType' => (string)$row['mime_type'],
                    'fileSize' => (int)$row['file_size'],
                    'storagePath' => (string)$row['storage_path'],
                    'publicUrl' => (string)$row['public_url'],
                    'uploaderId' => (string)$row['uploader_id'],
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                ];
            }
        }

        return ['id' => $id, 'data' => $items];
    }

    /** @param array<string,mixed> $body */
    public function createAttachment(string $id, array $body): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null) {
            acep_error('NOT_FOUND', 'Consult not found.', 404);
        }
        if (($target['kind'] ?? '') !== 'crm' || !acep_table_exists($this->pdo, 'consult_files')) {
            acep_error('VALIDATION_ERROR', 'CRM consult_files table is required.', 400);
        }

        $name = trim((string)($body['fileName'] ?? $body['origName'] ?? ''));
        $path = trim((string)($body['savedPath'] ?? $body['storagePath'] ?? ''));
        if ($name === '' || $path === '') {
            acep_error('VALIDATION_ERROR', 'fileName and savedPath are required.', 400);
        }

        $category = mb_substr(trim((string)($body['category'] ?? 'etc')), 0, 50);
        $size = max(0, (int)($body['fileSize'] ?? 0));
        $ext = mb_substr(trim((string)($body['fileExt'] ?? pathinfo($name, PATHINFO_EXTENSION))), 0, 20);
        $uploader = (int)($body['uploaderId'] ?? 0);

        $this->pdo->prepare(
            'INSERT INTO consult_files
             (consult_id, file_category, orig_name, saved_path, file_size, file_ext, uploader_id)
             VALUES (:cid, :cat, :name, :path, :size, :ext, :uid)'
        )->execute([
            ':cid'  => $target['consultId'],
            ':cat'  => $category,
            ':name' => $name,
            ':path' => $path,
            ':size' => $size,
            ':ext'  => $ext,
            ':uid'  => $uploader,
        ]);

        $fileId = (int)$this->pdo->lastInsertId();
        $this->recordHistory(
            (int)$target['consultId'],
            (string)($target['row']['status'] ?? ''),
            (string)($target['row']['status'] ?? ''),
            $uploader > 0 ? $uploader : null,
            'Admin attachment added'
        );

        return ['id' => $fileId, 'created' => true];
    }

    public function deleteAttachment(string $id, string $fileId): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null) {
            acep_error('NOT_FOUND', 'Consult not found.', 404);
        }

        if (ctype_digit($fileId) && acep_table_exists($this->pdo, 'consult_files')) {
            $st = $this->pdo->prepare('DELETE FROM consult_files WHERE id = :fid AND consult_id = :cid');
            $st->execute([':fid' => (int)$fileId, ':cid' => (int)($target['consultId'] ?? 0)]);
            return ['id' => $fileId, 'deleted' => $st->rowCount() > 0];
        }

        if (acep_table_exists($this->pdo, 'attachments')) {
            $st = $this->pdo->prepare(
                'UPDATE attachments SET deleted_at = CURRENT_TIMESTAMP(3)
                 WHERE id = :fid AND room_id = :rid AND deleted_at IS NULL'
            );
            $st->execute([':fid' => $fileId, ':rid' => (string)($target['roomId'] ?? '')]);
            return ['id' => $fileId, 'deleted' => $st->rowCount() > 0];
        }

        return ['id' => $fileId, 'deleted' => false];
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function createTimelineEvent(string $id, array $body): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null || ($target['kind'] ?? '') !== 'crm') {
            acep_error('NOT_FOUND', 'CRM consult not found.', 404);
        }

        $note = mb_substr(trim((string)($body['note'] ?? $body['content'] ?? '')), 0, 255);
        if ($note === '') {
            acep_error('VALIDATION_ERROR', 'note is required.', 400);
        }

        $current = (string)($target['row']['status'] ?? '');
        $toStatus = trim((string)($body['toStatus'] ?? $body['to_status'] ?? $current));
        $crmStatus = $this->normalizeCrmStatus($toStatus) ?? $current;
        $managerRaw = $body['managerId'] ?? $body['manager_id'] ?? null;
        $managerId = $managerRaw !== null && ctype_digit((string)$managerRaw) ? (int)$managerRaw : null;

        if ($crmStatus !== $current) {
            $this->pdo->prepare('UPDATE consults SET status = :status, updated_at = NOW() WHERE id = :id')
                ->execute([':status' => $crmStatus, ':id' => $target['consultId']]);
        }

        $this->recordHistory((int)$target['consultId'], $current, $crmStatus, $managerId, $note);
        $historyId = (int)$this->pdo->lastInsertId();

        return ['id' => $historyId, 'consultId' => (int)$target['consultId'], 'created' => true];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function listTags(array $query): array
    {
        $q = mb_strtolower(trim((string)($query['q'] ?? '')));
        $limit = min(100, max(1, (int)($query['limit'] ?? 50)));
        $tags = [];

        if (acep_table_exists($this->pdo, 'consults') && acep_column_exists($this->pdo, 'consults', 'tags')) {
            $rows = $this->pdo->query("SELECT tags FROM consults WHERE tags IS NOT NULL AND tags != '' LIMIT 1000")->fetchAll() ?: [];
            foreach ($rows as $row) {
                foreach ($this->splitTagText((string)$row['tags']) as $tag) {
                    if ($q !== '' && !str_contains(mb_strtolower($tag), $q)) {
                        continue;
                    }
                    $tags[$tag] = ($tags[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($tags);
        $data = [];
        foreach (array_slice($tags, 0, $limit, true) as $tag => $count) {
            $data[] = ['name' => (string)$tag, 'count' => (int)$count];
        }

        return ['data' => $data, 'meta' => ['limit' => $limit, 'total' => count($tags)]];
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function createTag(array $body): array
    {
        $tags = $this->normalizeTags([$body['tag'] ?? $body['name'] ?? '']);
        if ($tags === []) {
            acep_error('VALIDATION_ERROR', 'tag is required.', 400);
        }
        return ['name' => $tags[0], 'created' => true];
    }

    /** @return array<string,mixed> */
    public function deleteTag(string $tag): array
    {
        $tag = trim(rawurldecode($tag));
        if ($tag === '') {
            acep_error('VALIDATION_ERROR', 'tag is required.', 400);
        }

        $updated = 0;
        if (acep_table_exists($this->pdo, 'consults') && acep_column_exists($this->pdo, 'consults', 'tags')) {
            $rows = $this->pdo->query("SELECT id, tags FROM consults WHERE tags IS NOT NULL AND tags != ''")->fetchAll() ?: [];
            $st = $this->pdo->prepare('UPDATE consults SET tags = :tags, updated_at = NOW() WHERE id = :id');
            foreach ($rows as $row) {
                $current = $this->splitTagText((string)$row['tags']);
                $next = array_values(array_filter($current, static fn(string $value): bool => $value !== $tag));
                if ($next !== $current) {
                    $st->execute([':tags' => implode(' ', $next), ':id' => (int)$row['id']]);
                    $updated++;
                }
            }
        }

        return ['name' => $tag, 'deleted' => true, 'updatedConsults' => $updated];
    }

    /** @return array<string,mixed> */
    public function attachment(string $id, string $fileId): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null) {
            acep_error('NOT_FOUND', 'Consult not found.', 404);
        }

        if (ctype_digit($fileId) && acep_table_exists($this->pdo, 'consult_files')) {
            $st = $this->pdo->prepare(
                'SELECT id, file_category, orig_name, saved_path, file_size, file_ext, uploader_id, created_at
                 FROM consult_files
                 WHERE id = :fid AND consult_id = :cid
                 LIMIT 1'
            );
            $st->execute([':fid' => (int)$fileId, ':cid' => (int)($target['consultId'] ?? 0)]);
            $row = $st->fetch();
            if ($row) {
                return $this->formatCrmFile($row) + ['downloadUrl' => (string)$row['saved_path']];
            }
        }

        if (acep_table_exists($this->pdo, 'attachments')) {
            $st = $this->pdo->prepare(
                'SELECT id, file_name, mime_type, file_size, storage_path, public_url, uploader_id, created_at
                 FROM attachments
                 WHERE id = :fid AND room_id = :rid AND deleted_at IS NULL
                 LIMIT 1'
            );
            $st->execute([':fid' => $fileId, ':rid' => (string)($target['roomId'] ?? '')]);
            $row = $st->fetch();
            if ($row) {
                return [
                    'id' => (string)$row['id'],
                    'source' => 'acep',
                    'fileName' => (string)$row['file_name'],
                    'mimeType' => (string)$row['mime_type'],
                    'fileSize' => (int)$row['file_size'],
                    'storagePath' => (string)$row['storage_path'],
                    'publicUrl' => (string)$row['public_url'],
                    'downloadUrl' => (string)$row['public_url'],
                    'uploaderId' => (string)$row['uploader_id'],
                    'createdAt' => date('c', strtotime((string)$row['created_at'])),
                ];
            }
        }

        acep_error('NOT_FOUND', 'Attachment not found.', 404);
    }

    /** @param array<string,mixed> $file @param array<string,mixed> $body @return array<string,mixed> */
    public function uploadAttachment(string $id, array $file, array $body): array
    {
        $target = $this->resolveTarget($id);
        if ($target === null || ($target['kind'] ?? '') !== 'crm') {
            acep_error('NOT_FOUND', 'CRM consult not found.', 404);
        }
        if (!acep_table_exists($this->pdo, 'consult_files')) {
            acep_error('VALIDATION_ERROR', 'CRM consult_files table is required.', 400);
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            acep_error('VALIDATION_ERROR', 'file upload failed.', 400);
        }

        $original = trim((string)($file['name'] ?? ''));
        $size = (int)($file['size'] ?? 0);
        $tmp = (string)($file['tmp_name'] ?? '');
        $ext = mb_strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        if ($original === '' || $tmp === '' || $size <= 0) {
            acep_error('VALIDATION_ERROR', 'invalid file.', 400);
        }
        if (defined('UPLOAD_MAX_BYTES') && $size > UPLOAD_MAX_BYTES) {
            acep_error('VALIDATION_ERROR', 'file too large.', 400);
        }
        if (defined('UPLOAD_ALLOWED_EXT') && !in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
            acep_error('VALIDATION_ERROR', 'file extension is not allowed.', 400);
        }

        $dir = rtrim(UPLOAD_PATH, '/\\') . '/admin-consults/' . date('Ym');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            acep_error('SERVER_ERROR', 'upload directory cannot be created.', 500);
        }
        $safeName = bin2hex(random_bytes(12)) . ($ext !== '' ? '.' . $ext : '');
        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            acep_error('SERVER_ERROR', 'file cannot be stored.', 500);
        }

        return $this->createAttachment($id, [
            'fileName' => $original,
            'savedPath' => 'uploads/admin-consults/' . date('Ym') . '/' . $safeName,
            'fileSize' => $size,
            'fileExt' => $ext,
            'category' => $body['category'] ?? 'etc',
            'uploaderId' => $body['uploaderId'] ?? 0,
        ]);
    }

    /** @return array<string,mixed> */
    private function getAcepDetail(string $roomId): array
    {
        $room = $this->rooms->findById($roomId);
        if (!$room || !empty($room['deleted_at'])) {
            acep_error('NOT_FOUND', '?�담??찾을 ???�습?�다.', 404);
        }

        $customer = $this->customers->findById((string)$room['customer_id']);
        if (!$customer) {
            acep_error('NOT_FOUND', '고객??찾을 ???�습?�다.', 404);
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
            acep_error('NOT_FOUND', '?�담??찾을 ???�습?�다.', 404);
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
            acep_error('NOT_FOUND', '?�담??찾을 ???�습?�다.', 404);
        }

        $roomId = $this->resolveRoomId($row);
        $phoneMasked = null;
        $customerName = 'Unknown';
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

        $detailJson = !empty($row['detail_json'])
            ? json_decode((string)$row['detail_json'], true)
            : null;
        if (!is_array($detailJson)) {
            $detailJson = null;
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
            'aiSummaryAt'         => !empty($row['ai_summary_at']) ? date('c', strtotime((string)$row['ai_summary_at'])) : null,
            'sentiment'           => !empty($row['sentiment']) ? (string)$row['sentiment'] : null,
            'priority'            => !empty($row['priority']) ? (string)$row['priority'] : null,
            'tags'                => !empty($row['tags']) ? preg_split('/[\s,]+/', trim((string)$row['tags'])) : [],
            'categoryAi'          => !empty($row['category_ai']) ? (string)$row['category_ai'] : null,
            'contractScore'       => null,
            'confidence'          => isset($row['ai_confidence']) ? (float)$row['ai_confidence'] : null,
            'aiAnalyzedAt'        => !empty($row['ai_analyzed_at']) ? date('c', strtotime((string)$row['ai_analyzed_at'])) : null,
            'detailJson'          => $detailJson,
            'consultMeta'         => $this->fetchConsultMeta((int)$row['id']),
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
    /** @param array<string,mixed> $body @return list<string> */
    private function normalizeIds(array $body): array
    {
        $ids = $body['ids'] ?? $body['consultIds'] ?? $body['consult_ids'] ?? [];
        if (!is_array($ids)) {
            acep_error('VALIDATION_ERROR', 'ids must be an array.', 400);
        }
        $out = [];
        foreach ($ids as $id) {
            $value = trim((string)$id);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        if ($out === []) {
            acep_error('VALIDATION_ERROR', 'ids are required.', 400);
        }
        return array_values(array_unique($out));
    }

    /** @return array<string,mixed>|null */
    private function resolveTarget(string $id): ?array
    {
        if (!acep_table_exists($this->pdo, 'consults') && !acep_table_exists($this->pdo, 'chat_rooms')) {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            $room = $this->rooms->findById($id);
            if (!$room) {
                return null;
            }
            $crmRow = acep_table_exists($this->pdo, 'consults') ? $this->consults->findByRoomId($id) : null;
            if (!$crmRow && acep_table_exists($this->pdo, 'consults') && acep_column_exists($this->pdo, 'chat_rooms', 'legacy_consult_id')) {
                $st = $this->pdo->prepare('SELECT * FROM consults WHERE id = :id LIMIT 1');
                $st->execute([':id' => (int)($room['legacy_consult_id'] ?? 0)]);
                $crmRow = $st->fetch() ?: null;
            }
            return [
                'kind' => $crmRow ? 'crm' : 'acep',
                'roomId' => $id,
                'consultId' => $crmRow ? (int)$crmRow['id'] : null,
                'row' => $crmRow ?: $room,
            ];
        }

        if (acep_table_exists($this->pdo, 'consults')) {
            if (ctype_digit($id)) {
                $st = $this->pdo->prepare('SELECT * FROM consults WHERE id = :id LIMIT 1');
                $st->execute([':id' => (int)$id]);
                $row = $st->fetch();
            } else {
                $row = $this->consults->findByConsultNo($id);
            }
            if ($row) {
                return [
                    'kind' => 'crm',
                    'consultId' => (int)$row['id'],
                    'roomId' => $this->resolveRoomId($row),
                    'row' => $row,
                ];
            }
        }

        return null;
    }

    private function mapAcepStatus(string $status): string
    {
        return match ($status) {
            'new', 'receipt' => 'new',
            'active', 'progress', 'consulting', 'quoted', 'contracted' => 'active',
            'closed', 'completed', 'installed', 'hold', 'canceled' => 'closed',
            default => in_array($status, ['new', 'active', 'closed'], true) ? $status : 'active',
        };
    }

    private function normalizeCrmStatus(string $status): ?string
    {
        $values = ConsultSchema::statusEnumValues($this->pdo);
        $map = [
            'receipt' => 'new',
            'active' => 'progress',
            'completed' => 'installed',
            'closed' => 'installed',
        ];
        $candidate = $map[$status] ?? $status;
        return in_array($candidate, $values, true) ? $candidate : null;
    }

    private function recordHistory(int $consultId, ?string $from, string $to, ?int $managerId, string $note): void
    {
        if (!acep_table_exists($this->pdo, 'consult_history')) {
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO consult_history (consult_id, from_status, to_status, manager_id, note)
             VALUES (:cid, :from, :to, :mid, :note)'
        )->execute([
            ':cid' => $consultId,
            ':from' => $from !== '' ? $from : null,
            ':to' => $to,
            ':mid' => $managerId,
            ':note' => $note,
        ]);
    }

    /** @param mixed $tags @return list<string> */
    private function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[\s,]+/', $tags) ?: [];
        }
        if (!is_array($tags)) {
            acep_error('VALIDATION_ERROR', 'tags must be an array or string.', 400);
        }

        $out = [];
        foreach ($tags as $tag) {
            $value = trim((string)$tag);
            if ($value !== '') {
                $out[] = mb_substr($value, 0, 40);
            }
        }
        return array_values(array_unique(array_slice($out, 0, 12)));
    }

    private function unreadCountForRoom(?string $roomId): int
    {
        if ($roomId === null || $roomId === '' || !acep_table_exists($this->pdo, 'chat_messages')) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM chat_messages cm
             WHERE cm.room_id = :rid
               AND cm.sender_type = \'customer\'
               AND cm.deleted_at IS NULL
               AND NOT EXISTS (
                 SELECT 1 FROM chat_read_status rs
                 WHERE rs.message_id = cm.id
                   AND rs.reader_type = \'agent\'
                   AND rs.read_at IS NOT NULL
               )'
        );
        $st->execute([':rid' => $roomId]);
        return (int)$st->fetchColumn();
    }

    /** @param array<string,mixed> $row @return array<string,mixed>|null */
    private function formatCrmAgent(array $row): ?array
    {
        $managerId = $row['manager_id'] ?? null;
        if ($managerId === null || $managerId === '') {
            $detail = !empty($row['detail_json']) ? json_decode((string)$row['detail_json'], true) : null;
            if (is_array($detail) && !empty($detail['assigned_agent_id'])) {
                $managerId = (string)$detail['assigned_agent_id'];
            }
        }
        if ($managerId === null || $managerId === '') {
            return null;
        }
        $agent = $this->agents->findById((string)$managerId);
        if ($agent) {
            return ['id' => (string)$agent['id'], 'displayName' => (string)$agent['name']];
        }
        return ['id' => (string)$managerId, 'displayName' => null];
    }

    private function splitTagText(string $tags): array
    {
        $parts = preg_split('/[\s,]+/', trim($tags)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $value = trim((string)$part);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return array_values(array_unique($out));
    }
    /** @param array<string,mixed> $row */
    private function withDetailValue(array $row, string $key, ?string $value): string
    {
        $detail = !empty($row['detail_json'])
            ? json_decode((string)$row['detail_json'], true)
            : [];
        if (!is_array($detail)) {
            $detail = [];
        }
        $detail[$key] = $value;
        return (string)json_encode($detail, JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function formatCrmFile(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'source' => 'crm',
            'category' => (string)($row['file_category'] ?? 'etc'),
            'fileName' => (string)$row['orig_name'],
            'fileSize' => (int)$row['file_size'],
            'fileExt' => $row['file_ext'] !== null ? (string)$row['file_ext'] : null,
            'savedPath' => (string)$row['saved_path'],
            'uploaderId' => (int)$row['uploader_id'],
            'createdAt' => date('c', strtotime((string)$row['created_at'])),
        ];
    }
}
