<?php
declare(strict_types=1);
/**
 * 임베드 상담폼 백엔드.
 *  - GET  ?site=<code>&action=config : 사이트 설정(JSON) 반환
 *  - POST (JSON)                     : 서버 프록시로 consult.php 대신 호출 (API Key 은닉)
 *  - OPTIONS                         : CORS preflight
 * 브라우저에 API Key를 노출하지 않는다. (API.md 인증 / SPEC A-1)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/** 요청 site_code로 사이트 조회 (config용은 GET, 제출용은 body) */
function load_site(string $code): ?array
{
    if ($code === '') return null;
    $stmt = db()->prepare('SELECT * FROM sites WHERE site_code = :c AND status = 1 LIMIT 1');
    $stmt->execute([':c' => $code]);
    $s = $stmt->fetch();
    return $s ?: null;
}

/** 사이트 도메인 기반 CORS 허용 오리진 셋 */
function allowed_origins(array $site): array
{
    $d = strtolower(trim($site['domain']));
    $d = preg_replace('#^https?://#', '', $d);
    $d = preg_replace('#/.*$#', '', $d);
    $bare = preg_replace('#^www\.#', '', $d);
    $set = [];
    foreach ([$bare, 'www.' . $bare] as $host) {
        $set[] = 'https://' . $host;
        $set[] = 'http://' . $host;
    }
    return $set;
}

/** CORS 응답 헤더 출력 */
function send_cors_headers(string $origin): void
{
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

/** CORS 헤더 부착 (특정 사이트 도메인 기준 허용 오리진일 때만) */
function apply_cors(array $site): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, allowed_origins($site), true)) {
        send_cors_headers($origin);
    }
}

/**
 * 프리플라이트(OPTIONS)는 site 파라미터 없이 오므로, Origin이 "등록된 어떤 사이트"의
 * 도메인과 일치하면 허용한다. 실제 접수(POST)는 body의 api_key로 사이트를 재검증한다.
 */
function origin_is_registered(string $origin): bool
{
    if ($origin === '') return false;
    $rows = db()->query('SELECT domain FROM sites WHERE status = 1')->fetchAll();
    foreach ($rows as $r) {
        if (in_array($origin, allowed_origins(['domain' => $r['domain']]), true)) {
            return true;
        }
    }
    return false;
}

// --- OPTIONS preflight ---
if ($method === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (origin_is_registered($origin)) {
        send_cors_headers($origin);
    }
    http_response_code(204);
    exit;
}

// --- GET config ---
if ($method === 'GET') {
    $site = load_site(clean_str($_GET['site'] ?? '', 50));
    if (!$site) json_error('NOT_FOUND', '사이트를 찾을 수 없습니다.', 404);
    apply_cors($site);

    $ps = db()->prepare('SELECT category, product_name FROM products WHERE brand = :b AND use_yn = 1 ORDER BY sort_order, id');
    $ps->execute([':b' => $site['brand']]);
    $products = $ps->fetchAll();

    json_success([
        'site_code' => $site['site_code'],
        'site_name' => $site['site_name'],
        'brand'     => $site['brand'],
        'persona'   => $site['persona'] ?? ('안녕하세요. ' . $site['site_name'] . ' 상담입니다.'),
        'products'  => array_map(fn($p) => ['category' => $p['category'], 'name' => $p['product_name']], $products),
    ]);
}

// --- POST submit (proxy) ---
if ($method === 'POST') {
    $body = read_json_body() ?: $_POST;
    $site = load_site(clean_str($body['site_code'] ?? '', 50));
    if (!$site) json_error('NOT_FOUND', '사이트를 찾을 수 없습니다.', 404);
    apply_cors($site);

    // referer/device 보강
    $body['referer'] = $body['referer'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $body['device']  = $body['device'] ?? (preg_match('/Mobile|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? '') ? 'mobile' : 'pc');

    // 서버측에서 API Key를 붙여 consult.php로 프록시 호출
    $ch = curl_init(API_BASE . '/consult.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . $site['api_key']],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        log_error('embed_form', 'curl 실패: ' . $err);
        json_error('SERVER_ERROR', '접수 처리 중 오류가 발생했습니다.', 500);
    }
    // consult.php의 JSON 응답을 그대로 전달
    http_response_code($httpCode ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo $resp;
    exit;
}

json_error('METHOD_NOT_ALLOWED', '허용되지 않은 메서드입니다.', 405);
