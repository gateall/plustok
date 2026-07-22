<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/PiiEncryptor.php';

final class CustomerBridgeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findLegacyId(string $acepCustomerId): ?int
    {
        if (!acep_table_exists($this->pdo, 'customer_bridge')) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT legacy_customer_id FROM customer_bridge WHERE acep_customer_id = :id LIMIT 1'
        );
        $st->execute([':id' => $acepCustomerId]);
        $v = $st->fetchColumn();
        return $v !== false ? (int)$v : null;
    }

    /**
     * ACEP customer → legacy CRM customer id (find or create + bridge).
     * @param array<string,mixed> $acepCustomer
     */
    public function resolveLegacyCustomer(array $acepCustomer): int
    {
        $acepId = (string)$acepCustomer['id'];
        $existing = $this->findLegacyId($acepId);
        if ($existing !== null) {
            return $existing;
        }

        $phone = $this->decryptPhone($acepCustomer);
        if ($phone === '') {
            throw new RuntimeException('Customer phone required for CRM save');
        }

        $table = CrmSchema::legacyCustomerTable($this->pdo);
        $st = $this->pdo->prepare("SELECT id FROM {$table} WHERE phone = :p LIMIT 1");
        $st->execute([':p' => $phone]);
        $legacyId = $st->fetchColumn();

        if (!$legacyId) {
            $legacyId = $this->createLegacyCustomer($table, $acepCustomer, $phone);
        }

        $legacyId = (int)$legacyId;
        $this->linkBridge($acepId, $legacyId);
        $this->pdo->prepare(
            'UPDATE customers SET external_crm_id = :ext, updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([':ext' => (string)$legacyId, ':id' => $acepId]);

        return $legacyId;
    }

    /** @param array<string,mixed> $acepCustomer */
    private function createLegacyCustomer(string $table, array $acepCustomer, string $phone): int
    {
        $customerNo = $this->nextLegacyCustomerNo($table);
        $sql = "INSERT INTO {$table}
                (customer_no, name, phone, company, email, region, memo)
                VALUES (:no, :name, :phone, NULL, NULL, NULL, NULL)";
        $this->pdo->prepare($sql)->execute([
            ':no'   => $customerNo,
            ':name' => (string)$acepCustomer['name'],
            ':phone'=> $phone,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function nextLegacyCustomerNo(string $table): string
    {
        $prefix = 'M' . date('Ymd');
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE customer_no LIKE :p"
        );
        $st->execute([':p' => $prefix . '%']);
        $seq = ((int)$st->fetchColumn()) + 1;
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function linkBridge(string $acepId, int $legacyId): void
    {
        if (!acep_table_exists($this->pdo, 'customer_bridge')) {
            return;
        }
        $this->pdo->prepare(
            'INSERT IGNORE INTO customer_bridge (acep_customer_id, legacy_customer_id)
             VALUES (:a, :l)'
        )->execute([':a' => $acepId, ':l' => $legacyId]);
    }

    /** @param array<string,mixed> $acepCustomer */
    private function decryptPhone(array $acepCustomer): string
    {
        try {
            return preg_replace('/\D/', '', PiiEncryptor::decrypt((string)$acepCustomer['phone'])) ?? '';
        } catch (Throwable) {
            return preg_replace('/\D/', '', (string)$acepCustomer['phone']) ?? '';
        }
    }
}
