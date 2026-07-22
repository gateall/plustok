<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';

final class SettingsService
{
    private const DEFAULTS = [
        'theme'            => 'light',
        'locale'           => 'ko-KR',
        'notifySound'      => true,
        'desktopNotify'    => true,
        'messagesPerPage'  => 50,
    ];

    public function __construct(private AgentRepository $agents)
    {
    }

    /** @return array<string,mixed> */
    public function getForAgent(string $agentId): array
    {
        $agent = $this->agents->findById($agentId);
        if (!$agent) {
            acep_error('AGENT_NOT_FOUND', '상담원을 찾을 수 없습니다.', 404);
        }
        $stored = [];
        if (!empty($agent['settings_json'])) {
            $decoded = json_decode((string)$agent['settings_json'], true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        }
        return ['settings' => array_merge(self::DEFAULTS, $stored)];
    }

    /** @param array<string,mixed> $body */
    public function updateForAgent(string $agentId, array $body): array
    {
        $current = $this->getForAgent($agentId)['settings'];
        $incoming = $body['settings'] ?? $body;
        if (!is_array($incoming)) {
            acep_error('VALIDATION_ERROR', 'settings 객체가 필요합니다.', 400);
        }
        $merged = array_merge($current, array_intersect_key($incoming, self::DEFAULTS));
        $this->agents->updateSettingsJson($agentId, $merged);
        return ['settings' => $merged];
    }
}
