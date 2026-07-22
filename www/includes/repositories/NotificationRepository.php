<?php
declare(strict_types=1);

final class NotificationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listForAgent(string $agentId, bool $unreadOnly, int $limit): array
    {
        $sql = 'SELECT * FROM agent_notifications WHERE agent_id = :aid';
        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int)$limit;
        $st = $this->pdo->prepare($sql);
        $st->execute([':aid' => $agentId]);
        return $st->fetchAll() ?: [];
    }

    public function markRead(string $id, string $agentId): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE agent_notifications SET read_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND agent_id = :aid AND read_at IS NULL'
        );
        $st->execute([':id' => $id, ':aid' => $agentId]);
        return $st->rowCount() > 0;
    }

    public function create(string $id, string $agentId, string $type, string $title, ?string $body = null): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO agent_notifications (id, agent_id, type, title, body)
             VALUES (:id, :aid, :type, :title, :body)'
        );
        $st->execute([
            ':id'    => $id,
            ':aid'   => $agentId,
            ':type'  => $type,
            ':title' => $title,
            ':body'  => $body,
        ]);
    }
}
