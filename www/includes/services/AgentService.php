<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/PiiEncryptor.php';

final class AgentService
{
    private const STATUSES = ['online', 'away', 'offline'];

    public function __construct(
        private AgentRepository $agents,
        private AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function updateStatus(string $agentId, string $requesterId, string $requesterRole, string $status): array
    {
        if ($agentId !== $requesterId && !in_array($requesterRole, ['admin', 'operator'], true)) {
            acep_error('FORBIDDEN', '상태 변경 권한이 없습니다.', 403);
        }
        if (!in_array($status, self::STATUSES, true)) {
            acep_error('VALIDATION_ERROR', '유효하지 않은 status입니다.', 400);
        }
        if (!$this->agents->updateStatus($agentId, $status)) {
            acep_error('AGENT_NOT_FOUND', '상담원을 찾을 수 없습니다.', 404);
        }
        $this->audit->agentAction($requesterId, 'agent.status.update', 'agent', $agentId, ['status' => $status]);
        return ['id' => $agentId, 'status' => $status];
    }

    /** @param array<string,mixed> $body */
    public function updateProfile(string $agentId, array $body): array
    {
        $fields = [];
        if (isset($body['name'])) {
            $fields['name'] = trim((string)$body['name']);
        }
        if (array_key_exists('avatarUrl', $body)) {
            $fields['avatar_url'] = $body['avatarUrl'];
        }
        if (isset($body['email'])) {
            $fields['email'] = PiiEncryptor::encrypt(trim((string)$body['email']));
        }
        if (isset($body['phone'])) {
            $fields['phone'] = PiiEncryptor::encrypt(trim((string)$body['phone']));
        }
        if ($fields === []) {
            acep_error('VALIDATION_ERROR', '수정할 필드가 없습니다.', 400);
        }
        if (!$this->agents->updateProfile($agentId, $fields)) {
            acep_error('AGENT_NOT_FOUND', '상담원을 찾을 수 없습니다.', 404);
        }
        $this->audit->agentAction($agentId, 'agent.profile.update', 'agent', $agentId);
        $agent = $this->agents->findById($agentId);
        return [
            'id'        => $agentId,
            'name'      => $agent['name'],
            'avatarUrl' => $agent['avatar_url'],
            'updatedAt' => date('c', strtotime((string)$agent['updated_at'])),
        ];
    }
}
