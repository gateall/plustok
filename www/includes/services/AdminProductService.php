<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../util/ProductSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class AdminProductService
{
    public function __construct(
        private ProductRepository $products,
        private AuditService $audit,
        private PDO $pdo,
    ) {
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

        $useYn = null;
        if (array_key_exists('use_yn', $query)) {
            $useYn = $query['use_yn'];
        } elseif (array_key_exists('useYn', $query)) {
            $useYn = $query['useYn'];
        }

        $filters = [
            'q'      => $query['q'] ?? null,
            'brand'  => $query['brand'] ?? null,
            'use_yn' => $useYn,
            'page'   => $page,
            'limit'  => $limit,
        ];

        $result = $this->products->paginate($filters);
        $items = [];
        foreach ($result['items'] as $row) {
            $items[] = $this->mapProduct($row);
        }

        return [
            'items' => $items,
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    public function get(int $id): array
    {
        return $this->mapProduct($this->requireProduct($id));
    }

    /** @param array<string,mixed> $body */
    public function create(string $actorId, array $body): array
    {
        $payload = $this->validatePayload($body);
        $id = $this->products->create($payload);
        $this->audit->agentAction($actorId, 'product.create', 'product', (string)$id, [
            'brand' => $payload['brand'],
            'productName' => $payload['product_name'],
        ]);
        return $this->get($id);
    }

    /** @param array<string,mixed> $body */
    public function update(string $actorId, int $id, array $body): array
    {
        $this->requireProduct($id);
        $payload = $this->validatePayload($body);
        $this->products->update($id, $payload);
        $this->audit->agentAction($actorId, 'product.update', 'product', (string)$id, [
            'brand' => $payload['brand'],
            'productName' => $payload['product_name'],
        ]);
        return $this->get($id);
    }

    public function delete(string $actorId, int $id): array
    {
        $product = $this->requireProduct($id);
        if ($this->products->hasConsults($id)) {
            acep_error('PRODUCT_IN_USE', '상담이 연결된 상품은 삭제할 수 없습니다. 사용 중지를 권장합니다.', 409);
        }
        $this->products->delete($id);
        $this->audit->agentAction($actorId, 'product.delete', 'product', (string)$id, [
            'productName' => $product['product_name'] ?? '',
        ]);
        return ['deleted' => true, 'id' => $id];
    }

    public function toggle(string $actorId, int $id): array
    {
        $before = $this->requireProduct($id);
        $this->products->toggleActive($id);
        $after = $this->requireProduct($id);
        $this->audit->agentAction($actorId, 'product.toggle', 'product', (string)$id, [
            'from' => $this->productIsActive($before),
            'to' => $this->productIsActive($after),
        ]);
        return $this->mapProduct($after);
    }

    /** @param array<string,mixed> $body */
    private function validatePayload(array $body): array
    {
        $brand = trim((string)($body['brand'] ?? ''));
        $category = trim((string)($body['category'] ?? ''));
        $productName = trim((string)($body['productName'] ?? $body['product_name'] ?? ''));
        $sortOrder = (int)($body['sortOrder'] ?? $body['sort_order'] ?? 0);

        if ($brand === '' || $category === '' || $productName === '') {
            acep_error('VALIDATION_ERROR', 'brand, category, productName은 필수입니다.', 400);
        }
        if (mb_strlen($brand) > 50) {
            acep_error('VALIDATION_ERROR', 'brand는 50자 이하여야 합니다.', 400);
        }
        if (mb_strlen($category) > 60) {
            acep_error('VALIDATION_ERROR', 'category는 60자 이하여야 합니다.', 400);
        }
        if (mb_strlen($productName) > 100) {
            acep_error('VALIDATION_ERROR', 'productName은 100자 이하여야 합니다.', 400);
        }

        $payload = [
            'brand' => $brand,
            'category' => $category,
            'product_name' => $productName,
            'sort_order' => $sortOrder,
        ];

        if (ProductSchema::hasSiteScope($this->pdo)) {
            $siteId = $body['siteId'] ?? $body['site_id'] ?? null;
            if ($siteId === '' || $siteId === null) {
                $payload['site_id'] = null;
            } else {
                $payload['site_id'] = (int)$siteId;
            }
        }

        return $payload;
    }

    /** @return array<string,mixed> */
    private function requireProduct(int $id): array
    {
        $row = $this->products->findById($id);
        if (!$row) {
            acep_error('NOT_FOUND', '상품을 찾을 수 없습니다.', 404);
        }
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function mapProduct(array $row): array
    {
        $activeCol = $this->activeColumn();
        $useYn = true;
        if ($activeCol !== '') {
            $useYn = (int)($row[$activeCol] ?? 0) === 1;
        }

        $item = [
            'id'          => (int)$row['id'],
            'brand'       => (string)$row['brand'],
            'category'    => (string)$row['category'],
            'productName' => (string)$row['product_name'],
            'sortOrder'   => (int)$row['sort_order'],
            'useYn'       => $useYn,
            'siteId'      => null,
            'siteName'    => null,
            'createdAt'   => isset($row['created_at']) && $row['created_at'] !== ''
                ? date('c', strtotime((string)$row['created_at']))
                : null,
        ];

        if (ProductSchema::hasSiteScope($this->pdo)) {
            $item['siteId'] = isset($row['site_id']) && $row['site_id'] !== null
                ? (int)$row['site_id']
                : null;
            $item['siteName'] = isset($row['site_name']) && $row['site_name'] !== ''
                ? (string)$row['site_name']
                : null;
        }

        return $item;
    }

    /** @param array<string,mixed> $row */
    private function productIsActive(array $row): bool
    {
        $activeCol = $this->activeColumn();
        if ($activeCol === '') {
            return true;
        }
        return (int)($row[$activeCol] ?? 0) === 1;
    }

    private function activeColumn(): string
    {
        if (acep_column_exists($this->pdo, 'products', 'use_yn')) {
            return 'use_yn';
        }
        if (acep_column_exists($this->pdo, 'products', 'status')) {
            return 'status';
        }
        return '';
    }
}
