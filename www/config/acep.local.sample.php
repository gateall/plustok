<?php
declare(strict_types=1);
/**
 * Copy to config/acep.local.php (gitignored) and set production values.
 * Prefer environment variables in deployment (ACEP_JWT_SECRET, ACEP_PII_KEY, ACEP_REDIS_URL).
 */

// define('ACEP_JWT_SECRET', 'generate-with: php -r "echo bin2hex(random_bytes(32));"');
// define('ACEP_PII_KEY', base64_encode(random_bytes(32)));
// define('ACEP_REDIS_URL', 'redis://localhost:6379');
