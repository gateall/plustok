<?php
declare(strict_types=1);
/**
 * ACEP V3.0 설정 — JWT, PII, 토큰 TTL.
 * 운영: config/acep.local.php 로 오버라이드 (gitignore 권장).
 */

// acep.local.php 먼저 로드 — const는 define()으로 재정의 불가하므로 define 기본값 패턴 사용
$acepLocal = __DIR__ . '/acep.local.php';
if (is_file($acepLocal)) {
    require $acepLocal;
}

if (!defined('ACEP_JWT_SECRET')) {
    define('ACEP_JWT_SECRET', 'CHANGE_ME_IN_acep.local.php');
}
if (!defined('ACEP_PII_KEY')) {
    define('ACEP_PII_KEY', '');
}
if (!defined('ACEP_REDIS_URL')) {
    define('ACEP_REDIS_URL', '');
}
if (!defined('ACEP_CHAT_SERVER_URL')) {
    define('ACEP_CHAT_SERVER_URL', '');
}
if (!defined('ACEP_CHAT_INTERNAL_SECRET')) {
    define('ACEP_CHAT_INTERNAL_SECRET', '');
}

const ACEP_JWT_ACCESS_TTL = 86400;   // 24h
const ACEP_JWT_REFRESH_TTL = 604800; // 7d
const ACEP_CUSTOMER_JWT_TTL = 14400; // 4h — embed chat widget session
const ACEP_REFRESH_COOKIE = 'acep_refresh';

// 로그인 잠금 (API-001)
const ACEP_LOGIN_MAX_FAIL = 3;
const ACEP_LOGIN_LOCK_MINUTES = 30;

// 비밀번호 재설정 토큰 유효시간
const ACEP_RESET_TOKEN_TTL_MINUTES = 30;

// bcrypt
const ACEP_PASSWORD_COST = 12;

function acep_jwt_secret(): string
{
    $s = ACEP_JWT_SECRET;
    if ($s === '' || $s === 'CHANGE_ME_IN_acep.local.php') {
        // 개발용 fallback — 운영에서는 acep.local.php 필수
        return hash('sha256', BASE_PATH . 'acep-dev-secret');
    }
    return $s;
}

function acep_pii_key(): string
{
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
