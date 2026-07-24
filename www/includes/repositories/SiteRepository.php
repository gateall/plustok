<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/SiteSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class SiteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $query */
    public function findAll(array $query): array
    {
        $where = ['1=1'];
        $params = [];
        $activeColumn = SiteSchema::activeColumn($this->pdo);

        if (!empty($query['brand'])) {
            $where[] = 'brand = :brand';
            $params[':brand'] = trim((string)$query['brand']);
        }
        if (!empty($query['division']) && acep_column_exists($this->pdo, 'sites', 'division')) {
            $where[] = 'division = :division';
            $params[':division'] = trim((string)$query['division']);
        }
        if (array_key_exists('status', $query) && $query['status'] !== '' && $activeColumn !== '') {
            $where[] = 's.' . $activeColumn . ' = :status';
            $params[':status'] = $this->normalizeStatus($query['status']);
        }
        if (!empty($query['q'])) {
            $parts = ['s.site_code LIKE :q', 's.site_name LIKE :q2', 's.brand LIKE :q3'];
            $params[':q'] = '%' . trim((string)$query['q']) . '%';
            $params[':q2'] = $params[':q'];
            $params[':q3'] = $params[':q'];
            if (acep_column_exists($this->pdo, 'sites', 'domain')) {
                $parts[] = 's.domain LIKE :q4';
                $params[':q4'] = $params[':q'];
            }
            if (acep_column_exists($this->pdo, 'sites', 'division')) {
                $parts[] = 's.division LIKE :q5';
                $params[':q5'] = $params[':q'];
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $sql = 'SELECT s.* FROM sites s WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . (acep_column_exists($this->pdo, 'sites', 'division') ? 's.division ASC, ' : '')
            . 's.brand ASC, s.site_name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $columns = ['site_code', 'site_name', 'brand', 'api_key'];
        $values = [':site_code', ':site_name', ':brand', ':api_key'];
        $params = [
            ':site_code' => $payload['site_code'],
            ':site_name' => $payload['site_name'],
            ':brand' => $payload['brand'],
            ':api_key' => $payload['api_key'],
        ];

        if (acep_column_exists($this->pdo, 'sites', 'domain')) {
            $columns[] = 'domain';
            $values[] = ':domain';
            $params[':domain'] = $payload['domain'];
        }
        if (acep_column_exists($this->pdo, 'sites', 'division')) {
            $columns[] = 'division';
            $values[] = ':division';
            $params[':division'] = $payload['division'];
        }
        if (acep_column_exists($this->pdo, 'sites', 'persona')) {
            $columns[] = 'persona';
            $values[] = ':persona';
            $params[':persona'] = $payload['persona'];
        }

        $activeColumn = SiteSchema::activeColumn($this->pdo);
        if ($activeColumn !== '') {
            $columns[] = $activeColumn;
            $values[] = ':active';
            $params[':active'] = $payload['is_active'];
        }

        $sql = 'INSERT INTO sites (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $this->pdo->prepare($sql)->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $fields = [
            'site_code = :site_code',
            'site_name = :site_name',
            'brand = :brand',
        ];
        $params = [
            ':id' => $id,
            ':site_code' => $payload['site_code'],
            ':site_name' => $payload['site_name'],
            ':brand' => $payload['brand'],
        ];

        if (acep_column_exists($this->pdo, 'sites', 'domain')) {
            $fields[] = 'domain = :domain';
            $params[':domain'] = $payload['domain'];
        }
        if (acep_column_exists($this->pdo, 'sites', 'division')) {
            $fields[] = 'division = :division';
            $params[':division'] = $payload['division'];
        }
        if (acep_column_exists($this->pdo, 'sites', 'persona')) {
            $fields[] = 'persona = :persona';
            $params[':persona'] = $payload['persona'];
        }

        $this->pdo->prepare('UPDATE sites SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
    }

    public function updateApiKey(int $id, string $apiKey): void
    {
        $this->pdo->prepare('UPDATE sites SET api_key = :api_key WHERE id = :id')->execute([
            ':id' => $id,
            ':api_key' => $apiKey,
        ]);
    }

    public function toggleActive(int $id): void
    {
        $column = SiteSchema::activeColumn($this->pdo);
        if ($column === '') {
            return;
        }
        $this->pdo->prepare('UPDATE sites SET ' . $column . ' = 1 - ' . $column . ' WHERE id = :id')->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM sites WHERE id = :id')->execute([':id' => $id]);
    }

    public function consultCount(int $siteId): int
    {
        if (!acep_table_exists($this->pdo, 'consults')) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM consults WHERE site_id = :site_id');
        $st->execute([':site_id' => $siteId]);
        return (int)$st->fetchColumn();
    }

    public function todayConsultCount(int $siteId): int
    {
        if (!acep_table_exists($this->pdo, 'consults')) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM consults WHERE site_id = :site_id AND DATE(created_at) = CURDATE()');
        $st->execute([':site_id' => $siteId]);
        return (int)$st->fetchColumn();
    }

    public function lastConsultAt(int $siteId): ?string
    {
        if (!acep_table_exists($this->pdo, 'consults')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT MAX(created_at) FROM consults WHERE site_id = :site_id');
        $st->execute([':site_id' => $siteId]);
        $value = $st->fetchColumn();
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function lastCommunicationAt(int $siteId): ?string
    {
        if (acep_table_exists($this->pdo, 'consult_history')) {
            $st = $this->pdo->prepare(
                'SELECT MAX(h.created_at)
                   FROM consult_history h
                   INNER JOIN consults c ON c.id = h.consult_id
                  WHERE c.site_id = :site_id'
            );
            $st->execute([':site_id' => $siteId]);
            $value = $st->fetchColumn();
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->lastConsultAt($siteId);
    }

    public function latestHealth(int $siteId): ?array
    {
        if (!acep_table_exists($this->pdo, 'site_health_log')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM site_health_log WHERE site_id = :site_id ORDER BY checked_at DESC, id DESC LIMIT 1');
        $st->execute([':site_id' => $siteId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function healthHistory(int $siteId, int $limit = 20): array
    {
        if (!acep_table_exists($this->pdo, 'site_health_log')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM site_health_log WHERE site_id = :site_id ORDER BY checked_at DESC, id DESC LIMIT ' . max(1, $limit)
        );
        $st->execute([':site_id' => $siteId]);
        return $st->fetchAll() ?: [];
    }

    public function recordHealthCheck(int $siteId, array $result): void
    {
        if (!acep_table_exists($this->pdo, 'site_health_log')) {
            return;
        }

        $columns = ['site_id', 'checked_at'];
        $values = [':site_id', ':checked_at'];
        $params = [
            ':site_id' => $siteId,
            ':checked_at' => $result['checked_at'],
        ];

        $map = [
            'is_healthy' => 'is_healthy',
            'response_ms' => 'response_ms',
            'status_code' => 'status_code',
            'error_message' => 'error_message',
            'target_url' => 'target_url',
        ];

        foreach ($map as $column => $key) {
            if (acep_column_exists($this->pdo, 'site_health_log', $column)) {
                $columns[] = $column;
                $values[] = ':' . $column;
                $params[':' . $column] = $result[$key] ?? null;
            }
        }

        $sql = 'INSERT INTO site_health_log (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $this->pdo->prepare($sql)->execute($params);
    }

    private function normalizeStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'active', 'enabled', 'yes', 'y'], true) ? 1 : 0;
    }
}

