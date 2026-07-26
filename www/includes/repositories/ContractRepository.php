<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/Uuid.php';

final class ContractRepository
{
    /** 외부 정렬명 → 실제 SQL 컬럼. 화이트리스트 밖 값은 거부(Service 계층에서 검증). */
    public const SORT_MAP = [
        'contract_no'   => 'c.contract_no',
        'customer_name' => 'cu.name',
        'total_amount'  => 'c.total_amount',
        'status'        => 'c.status',
        'start_date'    => 'c.start_date',
        'end_date'      => 'c.end_date',
        'contracted_at' => 'c.created_at',
        'created_at'    => 'c.created_at',
        'updated_at'    => 'c.updated_at',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function paginateForAdmin(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sortColumn = self::SORT_MAP[$filters['sort']] ?? self::SORT_MAP['created_at'];
        $order = strtoupper((string)($filters['order'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $customerJoin = $this->customerJoinSql();

        $sql = "SELECT c.*, {$this->customerSelectSql()}
                FROM contracts c
                LEFT JOIN {$customerJoin}
                WHERE {$where}
                ORDER BY {$sortColumn} {$order}, c.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) FROM contracts c LEFT JOIN {$customerJoin} WHERE {$where}";
        $countSt = $this->pdo->prepare($countSql);
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $ids = array_map(static fn (array $r): string => (string)$r['id'], $rows);
        $payments = $this->paymentTotalsByContract($ids);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRow($row, $payments[(string)$row['id']] ?? ['paid' => 0.0, 'refunded' => 0.0]);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function findByIdForAdmin(string $id): ?array
    {
        $customerJoin = $this->customerJoinSql();
        $st = $this->pdo->prepare(
            "SELECT c.*, {$this->customerSelectSql()}
             FROM contracts c
             LEFT JOIN {$customerJoin}
             WHERE c.id = :id AND c.deleted_at IS NULL
             LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $payments = $this->paymentTotalsByContract([(string)$row['id']]);
        return $this->mapRow($row, $payments[(string)$row['id']] ?? ['paid' => 0.0, 'refunded' => 0.0]);
    }

    /** 존재 여부만 필요할 때(권한 판정용) — 매핑하지 않고 raw row 반환. */
    public function findRawById(string $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM contracts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): string
    {
        $id = uuid_v4();
        $st = $this->pdo->prepare(
            'INSERT INTO contracts
                (id, contract_no, title, customer_id, site_id, product_name, manager_id,
                 total_amount, status, document_status, start_date, end_date, notes)
             VALUES
                (:id, :contract_no, :title, :customer_id, :site_id, :product_name, :manager_id,
                 :total_amount, :status, :document_status, :start_date, :end_date, :notes)'
        );
        $st->execute([
            ':id'              => $id,
            ':contract_no'     => $data['contractNo'],
            ':title'           => $data['title'],
            ':customer_id'     => $data['customerId'],
            ':site_id'         => $data['siteId'],
            ':product_name'    => $data['productName'],
            ':manager_id'      => $data['managerId'],
            ':total_amount'    => $data['totalAmount'],
            ':status'          => $data['status'] ?? 'draft',
            ':document_status' => $data['documentStatus'] ?? 'none',
            ':start_date'      => $data['startDate'],
            ':end_date'        => $data['endDate'],
            ':notes'           => $data['notes'],
        ]);
        return $id;
    }

    /** @param array<string,mixed> $fields */
    public function update(string $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $sets = [];
        $params = [':id' => $id];
        $columnMap = [
            'title'       => 'title',
            'siteId'      => 'site_id',
            'productName' => 'product_name',
            'managerId'   => 'manager_id',
            'totalAmount' => 'total_amount',
            'startDate'   => 'start_date',
            'endDate'     => 'end_date',
            'notes'       => 'notes',
        ];
        foreach ($columnMap as $key => $column) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "{$column} = :{$column}";
                $params[":{$column}"] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE contracts SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function updateStatus(string $id, string $status): void
    {
        $this->pdo->prepare(
            'UPDATE contracts SET status = :status WHERE id = :id AND deleted_at IS NULL'
        )->execute([':status' => $status, ':id' => $id]);
    }

    public function markSigned(string $id, string $signerName): void
    {
        $this->pdo->prepare(
            "UPDATE contracts
                SET status = 'signed', document_status = 'signed', signed_at = NOW(), signer_name = :signer
              WHERE id = :id AND deleted_at IS NULL"
        )->execute([':signer' => $signerName, ':id' => $id]);
    }

    public function cancel(string $id, string $reasonCode, string $reason): void
    {
        $this->pdo->prepare(
            "UPDATE contracts
                SET status = 'cancelled', cancel_reason_code = :code, cancel_reason = :reason, cancelled_at = NOW()
              WHERE id = :id AND deleted_at IS NULL"
        )->execute([':code' => $reasonCode, ':reason' => $reason, ':id' => $id]);
    }

    public function archive(string $id): void
    {
        $this->pdo->prepare(
            "UPDATE contracts SET status = 'archived', archived_at = NOW() WHERE id = :id AND deleted_at IS NULL"
        )->execute([':id' => $id]);
    }

    /** 소프트 삭제 — 물리 삭제 없음, 결제/서명 원장은 별도 테이블이라 항상 보존됨. */
    public function softDelete(string $id): void
    {
        $this->pdo->prepare(
            'UPDATE contracts SET deleted_at = NOW(3) WHERE id = :id AND deleted_at IS NULL'
        )->execute([':id' => $id]);
    }

    public function paymentCount(string $contractId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM contract_payments WHERE contract_id = :id');
        $st->execute([':id' => $contractId]);
        return (int)$st->fetchColumn();
    }

    public function addPayment(string $contractId, float $amount, string $type, string $paidAt, ?string $memo, ?string $createdBy): string
    {
        $id = uuid_v4();
        $this->pdo->prepare(
            'INSERT INTO contract_payments (id, contract_id, amount, type, paid_at, memo, created_by)
             VALUES (:id, :contract_id, :amount, :type, :paid_at, :memo, :created_by)'
        )->execute([
            ':id'          => $id,
            ':contract_id' => $contractId,
            ':amount'      => $amount,
            ':type'        => $type,
            ':paid_at'     => $paidAt,
            ':memo'        => $memo,
            ':created_by'  => $createdBy,
        ]);
        return $id;
    }

    /**
     * @param list<string> $ids
     * @return array<string, array{paid: float, refunded: float}>
     */
    private function paymentTotalsByContract(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "SELECT contract_id,
                    SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) AS paid,
                    SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) AS refunded
             FROM contract_payments
             WHERE contract_id IN ({$placeholders})
             GROUP BY contract_id"
        );
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll() ?: [] as $row) {
            $out[(string)$row['contract_id']] = [
                'paid'     => (float)$row['paid'],
                'refunded' => (float)$row['refunded'],
            ];
        }
        return $out;
    }

    /** @return array{0: string, 1: array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(c.contract_no LIKE :q1 OR c.title LIKE :q2 OR cu.name LIKE :q3 OR c.product_name LIKE :q4)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['siteId'])) {
            $where[] = 'c.site_id = :site_id';
            $params[':site_id'] = (int)$filters['siteId'];
        }
        if (!empty($filters['managerId'])) {
            $where[] = 'c.manager_id = :manager_id';
            $params[':manager_id'] = $filters['managerId'];
        }
        if (!empty($filters['customerId'])) {
            $where[] = 'c.customer_id = :customer_id';
            $params[':customer_id'] = $filters['customerId'];
        }

        $dateType = in_array($filters['dateType'] ?? '', ['created_at', 'start_date', 'end_date', 'updated_at'], true)
            ? $filters['dateType']
            : 'created_at';
        if (!empty($filters['dateFrom'])) {
            $where[] = "c.{$dateType} >= :date_from";
            $params[':date_from'] = $filters['dateFrom'] . ' 00:00:00';
        }
        if (!empty($filters['dateTo'])) {
            // 종료일 23:59:59까지 포함
            $where[] = "c.{$dateType} <= :date_to";
            $params[':date_to'] = $filters['dateTo'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }

    private function customerJoinSql(): string
    {
        return 'customers cu ON cu.id = c.customer_id';
    }

    private function customerSelectSql(): string
    {
        return 'cu.name AS customer_name, cu.phone_hash AS customer_phone_hash';
    }

    /** @param array{paid: float, refunded: float} $payments */
    private function mapRow(array $row, array $payments): array
    {
        $paid = $payments['paid'] - $payments['refunded'];
        return [
            'id'          => (string)$row['id'],
            'contractNo'  => (string)$row['contract_no'],
            'title'       => (string)$row['title'],
            'customerId'  => (string)$row['customer_id'],
            'customerName' => $row['customer_name'] ?? null,
            'siteId'      => $row['site_id'] !== null ? (int)$row['site_id'] : null,
            'productName' => $row['product_name'],
            'managerId'   => $row['manager_id'],
            'totalAmount' => (float)$row['total_amount'],
            'paidAmount'  => round($paid, 2),
            'outstandingAmount' => round(max(0.0, (float)$row['total_amount'] - $paid), 2),
            'status'          => (string)$row['status'],
            'documentStatus'  => (string)$row['document_status'],
            'startDate'   => $row['start_date'],
            'endDate'     => $row['end_date'],
            'signedAt'    => $row['signed_at'],
            'signerName'  => $row['signer_name'],
            'cancelReasonCode' => $row['cancel_reason_code'],
            'cancelReason'     => $row['cancel_reason'],
            'cancelledAt'      => $row['cancelled_at'],
            'archivedAt'       => $row['archived_at'],
            'notes'       => $row['notes'],
            'contractedAt' => $row['created_at'],
            'createdAt'   => $row['created_at'],
            'updatedAt'   => $row['updated_at'],
            '_hasPayments' => ($payments['paid'] > 0 || $payments['refunded'] > 0),
        ];
    }
}
