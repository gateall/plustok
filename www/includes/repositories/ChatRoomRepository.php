<?php
declare(strict_types=1);

final class ChatRoomRepository
{
    public function __construct(private PDO $pdo)
    {
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
        $sql = 'SELECT cr.*, c.name AS customer_name, c.phone AS customer_phone, c.tags AS customer_tags
                FROM chat_rooms cr
                JOIN customers c ON c.id = cr.customer_id AND c.deleted_at IS NULL
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
                JOIN customers c ON c.id = cr.customer_id AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where);
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO chat_rooms (id, customer_id, agent_id, inquiry_type, status, channel, subject)
             VALUES (:id, :customer_id, :agent_id, :inquiry_type, :status, :channel, :subject)'
        );
        $st->execute($data);
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
