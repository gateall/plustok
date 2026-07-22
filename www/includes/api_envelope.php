<?php
declare(strict_types=1);
/**
 * ACEP API 표준 응답 — 03_SYSTEM/02_API설계.md §1.1
 */

require_once __DIR__ . '/AcepHttpResponse.php';

function acep_test_mode(bool $enabled = true): void
{
    $GLOBALS['acep_test_mode'] = $enabled;
}

function acep_is_test_mode(): bool
{
    return !empty($GLOBALS['acep_test_mode']);
}

function acep_set_request_json(?array $data): void
{
    $GLOBALS['acep_test_json'] = $data;
}

function acep_success(array $data = [], int $http = 200): void
{
    $body = [
        'success'   => true,
        'data'      => $data,
        'error'     => null,
        'timestamp' => date('c'),
    ];
    if (acep_is_test_mode()) {
        throw new AcepHttpResponse($body, $http);
    }
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function acep_error(string $code, string $message, int $http = 400): void
{
    $body = [
        'success'   => false,
        'data'      => null,
        'error'     => [
            'code'    => $code,
            'message' => $message,
        ],
        'timestamp' => date('c'),
    ];
    if (acep_is_test_mode()) {
        throw new AcepHttpResponse($body, $http);
    }
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function acep_read_json(): array
{
    if (array_key_exists('acep_test_json', $GLOBALS) && $GLOBALS['acep_test_json'] !== null) {
        return is_array($GLOBALS['acep_test_json']) ? $GLOBALS['acep_test_json'] : [];
    }
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
