<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/../util/PiiEncryptor.php';
require_once __DIR__ . '/../../migrations/lib.php';

/** Legacy CRM customers (BIGINT id, plain phone) — used when chat_rooms.customer_id is BIGINT. */
final class LegacyCustomerReader
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isActive(): bool
    {
        return acep_is_legacy_crm($this->pdo)
            || acep_table_exists($this->pdo, 'crm_customers');
    }

    public function table(): string
    {
        return CrmSchema::legacyCustomerTable($this->pdo);
    }

    /** Numeric legacy CRM id only — ACEP UUID must use CustomerRepository ACEP path. */
    public function shouldResolve(string $id): bool
    {
        return ctype_digit($id);
    }

    /** @param array<string,mixed> $row */
    public function isLegacyRow(array $row): bool
    {
        if (isset($row['customer_no'])) {
            return true;
        }
        return isset($row['id']) && ctype_digit((string)$row['id']);
    }

    public function findById(int|string $id): ?array
    {
        $table = $this->table();
        $where = 'id = :id';
        if (acep_column_exists($this->pdo, $table, 'deleted_at')) {
            $where .= ' AND deleted_at IS NULL';
        }
        $st = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$where} LIMIT 1");
        $st->execute([':id' => (string)$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $row */
    public function maskRow(array $row): array
    {
        $phone = preg_replace('/\D/', '', (string)($row['phone'] ?? '')) ?? '';
        if ($phone === '') {
            $phone = (string)($row['phone'] ?? '');
        }
        return [
            'id'          => (string)$row['id'],
            'name'        => (string)$row['name'],
            'phoneMasked' => PiiEncryptor::maskPhone($phone),
            'tags'        => [],
        ];
    }
}
