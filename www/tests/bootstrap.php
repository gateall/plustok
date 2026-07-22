<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/AcepHttpResponse.php';
require_once __DIR__ . '/../includes/middleware/JwtMiddleware.php';

if (!defined('ACEP_TESTING')) {
    define('ACEP_TESTING', true);
}
