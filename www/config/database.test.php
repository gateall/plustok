<?php
declare(strict_types=1);
/**
 * PHPUnit 전용 DB — gitignore 권장.
 * acep_test DB를 로컬 MariaDB/MySQL에 생성 후 사용.
 */
return [
    'host'    => getenv('ACEP_TEST_DB_HOST') ?: '127.0.0.1',
    'name'    => getenv('ACEP_TEST_DB_NAME') ?: 'acep_test',
    'user'    => getenv('ACEP_TEST_DB_USER') ?: 'root',
    'pass'    => getenv('ACEP_TEST_DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
