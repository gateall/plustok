<?php
declare(strict_types=1);
/**
 * php migrations/seed.php — AI 설정 + admin 계정 시드
 * migrate.php --seed 에서도 호출됨. 단독 실행 가능.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/lib.php';

$pdo = db();
$sql = file_get_contents(__DIR__ . '/V1.5.1__phase1_seed.sql');
acep_run_sql_file($pdo, $sql);

$promptSeed = __DIR__ . '/V1.5.2__ai_prompts_seed.sql';
if (is_file($promptSeed)) {
    acep_run_sql_file($pdo, file_get_contents($promptSeed));
}
$st = $pdo->prepare('SELECT 1 FROM agents WHERE login_id = :lid LIMIT 1');
$st->execute([':lid' => 'admin']);
if (!$st->fetch()) {
    $hash = password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 12]);
    $ins = $pdo->prepare(
        'INSERT INTO agents (id, login_id, password_hash, name, role, status)
         VALUES (:id, :login_id, :hash, :name, :role, :status)'
    );
    $ins->execute([
        ':id'        => '00000000-0000-4000-8000-000000000001',
        ':login_id'  => 'admin',
        ':hash'      => $hash,
        ':name'      => 'ACEP Admin',
        ':role'      => 'admin',
        ':status'    => 'offline',
    ]);
    echo "Admin created: admin / Admin123!\n";
} else {
    echo "Admin already exists.\n";
}

echo "Seed done.\n";
