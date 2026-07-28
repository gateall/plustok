<?php
declare(strict_types=1);
/**
 * AI 멀티 프로바이더 & 자동 Failover 다중 전환 설정 화면 — V2.0
 * 대상: Anthropic, OpenAI, Google Gemini, Grok(xAI), DeepSeek + AUTO 모드
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';
require_login();
require_role(['super', 'admin']);

$pdo = db();

// ── Anthropic 허용 모델 목록 (고정 드롭다운) ──
$ANTHROPIC_MODELS = [
    'claude-opus-4-8'  => 'Claude Opus 4 (8K) — 최고 품질',
    'claude-sonnet-5'  => 'Claude Sonnet 5 — 균형',
    'claude-haiku-4-5' => 'Claude Haiku 4.5 — 빠름/저렴',
];

// 허용되는 5대 벤더 목록
$ALLOWED_PROVIDERS = ['anthropic', 'openai', 'gemini', 'grok', 'deepseek'];
$ALLOWED_ACTIVE    = ['anthropic', 'openai', 'gemini', 'grok', 'deepseek', 'auto'];

// ── AJAX 1: 활성 프로바이더/모드 연결 테스트 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'test') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    $gCfg = ai_config(true);
    if (empty($gCfg['enabled'])) {
        echo json_encode(['ok' => false, 'error' => 'AI 전역 킬스위치가 OFF 상태입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($gCfg['provider'] !== 'auto' && empty($gCfg['api_key'])) {
        echo json_encode(['ok' => false, 'error' => '현재 활성 프로바이더(' . strtoupper($gCfg['provider'] ?? 'unknown') . ')에 API 키가 설정되지 않았습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $start = microtime(true);
    $res = ai_call('당신은 테스트 봇입니다.', "'ok'라고만 답하세요.", [
        'max_tokens' => 15,
        'feature'    => 'active_connection_test',
        'target_id'  => 0,
    ]);
    $durMs = (int)round((microtime(true) - $start) * 1000);

    echo json_encode([
        'ok'       => $res['ok'],
        'provider' => $gCfg['provider'] ?? '',
        'model'    => $gCfg['model'] ?? ($gCfg['provider'] === 'auto' ? 'Auto Failover Chain' : ''),
        'duration' => $durMs,
        'error'    => $res['error'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX 2: 특정 프로바이더 단독 연결 테스트 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'test_provider') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    $prov = strtolower(trim($_POST['provider'] ?? ''));
    if (!in_array($prov, $ALLOWED_PROVIDERS, true)) {
        echo json_encode(['ok' => false, 'error' => '유효하지 않은 프로바이더입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pCfg = ai_config_for_provider($prov);
    if (empty($pCfg['api_key'])) {
        echo json_encode(['ok' => false, 'error' => '[' . strtoupper($prov) . ']에 저장된 API 키가 없습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $start = microtime(true);
    $res = ai_call_single_provider($prov, '당신은 테스트 봇입니다.', "'ok'라고만 답하세요.", [
        'max_tokens' => 15,
        'feature'    => 'provider_connection_test',
        'target_id'  => 0,
    ], $pCfg);
    $durMs = (int)round((microtime(true) - $start) * 1000);

    echo json_encode([
        'ok'       => $res['ok'],
        'provider' => $prov,
        'model'    => $pCfg['model'] ?? $prov,
        'duration' => $durMs,
        'error'    => $res['error'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX 3: 5대 프로바이더 전체 동시/순회 테스트 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'test_all') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    $results = [];
    foreach ($ALLOWED_PROVIDERS as $prov) {
        $pCfg = ai_config_for_provider($prov);
        if (empty($pCfg['api_key'])) {
            $results[$prov] = [
                'ok'       => false,
                'provider' => $prov,
                'model'    => $pCfg['model'] ?? '',
                'duration' => 0,
                'error'    => 'API Key 미설정',
            ];
            continue;
        }
        $start = microtime(true);
        $res = ai_call_single_provider($prov, '당신은 테스트 봇입니다.', "'ok'라고만 답하세요.", [
            'max_tokens' => 15,
            'feature'    => 'test_all',
            'target_id'  => 0,
        ], $pCfg);
        $durMs = (int)round((microtime(true) - $start) * 1000);

        $results[$prov] = [
            'ok'       => $res['ok'],
            'provider' => $prov,
            'model'    => $pCfg['model'] ?? $prov,
            'duration' => $durMs,
            'error'    => $res['error'] ?? null,
        ];
    }
    echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX 4: 라디오 선택 시 전역 활성 프로바이더 즉시 변경 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'set_active_provider') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    $provider = strtolower(trim($_POST['provider'] ?? ''));
    if (!in_array($provider, $ALLOWED_ACTIVE, true)) {
        echo json_encode(['ok' => false, 'error' => '유효하지 않은 프로바이더입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pdo->prepare('INSERT INTO ai_provider_config (id, active_provider, enabled) VALUES (1, :ap, 1)
                   ON DUPLICATE KEY UPDATE active_provider = VALUES(active_provider)')
        ->execute([':ap' => $provider]);
    log_activity('ai_settings_update', 'ai_provider_config', "active_provider set to {$provider}");
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX 5: 개별 프로바이더 키/모델 저장 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_provider') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    $provider = strtolower(trim($_POST['provider'] ?? ''));
    if (!in_array($provider, $ALLOWED_PROVIDERS, true)) {
        echo json_encode(['ok' => false, 'error' => '유효하지 않은 프로바이더입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $apiKey = trim($_POST['api_key'] ?? '');
    $model  = trim($_POST['model'] ?? '');
    if ($provider === 'anthropic' && !array_key_exists($model, $ANTHROPIC_MODELS)) {
        $model = 'claude-opus-4-8';
    }
    if ($provider === 'openai' && $model === '')   $model = 'gpt-4o';
    if ($provider === 'gemini' && $model === '')   $model = 'gemini-flash-lite-latest';
    if ($provider === 'grok' && $model === '')     $model = 'grok-2-latest';
    if ($provider === 'deepseek' && $model === '') $model = 'deepseek-chat';

    $mgrId = current_manager()['id'] ?? null;
    if ($apiKey !== '') {
        $pdo->prepare('INSERT INTO ai_settings (provider, api_key, model, updated_by, updated_at) VALUES (:p, :k, :m, :u, NOW())
                       ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), model = VALUES(model), updated_by = VALUES(updated_by), updated_at = NOW()')
            ->execute([':p' => $provider, ':k' => $apiKey, ':m' => $model, ':u' => $mgrId]);
    } else {
        $pdo->prepare('INSERT INTO ai_settings (provider, model, updated_by, updated_at) VALUES (:p, :m, :u, NOW())
                       ON DUPLICATE KEY UPDATE model = VALUES(model), updated_by = VALUES(updated_by), updated_at = NOW()')
            ->execute([':p' => $provider, ':m' => $model, ':u' => $mgrId]);
    }
    if (($_POST['is_active'] ?? '') === '1') {
        $pdo->prepare('INSERT INTO ai_provider_config (id, active_provider, enabled) VALUES (1, :ap, 1)
                       ON DUPLICATE KEY UPDATE active_provider = VALUES(active_provider)')
            ->execute([':ap' => $provider]);
    }
    log_activity('ai_settings_update', 'ai_settings', "provider={$provider}, action=save_provider");
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX 6: 개별 프로바이더 API 키 삭제 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete_key') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    $provider = strtolower(trim($_POST['provider'] ?? ''));
    if (!in_array($provider, $ALLOWED_PROVIDERS, true)) {
        echo json_encode(['ok' => false, 'error' => '유효하지 않은 프로바이더입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pdo->prepare('UPDATE ai_settings SET api_key = NULL, updated_by = :uid, updated_at = NOW() WHERE provider = :p')
        ->execute([':uid' => current_manager()['id'] ?? null, ':p' => $provider]);
    log_activity('ai_settings_update', 'ai_settings', "provider={$provider}, action=delete_key");
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}


// ── POST: 전체 설정 일괄 저장 ──
$msg = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    csrf_check();

    $enabled        = (int)(!empty($_POST['enabled']));
    $activeProvider = strtolower(trim($_POST['active_provider'] ?? 'anthropic'));
    if (!in_array($activeProvider, $ALLOWED_ACTIVE, true)) {
        $activeProvider = 'anthropic';
    }

    $mgrId = current_manager()['id'] ?? null;

    // 1) 전역 활성 프로바이더 및 킬스위치 UPSERT
    $pdo->prepare('INSERT INTO ai_provider_config (id, active_provider, enabled) VALUES (1, :ap, :en)
                   ON DUPLICATE KEY UPDATE active_provider = VALUES(active_provider), enabled = VALUES(enabled)')
        ->execute([':ap' => $activeProvider, ':en' => $enabled]);

    // 2) 프로바이더별 키 및 모델 저장
    $providersInput = [
        'anthropic' => [
            'key'   => trim($_POST['api_key_anthropic'] ?? ''),
            'model' => $_POST['model_anthropic'] ?? 'claude-opus-4-8',
        ],
        'openai' => [
            'key'   => trim($_POST['api_key_openai'] ?? ''),
            'model' => trim($_POST['model_openai'] ?? 'gpt-4o'),
        ],
        'gemini' => [
            'key'   => trim($_POST['api_key_gemini'] ?? ''),
            'model' => trim($_POST['model_gemini'] ?? 'gemini-flash-lite-latest'),
        ],
        'grok' => [
            'key'   => trim($_POST['api_key_grok'] ?? ''),
            'model' => trim($_POST['model_grok'] ?? 'grok-2-latest'),
        ],
        'deepseek' => [
            'key'   => trim($_POST['api_key_deepseek'] ?? ''),
            'model' => trim($_POST['model_deepseek'] ?? 'deepseek-chat'),
        ],
    ];

    if (!array_key_exists($providersInput['anthropic']['model'], $ANTHROPIC_MODELS)) {
        $providersInput['anthropic']['model'] = 'claude-opus-4-8';
    }
    if ($providersInput['openai']['model'] === '')   $providersInput['openai']['model'] = 'gpt-4o';
    if ($providersInput['gemini']['model'] === '')   $providersInput['gemini']['model'] = 'gemini-flash-lite-latest';
    if ($providersInput['grok']['model'] === '')     $providersInput['grok']['model'] = 'grok-2-latest';
    if ($providersInput['deepseek']['model'] === '') $providersInput['deepseek']['model'] = 'deepseek-chat';

    foreach ($providersInput as $prov => $pdata) {
        if ($pdata['key'] !== '') {
            $pdo->prepare('INSERT INTO ai_settings (provider, api_key, model, updated_by, updated_at) VALUES (:p, :k, :m, :u, NOW())
                           ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), model = VALUES(model), updated_by = VALUES(updated_by), updated_at = NOW()')
                ->execute([':p' => $prov, ':k' => $pdata['key'], ':m' => $pdata['model'], ':u' => $mgrId]);
        } else {
            $pdo->prepare('INSERT INTO ai_settings (provider, model, updated_by, updated_at) VALUES (:p, :m, :u, NOW())
                           ON DUPLICATE KEY UPDATE model = VALUES(model), updated_by = VALUES(updated_by), updated_at = NOW()')
                ->execute([':p' => $prov, ':m' => $pdata['model'], ':u' => $mgrId]);
        }
    }

    log_activity('ai_settings_update', 'ai_provider_config', "active={$activeProvider}, enabled={$enabled}");

    $msg = 'AI V2.0 멀티 프로바이더 및 Auto Failover 설정이 일괄 저장되었습니다.';
    $msgType = 'success';
}

// ── 현재 설정 로드 ──
$gRow = null;
try {
    $gRow = $pdo->query('SELECT active_provider, enabled FROM ai_provider_config WHERE id = 1')->fetch();
} catch (Throwable $e) {}

$currentEnabled        = $gRow ? (bool)$gRow['enabled'] : false;
$currentActiveProvider = strtolower((string)($gRow['active_provider'] ?? 'anthropic'));

$settingsRows = [];
try {
    $stmt = $pdo->query('SELECT * FROM ai_settings');
    while ($r = $stmt->fetch()) {
        $settingsRows[$r['provider']] = $r;
    }
} catch (Throwable $e) {}

// 마스킹 헬퍼
function mask_api_key(?string $key): string {
    if ($key === null || $key === '') return '';
    $len = strlen($key);
    if ($len > 10) {
        return substr($key, 0, 6) . str_repeat('*', min(12, $len - 10)) . substr($key, -4);
    }
    return '****' . substr($key, -2);
}

// 5대 프로바이더 데이터 구성
$provData = [
    'anthropic' => [
        'name'      => 'Anthropic Claude',
        'badge'     => '#2b6cb0',
        'desc'      => '최고의 품질과 논리 추론 능력을 자랑하는 언어모델',
        'key'       => $settingsRows['anthropic']['api_key'] ?? '',
        'masked'    => mask_api_key($settingsRows['anthropic']['api_key'] ?? ''),
        'model'     => $settingsRows['anthropic']['model'] ?? 'claude-opus-4-8',
        'updated_at'=> $settingsRows['anthropic']['updated_at'] ?? null,
    ],
    'openai' => [
        'name'      => 'OpenAI (GPT)',
        'badge'     => '#2f855a',
        'desc'      => '전 세계 표준 생태계 및 탁월한 속도/정확성',
        'key'       => $settingsRows['openai']['api_key'] ?? '',
        'masked'    => mask_api_key($settingsRows['openai']['api_key'] ?? ''),
        'model'     => $settingsRows['openai']['model'] ?? 'gpt-4o',
        'updated_at'=> $settingsRows['openai']['updated_at'] ?? null,
    ],
    'gemini' => [
        'name'      => 'Google Gemini',
        'badge'     => '#c05621',
        'desc'      => '압도적 초고속 응답 속도 및 대용량 텍스트 처리 능력',
        'key'       => $settingsRows['gemini']['api_key'] ?? '',
        'masked'    => mask_api_key($settingsRows['gemini']['api_key'] ?? ''),
        'model'     => (in_array(($settingsRows['gemini']['model'] ?? ''), ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-flash-latest', ''], true)) ? 'gemini-flash-lite-latest' : $settingsRows['gemini']['model'],
        'updated_at'=> $settingsRows['gemini']['updated_at'] ?? null,
    ],
    'grok' => [
        'name'      => 'Grok (xAI)',
        'badge'     => '#1a202c',
        'desc'      => '일론 머스크의 xAI가 개발한 최신 실시간 고성능 AI',
        'key'       => $settingsRows['grok']['api_key'] ?? '',
        'masked'    => mask_api_key($settingsRows['grok']['api_key'] ?? ''),
        'model'     => $settingsRows['grok']['model'] ?? 'grok-2-latest',
        'updated_at'=> $settingsRows['grok']['updated_at'] ?? null,
    ],
    'deepseek' => [
        'name'      => 'DeepSeek',
        'badge'     => '#3182ce',
        'desc'      => '놀라운 추론 성능 대비 최고의 경제성(가성비) 제공',
        'key'       => $settingsRows['deepseek']['api_key'] ?? '',
        'masked'    => mask_api_key($settingsRows['deepseek']['api_key'] ?? ''),
        'model'     => $settingsRows['deepseek']['model'] ?? 'deepseek-chat',
        'updated_at'=> $settingsRows['deepseek']['updated_at'] ?? null,
    ],
];

// 통계 로드 (이번달 총 사용량 및 벤더별 통계)
$statsTotal = ['calls' => 0, 'in_tokens' => 0, 'out_tokens' => 0, 'avg_dur' => 0];
$statsProv  = [];
try {
    $rTotal = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(input_tokens),0) AS it, COALESCE(SUM(output_tokens),0) AS ot, COALESCE(AVG(duration_ms),0) AS dur FROM ai_logs WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetch();
    if ($rTotal) {
        $statsTotal['calls']      = (int)$rTotal['cnt'];
        $statsTotal['in_tokens']  = (int)$rTotal['it'];
        $statsTotal['out_tokens'] = (int)$rTotal['ot'];
        $statsTotal['avg_dur']    = (int)round((float)$rTotal['dur']);
    }
    $rProv = $pdo->query("SELECT provider, COUNT(*) AS cnt, COALESCE(AVG(duration_ms),0) AS dur FROM ai_logs WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY provider")->fetchAll();
    foreach ($rProv as $rp) {
        $statsProv[$rp['provider']] = ['cnt' => (int)$rp['cnt'], 'dur' => (int)round((float)$rp['dur'])];
    }
} catch (Throwable $e) {}

// 최근 Failover 이력 로드
$failoverLogs = [];
try {
    $failoverLogs = $pdo->query("SELECT * FROM ai_failover_log ORDER BY id DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

$page_title = 'AI 멀티 프로바이더 & 자동전환 설정 (V2.0)'; $active = 'settings';
require INC_DIR . '/header.php';
?>
<style>
.provider-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 16px;
  margin-top: 16px;
}
.stat-box {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px 18px;
  flex: 1;
  min-width: 150px;
}
.stat-num {
  font-size: 22px;
  font-weight: 800;
  color: #1a202c;
  margin-top: 4px;
}
.auto-card {
  background: linear-gradient(135deg, #2b6cb0 0%, #3182ce 50%, #4c51bf 100%);
  color: #fff;
  border-radius: 12px;
  padding: 20px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(43, 108, 176, 0.25);
  margin-bottom: 20px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.auto-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(43, 108, 176, 0.35);
}
.table-failover th, .table-failover td {
  padding: 10px 12px;
  font-size: 13px;
  border-bottom: 1px solid #edf2f7;
  text-align: left;
}
</style>

  <h1 class="page">🤖 AI 멀티 프로바이더 & 설정 자동 전환 설정 (V2.0)</h1>
  
  <div style="background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; padding:16px; border-radius:8px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h2 style="margin:0 0 4px 0; font-size:16px;">✨ React 통합 관리자 이전 안내</h2>
      <p style="margin:0; font-size:14px; color:#4338ca;">이 AI 설정 화면은 새로운 React 기반 관리자 페이지로 기능이 모두 이전되었습니다.</p>
    </div>
    <a href="/frontend/#/admin/settings" style="background:#4f46e5; color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">새로운 관리자 설정으로 이동</a>
  </div>

  <?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'ok' : 'err' ?>" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:<?= $msgType === 'success' ? '#f0fff4' : '#fff5f5' ?>;border:1px solid <?= $msgType === 'success' ? '#c6f6d5' : '#fed7d7' ?>;color:<?= $msgType === 'success' ? '#276749' : '#c53030' ?>">
  <?= e($msg) ?>
</div>
<?php endif; ?>

<!-- ── 상단 대시보드 및 통계 ── -->
<div class="card" style="margin-bottom:20px;background:#fff">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <div>
      <h3 style="margin:0;font-size:18px;display:flex;align-items:center;gap:8px">
        <span>📊 이번 달 AI 운영 현황 및 토큰 사용 대시보드</span>
        <?php if ($currentActiveProvider === 'auto'): ?>
          <span style="font-size:13px;padding:3px 10px;background:#ebf8ff;color:#2b6cb0;border-radius:12px;font-weight:700">⚡ Auto Failover 무정지 전환 모드 가동 중</span>
        <?php else: ?>
          <span style="font-size:13px;padding:3px 10px;background:#edf2f7;color:#4a5568;border-radius:12px;font-weight:700">📌 단일 모드: <?= strtoupper($currentActiveProvider) ?> 고정 중</span>
        <?php endif; ?>
      </h3>
      <p class="muted" style="font-size:13px;margin:4px 0 0">어떤 AI 서버에 503 과부하나 장애가 발생해도 서비스가 절대 멈추지 않고 다음 AI로 즉시 자동 우회합니다.</p>
    </div>
    <div>
      <button type="button" id="btn-test-all" style="padding:10px 20px;font-size:14px;font-weight:700;background:#4a5568;color:#fff;border:0;border-radius:6px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background 0.2s">
        🚀 5대 AI 프로바이더 전체 동시 연결 테스트
      </button>
    </div>
  </div>

  <!-- 통계 4박스 -->
  <div style="display:flex;flex-wrap:wrap;gap:12px">
    <div class="stat-box">
      <div class="muted" style="font-size:13px;font-weight:600">이번 달 총 AI 호출 수</div>
      <div class="stat-num"><?= number_format($statsTotal['calls']) ?> <span style="font-size:14px;font-weight:500;color:#718096">회</span></div>
    </div>
    <div class="stat-box">
      <div class="muted" style="font-size:13px;font-weight:600">총 입력 토큰 (Prompt)</div>
      <div class="stat-num"><?= number_format($statsTotal['in_tokens']) ?> <span style="font-size:14px;font-weight:500;color:#718096">Tokens</span></div>
    </div>
    <div class="stat-box">
      <div class="muted" style="font-size:13px;font-weight:600">총 출력 토큰 (Output)</div>
      <div class="stat-num"><?= number_format($statsTotal['out_tokens']) ?> <span style="font-size:14px;font-weight:500;color:#718096">Tokens</span></div>
    </div>
    <div class="stat-box">
      <div class="muted" style="font-size:13px;font-weight:600">평균 응답 속도 (Latency)</div>
      <div class="stat-num"><?= number_format($statsTotal['avg_dur']) ?> <span style="font-size:14px;font-weight:500;color:#718096">ms</span></div>
    </div>
  </div>

  <!-- 전체 테스트 실시간 결과 Grid -->
  <div id="test-all-result" style="margin-top:16px;display:none">
    <h4 style="margin:0 0 10px;font-size:15px;color:#2d3748">⚡ 5대 프로바이더 전체 연결 및 응답속도 진단 결과</h4>
    <div id="test-all-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:10px"></div>
  </div>
</div>

<form method="post" id="ai-settings-form">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="save">

  <!-- 전역 킬스위치 + 현재 활성 테스트 -->
  <div class="card" style="margin-bottom:20px;border-left:4px solid #3182ce">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="enabled" value="1" <?= $currentEnabled ? 'checked' : '' ?> style="width:22px;height:22px">
          <span style="font-size:17px;font-weight:800;color:#1a202c">AI 기능 전역 활성화 (마스터 킬스위치)</span>
        </label>
        <p class="muted" style="font-size:13px;margin:4px 0 0 32px">체크를 해제하면 하단 설정과 무관하게 시스템 전체의 모든 AI 상담분석, 요약, 초안 생성이 즉시 중지됩니다.</p>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" style="padding:11px 26px;font-size:15px;font-weight:700;background:#2b6cb0;color:#fff;border:0;border-radius:8px;cursor:pointer;box-shadow:0 2px 6px rgba(43,108,176,0.3)">
          💾 전체 설정 일괄 저장
        </button>
        <button type="button" id="btn-test-active" style="padding:11px 22px;font-size:15px;font-weight:700;background:#38a169;color:#fff;border:0;border-radius:8px;cursor:pointer">
          🔗 현재 활성 모드 통신 테스트
        </button>
      </div>
    </div>
    <div id="test-active-result" style="margin-top:14px;display:none;padding:12px 16px;border-radius:6px;font-size:14px;font-weight:600"></div>
  </div>

  <!-- ── ⚡ 특별 카드: AUTO FAILOVER 모드 선택 ── -->
  <div class="auto-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div style="flex:1;min-width:280px">
        <label style="display:flex;align-items:center;gap:12px;cursor:pointer">
          <input type="radio" name="active_provider" value="auto" <?= $currentActiveProvider === 'auto' ? 'checked' : '' ?> style="width:22px;height:22px;accent-color:#fff">
          <span style="font-size:20px;font-weight:900;letter-spacing:-0.5px">⚡ AUTO : 다중 AI 자동전환 (Auto Failover Chain)</span>
          <span class="badge-active badge-active-auto" style="display:<?= $currentActiveProvider === 'auto' ? 'inline-block' : 'none' ?>;font-size:13px;padding:3px 12px;background:#fff;color:#2b6cb0;border-radius:20px;font-weight:800;box-shadow:0 2px 8px rgba(0,0,0,0.15)">
            👑 현재 활성 중 (무정지 가동)
          </span>
        </label>
        <p style="margin:8px 0 0 34px;font-size:14px;opacity:0.95;line-height:1.6">
          이 모드를 선택하면 API 과부하나 장애 발생 시 <strong>[ Claude ➔ OpenAI ➔ Gemini ➔ Grok ➔ DeepSeek ]</strong> 순서로 실시간 밀리초 단위로 자동 우회 호출합니다.<br>
          아래 5개 벤더 중 API Key가 입력된 프로바이더들만 지능적으로 순회하여 관리자님의 상담 분석 업무가 절대 끊기지 않습니다.
        </p>
      </div>
      <div style="background:rgba(255,255,255,0.15);padding:12px 18px;border-radius:8px;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.25)">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;font-weight:700;opacity:0.9">Failover Priority Chain</div>
        <div style="font-size:14px;font-weight:800;margin-top:4px">1. Claude ➔ 2. OpenAI ➔ 3. Gemini<br>➔ 4. Grok ➔ 5. DeepSeek</div>
      </div>
    </div>
  </div>

  <!-- ── 5대 프로바이더 개별 설정 카드 Grid ── -->
  <h3 style="margin:24px 0 8px;font-size:17px;color:#2d3748">🧩 개별 AI 프로바이더 API Key 및 모델 관리 (5대 벤더 지원)</h3>
  <p class="muted" style="margin:0 0 16px;font-size:13.5px">
    • 특정 벤더 하나만 고정해서 쓰고 싶으시면 해당 카드의 라디오 버튼(단일 모드 선택)을 체크하세요.<br>
    • API Key를 복사하여 붙여넣고 오른쪽 눈 모양(👁️) 버튼을 눌러 언제든지 안전하게 확인할 수 있습니다.
  </p>

  <div class="provider-grid">
    <?php foreach ($provData as $pKey => $pInfo): 
        $isActive = ($currentActiveProvider === $pKey);
        $hasKey   = ($pInfo['masked'] !== '');
    ?>
    <div class="card provider-card" data-provider="<?= e($pKey) ?>" data-badge="<?= e($pInfo['badge']) ?>" data-haskey="<?= $hasKey ? '1' : '0' ?>" style="border:2px solid <?= $isActive ? ($hasKey ? $pInfo['badge'] : '#e53e3e') : '#e2e8f0' ?>;border-radius:10px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;transition:all 0.2s ease;background:#fff">
      <div>
        <!-- 상단 헤더 및 단일 모드 선택 -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #edf2f7">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="radio" name="active_provider" value="<?= e($pKey) ?>" <?= $isActive ? 'checked' : '' ?> style="width:18px;height:18px">
            <span style="font-size:16px;font-weight:800;color:<?= $pInfo['badge'] ?>"><?= e($pInfo['name']) ?></span>
            <span class="badge-active badge-active-<?= e($pKey) ?>" style="display:<?= $isActive ? 'inline-block' : 'none' ?>;font-size:11.5px;padding:2px 8px;background:<?= $hasKey ? $pInfo['badge'] : '#e53e3e' ?>;color:#fff;border-radius:12px;font-weight:700">
              <?= $hasKey ? '🟢 단일 활성 중' : '⚠️ 키 미설정' ?>
            </span>
          </label>
          <?php if ($pInfo['updated_at']): ?>
            <span class="muted" style="font-size:11px">최근: <?= substr(e($pInfo['updated_at']), 0, 10) ?></span>
          <?php endif; ?>
        </div>
        <p class="muted" style="font-size:12.5px;margin:0 0 14px;min-height:36px;line-height:1.4"><?= e($pInfo['desc']) ?></p>

        <!-- API Key 입력 및 눈 표시 토글 -->
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
            <label style="font-weight:700;font-size:13.5px;color:#2d3748">API Key</label>
            <?php if ($pInfo['masked']): ?>
              <span class="muted" style="font-size:12px;color:#2f855a;font-weight:600">✅ 키 저장됨 (<?= e($pInfo['masked']) ?>)</span>
            <?php else: ?>
              <span style="font-size:12px;color:#e53e3e;font-weight:600">❌ 미등록</span>
            <?php endif; ?>
          </div>
          <div style="position:relative;display:flex;align-items:center">
            <input type="password" name="api_key_<?= e($pKey) ?>" class="input-apikey-<?= e($pKey) ?>" value="" placeholder="<?= $pInfo['masked'] ? '새 키 입력 시에만 변경 (비워두면 기존 키 유지)' : 'API 키를 붙여넣으세요' ?>"
                   autocomplete="off" spellcheck="false"
                   style="width:100%;padding:10px 40px 10px 10px;font-size:13.5px;border:1px solid #cbd5e0;border-radius:6px;font-family:monospace;transition:border 0.2s">
            <button type="button" class="btn-toggle-eye" data-target="api_key_<?= e($pKey) ?>" title="키 확인 (비밀번호 표시/숨김 토글)"
                    style="position:absolute;right:6px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:4px;font-size:15px;cursor:pointer;padding:4px 6px;line-height:1">
              👁️
            </button>
          </div>
        </div>

        <!-- 모델 선택/자유 입력 -->
        <div style="margin-bottom:16px">
          <label style="display:block;font-weight:700;font-size:13.5px;color:#2d3748;margin-bottom:4px">사용 모델명</label>
          <?php if ($pKey === 'anthropic'): ?>
            <select name="model_anthropic" class="input-model-<?= e($pKey) ?>" style="width:100%;padding:9px;font-size:13.5px;border:1px solid #cbd5e0;border-radius:6px;background:#fff">
              <?php foreach ($ANTHROPIC_MODELS as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $pInfo['model'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" name="model_<?= e($pKey) ?>" class="input-model-<?= e($pKey) ?>" value="<?= e($pInfo['model']) ?>"
                   placeholder="예: <?= $pKey==='openai' ? 'gpt-4o' : ($pKey==='gemini' ? 'gemini-flash-lite-latest' : ($pKey==='grok' ? 'grok-2-latest' : 'deepseek-chat')) ?>"
                   style="width:100%;padding:9px;font-size:13.5px;border:1px solid #cbd5e0;border-radius:6px">
          <?php endif; ?>
        </div>
      </div>

      <!-- 하단 액션 버튼 바 -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px dashed #edf2f7;gap:6px">
        <div>
          <?php if ($pInfo['masked']): ?>
            <button type="button" class="btn-delete-key" data-provider="<?= e($pKey) ?>" data-name="<?= e($pInfo['name']) ?>"
                    style="padding:7px 11px;font-size:12px;font-weight:700;background:#fff5f5;color:#e53e3e;border:1px solid #feb2b2;border-radius:6px;cursor:pointer">
              🗑️ 삭제
            </button>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:6px">
          <button type="button" class="btn-test-single" data-provider="<?= e($pKey) ?>" data-name="<?= e($pInfo['name']) ?>"
                  style="padding:7px 12px;font-size:12px;font-weight:700;background:#edf2f7;color:#2d3748;border:1px solid #cbd5e0;border-radius:6px;cursor:pointer">
            🔗 연결 테스트
          </button>
          <button type="button" class="btn-save-provider" data-provider="<?= e($pKey) ?>" data-name="<?= e($pInfo['name']) ?>"
                  style="padding:7px 14px;font-size:12px;font-weight:700;background:<?= $pInfo['badge'] ?>;color:#fff;border:0;border-radius:6px;cursor:pointer">
            💾 저장
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:24px;display:flex;justify-content:flex-end">
    <button type="submit" style="padding:14px 40px;font-size:16px;font-weight:800;background:#2b6cb0;color:#fff;border:0;border-radius:8px;cursor:pointer;box-shadow:0 4px 12px rgba(43,108,176,0.35)">
      💾 AI 멀티 프로바이더 설정 전체 저장
    </button>
  </div>
</form>

<!-- ── 최근 Failover 및 장애 극복 감사 로그 테이블 ── -->
<?php if (!empty($failoverLogs)): ?>
<div class="card" style="margin-top:28px">
  <h3 style="margin:0 0 12px;font-size:16px;color:#2d3748;display:flex;align-items:center;gap:8px">
    <span>🛡️ 최근 실시간 Auto Failover (장애 우회 극복) 감사 기록</span>
    <span style="font-size:12px;background:#c6f6d5;color:#22543d;padding:2px 8px;border-radius:12px;font-weight:700">무정지 보호 완수</span>
  </h3>
  <div style="overflow-x:auto">
    <table class="table-failover" style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:#f7fafc">
          <th>발생 일시</th>
          <th>기능 (Feature)</th>
          <th>실패 프로바이더/모델</th>
          <th>장애 원인 (Error)</th>
          <th>전환(우회) 성공 프로바이더/모델</th>
          <th>소요 속도</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($failoverLogs as $fLog): ?>
        <tr>
          <td class="muted" style="font-size:12px"><?= e(substr($fLog['created_at'], 2, 14)) ?></td>
          <td><span style="font-weight:600;color:#2b6cb0"><?= e($fLog['feature']) ?></span></td>
          <td><span style="color:#e53e3e;font-weight:700"><?= strtoupper(e($fLog['failed_provider'])) ?></span> (<?= e($fLog['failed_model']) ?>)</td>
          <td><code style="font-size:11.5px;color:#c53030;background:#fff5f5;padding:2px 6px;border-radius:4px"><?= e($fLog['error_code']) ?>: <?= e(substr($fLog['error_message'] ?? '', 0, 50)) ?></code></td>
          <td><span style="color:#2f855a;font-weight:700">🟢 <?= strtoupper(e($fLog['fallback_provider'])) ?></span> (<?= e($fLog['fallback_model']) ?>)</td>
          <td><?= number_format((int)$fLog['duration_ms']) ?> ms</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── 안내 사항 ── -->
<div class="card" style="margin-top:20px;background:#f7fafc;border:1px solid #e2e8f0">
  <h3 style="margin:0 0 10px;font-size:15px;color:#2d3748">💡 AI 다중 프로바이더(Auto Failover) 고도화 시스템 안내</h3>
  <ul style="font-size:13.5px;color:#4a5568;line-height:1.8;margin:0;padding-left:18px">
    <li><strong>AUTO 모드 (권장):</strong> 관리자님이 상단의 <strong>[⚡ AUTO]</strong> 모드를 선택하시면, 어떤 AI 벤더에서 서버 점검이나 HTTP 503 트래픽 과부하 에러가 나도 에러 팝업을 띄우지 않고 1초 내에 다음 AI로 자동 우회하여 분석을 완료합니다.</li>
    <li><strong>눈 모양(👁️) 확인 버튼:</strong> API Key 입력칸 오른쪽의 👁️ 버튼을 누르시면 입력해 두셨거나 방금 붙여넣은 API Key를 마스킹 없이 즉시 확인할 수 있습니다.</li>
    <li><strong>독립적 키 보존:</strong> 여러 AI 프로바이더의 키를 동시에 등록해 두시면, 프로바이더 간 전환 시에도 기존 키가 절대 지워지지 않고 안전하게 암호화 보존됩니다.</li>
    <li><strong>자유로운 모델 업데이트:</strong> OpenAI(GPT-4o 등), Gemini, Grok, DeepSeek 등 최신 모델명이 출시될 경우 텍스트 상자에 모델명만 적으면 즉시 적용됩니다.</li>
  </ul>
</div>

<script>
// 1. 현재 활성 모드 연결 테스트
document.getElementById('btn-test-active').addEventListener('click', function() {
  var btn = this;
  var box = document.getElementById('test-active-result');
  btn.disabled = true;
  btn.textContent = '⏳ 통신 진단 중…';
  box.style.display = 'none';

  var fd = new FormData();
  fd.append('_csrf', '<?= csrf_token() ?>');
  fd.append('_action', 'test');

  fetch(location.href, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      box.style.display = 'block';
      if (data.ok) {
        box.style.background = '#f0fff4';
        box.style.border = '1px solid #c6f6d5';
        box.style.color = '#276749';
        box.textContent = '✅ 응답 성공! (' + data.provider.toUpperCase() + ' : ' + data.model + ' / 속도: ' + data.duration + 'ms) — 실시간 통신이 완벽합니다.';
      } else {
        box.style.background = '#fff5f5';
        box.style.border = '1px solid #fed7d7';
        box.style.color = '#c53030';
        box.textContent = '❌ 통신 실패 (' + (data.provider ? data.provider.toUpperCase() : '') + '): ' + (data.error || '알 수 없는 오류');
      }
    })
    .catch(function(err) {
      box.style.display = 'block';
      box.style.background = '#fff5f5';
      box.style.border = '1px solid #fed7d7';
      box.style.color = '#c53030';
      box.textContent = '❌ 네트워크 오류: ' + err.message;
    })
    .finally(function() {
      btn.disabled = false;
      btn.textContent = '🔗 현재 활성 모드 통신 테스트';
    });
});

// 2. 5대 프로바이더 전체 동시 연결 테스트
document.getElementById('btn-test-all').addEventListener('click', function() {
  var btn = this;
  var wrap = document.getElementById('test-all-result');
  var grid = document.getElementById('test-all-grid');
  btn.disabled = true;
  btn.innerHTML = '⏳ 5대 AI 동시 진단 중… (최대 10~15초)';
  wrap.style.display = 'none';
  grid.innerHTML = '';

  var fd = new FormData();
  fd.append('_csrf', '<?= csrf_token() ?>');
  fd.append('_action', 'test_all');

  fetch(location.href, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      wrap.style.display = 'block';
      if (data.ok && data.results) {
        Object.keys(data.results).forEach(function(prov) {
          var res = data.results[prov];
          var div = document.createElement('div');
          div.style.padding = '10px 12px';
          div.style.borderRadius = '6px';
          div.style.fontSize = '13px';
          div.style.fontWeight = '600';
          if (res.ok) {
            div.style.background = '#f0fff4';
            div.style.border = '1px solid #c6f6d5';
            div.style.color = '#276749';
            div.innerHTML = '🟢 <b>' + prov.toUpperCase() + '</b><br><span style="font-size:11.5px;color:#4a5568">' + res.model + ' (' + res.duration + 'ms)</span><br><span style="font-size:12px">✅ 정상 응답</span>';
          } else {
            div.style.background = '#fff5f5';
            div.style.border = '1px solid #fed7d7';
            div.style.color = '#c53030';
            div.innerHTML = '🔴 <b>' + prov.toUpperCase() + '</b><br><span style="font-size:11.5px;color:#4a5568">' + (res.model || '미설정') + '</span><br><span style="font-size:11.5px">❌ ' + (res.error || '실패') + '</span>';
          }
          grid.appendChild(div);
        });
      }
    })
    .catch(function(err) {
      alert('전체 테스트 통신 오류: ' + err.message);
    })
    .finally(function() {
      btn.disabled = false;
      btn.innerHTML = '🚀 5대 AI 프로바이더 전체 동시 연결 테스트';
    });
});

// 3. 개별 프로바이더 단독 연결 테스트
document.querySelectorAll('.btn-test-single').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var provider = this.getAttribute('data-provider');
    var name = this.getAttribute('data-name');
    var origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ 진단…';

    var fd = new FormData();
    fd.append('_csrf', '<?= csrf_token() ?>');
    fd.append('_action', 'test_provider');
    fd.append('provider', provider);

    fetch(location.href, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          alert('✅ [' + name + '] 연결 성공!\n• 모델: ' + data.model + '\n• 응답속도: ' + data.duration + 'ms');
        } else {
          alert('❌ [' + name + '] 연결 실패:\n' + (data.error || '알 수 없는 오류'));
        }
      })
      .catch(function(err) {
        alert('네트워크 오류: ' + err.message);
      })
      .finally(function() {
        btn.disabled = false;
        btn.textContent = origText;
      });
  });
});

// 4. 개별 프로바이더 설정 저장 핸들러
document.querySelectorAll('.btn-save-provider').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var provider = this.getAttribute('data-provider');
    var name = this.getAttribute('data-name');
    var keyInput = document.querySelector('input[name="api_key_' + provider + '"]');
    var modelInput = document.querySelector('.input-model-' + provider);
    
    var fd = new FormData();
    fd.append('_csrf', '<?= csrf_token() ?>');
    fd.append('_action', 'save_provider');
    fd.append('provider', provider);
    fd.append('api_key', keyInput ? keyInput.value : '');
    fd.append('model', modelInput ? modelInput.value : '');
    var radioInput = document.querySelector('input[name="active_provider"][value="' + provider + '"]');
    if (radioInput && radioInput.checked) {
      fd.append('is_active', '1');
    }

    btn.disabled = true;
    var origText = btn.textContent;
    btn.textContent = '⏳ 저장 중…';

    fetch(location.href, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          alert('[' + name + '] 설정이 성공적으로 저장되었습니다.');
          location.reload();
        } else {
          alert('저장 실패: ' + (data.error || '알 수 없는 오류'));
          btn.disabled = false;
          btn.textContent = origText;
        }
      })
      .catch(function(err) {
        alert('네트워크 오류: ' + err.message);
        btn.disabled = false;
        btn.textContent = origText;
      });
  });
});

// 5. 개별 프로바이더 키 삭제 버튼 핸들러
document.querySelectorAll('.btn-delete-key').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var provider = this.getAttribute('data-provider');
    var name = this.getAttribute('data-name');
    if (!confirm('정말 [' + name + ']의 저장된 API 키를 완전히 삭제(초기화)하시겠습니까?\n삭제 후에는 해당 프로바이더로 AI 기능을 호출할 수 없습니다.')) {
      return;
    }
    btn.disabled = true;
    var origText = btn.textContent;
    btn.textContent = '⏳ 삭제 중…';

    var fd = new FormData();
    fd.append('_csrf', '<?= csrf_token() ?>');
    fd.append('_action', 'delete_key');
    fd.append('provider', provider);

    fetch(location.href, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          alert('[' + name + '] API 키가 성공적으로 삭제되었습니다.');
          location.reload();
        } else {
          alert('키 삭제 실패: ' + (data.error || '알 수 없는 오류'));
          btn.disabled = false;
          btn.textContent = origText;
        }
      })
      .catch(function(err) {
        alert('네트워크 오류: ' + err.message);
        btn.disabled = false;
        btn.textContent = origText;
      });
  });
});

// 6. API Key 눈 모양(👁️) 표시/숨김 토글 핸들러
document.querySelectorAll('.btn-toggle-eye').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var targetName = this.getAttribute('data-target');
    var input = document.querySelector('input[name="' + targetName + '"]');
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      this.textContent = '🔒';
      this.title = '비밀번호 숨기기';
    } else {
      input.type = 'password';
      this.textContent = '👁️';
      this.title = '비밀번호 표시 (키 확인)';
    }
  });
});

// 7. 라디오 버튼 선택 시 실시간 테두리/뱃지 이동 및 DB 즉시 반영 (AJAX)
document.querySelectorAll('input[name="active_provider"]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    var selectedProv = this.value;
    
    // Auto 카드 뱃지 처리
    var autoBadge = document.querySelector('.badge-active-auto');
    if (autoBadge) autoBadge.style.display = (selectedProv === 'auto') ? 'inline-block' : 'none';

    document.querySelectorAll('.provider-card').forEach(function(card) {
      var prov = card.getAttribute('data-provider');
      var badgeColor = card.getAttribute('data-badge');
      var hasKey = (card.getAttribute('data-haskey') === '1');
      var badgeEl = card.querySelector('.badge-active-' + prov);
      if (prov === selectedProv) {
        card.style.borderColor = hasKey ? badgeColor : '#e53e3e';
        if (badgeEl) {
          badgeEl.style.display = 'inline-block';
          badgeEl.style.background = hasKey ? badgeColor : '#e53e3e';
          badgeEl.textContent = hasKey ? '🟢 단일 활성 중' : '⚠️ 키 미설정';
        }
      } else {
        card.style.borderColor = '#e2e8f0';
        if (badgeEl) badgeEl.style.display = 'none';
      }
    });

    // 서버 DB에 즉시 active_provider 변경 반영
    var fd = new FormData();
    fd.append('_csrf', '<?= csrf_token() ?>');
    fd.append('_action', 'set_active_provider');
    fd.append('provider', selectedProv);
    fetch(location.href, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok) {
          console.error('활성 프로바이더 자동 변경 실패:', data.error);
        }
      })
      .catch(function(err) {
        console.error('네트워크 오류:', err);
      });
  });
});
</script>

<?php require INC_DIR . '/footer.php'; ?>
