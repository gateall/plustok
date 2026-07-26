<?php
declare(strict_types=1);

if (!defined('ACEP_TESTING')) {
    define('ACEP_TESTING', true);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/AcepHttpResponse.php';
require_once __DIR__ . '/../includes/middleware/JwtMiddleware.php';
