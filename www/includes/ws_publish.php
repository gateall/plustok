<?php
declare(strict_types=1);

require_once __DIR__ . '/redis.php';
require_once __DIR__ . '/../config/acep.php';
if (!defined('LOG_PATH')) {
    require_once __DIR__ . '/../config/app.php';
}

/**
 * Room-scoped WebSocket 이벤트 — Chat Server Redis psubscribe.
 * SSOT: 05_CHAT/01_WebSocket설계.md §9
 */
function acep_ws_publish_room(string $roomId, string $event, array $payload): void
{
    $envelope = [
        'event'     => $event,
        'roomId'    => $roomId,
        'payload'   => $payload,
        'timestamp' => date('c'),
        'source'    => 'backend',
    ];
    $json = json_encode($envelope, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }

    $redisPublished = false;
    $redis = acep_redis();
    if ($redis !== null) {
        try {
            $redis->publish('acep:room:' . $roomId . ':events', $json);
            $redisPublished = true;
        } catch (Throwable $e) {
            acep_ws_log('redis publish failed: ' . $e->getMessage());
        }
    }

    if (!$redisPublished) {
        acep_ws_http_broadcast($event, $payload, $roomId);
    }
}

/** Global broadcast (ChatList room:update) */
function acep_ws_publish_broadcast(string $event, array $payload): void
{
    $envelope = [
        'event'     => $event,
        'payload'   => $payload,
        'timestamp' => date('c'),
        'source'    => 'backend',
    ];
    $json = json_encode($envelope, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }

    $redisPublished = false;
    $redis = acep_redis();
    if ($redis !== null) {
        try {
            $redis->publish('acep:events:broadcast', $json);
            $redisPublished = true;
        } catch (Throwable $e) {
            acep_ws_log('redis broadcast failed: ' . $e->getMessage());
        }
    }

    if (!$redisPublished) {
        acep_ws_http_broadcast($event, $payload);
    }
}

/**
 * Redis 미사용 시 Chat Server HTTP broadcast fallback.
 * Non-blocking best effort — 예외를 던지지 않는다.
 */
function acep_ws_http_broadcast(string $event, array $payload, ?string $roomId = null): void
{
    $baseUrl = acep_chat_server_url();
    if ($baseUrl === '') {
        return;
    }

    $secret = acep_chat_internal_secret();
    if ($secret === '') {
        acep_ws_log('http broadcast skipped: no internal secret');
        return;
    }

    $endpoint = rtrim($baseUrl, '/') . '/internal/ws/broadcast';
    $bodyData = ['event' => $event, 'payload' => $payload];
    if ($roomId !== null && $roomId !== '') {
        $bodyData['roomId'] = $roomId;
    }
    $body = json_encode($bodyData, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return;
    }

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Content-Type: application/json',
                'X-Chat-Internal-Secret: ' . $secret,
            ]),
            'content'       => $body,
            'timeout'       => 3,
            'ignore_errors' => true,
        ],
    ]);

    try {
        $result = @file_get_contents($endpoint, false, $context);
        if ($result === false) {
            acep_ws_log("http broadcast failed: event={$event} url={$endpoint}");
            return;
        }

        if (isset($http_response_header[0]) && !str_contains($http_response_header[0], ' 200 ')) {
            acep_ws_log("http broadcast non-200: event={$event} response={$http_response_header[0]}");
        }
    } catch (Throwable $e) {
        acep_ws_log('http broadcast error: ' . $e->getMessage());
    }
}

function acep_ws_publish_ai_update(
    string $roomId,
    string $recommendationId,
    string $status,
    ?int $contractProbability = null,
): void {
    $payload = [
        'roomId'            => $roomId,
        'recommendationId'  => $recommendationId,
        'status'            => $status,
        'timestamp'         => date('c'),
    ];
    if ($contractProbability !== null) {
        $payload['contractProbability'] = $contractProbability;
    }
    acep_ws_publish_room($roomId, 'ai:update', $payload);
}

function acep_ws_log(string $msg): void
{
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0750, true);
    }
    @file_put_contents(
        LOG_PATH . '/ws-' . date('Ymd') . '.log',
        sprintf("[%s] %s\n", date('c'), $msg),
        FILE_APPEND | LOCK_EX
    );
}
