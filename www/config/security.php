<?php
declare(strict_types=1);
/**
 * 보안 설정. 세션/해시/CORS 관련.
 */

// 세션 쿠키 (관리자)
const SESSION_NAME = 'plustok_sid';
const SESSION_IDLE_TIMEOUT = 3600;   // 초. 유휴 1시간 후 로그아웃

// 비밀번호 해시
const PASSWORD_ALGO = PASSWORD_DEFAULT;   // bcrypt (PHP 8.1)

// 로그인 실패 잠금
const LOGIN_MAX_FAIL = 5;
const LOGIN_LOCK_SECONDS = 300;

/**
 * 상담 접수 API를 직접(CORS) 허용할 오리진.
 * V1.0 기본은 서버 프록시(embed/form.php)를 쓰므로 비워둔다.
 * 사이트에서 직접 호출이 필요하면 sites.domain 기반으로 동적 허용하도록 구현한다.
 */
const CORS_ALLOWED_ORIGINS = [];

/**
 * 안전한 세션 시작 헬퍼. 관리자 진입점에서 호출한다.
 */
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,      // HTTPS 전용
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // 유휴 타임아웃
    $now = time();
    if (isset($_SESSION['_last']) && ($now - (int)$_SESSION['_last']) > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['_last'] = $now;
}
