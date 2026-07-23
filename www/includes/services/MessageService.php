<?php
declare(strict_types=1);

require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../ws_publish.php';

final class MessageService
{
    private const MAX_CONTENT = 2000;

    public function __construct(
        private ChatRoomRepository $rooms,
        private ChatMessageRepository $messages,
        private ReadStatusRepository $readStatus,
        private AiRecommendationRepository $aiRecs,
        private ChatService $chatSvc,
        private AuditService $audit,
        private ?AiRouterService $aiRouter = null,
    ) {
    }
    /** @return array<string,mixed> */
    public function listMessages(string $roomId, string $agentId, string $role, array $query): array
    {
        $this->chatSvc->requireRoomAccess($roomId, $agentId, $role);

        $limit = min(50, max(1, (int)($query['limit'] ?? 50)));
        $before = isset($query['before']) ? (string)$query['before'] : null;

        $rows = $this->messages->listByRoom($roomId, $limit + 1, $before);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = [
                'id'             => $row['id'],
                'senderType'     => $row['sender_type'],
                'senderId'       => $row['sender_id'],
                'content'        => $row['content'],
                'attachmentUrl'  => $row['attachment_url'],
                'attachmentType' => $row['attachment_type'],
                'source'         => $row['source'],
                'createdAt'      => date('c', strtotime((string)$row['created_at'])),
                'readStatus'     => $this->readStatus->readStatusForMessage(
                    (string)$row['id'],
                    'agent',
                    $agentId
                ),
            ];
        }

        return ['messages' => array_reverse($messages), 'hasMore' => $hasMore];
    }

    /** @param array<string,mixed> $body */
    public function createMessage(string $roomId, string $agentId, string $role, array $body): array
    {
        $room = $this->chatSvc->requireRoomAccess($roomId, $agentId, $role);

        // DB column is chat_messages.content — accept legacy JSON key "message" on input only.
        $content = trim((string)($body['content'] ?? $body['message'] ?? ''));
        if ($content === '') {
            acep_error('VALIDATION_ERROR', 'content가 필요합니다.', 400);
        }
        if (mb_strlen($content) > self::MAX_CONTENT) {
            acep_error('VALIDATION_ERROR', '메시지는 2000자를 초과할 수 없습니다.', 400);
        }

        $source = (string)($body['source'] ?? 'manual');
        if (!in_array($source, ['manual', 'ai_recommendation'], true)) {
            acep_error('VALIDATION_ERROR', 'source가 올바르지 않습니다.', 400);
        }

        $senderType = in_array($role, ['agent', 'admin', 'operator'], true) ? 'agent' : 'customer';
        $senderId = $agentId;

        $msgId = uuid_v4();
        try {
            $this->messages->create([
                ':id'                   => $msgId,
                ':room_id'              => $roomId,
                ':sender_type'          => $senderType,
                ':sender_id'            => $senderId,
                ':content'              => $content,
                ':attachment_url'       => $body['attachmentUrl'] ?? null,
                ':attachment_id'        => $body['attachmentId'] ?? null,
                ':attachment_type'      => null,
                ':source'               => $source,
                ':ai_recommendation_id' => $body['aiRecommendationId'] ?? null,
            ]);
        } catch (Throwable) {
            acep_error('MSG_SEND_FAILED', '메시지 저장에 실패했습니다.', 500);
        }

        $this->rooms->touchUpdatedAt($roomId);

        if ($room['status'] === 'new' && $senderType === 'agent') {
            $this->rooms->setActive($roomId, $agentId);
        }

        if ($senderType === 'customer') {
            $recId = uuid_v4();
            $this->aiRecs->createPending($recId, $roomId);
            acep_ws_publish_ai_update($roomId, $recId, 'pending');
            if ($this->aiRouter !== null) {
                AiRouterService::dispatchAfterResponse($this->aiRouter, $roomId, $recId, $msgId);
            }

            if ($room['status'] === 'new' && empty($room['agent_id'])) {
                acep_ws_publish_broadcast('room:update', [
                    'roomId'      => $roomId,
                    'lastMessage' => $content,
                    'updatedAt'   => date('c'),
                ]);
            }
        }
        $this->audit->agentAction($agentId, 'message.create', 'chat_message', $msgId, ['roomId' => $roomId]);

        return [
            'messageId' => $msgId,
            'createdAt' => date('c'),
        ];
    }
}
