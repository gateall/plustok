<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/JwtHelper.php';
require_once __DIR__ . '/../api_envelope.php';

final class JwtMiddleware
{
    /** @var array<string,mixed>|null */
    private static ?array $claims = null;

    public static function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /** @return array<string,mixed> */
    public static function requireAuth(): array
    {
        if (self::$claims !== null) {
            return self::$claims;
        }
        $token = self::bearerToken();
        if ($token === '') {
            acep_error('UNAUTHORIZED', '인증 토큰이 필요합니다.', 401);
        }
        $claims = JwtHelper::decode($token);
        if ($claims === null || empty($claims['sub'])) {
            acep_error('UNAUTHORIZED', '유효하지 않거나 만료된 토큰입니다.', 401);
        }
        self::$claims = $claims;
        return $claims;
    }

    /** @param list<string> $roles */
    public static function requireRole(array $roles): array
    {
        $claims = self::requireAuth();
        $role = (string)($claims['role'] ?? '');
        if (!in_array($role, $roles, true)) {
            acep_error('FORBIDDEN', '접근 권한이 없습니다.', 403);
        }
        return $claims;
    }

    public static function reset(): void
    {
        self::$claims = null;
    }
}
