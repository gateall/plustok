<?php
declare(strict_types=1);

/**
 * ACEP 통합 사용자 관리 — Frontend / Admin / API 공용
 *
 * SSOT: `agents` 테이블 (acep_users 별도 테이블 없음 — Phase 2 설계와 동일 역할)
 * @see includes/services/AuthService.php
 */

require_once __DIR__ . '/acep.php';
require_once __DIR__ . '/../includes/repositories/AgentRepository.php';
require_once __DIR__ . '/../includes/util/JwtHelper.php';
require_once __DIR__ . '/../includes/util/Uuid.php';

final class AcepUserManager
{
    public function __construct(private AgentRepository $agents)
    {
    }

    /**
     * @return array{userId:string,username:string,loginId:string,name:string,role:string}|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        $loginId = trim($username);
        if ($loginId === '' || $password === '') {
            return null;
        }

        $agent = $this->agents->findByLoginId($loginId);
        if (!$agent) {
            return null;
        }

        if (!empty($agent['locked_until']) && strtotime((string)$agent['locked_until']) > time()) {
            return null;
        }

        if (!password_verify($password, (string)$agent['password_hash'])) {
            return null;
        }

        return $this->toPublicUser($agent);
    }

    /** @param array<string,mixed> $agent */
    public function toPublicUser(array $agent): array
    {
        return [
            'userId'   => (string)$agent['id'],
            'username' => (string)$agent['login_id'],
            'loginId'  => (string)$agent['login_id'],
            'name'     => (string)$agent['name'],
            'role'     => (string)$agent['role'],
        ];
    }

    /**
     * @return array{userId:string,username:string,role:string}
     */
    public function createUser(
        string $username,
        string $password,
        string $role = 'agent',
        ?string $name = null,
    ): array {
        $loginId = trim($username);
        if ($loginId === '' || strlen($password) < 8) {
            throw new InvalidArgumentException('username and password (8+) required');
        }
        if (!in_array($role, ['agent', 'admin', 'operator'], true)) {
            throw new InvalidArgumentException('invalid role');
        }
        if ($this->agents->findByLoginId($loginId)) {
            throw new RuntimeException('username already exists');
        }

        $id = uuid_v4();
        $this->agents->create([
            ':id'             => $id,
            ':login_id'       => $loginId,
            ':password_hash'  => password_hash($password, PASSWORD_BCRYPT, ['cost' => ACEP_PASSWORD_COST]),
            ':name'           => $name ?? $loginId,
            ':email'          => null,
            ':phone'          => null,
            ':role'           => $role,
            ':status'         => 'offline',
        ]);

        return [
            'userId'   => $id,
            'username' => $loginId,
            'role'     => $role,
        ];
    }

    public function changePassword(string $userId, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            return false;
        }
        if (!$this->agents->findById($userId)) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => ACEP_PASSWORD_COST]);
        return $this->agents->updatePasswordHash($userId, $hash);
    }

    /** JWT access token (Frontend / Admin 세션 공용) */
    public function createAccessToken(array $publicUser): string
    {
        return JwtHelper::encode([
            'sub'  => $publicUser['userId'],
            'role' => $publicUser['role'],
            'name' => $publicUser['name'],
        ], ACEP_JWT_ACCESS_TTL);
    }

    /** Legacy PHP admin `managers.role` 호환 */
    public static function legacyManagerSession(array $publicUser): array
    {
        $legacyRole = match ($publicUser['role']) {
            'admin'    => 'admin',
            'operator' => 'manager',
            default    => 'sales',
        };

        return [
            'id'        => $publicUser['userId'],
            'login_id'  => $publicUser['username'],
            'name'      => $publicUser['name'],
            'role'      => $legacyRole,
            'acep_role' => $publicUser['role'],
        ];
    }
}
