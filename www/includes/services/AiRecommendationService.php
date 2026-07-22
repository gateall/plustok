<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class AiRecommendationService
{
    public function __construct(
        private AiRecommendationRepository $aiRecs,
        private ChatService $chatSvc,
    ) {
    }

    /** @return array<string,mixed> */
    public function getByRoom(string $roomId, string $agentId, string $role): array
    {
        $this->chatSvc->requireRoomAccess($roomId, $agentId, $role);

        $row = $this->aiRecs->findLatestByRoom($roomId);
        if (!$row) {
            return [
                'roomId'              => $roomId,
                'contractProbability' => null,
                'contractLabel'       => null,
                'sentiment'           => null,
                'intent'              => null,
                'customerTags'        => [],
                'recommendations'     => [],
                'faq'                 => [],
                'aiModel'             => null,
                'status'              => 'pending',
                'updatedAt'           => date('c'),
            ];
        }

        $content = json_decode((string)$row['content'], true) ?: [];
        $prob = $row['contract_probability'] !== null ? (int)$row['contract_probability'] : null;

        return [
            'roomId'              => $roomId,
            'contractProbability' => $prob,
            'contractLabel'       => $this->contractLabel($prob),
            'sentiment'           => $row['sentiment'],
            'intent'              => $row['intent'],
            'customerTags'        => $content['customerTags'] ?? [],
            'recommendations'     => $content['recommendations'] ?? [],
            'faq'                   => $content['faq'] ?? [],
            'aiModel'             => $row['ai_model'],
            'status'              => $row['status'],
            'updatedAt'           => date('c', strtotime((string)$row['created_at'])),
        ];
    }

    private function contractLabel(?int $prob): ?string
    {
        if ($prob === null) {
            return null;
        }
        if ($prob >= 80) {
            return '높음 - 우선 대응';
        }
        if ($prob >= 50) {
            return '보통';
        }
        return '낮음';
    }

    /** @return array<string,mixed> */
    public function retry(string $roomId, string $agentId, string $role, AiRouterService $router): array
    {
        $this->chatSvc->requireRoomAccess($roomId, $agentId, $role);
        return $router->retry($roomId, $agentId, $role);
    }
}