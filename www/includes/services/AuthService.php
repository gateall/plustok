<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/acep.php';
require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../util/JwtHelper.php';
require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../util/PiiEncryptor.php';
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

    /**
     * 이름+이메일로 아이디(login_id) 이메일 발송. 계정 존재 여부를 노출하지 않기 위해
     * 항상 성공(void) 반환 — 실제 발송 여부는 조건부.
     */
    public function forgotId(string $name, string $email): void
    {
        $name = trim($name);
        $email = trim($email);
        if ($name === '' || $email === '') {
            return;
        }

        foreach ($this->agents->findAllActiveWithEmail() as $row) {
            $storedEmail = PiiEncryptor::decryptEmail((string)$row['email']);
            if ($storedEmail === null) {
                continue;
            }
            if (strcasecmp($storedEmail, $email) !== 0) {
                continue;
            }

            $agent = $this->agents->findById((string)$row['id']);
            if (!$agent || strcasecmp(trim((string)$agent['name']), $name) !== 0) {
                continue;
            }

            $subject = '[' . APP_BRAND . '] 아이디 안내';
            $body = "{$agent['name']}님,\n\n요청하신 아이디는 다음과 같습니다:\n\n로그인 ID: {$agent['login_id']}\n\n본인이 요청하지 않았다면 이 메일을 무시하세요.";
            $this->sendAuthMail($storedEmail, $subject, $body, 'auth.forgot_id');
            return;
        }

        log_error('auth.forgot_id', 'skip: no_match');
    }

    /**
     * 이메일로 비밀번호 재설정 링크 발송. 계정 존재 여부를 노출하지 않기 위해
     * 항상 성공(void) 반환 — 실제 발송 여부는 조건부.
     */
    public function forgotPassword(string $loginId, string $email): void
    {
        $loginId = trim($loginId);
        $email = trim($email);

        $agent = $this->agents->findByLoginId($loginId);
        if (!$agent) {
            log_error('auth.forgot_password', 'skip: agent_not_found id_hash=' . substr(hash('sha256', $loginId), 0, 12));
            return;
        }
        if (empty($agent['email'])) {
            log_error('auth.forgot_password', 'skip: no_email agent_id=' . substr((string)$agent['id'], 0, 8));
            return;
        }

        $storedEmail = PiiEncryptor::decryptEmail((string)$agent['email']);
        if ($storedEmail === null) {
            log_error('auth.forgot_password', 'skip: decrypt_failed agent_id=' . substr((string)$agent['id'], 0, 8));
            return;
        }

        if (strcasecmp($storedEmail, $email) !== 0) {
            log_error('auth.forgot_password', 'skip: email_mismatch agent_id=' . substr((string)$agent['id'], 0, 8));
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s.v', strtotime('+' . ACEP_RESET_TOKEN_TTL_MINUTES . ' minutes'));

        try {
            $this->agents->setResetToken((string)$agent['id'], $tokenHash, $expiresAt);
        } catch (Throwable $e) {
            log_api_error($e);
            log_error('auth.forgot_password', 'skip: db_reset_token agent_id=' . substr((string)$agent['id'], 0, 8));
            return;
        }

        $resetUrl = BASE_URL . '/frontend/#/reset-password?token=' . urlencode($token);
        $subject = '[' . APP_BRAND . '] 비밀번호 재설정';
        $body = "{$agent['name']}님,\n\n아래 링크에서 비밀번호를 재설정하세요 (유효시간 " . ACEP_RESET_TOKEN_TTL_MINUTES . "분):\n{$resetUrl}\n\n본인이 요청하지 않았다면 이 메일을 무시하세요.";

        if ($this->sendAuthMail($storedEmail, $subject, $body, 'auth.forgot_password')) {
            log_error('auth.forgot_password', 'ok: mail_sent agent_id=' . substr((string)$agent['id'], 0, 8));
        }
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            acep_error('VALIDATION_ERROR', '비밀번호는 8자 이상이어야 합니다.', 400);
        }

        $tokenHash = hash('sha256', $token);
        $agent = $this->agents->findByResetTokenHash($tokenHash);

        if (!$agent
            || empty($agent['reset_token_expires_at'])
            || strtotime((string)$agent['reset_token_expires_at']) < time()
        ) {
            acep_error('INVALID_TOKEN', '재설정 링크가 유효하지 않거나 만료되었습니다.', 400);
        }

        $this->agents->updatePasswordHash(
            (string)$agent['id'],
            password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => ACEP_PASSWORD_COST]),
        );
        $this->agents->clearResetToken((string)$agent['id']);
        $this->safeAudit((string)$agent['id'], 'password.reset');
    }

    private function sendAuthMail(string $to, string $subject, string $body, string $context): bool
    {
        return acep_send_mail($to, $subject, $body, $context);
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
