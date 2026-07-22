<?php
declare(strict_types=1);

/**
 * ACEP REST API entry — /api/v1/*
 */
try {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/router.php';
    $container = acep_bootstrap();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => false,
        'data'      => null,
        'error'     => [
            'code'    => 'BOOTSTRAP_ERROR',
            'message' => $e->getMessage(),
        ],
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

CorsMiddleware::apply();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($uri, PHP_URL_PATH) ?: '/';
$uri = preg_replace('#^/api/v1#', '', $uri) ?? $uri;
$uri = preg_replace('#/index\.php#', '', $uri) ?? $uri;
$uri = rtrim($uri, '/') ?: '/';

try {
    acep_route($method, $uri, $container);
} catch (Throwable $e) {
    if ($e instanceof AcepHttpResponse) {
        throw $e;
    }
    log_api_error($e);
    acep_error('MSG_SEND_FAILED', '서버 오류가 발생했습니다.', 500);
}
