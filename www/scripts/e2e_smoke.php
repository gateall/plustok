<?php
declare(strict_types=1);
/**
 * ACEP E2E Smoke Test — Cafe24 서버에서 실행
 *
 * Usage:
 *   php scripts/e2e_smoke.php
 *   php scripts/e2e_smoke.php --base=https://plustok.mycafe24.com/api/v1
 *
 * Exit 0 = all critical checks pass, 1 = failures
 */

$argv = $argv ?? [];
$base = 'http://127.0.0.1/api/v1';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

$results = [];
$failures = [];

function smoke(string $name, callable $fn): void
{
    global $results, $failures;
    try {
        $fn();
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => ''];
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $results[] = ['name' => $name, 'status' => 'FAIL', 'detail' => $msg];
        $failures[] = $name . ': ' . $msg;
        echo "[FAIL] {$name} — {$msg}\n";
    }
}

function httpJson(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException("curl: {$err}");
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("HTTP {$code} invalid JSON");
    }
    return ['http' => $code, 'json' => $json, 'raw' => $raw];
}

echo "=== ACEP E2E Smoke ===\nBase: {$base}\n\n";

smoke('1. DB migration check', function (): void {
    passthru('php ' . escapeshellarg(dirname(__DIR__) . '/migrations/migrate.php') . ' --check 2>&1', $code);
    if ($code !== 0) {
        throw new RuntimeException('migrate --check exit ' . $code);
    }
});

smoke('2. GET /system/health', function () use ($base): void {
    try {
        $r = httpJson('GET', $base . '/system/health');
        if ($r['http'] !== 200 || !($r['json']['success'] ?? false)) {
            throw new RuntimeException('health not OK');
        }
    } catch (Throwable $e) {
        // Legacy fallback (pre-ACEP router deploy)
        $legacy = preg_replace('#/api/v1$#', '/api/v1/health.php', $base);
        $r = httpJson('GET', $legacy);
        if ($r['http'] !== 200) {
            throw new RuntimeException('health failed: ' . $e->getMessage());
        }
    }
});

smoke('3. GET /health (v1.5)', function () use ($base): void {
    $r = httpJson('GET', $base . '/health');
    if ($r['http'] !== 200 || ($r['json']['data']['version'] ?? '') !== '1.5') {
        throw new RuntimeException('health v1.5 mismatch');
    }
});

$token = null;
smoke('4. POST /auth/login', function () use ($base, &$token): void {
    $r = httpJson('POST', $base . '/auth/login', [
        'loginId'  => getenv('ACEP_SMOKE_LOGIN') ?: 'admin',
        'password' => getenv('ACEP_SMOKE_PASSWORD') ?: 'Admin123!',
    ]);
    if ($r['http'] !== 200 || empty($r['json']['data']['accessToken'])) {
        throw new RuntimeException('login failed — check admin seed');
    }
    $token = $r['json']['data']['accessToken'];
});

smoke('5. GET /auth/me (JWT)', function () use ($base, &$token): void {
    if (!$token) {
        throw new RuntimeException('no token');
    }
    $r = httpJson('GET', $base . '/auth/me', null, $token);
    if ($r['http'] !== 200) {
        throw new RuntimeException('JWT rejected');
    }
});

smoke('6. GET /chats/rooms', function () use ($base, &$token): void {
    $r = httpJson('GET', $base . '/chats/rooms', null, $token);
    if ($r['http'] !== 200) {
        throw new RuntimeException('rooms list failed');
    }
});

smoke('7. GET /dashboard/stats', function () use ($base, &$token): void {
    $r = httpJson('GET', $base . '/dashboard/stats', null, $token);
    if ($r['http'] !== 200) {
        throw new RuntimeException('dashboard failed');
    }
});

smoke('8. Chat server /health', function (): void {
    $wsBase = getenv('ACEP_WS_HEALTH') ?: 'http://127.0.0.1:3001/health';
    $ch = curl_init($wsBase);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) {
        throw new RuntimeException("WS health HTTP {$code} (is chat-server running?)");
    }
});

$reportPath = dirname(__DIR__) . '/logs/e2e-smoke-' . date('Ymd-His') . '.json';
@mkdir(dirname($reportPath), 0750, true);
file_put_contents($reportPath, json_encode([
    'timestamp' => date('c'),
    'base'      => $base,
    'results'   => $results,
    'failures'  => $failures,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "\nReport: {$reportPath}\n";
echo 'Summary: ' . count(array_filter($results, fn ($r) => $r['status'] === 'PASS')) . '/' . count($results) . " PASS\n";

exit($failures === [] ? 0 : 1);
