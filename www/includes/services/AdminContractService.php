<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class AdminContractService
{
    /** 상태 전이 화이트리스트 — 여기 없는 전이는 전부 거부(요청 문자열로 단순 UPDATE 금지). */
    private const ALLOWED_TRANSITIONS = [
        'draft'              => ['review', 'sent', 'cancelled'],
        'review'             => ['draft', 'sent', 'cancelled'],
        'sent'               => ['signature_pending', 'draft', 'cancelled'],
        'signature_pending'  => ['signed', 'sent', 'cancelled'],
        'signed'             => ['active', 'cancelled'],
        'active'             => ['completed', 'on_hold', 'cancelled'],
        'on_hold'            => ['active', 'cancelled'],
        'completed'          => ['archived'],
        'cancelled'          => ['archived'],
        'expired'            => ['archived'],
        'archived'           => [],
    ];

    private const CORE_FIELDS = ['title', 'totalAmount', 'startDate', 'endDate', 'productName'];

    public function __construct(
        private ContractRepository $contracts,
        private AuditService $audit,
        private PDO $pdo,
    ) {
    }

    /** @param array<string,mixed> $query */
    public function list(array $query): array
    {
        $filters = [
            'q'          => $query['q'] ?? null,
            'status'     => $this->nullableEnum($query['status'] ?? null),
            'siteId'     => $query['site_id'] ?? null,
            'managerId'  => $query['manager_id'] ?? null,
            'customerId' => $query['customer_id'] ?? null,
            'dateType'   => $query['date_type'] ?? null,
            'dateFrom'   => $this->validDate($query['date_from'] ?? null),
            'dateTo'     => $this->validDate($query['date_to'] ?? null),
            'sort'       => array_key_exists($query['sort'] ?? '', ContractRepository::SORT_MAP) ? $query['sort'] : 'contracted_at',
            'order'      => $query['order'] ?? 'desc',
            'page'       => $this->positiveInt($query['page'] ?? 1, 1),
            'limit'      => $this->positiveInt($query['limit'] ?? 20, 20),
        ];

        if (isset($query['page']) && (!ctype_digit((string)$query['page']) || (int)$query['page'] < 1)) {
            acep_error('VALIDATION_ERROR', 'page는 1 이상의 정수여야 합니다.', 400);
        }
        if (isset($query['limit']) && (!ctype_digit((string)$query['limit']) || (int)$query['limit'] < 1 || (int)$query['limit'] > 100)) {
            acep_error('VALIDATION_ERROR', 'limit은 1~100 사이의 정수여야 합니다.', 400);
        }

        $result = $this->contracts->paginateForAdmin($filters);
        $items = array_map(fn (array $row): array => $this->withPermissions($row), $result['items']);

        return [
            'items' => $items,
            'total' => $result['total'],
            'page'  => $filters['page'],
            'limit' => $filters['limit'],
            'sort'  => $filters['sort'],
            'order' => strtolower((string)$filters['order']) === 'asc' ? 'asc' : 'desc',
        ];
    }

    public function get(string $id): array
    {
        return $this->withPermissions($this->requireContract($id));
    }

    /** @param array<string,mixed> $body */
    public function create(string $actorId, array $body): array
    {
        $title = trim((string)($body['title'] ?? ''));
        $customerId = trim((string)($body['customerId'] ?? ''));
        $totalAmount = $body['totalAmount'] ?? null;
        if ($title === '' || $customerId === '' || !is_numeric($totalAmount)) {
            acep_error('VALIDATION_ERROR', 'title, customerId, totalAmount는 필수입니다.', 400);
        }

        $id = null;
        $this->pdo->beginTransaction();
        try {
            $id = $this->contracts->create([
                'contractNo'     => $this->generateContractNo(),
                'title'          => $title,
                'customerId'     => $customerId,
                'siteId'         => isset($body['siteId']) ? (int)$body['siteId'] : null,
                'productName'    => isset($body['productName']) ? trim((string)$body['productName']) : null,
                'managerId'      => isset($body['managerId']) ? (string)$body['managerId'] : null,
                'totalAmount'    => (float)$totalAmount,
                'startDate'      => $this->validDate($body['startDate'] ?? null),
                'endDate'        => $this->validDate($body['endDate'] ?? null),
                'notes'          => isset($body['notes']) ? (string)$body['notes'] : null,
            ]);
            $this->audit->agentAction($actorId, 'contract.create', 'contract', $id, ['title' => $title]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof AcepHttpResponse) {
                throw $e;
            }
            acep_error('CONTRACT_WRITE_FAILED', '계약을 생성할 수 없습니다.', 409);
        }

        return $this->get((string)$id);
    }

    /** @param array<string,mixed> $body */
    public function update(string $actorId, string $id, array $body): array
    {
        $contract = $this->requireContract($id);
        $isSigned = $contract['signedAt'] !== null;

        if ($isSigned) {
            foreach (self::CORE_FIELDS as $field) {
                if (array_key_exists($field, $body)) {
                    acep_error(
                        'CONTRACT_SIGNED_LOCKED',
                        '서명 완료된 계약의 핵심 정보는 수정할 수 없습니다. 변경계약서 또는 신규 계약으로 처리하세요.',
                        409
                    );
                }
            }
        }

        $fields = [];
        foreach (['title', 'siteId', 'productName', 'managerId', 'totalAmount', 'startDate', 'endDate', 'notes'] as $key) {
            if (array_key_exists($key, $body)) {
                $fields[$key] = $key === 'startDate' || $key === 'endDate'
                    ? $this->validDate($body[$key])
                    : $body[$key];
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->contracts->update($id, $fields);
            $this->audit->agentAction($actorId, 'contract.update', 'contract', $id, ['fields' => array_keys($fields)]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof AcepHttpResponse) {
                throw $e;
            }
            acep_error('CONTRACT_WRITE_FAILED', '계약을 수정할 수 없습니다.', 409);
        }

        return $this->get($id);
    }

    /** @param array<string,mixed> $body */
    public function updateStatus(string $actorId, string $id, array $body): array
    {
        $contract = $this->requireContract($id);
        $current = $contract['status'];
        $target = (string)($body['status'] ?? '');

        if (!isset(self::ALLOWED_TRANSITIONS[$target])) {
            acep_error('VALIDATION_ERROR', '알 수 없는 status 값입니다.', 400);
        }
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
        if (!in_array($target, $allowed, true)) {
            acep_error(
                'CONTRACT_INVALID_TRANSITION',
                "{$current} 상태에서 {$target}(으)로 전환할 수 없습니다.",
                409
            );
        }

        $signerName = null;
        if ($target === 'signed') {
            $signerName = trim((string)($body['signerName'] ?? ''));
            if ($signerName === '') {
                acep_error('VALIDATION_ERROR', 'signed 전환에는 signerName이 필요합니다.', 400);
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($target === 'signed') {
                $this->contracts->markSigned($id, $signerName);
            } else {
                $this->contracts->updateStatus($id, $target);
            }
            $this->audit->agentAction($actorId, 'contract.status_change', 'contract', $id, [
                'from' => $current,
                'to'   => $target,
            ]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof AcepHttpResponse) {
                throw $e;
            }
            acep_error('CONTRACT_WRITE_FAILED', '상태를 변경할 수 없습니다.', 409);
        }

        return $this->get($id);
    }

    /** @param array<string,mixed> $body */
    public function cancel(string $actorId, string $id, array $body): array
    {
        $contract = $this->requireContract($id);
        if ($contract['status'] === 'cancelled') {
            acep_error('CONTRACT_ALREADY_CANCELLED', '이미 취소된 계약입니다.', 409);
        }
        if (!in_array('cancelled', self::ALLOWED_TRANSITIONS[$contract['status']] ?? [], true)) {
            acep_error('CONTRACT_INVALID_TRANSITION', "{$contract['status']} 상태는 취소할 수 없습니다.", 409);
        }

        $reasonCode = trim((string)($body['reasonCode'] ?? ''));
        $reason = trim((string)($body['reason'] ?? ''));
        if ($reasonCode === '' || $reason === '') {
            acep_error('VALIDATION_ERROR', 'reasonCode와 reason은 필수입니다.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $this->contracts->cancel($id, $reasonCode, $reason);
            $this->audit->agentAction($actorId, 'contract.cancel', 'contract', $id, [
                'reasonCode' => $reasonCode,
                'reason'     => $reason,
            ]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof AcepHttpResponse) {
                throw $e;
            }
            acep_error('CONTRACT_WRITE_FAILED', '계약을 취소할 수 없습니다.', 409);
        }

        $updated = $this->get($id);
        $updated['refundRequired'] = $contract['paidAmount'] > 0;
        return $updated;
    }

    public function archive(string $actorId, string $id): array
    {
        $contract = $this->requireContract($id);
        if (!in_array('archived', self::ALLOWED_TRANSITIONS[$contract['status']] ?? [], true)) {
            acep_error('CONTRACT_INVALID_TRANSITION', "{$contract['status']} 상태는 보관할 수 없습니다.", 409);
        }

        $this->pdo->beginTransaction();
        try {
            $this->contracts->archive($id);
            $this->audit->agentAction($actorId, 'contract.archive', 'contract', $id);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof AcepHttpResponse) {
                throw $e;
            }
            acep_error('CONTRACT_WRITE_FAILED', '계약을 보관 처리할 수 없습니다.', 409);
        }

        return $this->get($id);
    }

    public function delete(string $actorId, string $id): array
    {
        $contract = $this->requireContract($id);
        $reason = $this->deleteBlockReason($contract);
        if ($reason !== null) {
            acep_error('CONTRACT_DELETE_BLOCKED', $this->deleteBlockMessage($reason), 409);
        }

        $this->pdo->beginTransaction();
        try {
            // 감사로그를 별도 테이블(audit_logs)에 먼저 기록 — 계약 행이 사라져도 삭제 이력은 남는다.
            $this->audit->agentAction($actorId, 'contract.delete', 'contract', $id, [
                'contractNo' => $contract['contractNo'],
            ]);
            $this->contracts->softDelete($id);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof AcepHttpResponse) {
                throw $e;
            }
            acep_error('CONTRACT_WRITE_FAILED', '계약을 삭제할 수 없습니다.', 409);
        }

        return ['deleted' => true, 'id' => $id];
    }

    private function requireContract(string $id): array
    {
        $row = $this->contracts->findByIdForAdmin($id);
        if (!$row) {
            acep_error('CONTRACT_NOT_FOUND', '계약을 찾을 수 없습니다.', 404);
        }
        return $row;
    }

    private function deleteBlockReason(array $contract): ?string
    {
        if ($contract['status'] !== 'draft') {
            return 'NOT_DRAFT';
        }
        if ($contract['signedAt'] !== null) {
            return 'SIGNED';
        }
        if ($contract['_hasPayments']) {
            return 'PAYMENT_EXISTS';
        }
        return null;
    }

    private function deleteBlockMessage(string $reason): string
    {
        return match ($reason) {
            'NOT_DRAFT'       => '초안(draft) 상태의 계약만 삭제할 수 있습니다.',
            'SIGNED'          => '서명이 완료된 계약은 삭제할 수 없습니다.',
            'PAYMENT_EXISTS'  => '결제 이력이 있는 계약은 삭제할 수 없습니다.',
            default           => '이 계약은 삭제할 수 없습니다.',
        };
    }

    private function withPermissions(array $row): array
    {
        $status = $row['status'];
        $blockReason = $this->deleteBlockReason($row);
        $row['permissions'] = [
            'canEdit'    => $row['signedAt'] === null && !in_array($status, ['cancelled', 'archived'], true),
            'canCancel'  => in_array('cancelled', self::ALLOWED_TRANSITIONS[$status] ?? [], true),
            'canArchive' => in_array('archived', self::ALLOWED_TRANSITIONS[$status] ?? [], true),
            'canDelete'  => $blockReason === null,
        ];
        $row['deleteBlockReason'] = $blockReason;
        unset($row['_hasPayments']);
        return $row;
    }

    private function generateContractNo(): string
    {
        return 'CT' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function nullableEnum(mixed $value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function validDate(mixed $value): ?string
    {
        $v = trim((string)$value);
        if ($v === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            acep_error('VALIDATION_ERROR', "날짜 형식이 올바르지 않습니다: {$v} (YYYY-MM-DD)", 400);
        }
        return $v;
    }

    private function positiveInt(mixed $value, int $default): int
    {
        return ctype_digit((string)$value) && (int)$value > 0 ? (int)$value : $default;
    }
}
