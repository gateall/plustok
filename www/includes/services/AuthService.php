<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/acep.php';
require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../util/JwtHelper.php';
require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../../config/acep.users.php';

final class AuthService
{
    private AcepUserManager $users;

    public function __construct(
        private AgentRepository $agents,
        private AuditService $audit,
    ) {
        $this->users = new AcepUserManager($agents);
    }

    /** @return array{accessToken:string,expiresIn:int,agent:array<string,mixed>} */
    public function login(string $loginId, string $password): array
    {
        try {
            $agent = $this->agents->findByLoginId($loginId);
        } catch (PDOException $e) {
            log_api_error($e);
            acep_error(
                'AUTH_DB_ERROR',
                'agents 테이블이 없거나 스키마가 맞지 않습니다. Cafe24 phpMyAdmin에서 migrations/V1.5.0__agents_ai_ops.sql 을 실행하세요.',
                503,
            );
        }

        if (!$agent) {
            acep_error('UNAUTHORIZED', '아이디 또는 비밀번호가 올바르지 않습니다.', 401);
        }

        if (!empty($agent['locked_until']) && strtotime((string)$agent['locked_until']) > time()) {
            acep_error('ACCOUNT_LOCKED', '로그인 실패 횟수 초과로 계정이 잠겼습니다.', 403);
        }

        if (!password_verify($password, (string)($agent['password_hash'] ?? ''))) {
            try {
                $this->agents->incrementFailedLogin((string)$agent['id']);
                $fail = (int)($agent['failed_login_count'] ?? 0) + 1;
                if ($fail >= ACEP_LOGIN_MAX_FAIL) {
                    $this->agents->lockAccount((string)$agent['id'], ACEP_LOGIN_LOCK_MINUTES);
                }
            } catch (PDOException $e) {
                log_api_error($e);
                acep_error(
                    'AUTH_DB_ERROR',
                    'agents 테이블 스키마를 확인하세요 (failed_login_count, locked_until).',
                    503,
                );
            }
            acep_error('UNAUTHORIZED', '아이디 또는 비밀번호가 올바르지 않습니다.', 401);
        }

        try {
            $this->agents->updateLoginSuccess((string)$agent['id']);
        } catch (PDOException $e) {
            log_api_error($e);
            acep_error(
                'AUTH_DB_ERROR',
                'agents 테이블 last_login_at 업데이트 실패. V1.5.0 마이그레이션을 확인하세요.',
                503,
            );
        }

        $this->safeAudit((string)$agent['id'], 'login');

        try {
            return $this->issueTokens($agent);
        } catch (Throwable $e) {
            log_api_error($e);
            acep_error('AUTH_TOKEN_ERROR', 'JWT 발급 실패. config/acep.local.php ACEP_JWT_SECRET 을 확인하세요.', 500);
        }
    }

    private function safeAudit(string $agentId, string $action): void
    {
        try {
            $this->audit->agentAction($agentId, $action);
        } catch (Throwable $e) {
            // audit_logs 미생성 등 — 로그인 자체는 허용
            log_api_error($e);
        }
    }

    /** @return array{accessToken:string,expiresIn:int,agent:array<string,mixed>} */
    public function refresh(string $refreshToken): array
    {
        $claims = JwtHelper::decode($refreshToken);
        if ($claims === null || ($claims['typ'] ?? '') !== 'refresh' || empty($claims['sub'])) {
            acep_error('UNAUTHORIZED', 'Refresh 토큰이 유효하지 않습니다.', 401);
        }
        $agent = $this->agents->findById((string)$claims['sub']);
        if (!$agent) {
            acep_error('UNAUTHORIZED', 'Refresh 토큰이 유효하지 않습니다.', 401);
        }
        return $this->issueTokens($agent, false);
    }

    public function logout(string $agentId): void
    {
        $this->clearRefreshCookie();
        $this->audit->agentAction($agentId, 'logout');
    }

    /** @return array<string,mixed> */
    public function me(string $agentId): array
    {
        $agent = $this->agents->findById($agentId);
        if (!$agent) {
            acep_error('AGENT_NOT_FOUND', '상담원을 찾을 수 없습니다.', 404);
        }
        return $this->publicAgent($agent);
    }

    /** @return array<string,mixed> */
    public function register(string $loginId, string $password, string $name, string $role = 'agent'): array
    {
        if ($this->agents->findByLoginId($loginId)) {
            acep_error('VALIDATION_ERROR', '이미 사용 중인 loginId입니다.', 400);
        }
        if (strlen($password) < 8) {
            acep_error('VALIDATION_ERROR', '비밀번호는 8자 이상이어야 합니다.', 400);
        }
        if (!in_array($role, ['agent', 'admin', 'operator'], true)) {
            acep_error('VALIDATION_ERROR', '유효하지 않은 role입니다.', 400);
        }

        $id = uuid_v4();
        $this->agents->create([
            ':id'             => $id,
            ':login_id'       => $loginId,
            ':password_hash'  => password_hash($password, PASSWORD_BCRYPT, ['cost' => ACEP_PASSWORD_COST]),
            ':name'           => $name,
            ':email'          => null,
            ':phone'          => null,
            ':role'           => $role,
            ':status'         => 'offline',
        ]);

        $agent = $this->agents->findById($id);
        $this->audit->agentAction($id, 'agent.register');
        return $this->issueTokens($agent);
    }

    /** @param array<string,mixed> $agent */
    private function issueTokens(array $agent, bool $setCookie = true): array
    {
        $payload = [
            'sub'  => $agent['id'],
            'role' => $agent['role'],
            'name' => $agent['name'],
        ];
        $access = JwtHelper::encode($payload, ACEP_JWT_ACCESS_TTL);

        if ($setCookie) {
            $refresh = JwtHelper::encode($payload + ['typ' => 'refresh'], ACEP_JWT_REFRESH_TTL);
            $this->setRefreshCookie($refresh);
        }

        return [
            'accessToken' => $access,
            'expiresIn'   => ACEP_JWT_ACCESS_TTL,
            'agent'       => [
                'id'     => $agent['id'],
                'name'   => $agent['name'],
                'role'   => $agent['role'],
                'status' => $agent['status'],
            ],
        ];
    }

    /** @param array<string,mixed> $agent */
    private function publicAgent(array $agent): array
    {
        return [
            'id'          => $agent['id'],
            'loginId'     => $agent['login_id'],
            'name'        => $agent['name'],
            'role'        => $agent['role'],
            'status'      => $agent['status'],
            'avatarUrl'   => $agent['avatar_url'],
            'lastLoginAt' => $agent['last_login_at']
                ? date('c', strtotime((string)$agent['last_login_at']))
                : null,
        ];
    }

    private function setRefreshCookie(string $token): void
    {
        setcookie(ACEP_REFRESH_COOKIE, $token, [
            'expires'  => time() + ACEP_JWT_REFRESH_TTL,
            'path'     => '/api/v1/auth',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearRefreshCookie(): void
    {
        setcookie(ACEP_REFRESH_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/api/v1/auth',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
