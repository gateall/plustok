<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/PiiEncryptor.php';
require_once __DIR__ . '/../api_envelope.php';

final class CustomerService
{
    public function __construct(
        private CustomerRepository $customers,
        private AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function getById(string $customerId, string $agentId, string $role): array
    {
        if (!$this->customers->agentCanAccessCustomer($agentId, $customerId, $role)) {
            acep_error('FORBIDDEN', '고객 정보 접근 권한이 없습니다.', 403);
        }
        $row = $this->customers->findById($customerId);
        if (!$row) {
            acep_error('CUSTOMER_NOT_FOUND', '고객을 찾을 수 없습니다.', 404);
        }
        return $this->toPublic($row);
    }

    /** @param array<string,mixed> $body */
    public function update(string $customerId, string $agentId, string $role, array $body): array
    {
        if (!in_array($role, ['agent', 'admin', 'operator'], true)) {
            acep_error('FORBIDDEN', '고객 정보 수정 권한이 없습니다.', 403);
        }
        if (!$this->customers->agentCanAccessCustomer($agentId, $customerId, $role)) {
            acep_error('FORBIDDEN', '고객 정보 접근 권한이 없습니다.', 403);
        }

        $fields = [];
        if (isset($body['name'])) {
            $fields['name'] = trim((string)$body['name']);
        }
        if (isset($body['tags']) && is_array($body['tags'])) {
            $fields['tags'] = array_values(array_map('strval', $body['tags']));
        }
        if ($fields === []) {
            acep_error('VALIDATION_ERROR', '수정할 필드가 없습니다.', 400);
        }

        if (!$this->customers->update($customerId, $fields)) {
            acep_error('CUSTOMER_NOT_FOUND', '고객을 찾을 수 없습니다.', 404);
        }

        $this->audit->agentAction($agentId, 'customer.update', 'customer', $customerId, $fields);
        $row = $this->customers->findById($customerId);

        return [
            'id'        => $customerId,
            'name'      => $row['name'],
            'tags'      => json_decode((string)($row['tags'] ?? '[]'), true) ?: [],
            'updatedAt' => date('c', strtotime((string)$row['updated_at'])),
        ];
    }

    /** @param array<string,mixed> $row */
    private function toPublic(array $row): array
    {
        $phone = '';
        $email = '';
        $address = '';
        try {
            $phone = PiiEncryptor::decrypt((string)$row['phone']);
        } catch (Throwable) {
            $phone = (string)$row['phone'];
        }
        if (!empty($row['email'])) {
            try {
                $email = PiiEncryptor::decrypt((string)$row['email']);
            } catch (Throwable) {
                $email = (string)$row['email'];
            }
        }
        if (!empty($row['address'])) {
            try {
                $address = PiiEncryptor::decrypt((string)$row['address']);
            } catch (Throwable) {
                $address = (string)$row['address'];
            }
        }

        return [
            'id'                  => $row['id'],
            'name'                => $row['name'],
            'phoneMasked'         => PiiEncryptor::maskPhone($phone),
            'emailMasked'         => $email !== '' ? PiiEncryptor::maskEmail($email) : null,
            'addressMasked'       => $address !== '' ? PiiEncryptor::maskAddress($address) : null,
            'tags'                => json_decode((string)($row['tags'] ?? '[]'), true) ?: [],
            'consultationCount'   => (int)$row['consultation_count'],
            'createdAt'           => date('c', strtotime((string)$row['created_at'])),
        ];
    }
}
