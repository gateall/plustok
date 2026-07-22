<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';

echo "=== [ AI V2.0 System Remote Diagnostics ] ===\n\n";

$pdo = db();

// 1. ai_provider_config 확인
echo "1. Current Global Configuration:\n";
$cfg = $pdo->query('SELECT * FROM ai_provider_config WHERE id = 1')->fetch();
print_r($cfg);
echo "\n";

// 2. ai_settings 5대 벤더 확인
echo "2. Provider Settings in DB:\n";
$stmt = $pdo->query('SELECT provider, model, IF(api_key IS NOT NULL AND api_key != "", "SET(MASKED)", "EMPTY") AS key_status, updated_at FROM ai_settings');
while ($r = $stmt->fetch()) {
    echo " - [{$r['provider']}] Model: {$r['model']} | Key: {$r['key_status']} | Updated: {$r['updated_at']}\n";
}
echo "\n";

// 3. ai_config(true) 반환값 확인
echo "3. Result of ai_config(true):\n";
$ac = ai_config(true);
$ac['api_key'] = !empty($ac['api_key']) ? '****(SET)' : 'EMPTY';
print_r($ac);
echo "\n";

// 4. 단일 프로바이더별 라이브 호출 테스트 (키가 있는 벤더만)
echo "4. Single Provider Call Test (for configured keys):\n";
$providers = ['anthropic', 'openai', 'gemini', 'grok', 'deepseek'];
foreach ($providers as $p) {
    $pCfg = ai_config_for_provider($p);
    if (empty($pCfg['api_key'])) {
        echo " - [{$p}]: SKIPPED (No API Key)\n";
        continue;
    }
    $start = microtime(true);
    $res = ai_call_single_provider($p, "You are test helper.", "Respond with exact word 'OK_V2'.", [
        'max_tokens' => 15,
        'feature'    => 'diag_single_' . $p,
        'target_id'  => 0,
    ], $pCfg);
    $dur = round((microtime(true) - $start) * 1000);
    if ($res['ok']) {
        echo " - [{$p}] ({$pCfg['model']}): SUCCESS! Response='{$res['text']}' ({$dur}ms)\n";
    } else {
        echo " - [{$p}] ({$pCfg['model']}): FAILED! Error='{$res['error']}' ({$dur}ms)\n";
    }
}
echo "\n";

// 5. Auto Failover 모드 시뮬레이션
echo "5. Auto Failover Chain Simulation:\n";
// 임시로 DB의 active_provider를 auto로 변경 후 ai_call 호출, 테스트 끝나면 롤백
$origProvider = $cfg['active_provider'] ?? 'anthropic';
$pdo->prepare("UPDATE ai_provider_config SET active_provider = 'auto' WHERE id = 1")->execute();

$start = microtime(true);
$resAuto = ai_call("You are Auto Failover tester.", "Say 'AUTO_OK'.", [
    'max_tokens' => 15,
    'feature'    => 'diag_auto_failover',
    'target_id'  => 0,
]);
$durAuto = round((microtime(true) - $start) * 1000);

if ($resAuto['ok']) {
    echo " -> Auto Call SUCCESS! Text='{$resAuto['text']}' ({$durAuto}ms)\n";
} else {
    echo " -> Auto Call FAILED! Error='{$resAuto['error']}' ({$durAuto}ms)\n";
}

// 롤백
$pdo->prepare("UPDATE ai_provider_config SET active_provider = :ap WHERE id = 1")->execute([':ap' => $origProvider]);
echo " -> Restored original active_provider to: {$origProvider}\n\n";

// 6. 최근 ai_failover_log 및 ai_logs 확인
echo "6. Recent Logs Check:\n";
echo " [ai_failover_log recent entries]:\n";
$flogs = $pdo->query("SELECT created_at, feature, failed_provider, fallback_provider, error_code FROM ai_failover_log ORDER BY id DESC LIMIT 3")->fetchAll();
foreach ($flogs as $fl) {
    echo " - {$fl['created_at']} | Feature: {$fl['feature']} | Failed: {$fl['failed_provider']} -> Fallback: {$fl['fallback_provider']} ({$fl['error_code']})\n";
}

echo "\n [ai_logs recent 3 entries]:\n";
$alogs = $pdo->query("SELECT created_at, feature, provider, model, status, duration_ms FROM ai_logs ORDER BY id DESC LIMIT 3")->fetchAll();
foreach ($alogs as $al) {
    echo " - {$al['created_at']} | Feature: {$al['feature']} | Provider: {$al['provider']} ({$al['model']}) | Status: {$al['status']} ({$al['duration_ms']}ms)\n";
}

echo "\n=== Diagnostics Completed ===\n";
