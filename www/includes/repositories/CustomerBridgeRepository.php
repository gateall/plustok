<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/PiiEncryptor.php';
require_once __DIR__ . '/../util/Uuid.php';

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

    public function findAcepIdByLegacy(int $legacyId): ?string
    {
        if (!acep_table_exists($this->pdo, 'customer_bridge')) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT acep_customer_id FROM customer_bridge WHERE legacy_customer_id = :id LIMIT 1'
        );
        $st->execute([':id' => $legacyId]);
        $v = $st->fetchColumn();
        return $v !== false ? (string)$v : null;
    }

    /** Legacy CRM customer → ACEP UUID (find or create + bridge). */
    public function resolveAcepCustomer(int $legacyId, string $name, string $phone, ?string $email): string
    {
        $existing = $this->findAcepIdByLegacy($legacyId);
        if ($existing !== null) {
            return $existing;
        }

        $acepTable = CrmSchema::acepCustomerTable($this->pdo);
        if ($acepTable === null) {
            throw new RuntimeException('ACEP customers table not available');
        }

        $legacyTable = CrmSchema::legacyCustomerTable($this->pdo);
        $legacyRow = $this->pdo->prepare("SELECT name, phone, email FROM {$legacyTable} WHERE id = :id LIMIT 1");
        $legacyRow->execute([':id' => $legacyId]);
        $legacy = $legacyRow->fetch();
        if (is_array($legacy)) {
            if ($name === '' && isset($legacy['name'])) {
                $name = (string)$legacy['name'];
            }
            if ($phone === '' && isset($legacy['phone'])) {
                $phone = (string)$legacy['phone'];
            }
            if (($email === null || $email === '') && !empty($legacy['email'])) {
                $email = (string)$legacy['email'];
            }
        }

        $phoneNorm = preg_replace('/\D/', '', $phone) ?? '';
        $hash = PiiEncryptor::phoneHash($phoneNorm);

        $deletedFilter = acep_column_exists($this->pdo, $acepTable, 'deleted_at')
            ? ' AND deleted_at IS NULL'
            : '';
        $st = $this->pdo->prepare(
            "SELECT id FROM {$acepTable} WHERE phone_hash = :h{$deletedFilter} LIMIT 1"
        );
        $st->execute([':h' => $hash]);
        $acepId = $st->fetchColumn();

        if (!$acepId) {
            $acepId = uuid_v4();
            $encEmail = ($email !== null && $email !== '') ? PiiEncryptor::encrypt($email) : null;
            $this->pdo->prepare(
                "INSERT INTO {$acepTable} (id, name, phone, phone_hash, email, address, tags)
                 VALUES (:id, :name, :phone, :phone_hash, :email, NULL, :tags)"
            )->execute([
                ':id'         => $acepId,
                ':name'       => $name,
                ':phone'      => PiiEncryptor::encrypt($phoneNorm),
                ':phone_hash' => $hash,
                ':email'      => $encEmail,
                ':tags'       => json_encode(['상담신청'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        $acepId = (string)$acepId;
        $this->linkBridge($acepId, $legacyId);
        if (acep_column_exists($this->pdo, $acepTable, 'external_crm_id')) {
            $this->pdo->prepare(
                "UPDATE {$acepTable} SET external_crm_id = :ext, updated_at = CURRENT_TIMESTAMP(3)
                 WHERE id = :id{$deletedFilter}"
            )->execute([':ext' => (string)$legacyId, ':id' => $acepId]);
        }

        return $acepId;
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
        $acepTable = CrmSchema::acepCustomerTable($this->pdo);
        if ($acepTable !== null && acep_column_exists($this->pdo, $acepTable, 'external_crm_id')) {
            $deletedFilter = acep_column_exists($this->pdo, $acepTable, 'deleted_at')
                ? ' AND deleted_at IS NULL'
                : '';
            $this->pdo->prepare(
                "UPDATE {$acepTable} SET external_crm_id = :ext, updated_at = CURRENT_TIMESTAMP(3)
                 WHERE id = :id{$deletedFilter}"
            )->execute([':ext' => (string)$legacyId, ':id' => $acepId]);
        }

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
