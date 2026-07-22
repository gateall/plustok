<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * AI 설정 로드: DB(ai_settings) 우선, 없으면 config/ai.php 폴백.
 * api_url·api_version·timeout·max_tokens는 파일에서만 로드.
 * @param bool $forceReload  true이면 static 캐시 무시(저장 직후 테스트용)
 */
/**
 * AI 설정 로드: DB(ai_provider_config + ai_settings) 우선, 없으면 config/ai.php 폴백.
 * @param bool $forceReload true이면 static 캐시 무시
 */
function ai_config(bool $forceReload = false): array
{
    static $cfg = null;
    if ($cfg !== null && !$forceReload) return $cfg;

    $fileCfg = require __DIR__ . '/../config/ai.php';
    $cfg = $fileCfg;

    try {
        $pdo = db();
        // 1) 전역 활성 프로바이더 및 enabled 조회
        $gRow = $pdo->query('SELECT active_provider, enabled FROM ai_provider_config WHERE id = 1')->fetch();
        $activeProvider = $gRow['active_provider'] ?? ($fileCfg['provider'] ?? 'anthropic');
        $enabled = (bool)($gRow['enabled'] ?? ($fileCfg['enabled'] ?? false));

        $cfg['provider'] = $activeProvider;
        $cfg['enabled']  = $enabled;

        // activeProvider가 auto가 아니라면 해당 벤더 키/모델 로드
        if ($activeProvider !== 'auto') {
            $stmt = $pdo->prepare('SELECT api_key, model FROM ai_settings WHERE provider = :p LIMIT 1');
            $stmt->execute([':p' => $activeProvider]);
            $pRow = $stmt->fetch();
            if ($pRow) {
                if ($pRow['api_key'] !== null && $pRow['api_key'] !== '') {
                    $cfg['api_key'] = $pRow['api_key'];
                }
                if (!empty($pRow['model'])) {
                    $cfg['model'] = $pRow['model'];
                }
            }
        }
    } catch (Throwable $e) {
        /* DB 오류 시 파일 설정 폴백 */
    }

    return $cfg;
}

/**
 * 특정 프로바이더 설정 단독 조회 (Auto Failover 순회 또는 개별 테스트용)
 */
function ai_config_for_provider(string $provider): array
{
    $fileCfg = require __DIR__ . '/../config/ai.php';
    $cfg = $fileCfg;
    $cfg['provider'] = $provider;

    try {
        $pdo = db();
        $gRow = $pdo->query('SELECT enabled FROM ai_provider_config WHERE id = 1')->fetch();
        $cfg['enabled'] = (bool)($gRow['enabled'] ?? ($fileCfg['enabled'] ?? false));

        $stmt = $pdo->prepare('SELECT api_key, model FROM ai_settings WHERE provider = :p LIMIT 1');
        $stmt->execute([':p' => strtolower($provider)]);
        $pRow = $stmt->fetch();
        if ($pRow) {
            $cfg['api_key'] = $pRow['api_key'] ?? '';
            if (!empty($pRow['model'])) {
                $cfg['model'] = $pRow['model'];
            } else {
                // 벤더별 기본 모델 설정
                $defaults = [
                    'anthropic' => 'claude-opus-4-8',
                    'openai'    => 'gpt-4o',
                    'gemini'    => 'gemini-flash-lite-latest',
                    'grok'      => 'grok-2-latest',
                    'deepseek'  => 'deepseek-chat',
                ];
                $cfg['model'] = $defaults[strtolower($provider)] ?? 'claude-opus-4-8';
            }
        }
    } catch (Throwable $e) {
        $cfg['api_key'] = '';
    }

    return $cfg;
}

/**
 * AI 메인 호출 라우터 (Auto Failover 무정지 전환 엔진 탑재)
 */
