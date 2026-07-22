<?php
declare(strict_types=1);
/**
 * JSON 응답 헬퍼. 모든 API는 이 함수로만 응답한다. (API.md 공통 형식)
 */

function json_success(array $data = [], string $message = '', int $http = 200): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'result'  => 'success',
        'data'    => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $code, string $message, int $http = 400): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'result'  => 'error',
        'code'    => $code,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
