<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/CrmSchema.php';

final class ChatRoomRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    private function usesLegacyCustomerFk(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (acep_is_legacy_crm($this->pdo)) {
            return $cache = true;
        }
        if (!acep_table_exists($this->pdo, 'chat_rooms')) {
            return $cache = false;
        }
        $st = $this->pdo->prepare(
            'SELECT DATA_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = \'chat_rooms\'
               AND column_name = \'customer_id\'
             LIMIT 1'
        );
        $st->execute();
        $cache = strtolower((string)$st->fetchColumn()) === 'bigint';
        return $cache;
    }

    private function customerTable(): string
    {
        if ($this->usesLegacyCustomerFk()) {
            return CrmSchema::legacyCustomerTable($this->pdo);
        }
        return 'customers';
    }

    private function customerJoinClause(): string
    {
        $table = $this->customerTable();
        $join = "JOIN {$table} c ON c.id = cr.customer_id";
        if ($table === 'customers' && acep_column_exists($this->pdo, 'customers', 'deleted_at')) {
            $join .= ' AND c.deleted_at IS NULL';
        }
        return $join;
    }

    /** @return string SQL select list for customer columns in room listings */
    private function customerSelectClause(): string
    {
        if ($this->usesLegacyCustomerFk()) {
            return 'c.name AS customer_name, c.phone AS customer_phone, NULL AS customer_tags';
        }
        return 'c.name AS customer_name, c.phone AS customer_phone, c.tags AS customer_tags';
    }

    public function findById(string $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM chat_rooms WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listForAgent(
        string $agentId,
        string $role,
        ?array $statuses,
        ?string $search,
        int $page,
        int $limit,
        string $sort
    ): array {
        $where = ['cr.deleted_at IS NULL'];
        $params = [];

        if ($statuses !== null && $statuses !== []) {
            $ph = [];
            foreach ($statuses as $i => $s) {
                $k = ':st' . $i;
                $ph[] = $k;
                $params[$k] = $s;
            }
            $where[] = 'cr.status IN (' . implode(',', $ph) . ')';
        }

        if (!in_array($role, ['admin', 'operator'], true)) {
            $where[] = '(cr.agent_id = :aid OR cr.status = \'new\')';
            $params[':aid'] = $agentId;
        }

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(c.name LIKE :q1 OR cr.inquiry_type LIKE :q2 OR cr.subject LIKE :q3)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $order = match ($sort) {
            'priority:desc' => 'cr.priority_score DESC, cr.updated_at DESC',
            default => 'FIELD(cr.status, \'new\', \'active\', \'closed\'), cr.priority_score DESC, cr.updated_at DESC',
        };

        $offset = ($page - 1) * $limit;
        $sql = 'SELECT cr.*, ' . $this->customerSelectClause() . '
                FROM chat_rooms cr
                ' . $this->customerJoinClause() . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order . '
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }

    public function countForAgent(
        string $agentId,
        string $role,
        ?array $statuses,
        ?string $search
    ): int {
        $where = ['cr.deleted_at IS NULL'];
        $params = [];

        if ($statuses !== null && $statuses !== []) {
            $ph = [];
            foreach ($statuses as $i => $s) {
                $k = ':st' . $i;
                $ph[] = $k;
                $params[$k] = $s;
            }
            $where[] = 'cr.status IN (' . implode(',', $ph) . ')';
        }

        if (!in_array($role, ['admin', 'operator'], true)) {
            $where[] = '(cr.agent_id = :aid OR cr.status = \'new\')';
            $params[':aid'] = $agentId;
        }

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(c.name LIKE :q1 OR cr.inquiry_type LIKE :q2 OR cr.subject LIKE :q3)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $sql = 'SELECT COUNT(*) FROM chat_rooms cr
                ' . $this->customerJoinClause() . '
                WHERE ' . implode(' AND ', $where);
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    /** @param array<string,mixed> $data bind keys like :id, :customer_id, … */
    public function create(array $data): void
    {
        if (!isset($data[':status']) || $data[':status'] === '') {
            $data[':status'] = 'new';
        }
        if (!isset($data[':channel']) || $data[':channel'] === '') {
            $data[':channel'] = 'web';
        }
        if (!isset($data[':inquiry_type']) || $data[':inquiry_type'] === '') {
            $data[':inquiry_type'] = '상담신청';
        }

        $candidates = [
            'id',
            'customer_id',
            'agent_id',
            'inquiry_type',
            'status',
            'channel',
            'subject',
            'legacy_consult_id',
            'crm_save_status',
        ];
        $columns = [];
        $params = [];
        $stringCustomerId = !$this->usesLegacyCustomerFk();
        foreach ($candidates as $col) {
            if (!acep_column_exists($this->pdo, 'chat_rooms', $col)) {
                continue;
            }
            $key = ':' . $col;
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $columns[] = $col;
            $value = $data[$key];
            if ($col === 'customer_id' && $stringCustomerId && $value !== null) {
                $value = (string)$value;
            }
            $params[$key] = $value;
        }

        if ($columns === [] || !in_array('id', $columns, true) || !in_array('customer_id', $columns, true)) {
            throw new RuntimeException('chat_rooms schema missing required columns (id, customer_id)');
        }

        $placeholders = array_map(static fn(string $c): string => ':' . $c, $columns);
        $sql = 'INSERT INTO chat_rooms (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    public function close(string $id): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE chat_rooms SET status = \'closed\', closed_at = CURRENT_TIMESTAMP(3),
             updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND deleted_at IS NULL AND status != \'closed\''
        );
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }

    public function touchUpdatedAt(string $id): void
    {
        $this->pdo->prepare(
            'UPDATE chat_rooms SET updated_at = CURRENT_TIMESTAMP(3) WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public function setActive(string $id, string $agentId): void
    {
        $this->pdo->prepare(
            'UPDATE chat_rooms SET status = \'active\', agent_id = :aid, updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([':id' => $id, ':aid' => $agentId]);
    }

    public function updatePriorityScore(string $id, int $score): void
    {
        $score = max(0, min(100, $score));
        $this->pdo->prepare(
            'UPDATE chat_rooms SET priority_score = :score, updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([':id' => $id, ':score' => $score]);
    }

    public function unreadCount(string $roomId, string $readerId, string $readerType): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM chat_read_status crs
             JOIN chat_messages cm ON cm.id = crs.message_id AND cm.deleted_at IS NULL
             WHERE crs.room_id = :rid AND crs.reader_type = :rt AND crs.reader_id = :rid2
               AND crs.read_at IS NULL'
        );
        $st->execute([':rid' => $roomId, ':rt' => $readerType, ':rid2' => $readerId]);
        return (int)$st->fetchColumn();
    }

    public function latestContractScore(string $roomId): ?int
    {
        $st = $this->pdo->prepare(
            'SELECT contract_probability FROM ai_recommendations
             WHERE room_id = :rid AND status = \'completed\'
             ORDER BY created_at DESC LIMIT 1'
        );
        $st->execute([':rid' => $roomId]);
        $v = $st->fetchColumn();
        return $v !== false ? (int)$v : null;
    }

    public function markCrmSaved(string $roomId, int $consultId): void
    {
        $this->pdo->prepare(
            'UPDATE chat_rooms SET legacy_consult_id = :cid, crm_save_status = \'saved\',
             crm_saved_at = CURRENT_TIMESTAMP(3), updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id'
        )->execute([':cid' => $consultId, ':id' => $roomId]);
    }

    public function markCrmFailed(string $roomId): void
    {
        $this->pdo->prepare(
            'UPDATE chat_rooms SET crm_save_status = \'failed\', updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id'
        )->execute([':id' => $roomId]);
    }

    public function messageCount(string $roomId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM chat_messages WHERE room_id = :rid AND deleted_at IS NULL'
        );
        $st->execute([':rid' => $roomId]);
        return (int)$st->fetchColumn();
    }

    public function conversationDurationSec(string $roomId): int
    {
        $st = $this->pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND,
                (SELECT MIN(created_at) FROM chat_messages WHERE room_id = :rid1 AND deleted_at IS NULL),
                COALESCE(closed_at, CURRENT_TIMESTAMP(3))
             ) FROM chat_rooms WHERE id = :rid2'
        );
        $st->execute([':rid1' => $roomId, ':rid2' => $roomId]);
        $v = $st->fetchColumn();
        return max(0, (int)$v);
    }
}