function ai_call(string $system, string $user, array $opt = []): array
{
    $gCfg = ai_config();

    if (empty($gCfg['enabled'])) {
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => 'AI 전역 킬스위치가 OFF 상태입니다.', 'usage' => []];
    }

    $activeProvider = strtolower((string)($gCfg['provider'] ?? 'anthropic'));

    // 단일 프로바이더 모드
    if ($activeProvider !== 'auto') {
        if (empty($gCfg['api_key'])) {
            return ['ok' => false, 'text' => '', 'json' => null, 'error' => strtoupper($activeProvider) . ' API KEY가 없습니다.', 'usage' => []];
        }
        return ai_call_single_provider($activeProvider, $system, $user, $opt, $gCfg);
    }

    // Auto Failover 모드! (순서: Claude -> OpenAI -> Gemini -> Grok -> DeepSeek)
    $failoverChain = ['anthropic', 'openai', 'gemini', 'grok', 'deepseek'];
    $failedProvider = '';
    $failedModel    = '';
    $lastError      = '';
    $errorCode      = '';

    foreach ($failoverChain as $prov) {
        $pCfg = ai_config_for_provider($prov);
        if (empty($pCfg['api_key'])) {
            continue; // 키 없는 프로바이더 건너뜀
        }

        $start = microtime(true);
        $res = ai_call_single_provider($prov, $system, $user, $opt, $pCfg);
        $durMs = (int)round((microtime(true) - $start) * 1000);

        if ($res['ok']) {
            // 이전에 실패한 프로바이더가 있었고 현재 성공했다면 장애 극복(failover) 이력 저장
            if ($failedProvider !== '') {
                ai_failover_log_insert(
                    $opt['feature'] ?? 'auto_failover',
                    $opt['target_id'] ?? null,
                    $failedProvider,
                    $failedModel,
                    $errorCode,
                    $lastError,
                    $prov,
                    $pCfg['model'] ?? $prov,
                    $durMs
                );
            }
            return $res;
        }

        // 실패 감지 -> 다음 호환 프로바이더로 폴백 준비
        $failedProvider = $prov;
        $failedModel    = $pCfg['model'] ?? $prov;
        $lastError      = $res['error'] ?? 'Unknown Error';
        if (preg_match('/HTTP\s+([0-9]{3})/', $lastError, $m)) {
            $errorCode = $m[1];
        } elseif (stripos($lastError, 'timeout') !== false) {
            $errorCode = 'TIMEOUT';
        } else {
            $errorCode = 'ERROR';
        }
    }

    return ['ok' => false, 'text' => '', 'json' => null, 'error' => 'Auto Failover 전체 프로바이더 호출 실패 (최종 오류: ' . $lastError . ')', 'usage' => []];
}

/**
 * 단일 프로바이더 실행 헬퍼
 */
function ai_call_single_provider(string $provider, string $system, string $user, array $opt, array $cfg): array
{
    switch (strtolower($provider)) {
        case 'openai':
            return ai_call_openai($system, $user, $opt, $cfg);
        case 'gemini':
            return ai_call_gemini($system, $user, $opt, $cfg);
        case 'grok':
            return ai_call_grok($system, $user, $opt, $cfg);
        case 'deepseek':
            return ai_call_deepseek($system, $user, $opt, $cfg);
        case 'anthropic':
        default:
            return ai_call_anthropic($system, $user, $opt, $cfg);
    }
}

function ai_call_anthropic(string $system, string $user, array $opt, array $cfg): array
{
    $start = microtime(true);
    $body = [
        'model'      => $opt['model']      ?? $cfg['model'],
        'max_tokens' => $opt['max_tokens'] ?? $cfg['max_tokens'],
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ];
    if (!empty($opt['json_schema'])) {
        $body['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $opt['json_schema']]];
    }

    $apiUrl = $cfg['api_url'] ?? 'https://api.anthropic.com/v1/messages';
    $apiVersion = $cfg['api_version'] ?? '2023-06-01';

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int)($cfg['timeout'] ?? 30),
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $cfg['api_key'],
            'anthropic-version: ' . $apiVersion,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    $durMs = (int)round((microtime(true) - $start) * 1000);

    if ($raw === false || $code >= 400) {
        $msg = $cerr ?: ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
        ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'anthropic', $body['model'], 'error', [], $durMs, $msg);
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => $msg, 'usage' => []];
    }

    $data = json_decode((string)$raw, true);
    $text = '';
    foreach (($data['content'] ?? []) as $b) {
        if (($b['type'] ?? '') === 'text') { $text .= $b['text']; }
    }
    $usage = $data['usage'] ?? [];
    $json  = null;
    if (!empty($opt['json_schema'])) { $json = json_decode($text, true); }

    ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'anthropic', $body['model'], 'ok', $usage, $durMs, null);
    return ['ok' => true, 'text' => trim($text), 'json' => $json, 'error' => null, 'usage' => $usage];
}

