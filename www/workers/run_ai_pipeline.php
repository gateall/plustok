#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * AI 파이프라인 수동 실행 (디버그/크론)
 * Usage: php workers/run_ai_pipeline.php <roomId> <recommendationId> <messageId>
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

[$script, $roomId, $recId, $msgId] = array_pad($argv, 4, '');
if ($roomId === '' || $recId === '' || $msgId === '') {
    fwrite(STDERR, "Usage: php workers/run_ai_pipeline.php <roomId> <recommendationId> <messageId>\n");
    exit(1);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/acep.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai_router_service.php';
require_once __DIR__ . '/../includes/repositories/AiRecommendationRepository.php';
require_once __DIR__ . '/../includes/repositories/AiPromptRepository.php';
require_once __DIR__ . '/../includes/repositories/ChatMessageRepository.php';
require_once __DIR__ . '/../includes/repositories/ChatRoomRepository.php';
require_once __DIR__ . '/../includes/repositories/CustomerRepository.php';

$pdo = db();
$router = new AiRouterService(
    new AiRecommendationRepository($pdo),
    new AiPromptRepository($pdo),
    new ChatMessageRepository($pdo),
    new ChatRoomRepository($pdo),
    new CustomerRepository($pdo),
);

$router->runPipeline($roomId, $recId, $msgId);
echo "done\n";
