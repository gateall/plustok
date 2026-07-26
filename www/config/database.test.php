<?php
declare(strict_types=1);
/**
 * PHPUnit 전용 DB — gitignore 대상.
 * acep_test DB 또는 Docker MariaDB(plustok_contract_test) 사용.
 */
return [
    'host'    => getenv('ACEP_TEST_DB_HOST') ?: '127.0.0.1',
    'port'    => (int) (getenv('ACEP_TEST_DB_PORT') ?: '3306'),
    'name'    => getenv('ACEP_TEST_DB_NAME') ?: 'plustok_contract_test',
    'user'    => getenv('ACEP_TEST_DB_USER') ?: 'plustok_test',
    'pass'    => getenv('ACEP_TEST_DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
