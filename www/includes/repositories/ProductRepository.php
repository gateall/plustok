<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/ProductSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class ProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $hasSiteScope = ProductSchema::hasSiteScope($this->pdo);
        $activeCol = $this->activeColumn();

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(p.brand LIKE :q OR p.category LIKE :q2 OR p.product_name LIKE :q3)';
            $term = '%' . trim((string)$filters['q']) . '%';
            $params[':q'] = $term;
            $params[':q2'] = $term;
            $params[':q3'] = $term;
        }
        if (!empty($filters['brand'])) {
            $where[] = 'p.brand = :brand';
            $params[':brand'] = trim((string)$filters['brand']);
        }
        if (
            array_key_exists('use_yn', $filters)
            && $filters['use_yn'] !== ''
            && $filters['use_yn'] !== null
            && $activeCol !== ''
        ) {
            $where[] = 'p.' . $activeCol . ' = :use_yn';
            $params[':use_yn'] = $this->normalizeUseYn($filters['use_yn']);
        }

        $whereSql = implode(' AND ', $where);
        $from = $hasSiteScope
            ? 'products p LEFT JOIN sites s ON s.id = p.site_id'
            : 'products p';
        $select = $hasSiteScope ? 'p.*, s.site_name' : 'p.*';

        $countSt = $this->pdo->prepare("SELECT COUNT(*) FROM {$from} WHERE {$whereSql}");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql = "SELECT {$select} FROM {$from} WHERE {$whereSql}"
            . ' ORDER BY p.brand, p.sort_order, p.id'
            . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return [
            'items' => $st->fetchAll() ?: [],
            'total' => $total,
        ];
    }

    public function findById(int $id): ?array
    {
        if (ProductSchema::hasSiteScope($this->pdo)) {
            $st = $this->pdo->prepare(
                'SELECT p.*, s.site_name
                   FROM products p
                   LEFT JOIN sites s ON s.id = p.site_id
                  WHERE p.id = :id
                  LIMIT 1'
            );
        } else {
            $st = $this->pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        }
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $payload */
    public function create(array $payload): int
    {
        $columns = ['brand', 'category', 'product_name', 'sort_order'];
        $values = [':brand', ':category', ':product_name', ':sort_order'];
        $params = [
            ':brand' => $payload['brand'],
            ':category' => $payload['category'],
            ':product_name' => $payload['product_name'],
            ':sort_order' => $payload['sort_order'],
        ];

        if (ProductSchema::hasSiteScope($this->pdo)) {
            $columns[] = 'site_id';
            $values[] = ':site_id';
            $params[':site_id'] = $payload['site_id'] ?? null;
        }

        $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $this->pdo->prepare($sql)->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $payload */
    public function update(int $id, array $payload): void
    {
        $fields = [
            'brand = :brand',
            'category = :category',
            'product_name = :product_name',
            'sort_order = :sort_order',
        ];
        $params = [
            ':id' => $id,
            ':brand' => $payload['brand'],
            ':category' => $payload['category'],
            ':product_name' => $payload['product_name'],
            ':sort_order' => $payload['sort_order'],
        ];

        if (ProductSchema::hasSiteScope($this->pdo)) {
            $fields[] = 'site_id = :site_id';
            $params[':site_id'] = $payload['site_id'] ?? null;
        }

        $this->pdo->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
    }

    public function toggleActive(int $id): void
    {
        $column = $this->activeColumn();
        if ($column === '') {
            return;
        }
        $this->pdo->prepare('UPDATE products SET ' . $column . ' = 1 - ' . $column . ' WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
    }

    public function hasConsults(int $id): bool
    {
        if (!acep_table_exists($this->pdo, 'consults')) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM consults WHERE product_id = :id');
        $st->execute([':id' => $id]);
        return ((int)$st->fetchColumn()) > 0;
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

    private function normalizeUseYn(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'active', 'enabled', 'yes', 'y'], true) ? 1 : 0;
    }
}
