<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_once __DIR__ . '/../util/Uuid.php';

final class AdminPromptService
{
    public function __construct(
        private PDO $pdo,
        private AuditService $audit,
    ) {
    }

    /** @param array<string,mixed> $query */
    public function list(array $query): array
    {
        if (!acep_table_exists($this->pdo, 'ai_prompts')) {
            return ['data' => []];
        }

        $where = ['1=1'];
        $params = [];
        if (isset($query['type']) && $query['type'] !== '') {
            $where[] = 'role = :role';
            $params[':role'] = (string)$query['type'];
        }
        if (isset($query['role']) && $query['role'] !== '') {
            $where[] = 'role = :role';
            $params[':role'] = (string)$query['role'];
        }
        if (isset($query['is_active'])) {
            $where[] = 'is_active = :active';
            $params[':active'] = filter_var($query['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if (!empty($query['q'])) {
            $where[] = '(prompt_id LIKE :q OR content LIKE :q2)';
            $params[':q'] = '%' . (string)$query['q'] . '%';
            $params[':q2'] = $params[':q'];
        }

        $sql = 'SELECT * FROM ai_prompts WHERE ' . implode(' AND ', $where)
            . ' ORDER BY role ASC, updated_at DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $data = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $data[] = $this->mapRow($r);
        }
        return ['data' => $data];
    }

    /** @param array<string,mixed> $body */
    public function create(string $actorId, array $body): array
    {
        $role = trim((string)($body['type'] ?? $body['role'] ?? ''));
        $promptKey = trim((string)($body['promptKey'] ?? $body['prompt_key'] ?? ''));
        $version = trim((string)($body['version'] ?? 'v1.0'));
        $content = trim((string)($body['template'] ?? $body['content'] ?? ''));
        $changelog = trim((string)($body['changelog'] ?? $body['name'] ?? ''));

        if ($role === '' || $promptKey === '' || $content === '') {
            acep_error('ADMIN_VALIDATION_ERROR', 'role(type), promptKey, content(template)가 필요합니다.', 422);
        }

        $dup = $this->pdo->prepare('SELECT id FROM ai_prompts WHERE prompt_id = :pid LIMIT 1');
        $dup->execute([':pid' => $promptKey]);
        if ($dup->fetch()) {
            acep_error('ADMIN_CONFLICT', '이미 존재하는 promptKey입니다.', 409);
        }

        $id = Uuid::v4();
        $this->pdo->prepare(
            'INSERT INTO ai_prompts (id, role, version, prompt_id, content, is_active, changelog, created_by)
             VALUES (:id, :role, :ver, :pid, :content, 0, :log, :by)'
        )->execute([
            ':id' => $id,
            ':role' => $role,
            ':ver' => $version,
            ':pid' => $promptKey,
            ':content' => $content,
            ':log' => $changelog !== '' ? $changelog : null,
            ':by' => $actorId,
        ]);

        $this->audit->agentAction($actorId, 'prompt.create', 'ai_prompts', $promptKey, ['id' => $id]);
        return $this->mapRow($this->findById($id));
    }

    /** @param array<string,mixed> $body */
    public function patch(string $actorId, string $id, array $body): array
    {
        $row = $this->findById($id);
        if (!$row) {
            acep_error('ADMIN_RESOURCE_NOT_FOUND', '프롬프트를 찾을 수 없습니다.', 404);
        }

        if (($body['action'] ?? '') === 'activate') {
            $this->pdo->prepare(
                'UPDATE ai_prompts SET is_active = 0 WHERE role = :role AND id != :id'
            )->execute([':role' => $row['role'], ':id' => $id]);
            $this->pdo->prepare(
                'UPDATE ai_prompts SET is_active = 1, updated_at = CURRENT_TIMESTAMP(3) WHERE id = :id'
            )->execute([':id' => $id]);
            $this->audit->agentAction($actorId, 'prompt.activate', 'ai_prompts', (string)$row['prompt_id'], ['id' => $id]);
            return $this->mapRow($this->findById($id));
        }

        if (isset($body['promptKey']) || isset($body['prompt_key'])) {
            acep_error('PROMPT_KEY_IMMUTABLE', 'promptKey는 변경할 수 없습니다.', 422);
        }

        $fields = [];
        $params = [':id' => $id];
        if (isset($body['template']) || isset($body['content'])) {
            $fields[] = 'content = :content';
            $params[':content'] = trim((string)($body['template'] ?? $body['content']));
        }
        if (isset($body['changelog']) || isset($body['name'])) {
            $fields[] = 'changelog = :log';
            $params[':log'] = trim((string)($body['changelog'] ?? $body['name']));
        }
        if (isset($body['version'])) {
            $fields[] = 'version = :ver';
            $params[':ver'] = trim((string)$body['version']);
        }
        if ($fields === []) {
            acep_error('ADMIN_VALIDATION_ERROR', '수정할 필드가 없습니다.', 422);
        }
        $fields[] = 'updated_at = CURRENT_TIMESTAMP(3)';
        $this->pdo->prepare('UPDATE ai_prompts SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        $this->audit->agentAction($actorId, 'prompt.update', 'ai_prompts', (string)$row['prompt_id'], ['id' => $id]);
        return $this->mapRow($this->findById($id));
    }

    public function delete(string $actorId, string $id, string $actorRole): void
    {
        $row = $this->findById($id);
        if (!$row) {
            acep_error('ADMIN_RESOURCE_NOT_FOUND', '프롬프트를 찾을 수 없습니다.', 404);
        }
        if ((int)$row['is_active'] === 1) {
            acep_error('PROMPT_ACTIVE_DELETE', '활성 프롬프트는 삭제할 수 없습니다.', 422);
        }
        if ($actorRole !== 'super' && $actorRole !== 'admin') {
            acep_error('ADMIN_FORBIDDEN', '프롬프트 삭제 권한이 없습니다.', 403);
        }

        $this->pdo->prepare('DELETE FROM ai_prompts WHERE id = :id')->execute([':id' => $id]);
        $this->audit->agentAction($actorId, 'prompt.delete', 'ai_prompts', (string)$row['prompt_id'], ['id' => $id]);
    }

    private function findById(string $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM ai_prompts WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $r */
    private function mapRow(array $r): array
    {
        return [
            'id'        => $r['id'],
            'promptKey' => $r['prompt_id'],
            'role'      => $r['role'],
            'type'      => $r['role'],
            'version'   => $r['version'],
            'name'      => $r['changelog'] ?? $r['prompt_id'],
            'isActive'  => (bool)$r['is_active'],
            'updatedAt' => date('c', strtotime((string)$r['updated_at'])),
            'createdBy' => $r['created_by'],
        ];
    }
}
