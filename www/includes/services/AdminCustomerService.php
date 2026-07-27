<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class AdminCustomerService
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    /** @param array<string,mixed> $query */
    public function list(array $query): array
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 20)));

        if (isset($query['page']) && (!ctype_digit((string)$query['page']) || (int)$query['page'] < 1)) {
            acep_error('VALIDATION_ERROR', 'page는 1 이상의 정수여야 합니다.', 400);
        }
        if (isset($query['limit']) && (!ctype_digit((string)$query['limit']) || (int)$query['limit'] < 1 || (int)$query['limit'] > 100)) {
            acep_error('VALIDATION_ERROR', 'limit은 1~100 사이의 정수여야 합니다.', 400);
        }

        $filters = [
            'q'      => $query['q'] ?? null,
            'status' => isset($query['status']) ? trim((string)$query['status']) : null,
            'siteId' => $query['site_id'] ?? null,
            'page'   => $page,
            'limit'  => $limit,
            'sort'   => (string)($query['sort'] ?? 'updated_at'),
            'order'  => (string)($query['order'] ?? 'desc'),
        ];

        $result = $this->customers->paginateForAdmin($filters);

        return [
            'items' => $result['items'],
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
        ];
    }
}
