<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/Uuid.php';

final class AdminAgentService
{
    public function __construct(
        private AgentRepository $agents,
        private ChatRoomRepository $rooms,
        private AuditService $audit,
        private PDO $pdo,
    ) {
    }

    public function list(): array
    {
        $st = $this->pdo->query(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM chat_rooms cr
                     WHERE cr.agent_id = a.id AND cr.status IN ('new','active') AND cr.deleted_at IS NULL) AS active_assignments
             FROM agents a WHERE a.deleted_at IS NULL ORDER BY a.name ASC"
        );
        $data = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $data[] = [
                'id'                => $r['id'],
                'displayName'       => $r['name'],
                'loginId'           => $r['login_id'],
                'role'              => $r['role'],
                'status'            => $r['status'],
                'activeAssignments' => (int)$r['active_assignments'],
            ];
        }
        return ['data' => $data];
    }

    /** @param array<string,mixed> $body */
    public function create(string $actorId, array $body): array
    {
        $loginId = trim((string)($body['loginId'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $name = trim((string)($body['displayName'] ?? $body['name'] ?? ''));
        $role = (string)($body['role'] ?? 'agent');
        if ($loginId === '' || $password === '' || $name === '') {
            acep_error('VALIDATION_ERROR', 'loginId, password, displayName이 필요합니다.', 400);
        }
        if (!in_array($role, ['agent', 'admin', 'operator'], true)) {
            acep_error('VALIDATION_ERROR', 'role은 agent, admin, operator 중 하나입니다.', 400);
        }
        if ($this->agents->findByLoginId($loginId)) {
            acep_error('VALIDATION_ERROR', '이미 사용 중인 loginId입니다.', 409);
        }
        $id = Uuid::v4();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->pdo->prepare(
            'INSERT INTO agents (id, login_id, password_hash, name, role, status)
             VALUES (:id, :login, :hash, :name, :role, :status)'
        )->execute([
            ':id' => $id, ':login' => $loginId, ':hash' => $hash,
            ':name' => $name, ':role' => $role, ':status' => 'offline',
        ]);
        $this->audit->agentAction($actorId, 'agent.create', 'agent', $id, ['loginId' => $loginId]);
        return ['id' => $id, 'displayName' => $name, 'role' => $role];
    }

    /** @param array<string,mixed> $body */
    public function update(string $actorId, string $agentId, string $actorRole, array $body): array
    {
        $agent = $this->agents->findById($agentId);
        if (!$agent) {
            acep_error('NOT_FOUND', '상담원을 찾을 수 없습니다.', 404);
        }
        $fields = [];
        $params = [':id' => $agentId];
        if (isset($body['displayName']) || isset($body['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = trim((string)($body['displayName'] ?? $body['name']));
        }
        if (isset($body['status']) && in_array($body['status'], ['online', 'away', 'offline'], true)) {
            $fields[] = 'status = :status';
            $params[':status'] = $body['status'];
        }
        if (isset($body['role'])) {
            $newRole = (string)$body['role'];
            if (!in_array($newRole, ['agent', 'admin', 'operator'], true)) {
                acep_error('VALIDATION_ERROR', '유효하지 않은 role입니다.', 400);
            }
            $fields[] = 'role = :role';
            $params[':role'] = $newRole;
        }
        if ($fields === []) {
            acep_error('VALIDATION_ERROR', '수정할 필드가 없습니다.', 400);
        }
        $fields[] = 'updated_at = CURRENT_TIMESTAMP(3)';
        $this->pdo->prepare('UPDATE agents SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        $this->audit->agentAction($actorId, 'agent.update', 'agent', $agentId, $body);
        return ['id' => $agentId, 'updated' => true];
    }

    /** @param array<string,mixed> $body */
    public function assignRoom(string $actorId, string $agentId, array $body): array
    {
        $roomId = trim((string)($body['chatRoomId'] ?? $body['chat_room_id'] ?? ''));
        if ($roomId === '') {
            acep_error('VALIDATION_ERROR', 'chatRoomId가 필요합니다.', 400);
        }
        $room = $this->rooms->findById($roomId);
        if (!$room) {
            acep_error('ROOM_NOT_FOUND', '상담방을 찾을 수 없습니다.', 404);
        }
        if (!empty($room['agent_id']) && $room['agent_id'] !== $agentId) {
            acep_error('VALIDATION_ERROR', '이미 다른 상담원에게 배정되었습니다.', 409);
        }
        $this->rooms->setActive($roomId, $agentId);
        if (acep_table_exists($this->pdo, 'chat_room_assignments')) {
            $this->pdo->prepare(
                'INSERT INTO chat_room_assignments (room_id, agent_id, assignment_type, assigned_by)
                 VALUES (:rid, :aid, \'manual\', :by)'
            )->execute([':rid' => $roomId, ':aid' => $agentId, ':by' => $actorId]);
        }
        $this->audit->agentAction($actorId, 'assignment.create', 'chat_room', $roomId, ['agentId' => $agentId]);
        return ['roomId' => $roomId, 'agentId' => $agentId];
    }

    public function releaseAssignment(string $actorId, string $agentId, int $assignmentId): array
    {
        if (!acep_table_exists($this->pdo, 'chat_room_assignments')) {
            acep_error('NOT_FOUND', '배정 기록을 찾을 수 없습니다.', 404);
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM chat_room_assignments WHERE id = :id AND agent_id = :aid AND is_active = 1 LIMIT 1'
        );
        $st->execute([':id' => $assignmentId, ':aid' => $agentId]);
        $row = $st->fetch();
        if (!$row) {
            acep_error('NOT_FOUND', '활성 배정을 찾을 수 없습니다.', 404);
        }

        $roomId = (string)$row['room_id'];
        $this->pdo->prepare(
            'UPDATE chat_room_assignments SET is_active = 0, unassigned_at = CURRENT_TIMESTAMP(3) WHERE id = :id'
        )->execute([':id' => $assignmentId]);

        $room = $this->rooms->findById($roomId);
        if ($room && ($room['agent_id'] ?? '') === $agentId) {
            $this->pdo->prepare(
                'UPDATE chat_rooms SET agent_id = NULL, updated_at = CURRENT_TIMESTAMP(3) WHERE id = :id'
            )->execute([':id' => $roomId]);
        }

        $this->audit->agentAction($actorId, 'assignment.release', 'chat_room_assignment', (string)$assignmentId, [
            'roomId'  => $roomId,
            'agentId' => $agentId,
        ]);
        return ['assignmentId' => $assignmentId, 'roomId' => $roomId, 'released' => true];
    }
}
