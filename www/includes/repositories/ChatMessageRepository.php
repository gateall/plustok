<?php
declare(strict_types=1);

final class ChatMessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(string $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM chat_messages WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listByRoom(string $roomId, int $limit, ?string $before): array
    {
        $params = [':room_id' => $roomId];
        $beforeSql = '';
        if ($before !== null && $before !== '') {
            $beforeSql = ' AND created_at < :before';
            $params[':before'] = date('Y-m-d H:i:s.v', strtotime($before));
        }

        $sql = 'SELECT * FROM chat_messages
                WHERE room_id = :room_id AND deleted_at IS NULL' . $beforeSql . '
                ORDER BY created_at DESC LIMIT ' . (int)$limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO chat_messages
             (id, room_id, sender_type, sender_id, content, attachment_url, attachment_id,
              attachment_type, source, ai_recommendation_id)
             VALUES
             (:id, :room_id, :sender_type, :sender_id, :content, :attachment_url, :attachment_id,
              :attachment_type, :source, :ai_recommendation_id)'
        );
        $st->execute($data);
    }

    public function softDelete(string $id): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE chat_messages SET deleted_at = CURRENT_TIMESTAMP(3) WHERE id = :id AND deleted_at IS NULL'
        );
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
