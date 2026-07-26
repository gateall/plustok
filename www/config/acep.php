<?php
declare(strict_types=1);
/**
 * ACEP V3.0 설정 — JWT, PII, 토큰 TTL.
 * Secrets: 환경변수 → acep.local.php (non-tracked) → fail-closed (production).
 */

function acep_define_from_env(string $name): void
{
    if (defined($name)) {
        return;
    }
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        define($name, $value);
    }
}

function acep_define_default(string $name, mixed $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

function acep_is_rejected_jwt_secret(string $secret): bool
{
    $secret = trim($secret);
    if ($secret === '') {
        return true;
    }
    if (strlen($secret) < 32) {
        return true;
    }
    $lower = strtolower($secret);
    $blocked = [
        'change_me_in_acep.local.php',
        'change_me',
        'your-256-bit-secret',
        'default-secret',
        'test-only',
    ];
    foreach ($blocked as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }
    return false;
}

function acep_bootstrap_secrets(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    $bootstrapped = true;

    // 1) Environment variables (highest priority)
    acep_define_from_env('ACEP_JWT_SECRET');
    acep_define_from_env('ACEP_PII_KEY');
    acep_define_from_env('ACEP_REDIS_URL');
    acep_define_from_env('ACEP_CHAT_SERVER_URL');
    acep_define_from_env('ACEP_CHAT_INTERNAL_SECRET');

    // 2) Non-tracked local overrides
    $local = __DIR__ . '/acep.local.php';
    if (is_file($local)) {
        require $local;
    }

    // 3) Non-secret defaults
    acep_define_default('ACEP_JWT_ACCESS_TTL', 86400);
    acep_define_default('ACEP_JWT_REFRESH_TTL', 604800);
    acep_define_default('ACEP_CUSTOMER_JWT_TTL', 14400);
    acep_define_default('ACEP_REFRESH_COOKIE', 'acep_refresh');
    acep_define_default('ACEP_LOGIN_MAX_FAIL', 3);
    acep_define_default('ACEP_LOGIN_LOCK_MINUTES', 30);
    acep_define_default('ACEP_RESET_TOKEN_TTL_MINUTES', 30);
    acep_define_default('ACEP_PASSWORD_COST', 12);
    acep_define_default('ACEP_PII_KEY', '');
    acep_define_default('ACEP_REDIS_URL', '');
    acep_define_default('ACEP_CHAT_SERVER_URL', '');
    acep_define_default('ACEP_CHAT_INTERNAL_SECRET', '');

    // 4) Fail-closed secret validation
    if (!defined('ACEP_JWT_SECRET')) {
        if (defined('ACEP_TESTING') && ACEP_TESTING) {
            define('ACEP_JWT_SECRET', hash('sha256', 'acep-phpunit-isolated-jwt-key-v1'));
        } else {
            throw new RuntimeException('ACEP_JWT_SECRET is not configured');
        }
    }

    $testing = defined('ACEP_TESTING') && ACEP_TESTING;
    if (!$testing && acep_is_rejected_jwt_secret(ACEP_JWT_SECRET)) {
        throw new RuntimeException('ACEP_JWT_SECRET placeholder or weak value rejected');
    }
}

acep_bootstrap_secrets();

function acep_jwt_secret(): string
{
    acep_bootstrap_secrets();
    return ACEP_JWT_SECRET;
}

function acep_pii_key(): string
{
    acep_bootstrap_secrets();
    $k = ACEP_PII_KEY;
    if ($k !== '') {
        $bin = base64_decode($k, true);
        return ($bin !== false && strlen($bin) === 32) ? $bin : $k;
    }
    return hash('sha256', acep_jwt_secret(), true);
}

function acep_chat_server_url(): string
{
    if (defined('ACEP_CHAT_SERVER_URL') && ACEP_CHAT_SERVER_URL !== '') {
        return ACEP_CHAT_SERVER_URL;
    }
    $env = getenv('ACEP_CHAT_SERVER_URL');
    return is_string($env) && $env !== '' ? $env : '';
}

function acep_chat_internal_secret(): string
{
    if (defined('ACEP_CHAT_INTERNAL_SECRET') && ACEP_CHAT_INTERNAL_SECRET !== '') {
        return ACEP_CHAT_INTERNAL_SECRET;
    }
    return acep_jwt_secret();
}

/** Chat Server WebSocket URL (http→ws, https→wss) */
function acep_chat_ws_url(): string
{
    $url = acep_chat_server_url();
    if ($url === '') {
        return '';
    }
    return (string)preg_replace('#^http#i', 'ws', $url);
}
