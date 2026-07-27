<?php
declare(strict_types=1);

if (!defined('ACEP_TESTING')) {
    define('ACEP_TESTING', true);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/AcepHttpResponse.php';
require_once __DIR__ . '/../includes/middleware/JwtMiddleware.php';

/**
 * Load CONTRACT_CLEAN/.env.test into getenv() for PHPUnit (local only).
 */
(function (): void {
    $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env.test';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
})();
