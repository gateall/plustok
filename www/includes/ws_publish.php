<?php
declare(strict_types=1);

require_once __DIR__ . '/redis.php';

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

    $redis = acep_redis();
    if ($redis !== null) {
        try {
            $redis->publish('acep:room:' . $roomId . ':events', $json);
        } catch (Throwable $e) {
            acep_ws_log('redis publish failed: ' . $e->getMessage());
        }
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

    $redis = acep_redis();
    if ($redis !== null) {
        try {
            $redis->publish('acep:events:broadcast', $json);
        } catch (Throwable) {
            acep_ws_log('redis broadcast failed: ' . $e->getMessage());
        }
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
