<?php
declare(strict_types=1);
/**
 * 상담 API 인증: X-API-KEY 헤더 → sites 조회. (API.md 인증)
 * 성공 시 site 배열 반환, 실패 시 json_error로 종료.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

function require_site_by_apikey(): array
{
    $key = api_key_from_request();
    if ($key === '') {
        json_error('UNAUTHORIZED', 'API Key가 필요합니다.', 401);
    }

    $stmt = db()->prepare(
        'SELECT id, site_code, site_name, domain, brand, division, status, api_key
         FROM sites WHERE api_key = :k LIMIT 1'
    );
    $stmt->execute([':k' => $key]);
    $site = $stmt->fetch();

    // hash_equals로 타이밍 안전 비교 (WHERE로 이미 걸렀지만 방어적)
    if (!$site || !hash_equals((string)$site['api_key'], $key)) {
        json_error('UNAUTHORIZED', '유효하지 않은 API Key입니다.', 401);
    }
    if ((int)$site['status'] !== 1) {
        json_error('FORBIDDEN', '비활성화된 사이트입니다.', 403);
    }
    return $site;
}

function api_key_from_request(): string
{
    // 헤더 X-API-KEY 우선, 없으면 서버 프록시가 넘긴 폼 필드 허용
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'X-API-KEY') === 0) {
            return trim((string)$value);
        }
    }
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        return trim((string)$_SERVER['HTTP_X_API_KEY']);
    }
    return '';
}
