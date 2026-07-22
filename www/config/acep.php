<?php
declare(strict_types=1);
/**
 * ACEP V3.0 설정 — JWT, PII, 토큰 TTL.
 * 운영: config/acep.local.php 로 오버라이드 (gitignore 권장).
 */

// JWT (HS256)
const ACEP_JWT_SECRET = 'CHANGE_ME_IN_acep.local.php';
const ACEP_JWT_ACCESS_TTL = 86400;   // 24h
const ACEP_JWT_REFRESH_TTL = 604800; // 7d
const ACEP_REFRESH_COOKIE = 'acep_refresh';

// PII AES-256-GCM (32 bytes base64 or raw — acep.local.php에서 설정)
const ACEP_PII_KEY = '';

// 로그인 잠금 (API-001)
const ACEP_LOGIN_MAX_FAIL = 3;
const ACEP_LOGIN_LOCK_MINUTES = 30;

// bcrypt
const ACEP_PASSWORD_COST = 12;

// Redis (WebSocket pub/sub, AI cache) — acep.local.php 또는 REDIS_URL env
const ACEP_REDIS_URL = '';

/**
 * acep.local.php 가 있으면 상수를 재정의한다.
 */
(function (): void {
    $local = __DIR__ . '/acep.local.php';
    if (is_file($local)) {
        require $local;
    }
})();

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