/**
 * OpenAI Chat Completions API 호출
 */
function ai_call_openai(string $system, string $user, array $opt, array $cfg): array
{
    $start = microtime(true);
    $model = $opt['model'] ?? $cfg['model'] ?: 'gpt-4o';
    $maxTokens = (int)($opt['max_tokens'] ?? $cfg['max_tokens'] ?: 1024);

    if (!empty($opt['json_schema'])) {
        $system .= "\n\nYou must respond strictly in valid JSON format.";
    }

    $body = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user]
        ],
    ];
    if (!empty($opt['json_schema'])) {
        $body['response_format'] = ['type' => 'json_object'];
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int)($cfg['timeout'] ?? 30),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    $durMs = (int)round((microtime(true) - $start) * 1000);

    if ($raw === false || $code >= 400) {
        $msg = $cerr ?: ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
        ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'openai', $model, 'error', [], $durMs, $msg);
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => $msg, 'usage' => []];
    }

    $data = json_decode((string)$raw, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    $rawUsage = $data['usage'] ?? [];
    $usage = [
        'input_tokens'  => (int)($rawUsage['prompt_tokens'] ?? 0),
        'output_tokens' => (int)($rawUsage['completion_tokens'] ?? 0),
    ];
    $json = null;
    if (!empty($opt['json_schema'])) { $json = json_decode($text, true); }

    ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'openai', $model, 'ok', $usage, $durMs, null);
    return ['ok' => true, 'text' => trim($text), 'json' => $json, 'error' => null, 'usage' => $usage];
}

/**
 * Google Gemini API 호출
 */
function ai_call_gemini(string $system, string $user, array $opt, array $cfg): array
{
    $start = microtime(true);
    $model = trim((string)($opt['model'] ?? $cfg['model'] ?: 'gemini-flash-lite-latest'));
    if (strpos($model, 'models/') === 0) {
        $model = substr($model, 7);
    }
    if (in_array($model, ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-flash-latest', ''], true)) {
        $model = 'gemini-flash-lite-latest';
    }
    $maxTokens = (int)($opt['max_tokens'] ?? $cfg['max_tokens'] ?: 1024);

    $body = [
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents'           => [['role' => 'user', 'parts' => [['text' => $user]]]],
        'generationConfig'   => ['maxOutputTokens' => $maxTokens],
    ];
    if (!empty($opt['json_schema'])) {
        $body['generationConfig']['responseMimeType'] = 'application/json';
    }

    $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

    $fallbackChain = array_values(array_unique([
        $model,
        'gemini-flash-lite-latest',
        'gemini-3.1-flash-lite',
        'gemma-4-31b-it',
        'gemini-flash-latest'
    ]));

    $raw  = false;
    $code = 0;
    $cerr = '';
    $usedModel = $model;

    foreach ($fallbackChain as $currentModel) {
        $usedModel = $currentModel;
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($currentModel) . ':generateContent';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int)($cfg['timeout'] ?? 30),
                CURLOPT_HTTPHEADER     => [
                    'x-goog-api-key: ' . $cfg['api_key'],
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => $jsonBody,
            ]);
            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);

            if ($raw !== false && $code < 400) {
                break 2;
            }
            if (($code === 503 || $code === 429 || $raw === false) && $attempt < 2) {
                usleep(1000000); // 1초 대기 후 같은 모델로 재시도
            }
        }
    }

    $durMs = (int)round((microtime(true) - $start) * 1000);

    if ($raw === false || $code >= 400) {
        $msg = $cerr ?: ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
        ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'gemini', $usedModel, 'error', [], $durMs, $msg);
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => $msg, 'usage' => []];
    }

    $data = json_decode((string)$raw, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $meta = $data['usageMetadata'] ?? [];
    $usage = [
        'input_tokens'  => (int)($meta['promptTokenCount'] ?? 0),
        'output_tokens' => (int)($meta['candidatesTokenCount'] ?? 0),
    ];
    $json = null;
    if (!empty($opt['json_schema'])) { $json = json_decode($text, true); }

    ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'gemini', $usedModel, 'ok', $usage, $durMs, null);
    return ['ok' => true, 'text' => trim($text), 'json' => $json, 'error' => null, 'usage' => $usage];
}

