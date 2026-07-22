<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class NotificationService
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    /** @param array<string,mixed> $query */
    public function listForAgent(string $agentId, array $query): array
    {
        $unreadOnly = filter_var($query['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit = min(50, max(1, (int)($query['limit'] ?? 20)));
        $rows = $this->notifications->listForAgent($agentId, $unreadOnly, $limit);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'        => $row['id'],
                'type'      => $row['type'],
                'title'     => $row['title'],
                'body'      => $row['body'],
                'readAt'    => $row['read_at'] ? date('c', strtotime((string)$row['read_at'])) : null,
                'createdAt' => date('c', strtotime((string)$row['created_at'])),
            ];
        }

        return ['notifications' => $items, 'unreadOnly' => $unreadOnly];
    }

    /** @return array<string,mixed> */
    public function markRead(string $notificationId, string $agentId): array
    {
        if (!$this->notifications->markRead($notificationId, $agentId)) {
            acep_error('NOT_FOUND', '알림을 찾을 수 없습니다.', 404);
        }
        return ['id' => $notificationId, 'readAt' => date('c')];
    }
}
