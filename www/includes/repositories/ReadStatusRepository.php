<?php
declare(strict_types=1);

final class ReadStatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function markRead(
        string $roomId,
        string $readerType,
        string $readerId,
        array $messageIds
    ): int {
        if ($messageIds === []) {
            return 0;
        }

        $count = 0;
        $st = $this->pdo->prepare(
            'INSERT INTO chat_read_status (room_id, message_id, reader_type, reader_id, delivered_at, read_at)
             VALUES (:room_id, :message_id, :reader_type, :reader_id, CURRENT_TIMESTAMP(3), CURRENT_TIMESTAMP(3))
             ON DUPLICATE KEY UPDATE read_at = IFNULL(read_at, CURRENT_TIMESTAMP(3)),
                                     delivered_at = IFNULL(delivered_at, CURRENT_TIMESTAMP(3))'
        );

        foreach ($messageIds as $mid) {
            $st->execute([
                ':room_id'     => $roomId,
                ':message_id'  => $mid,
                ':reader_type' => $readerType,
                ':reader_id'   => $readerId,
            ]);
            $count++;
        }
        return $count;
    }

    public function readStatusForMessage(string $messageId, string $readerType, string $readerId): string
    {
        $st = $this->pdo->prepare(
            'SELECT read_at, delivered_at FROM chat_read_status
             WHERE message_id = :mid AND reader_type = :rt AND reader_id = :rid LIMIT 1'
        );
        $st->execute([':mid' => $messageId, ':rt' => $readerType, ':rid' => $readerId]);
        $row = $st->fetch();
        if (!$row) {
            return 'sent';
        }
        if (!empty($row['read_at'])) {
            return 'read';
        }
        if (!empty($row['delivered_at'])) {
            return 'delivered';
        }
        return 'sent';
    }
}