/**
 * Grok (xAI) API 호출
 */
function ai_call_grok(string $system, string $user, array $opt, array $cfg): array
{
    $start = microtime(true);
    $model = $opt['model'] ?? $cfg['model'] ?: 'grok-2-latest';
    $maxTokens = (int)($opt['max_tokens'] ?? $cfg['max_tokens'] ?: 1024);

    if (!empty($opt['json_schema'])) {
        $system .= "\n\nYou must respond strictly in valid JSON format.";
    }

    $body = [
        'model'       => $model,
        'max_tokens'  => $maxTokens,
        'temperature' => 0.7,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user]
        ],
    ];
    if (!empty($opt['json_schema'])) {
        $body['response_format'] = ['type' => 'json_object'];
    }

    $ch = curl_init('https://api.x.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int)($cfg['timeout'] ?? 30),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    $durMs = (int)round((microtime(true) - $start) * 1000);

    if ($raw === false || $code >= 400) {
        $msg = $cerr ?: ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
        ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'grok', $model, 'error', [], $durMs, $msg);
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => $msg, 'usage' => []];
    }

    $data = json_decode((string)$raw, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    $rawUsage = $data['usage'] ?? [];
    $usage = [
        'input_tokens'  => (int)($rawUsage['prompt_tokens'] ?? 0),
        'output_tokens' => (int)($rawUsage['completion_tokens'] ?? 0),
    ];
    $json = null;
    if (!empty($opt['json_schema'])) { $json = json_decode($text, true); }

    ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'grok', $model, 'ok', $usage, $durMs, null);
    return ['ok' => true, 'text' => trim($text), 'json' => $json, 'error' => null, 'usage' => $usage];
}

/**
 * DeepSeek API 호출
 */
function ai_call_deepseek(string $system, string $user, array $opt, array $cfg): array
{
    $start = microtime(true);
    $model = $opt['model'] ?? $cfg['model'] ?: 'deepseek-chat';
    $maxTokens = (int)($opt['max_tokens'] ?? $cfg['max_tokens'] ?: 1024);

    if (!empty($opt['json_schema'])) {
        $system .= "\n\nYou must respond strictly in valid JSON format.";
    }

    $body = [
        'model'       => $model,
        'max_tokens'  => $maxTokens,
        'temperature' => 0.7,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user]
        ],
    ];
    if (!empty($opt['json_schema'])) {
        $body['response_format'] = ['type' => 'json_object'];
    }

    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int)($cfg['timeout'] ?? 30),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    $durMs = (int)round((microtime(true) - $start) * 1000);

    if ($raw === false || $code >= 400) {
        $msg = $cerr ?: ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
        ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'deepseek', $model, 'error', [], $durMs, $msg);
        return ['ok' => false, 'text' => '', 'json' => null, 'error' => $msg, 'usage' => []];
    }

    $data = json_decode((string)$raw, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    $rawUsage = $data['usage'] ?? [];
    $usage = [
        'input_tokens'  => (int)($rawUsage['prompt_tokens'] ?? 0),
        'output_tokens' => (int)($rawUsage['completion_tokens'] ?? 0),
    ];
    $json = null;
    if (!empty($opt['json_schema'])) { $json = json_decode($text, true); }

    ai_log($opt['feature'] ?? '', $opt['target_id'] ?? null, 'deepseek', $model, 'ok', $usage, $durMs, null);
    return ['ok' => true, 'text' => trim($text), 'json' => $json, 'error' => null, 'usage' => $usage];
}

