<?php
declare(strict_types=1);
/**
 * PDO 커넥션 팩토리. 모든 DB 접근은 이 커넥션 + Prepared Statement로만 한다.
 */

require_once __DIR__ . '/../config/app.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = load_db_config();

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['name'],
        $cfg['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/**
 * DB 접속정보 로드.
 *  1순위: config/database.php (있으면 사용 — VPS/별도 DB 이전 시)
 *  2순위: 그누보드 data/dbconfig.php (같은 호스팅·같은 DB 공존 시, 기본 경로)
 * "독립 공존": CRM 전용 테이블을 그누보드와 같은 DB에 두되, 비밀번호는 한 곳(dbconfig)에서만 관리.
 */
function load_db_config(): array
{
    if (defined('ACEP_TESTING') && ACEP_TESTING) {
        $testCfg = __DIR__ . '/../config/database.test.php';
        if (is_file($testCfg)) {
            return require $testCfg;
        }
    }

    $cfgFile = __DIR__ . '/../config/database.php';
    if (is_file($cfgFile)) {
        return require $cfgFile;
    }

    // 그누보드 dbconfig 재사용 (includes/ 기준 ../data/dbconfig.php)
    $gnu = __DIR__ . '/../data/dbconfig.php';
    if (is_file($gnu)) {
        if (!defined('_GNUBOARD_')) {
            define('_GNUBOARD_', true);
        }
        require_once $gnu;
        if (defined('G5_MYSQL_HOST')) {
            return [
                'host'    => G5_MYSQL_HOST,
                'name'    => G5_MYSQL_DB,
                'user'    => G5_MYSQL_USER,
                'pass'    => G5_MYSQL_PASSWORD,
                'charset' => defined('G5_DB_CHARSET') && G5_DB_CHARSET ? G5_DB_CHARSET : 'utf8mb4',
            ];
        }
    }

    throw new RuntimeException('DB 설정을 찾을 수 없습니다. config/database.php를 만들거나 그누보드 data/dbconfig.php 경로를 확인하세요.');
}
