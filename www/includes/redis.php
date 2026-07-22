<?php
declare(strict_types=1);

/**
 * Redis 연결 (선택). REDIS 확장 없거나 URL 미설정 시 null.
 */
function acep_redis(): ?Redis
{
    static $client = null;
    static $tried = false;

    if ($tried) {
        return $client;
    }
    $tried = true;

    if (!class_exists('Redis')) {
        return null;
    }

    $url = acep_redis_url();
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return null;
    }

    try {
        $r = new Redis();
        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int)($parts['port'] ?? 6379);
        if (!$r->connect($host, $port, 2.0)) {
            return null;
        }
        if (!empty($parts['pass'])) {
            $r->auth($parts['pass']);
        }
        $db = isset($parts['path']) ? (int)ltrim((string)$parts['path'], '/') : 0;
        if ($db > 0) {
            $r->select($db);
        }
        $client = $r;
    } catch (Throwable) {
        $client = null;
    }

    return $client;
}

function acep_redis_url(): string
{
    if (defined('ACEP_REDIS_URL') && ACEP_REDIS_URL !== '') {
        return ACEP_REDIS_URL;
    }
    $env = getenv('REDIS_URL');
    return is_string($env) && $env !== '' ? $env : '';
}