/** ai_logs 테이블에 호출 기록(비용/감사) */
function ai_log(string $feature, $targetId, string $provider, string $model, string $status, array $usage, int $durMs, ?string $err): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO ai_logs (feature, target_id, provider, model, status, input_tokens, output_tokens, duration_ms, error, created_at)
             VALUES (:f,:t,:p,:m,:s,:it,:ot,:dur,:e,NOW())'
        )->execute([
            ':f' => $feature,
            ':t' => ($targetId !== null ? (string)$targetId : null),
            ':p' => strtolower($provider),
            ':m' => $model,
            ':s' => $status,
            ':it' => (int)($usage['input_tokens'] ?? 0),
            ':ot' => (int)($usage['output_tokens'] ?? 0),
            ':dur' => $durMs,
            ':e' => $err ? substr($err, 0, 500) : null,
        ]);
    } catch (Throwable $e) { /* 로깅 실패는 무시 */ }
}

/** Auto Failover 전환 감사 로그 삽입 */
function ai_failover_log_insert(string $feature, $targetId, string $failedProv, string $failedModel, string $errCode, ?string $errMsg, string $fallbackProv, string $fallbackModel, int $durMs): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO ai_failover_log (feature, target_id, failed_provider, failed_model, error_code, error_message, fallback_provider, fallback_model, status, duration_ms, created_at)
             VALUES (:f,:t,:fp,:fm,:ec,:em,:fbp,:fbm,:s,:dur,NOW())'
        )->execute([
            ':f'   => $feature,
            ':t'   => ($targetId !== null ? (string)$targetId : null),
            ':fp'  => strtolower($failedProv),
            ':fm'  => $failedModel,
            ':ec'  => substr($errCode, 0, 20),
            ':em'  => $errMsg ? substr($errMsg, 0, 500) : null,
            ':fbp' => strtolower($fallbackProv),
            ':fbm' => $fallbackModel,
            ':s'   => 'success',
            ':dur' => $durMs,
        ]);
    } catch (Throwable $e) { /* 로깅 실패는 무시 */ }
}

/** PII(개인정보) 마스킹 처리: 전화번호, 이메일, 주소 등 민감정보를 외부 전송 전 안전하게 마스킹 (§7) */
function ai_mask_pii(string $text): string
{
    if ($text === '') { return ''; }

    // 1. 이메일 마스킹
    $text = preg_replace_callback('/([a-zA-Z0-9._%+-]{1,3})[a-zA-Z0-9._%+-]*@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', function($m) {
        return $m[1] . '***@' . $m[2];
    }, $text);

    // 2. 휴대전화 및 유선전화 마스킹
    $text = preg_replace_callback('/(0(1[016789]|2|[3-9][0-9]))[- ]?([0-9]{3,4})[- ]?([0-9]{4})/', function($m) {
        return $m[1] . '-' . str_repeat('*', strlen($m[3])) . '-' . $m[4];
    }, $text);

    // 3. 상세주소(동/호수/지번/빌딩명) 마스킹 (시/도/군/구/로/길 이후 민감 상세 주소 마스킹)
    $text = preg_replace('/([가-힣]{1,10}(?:시|도|군|구|읍|면|동|리|로|길)\s+[0-9-]+)(?:\s+[0-9a-zA-Z가-힣-]+(?:층|호|동|비즈니스센터|사무실))?/', '$1 [상세주소 마스킹]', $text);

    return $text;
}

/** 레이트 리밋 체크: 동일 target에 대해 30초 내 중복 호출 차단 (§3) */
function ai_check_rate_limit(string $feature, int $targetId): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $key = '_ai_rl_' . $feature . '_' . $targetId;
    $now = time();
    $last = (int)($_SESSION[$key] ?? 0);
    if ($last > 0 && ($now - $last) < 30) {
        return false;
    }
    $_SESSION[$key] = $now;
    return true;
}

/** AI 엔드포인트 JSON 반환 헬퍼 */
function ai_json_response(array $data, int $http = 200): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
