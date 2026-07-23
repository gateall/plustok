<?php
declare(strict_types=1);

/**
 * Apache/Cafe24: Bearer Authorization 헤더를 PHP로 복원.
 * api/v1, admin/sso.php 등 JWT Bearer가 필요한 진입점에서 호출.
 */
function acep_restore_authorization_header(): void
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return;
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        return;
    }
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (!empty($headers['Authorization'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = $headers['Authorization'];
            return;
        }
        if (!empty($headers['authorization'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = $headers['authorization'];
        }
    }
}
