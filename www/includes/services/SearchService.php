<?php
declare(strict_types=1);

final class SearchService
{
    public function __construct(
        private CustomerRepository $customers,
        private ChatRoomRepository $rooms,
    ) {
    }

    /** @param array<string,mixed> $query */
    public function searchCustomers(string $agentId, string $role, array $query): array
    {
        $q = trim((string)($query['q'] ?? ''));
        if ($q === '') {
            return ['results' => [], 'query' => $q];
        }
        $limit = min(20, max(1, (int)($query['limit'] ?? 10)));
        return [
            'query'   => $q,
            'results' => $this->customers->search($q, $limit),
        ];
    }

    /** @param array<string,mixed> $query */
    public function searchChats(string $agentId, string $role, array $query): array
    {
        $q = trim((string)($query['q'] ?? ''));
        if ($q === '') {
            return ['results' => [], 'query' => $q];
        }
        $limit = min(20, max(1, (int)($query['limit'] ?? 10)));
        $rows = $this->rooms->listForAgent($agentId, $role, null, $q, 1, $limit, 'updated_at:desc');
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id'          => $row['id'],
                'customerName'=> $row['customer_name'] ?? '',
                'inquiryType' => $row['inquiry_type'],
                'status'      => $row['status'],
                'updatedAt'   => date('c', strtotime((string)$row['updated_at'])),
            ];
        }
        return ['query' => $q, 'results' => $results];
    }
}
