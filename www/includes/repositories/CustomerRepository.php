<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/PiiEncryptor.php';
require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/LegacyCustomerReader.php';

final class CustomerRepository
{
    private LegacyCustomerReader $legacy;

    public function __construct(private PDO $pdo)
    {
        $this->legacy = new LegacyCustomerReader($pdo);
    }

    public function findById(string $id): ?array
    {
        if ($this->legacy->shouldResolve($id)) {
            $row = $this->legacy->findById($id);
            if ($row !== null) {
                return $row;
            }
            // consult.php writes to customers; crm_customers may exist empty (V3.0.1 without V0.0)
            $row = $this->findLegacyCustomersRow($id);
            if ($row !== null) {
                return $row;
            }
        }

        if (ctype_digit($id)) {
            $row = $this->findLegacyCustomersRow($id);
            if ($row !== null) {
                return $row;
            }
        }

        if (!acep_column_exists($this->pdo, 'customers', 'deleted_at')) {
            $st = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $row = $st->fetch();
            return $row ?: null;
        }

        $st = $this->pdo->prepare(
            'SELECT * FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private function findLegacyCustomersRow(string|int $id): ?array
    {
        if (!acep_table_exists($this->pdo, 'customers')) {
            return null;
        }
        $where = 'id = :id';
        if (acep_column_exists($this->pdo, 'customers', 'deleted_at')) {
            $where .= ' AND deleted_at IS NULL';
        }
        $st = $this->pdo->prepare("SELECT * FROM customers WHERE {$where} LIMIT 1");
        $st->execute([':id' => (string)$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByPhoneHash(string $hash): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM customers WHERE phone_hash = :h AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':h' => $hash]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO customers (id, name, phone, phone_hash, email, address, tags)
             VALUES (:id, :name, :phone, :phone_hash, :email, :address, :tags)'
        );
        $st->execute($data);
    }

    public function update(string $id, array $fields): bool
    {
        $sets = [];
        $params = [':id' => $id];

        if (isset($fields['name'])) {
            $sets[] = 'name = :name';
            $params[':name'] = $fields['name'];
        }
        if (isset($fields['tags'])) {
            $sets[] = 'tags = :tags';
            $params[':tags'] = json_encode($fields['tags'], JSON_UNESCAPED_UNICODE);
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP(3)';
        $sql = 'UPDATE customers SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount() > 0;
    }

    public function agentCanAccessCustomer(string $agentId, string $customerId, string $role): bool
    {
        if (in_array($role, ['admin', 'operator'], true)) {
            return true;
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM chat_rooms
             WHERE customer_id = :cid AND deleted_at IS NULL
               AND (agent_id = :aid OR status = \'new\')
             LIMIT 1'
        );
        $st->execute([':cid' => $customerId, ':aid' => $agentId]);
        return (bool)$st->fetch();
    }

    public function maskRow(array $row): array
    {
        if ($this->legacy->isLegacyRow($row)) {
            return $this->legacy->maskRow($row);
        }

        $phone = '';
        try {
            $phone = PiiEncryptor::decrypt((string)$row['phone']);
        } catch (Throwable) {
            $phone = (string)$row['phone'];
        }
        return [
            'id'          => (string)$row['id'],
            'name'        => $row['name'],
            'phoneMasked' => PiiEncryptor::maskPhone($phone),
            'tags'        => json_decode((string)($row['tags'] ?? '[]'), true) ?: [],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function search(string $q, int $limit): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, name, phone, tags FROM customers
             WHERE deleted_at IS NULL AND name LIKE :q
             ORDER BY updated_at DESC LIMIT ' . (int)$limit
        );
        $st->execute([':q' => '%' . $q . '%']);
        $rows = $st->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->maskRow($row);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function paginateForAdmin(array $filters): array
    {
        if (CrmSchema::acepCustomerTable($this->pdo) !== null) {
            return $this->paginateAcepForAdmin($filters);
        }

        if ($this->legacy->isActive() && acep_table_exists($this->pdo, 'consults')) {
            return $this->paginateLegacyForAdmin($filters);
        }

        return ['items' => [], 'total' => 0];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    private function paginateLegacyForAdmin(array $filters): array
    {
        $table = $this->legacy->table();
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $order = strtoupper((string)($filters['order'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $sortColumn = match ((string)($filters['sort'] ?? 'updated_at')) {
            'name'            => 'cu.name',
            'created_at'      => 'cu.created_at',
            'last_consult_at' => 'stats.last_consult_at',
            'consult_count'   => 'stats.consult_count',
            'status'          => 'stats.latest_status',
            default           => 'cu.updated_at',
        };

        $where = ['1=1'];
        $params = [];
        if (acep_column_exists($this->pdo, $table, 'deleted_at')) {
            $where[] = 'cu.deleted_at IS NULL';
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(cu.name LIKE :q1 OR cu.phone LIKE :q2 OR cu.email LIKE :q3 OR cu.customer_no LIKE :q4 OR cu.company LIKE :q5)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'stats.latest_status = :status';
            $params[':status'] = $status;
        }

        if (!empty($filters['siteId']) && ctype_digit((string)$filters['siteId'])) {
            $where[] = 'EXISTS (SELECT 1 FROM consults cs WHERE cs.customer_id = cu.id AND cs.site_id = :site_id)';
            $params[':site_id'] = (int)$filters['siteId'];
        }

        $siteJoin = acep_table_exists($this->pdo, 'sites')
            ? 'LEFT JOIN sites s ON s.id = stats.site_id'
            : '';
        $siteSelect = acep_table_exists($this->pdo, 'sites') ? ', s.site_name' : ', NULL AS site_name';

        $statsSql = 'SELECT c.customer_id,
                            COUNT(*) AS consult_count,
                            MAX(c.updated_at) AS last_consult_at,
                            MIN(c.created_at) AS first_consult_at,
                            SUBSTRING_INDEX(GROUP_CONCAT(c.status ORDER BY c.updated_at DESC), \',\', 1) AS latest_status,
                            SUBSTRING_INDEX(GROUP_CONCAT(c.site_id ORDER BY c.updated_at DESC), \',\', 1) AS site_id,
                            SUBSTRING_INDEX(GROUP_CONCAT(c.product_name ORDER BY c.updated_at DESC), \',\', 1) AS product_name,
                            SUBSTRING_INDEX(GROUP_CONCAT(c.manager_id ORDER BY c.updated_at DESC), \',\', 1) AS manager_id
                     FROM consults c
                     GROUP BY c.customer_id';

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT cu.*, stats.consult_count, stats.last_consult_at, stats.first_consult_at,
                       stats.latest_status, stats.site_id, stats.product_name, stats.manager_id
                       {$siteSelect}
                FROM {$table} cu
                LEFT JOIN ({$statsSql}) stats ON stats.customer_id = cu.id
                {$siteJoin}
                WHERE {$whereSql}
                ORDER BY {$sortColumn} {$order}, cu.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*)
                     FROM {$table} cu
                     LEFT JOIN ({$statsSql}) stats ON stats.customer_id = cu.id
                     WHERE {$whereSql}";
        $countSt = $this->pdo->prepare($countSql);
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapAdminListItem($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    private function paginateAcepForAdmin(array $filters): array
    {
        $table = CrmSchema::acepCustomerTable($this->pdo) ?? 'customers';
        if (!acep_table_exists($this->pdo, $table)) {
            return ['items' => [], 'total' => 0];
        }

        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $order = strtoupper((string)($filters['order'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $sortColumn = match ((string)($filters['sort'] ?? 'updated_at')) {
            'name'            => 'c.name',
            'created_at'      => 'c.created_at',
            'last_consult_at' => 'stats.last_consult_at',
            'consult_count'   => 'stats.consult_count',
            default           => 'c.updated_at',
        };

        $where = ['1=1'];
        $params = [];
        if (acep_column_exists($this->pdo, $table, 'deleted_at')) {
            $where[] = 'c.deleted_at IS NULL';
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'c.name LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && acep_table_exists($this->pdo, 'chat_rooms')) {
            $where[] = 'stats.latest_status = :status';
            $params[':status'] = $this->mapAcepListStatus($status);
        }

        if (!empty($filters['siteId']) && acep_table_exists($this->pdo, 'consults')) {
            $legacyTable = $this->legacy->isActive() ? $this->legacy->table() : null;
            if ($legacyTable !== null && acep_table_exists($this->pdo, 'customer_bridge')) {
                $where[] = 'EXISTS (
                    SELECT 1 FROM customer_bridge cb
                    JOIN consults cs ON cs.customer_id = cb.legacy_customer_id
                    WHERE cb.acep_customer_id = c.id AND cs.site_id = :site_id
                )';
                $params[':site_id'] = (int)$filters['siteId'];
            }
        }

        $statsSql = acep_table_exists($this->pdo, 'chat_rooms')
            ? 'SELECT cr.customer_id,
                      COUNT(*) AS consult_count,
                      MAX(cr.updated_at) AS last_consult_at,
                      MIN(cr.created_at) AS first_consult_at,
                      SUBSTRING_INDEX(GROUP_CONCAT(cr.status ORDER BY cr.updated_at DESC), \',\', 1) AS latest_status
               FROM chat_rooms cr
               WHERE cr.deleted_at IS NULL
               GROUP BY cr.customer_id'
            : 'SELECT NULL AS customer_id, 0 AS consult_count, NULL AS last_consult_at,
                      NULL AS first_consult_at, NULL AS latest_status
               FROM dual WHERE 1=0';

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT c.*, stats.consult_count, stats.last_consult_at, stats.first_consult_at, stats.latest_status
                FROM {$table} c
                LEFT JOIN ({$statsSql}) stats ON stats.customer_id = c.id
                WHERE {$whereSql}
                ORDER BY {$sortColumn} {$order}, c.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*)
                     FROM {$table} c
                     LEFT JOIN ({$statsSql}) stats ON stats.customer_id = c.id
                     WHERE {$whereSql}";
        $countSt = $this->pdo->prepare($countSql);
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapAdminListItem($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapAdminListItem(array $row): array
    {
        $masked = $this->maskRow($row);
        $plainEmail = !empty($row['email'])
            ? PiiEncryptor::decryptEmail((string)$row['email'])
            : null;
        $emailMasked = $plainEmail !== null ? PiiEncryptor::maskEmail($plainEmail) : null;
        if ($emailMasked === '보호된 정보') {
            $emailMasked = null;
        }

        $lastConsultAt = !empty($row['last_consult_at'])
            ? date('c', strtotime((string)$row['last_consult_at']))
            : null;
        $firstConsultAt = !empty($row['first_consult_at'])
            ? date('c', strtotime((string)$row['first_consult_at']))
            : null;

        return [
            'id'             => (string)$row['id'],
            'customerNo'     => !empty($row['customer_no']) ? (string)$row['customer_no'] : null,
            'name'           => (string)($row['name'] ?? ''),
            'companyName'    => !empty($row['company']) ? (string)$row['company'] : null,
            'phone'          => $masked['phoneMasked'] ?? null,
            'emailMasked'    => $emailMasked,
            'status'         => !empty($row['latest_status']) ? (string)$row['latest_status'] : 'active',
            'primaryProduct' => !empty($row['product_name']) ? (string)$row['product_name'] : null,
            'siteName'       => !empty($row['site_name']) ? (string)$row['site_name'] : null,
            'siteId'         => isset($row['site_id']) && $row['site_id'] !== null && $row['site_id'] !== ''
                ? (int)$row['site_id']
                : null,
            'consultCount'   => (int)($row['consult_count'] ?? $row['consultation_count'] ?? 0),
            'managerId'      => isset($row['manager_id']) && $row['manager_id'] !== null && $row['manager_id'] !== ''
                ? (string)$row['manager_id']
                : null,
            'lastConsultAt'  => $lastConsultAt,
            'firstConsultAt' => $firstConsultAt,
            'createdAt'      => !empty($row['created_at']) ? date('c', strtotime((string)$row['created_at'])) : null,
            'updatedAt'      => !empty($row['updated_at']) ? date('c', strtotime((string)$row['updated_at'])) : null,
        ];
    }

    private function mapAcepListStatus(string $status): string
    {
        return match ($status) {
            'new', 'receipt' => 'new',
            'active', 'progress', 'consulting' => 'active',
            'closed', 'completed', 'inactive', 'dormant', 'archived' => 'closed',
            default => $status,
        };
    }
}
