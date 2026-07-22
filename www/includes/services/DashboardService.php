<?php
declare(strict_types=1);

final class DashboardService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(string $agentId, string $role): array
    {
        $activeChats = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM chat_rooms WHERE status IN ('new','active') AND deleted_at IS NULL"
        )->fetchColumn();

        $totalCustomers = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'
        )->fetchColumn();

        $todayMessages = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM chat_messages
             WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()'
        )->fetchColumn();

        $avgResponse = $this->pdo->query(
            'SELECT AVG(latency_ms) FROM ai_recommendations
             WHERE status = \'completed\' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )->fetchColumn();

        return [
            'activeChats'     => $activeChats,
            'totalCustomers'  => $totalCustomers,
            'avgResponseTime' => $avgResponse !== null ? (int)round((float)$avgResponse) : 0,
            'todayMessages'   => $todayMessages,
        ];
    }
}
