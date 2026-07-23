<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/PiiEncryptor.php';
require_once __DIR__ . '/../util/Uuid.php';
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
            'SELECT id, name, tags FROM customers
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
}
