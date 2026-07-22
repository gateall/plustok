<?php
declare(strict_types=1);
/**
 * GET /api/v1/health.php — 작동 확인 (인증 없음). API.md 3.
 */

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';

$dbOk = false;
try {
    db()->query('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
}

json_success([
    'status' => 'ok',
    'time'   => date('c'),
    'db'     => $dbOk,
]);
