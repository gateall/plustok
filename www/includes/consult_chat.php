<?php
declare(strict_types=1);

require_once __DIR__ . '/repositories/ChatRoomRepository.php';
require_once __DIR__ . '/repositories/CustomerRepository.php';
require_once __DIR__ . '/repositories/CustomerBridgeRepository.php';
require_once __DIR__ . '/repositories/ChatMessageRepository.php';
require_once __DIR__ . '/repositories/AiRecommendationRepository.php';
require_once __DIR__ . '/services/CustomerTokenService.php';
require_once __DIR__ . '/services/ConsultChatService.php';

function acep_consult_chat_service(PDO $pdo): ConsultChatService
{
    return new ConsultChatService(
        $pdo,
        new ChatRoomRepository($pdo),
        new CustomerRepository($pdo),
        new CustomerBridgeRepository($pdo),
        new ChatMessageRepository($pdo),
        new AiRecommendationRepository($pdo),
        new CustomerTokenService(),
    );
}
