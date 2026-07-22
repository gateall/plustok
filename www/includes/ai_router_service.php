<?php
declare(strict_types=1);

require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/ws_publish.php';
require_once __DIR__ . '/util/Uuid.php';
require_once __DIR__ . '/repositories/AiRecommendationRepository.php';
require_once __DIR__ . '/repositories/AiPromptRepository.php';
require_once __DIR__ . '/repositories/ChatMessageRepository.php';
require_once __DIR__ . '/repositories/ChatRoomRepository.php';
require_once __DIR__ . '/repositories/CustomerRepository.php';

/**
 * AI Router — SSOT: 04_AI/03_AI엔진구현.md §9
 * 모든 AI 호출은 ai_call() 단일 진입점.
 */
final class AiRouterService
{
    private const RECOMMEND_TTL = 3600;
    private const DEBOUNCE_SEC = 30;

    public function __construct(
        private AiRecommendationRepository $aiRecs,
        private AiPromptRepository $prompts,
        private ChatMessageRepository $messages,
        private ChatRoomRepository $rooms,
        private CustomerRepository $customers,
    ) {
    }

    /** HTTP 응답 후 비동기 파이프라인 실행 */
    public static function dispatchAfterResponse(
        AiRouterService $svc,
        string $roomId,
        string $recommendationId,
        string $triggerMessageId,
    ): void {
        register_shutdown_function(static function () use ($svc, $roomId, $recommendationId, $triggerMessageId): void {
            ignore_user_abort(true);
            @set_time_limit(120);
            try {
                $svc->runPipeline($roomId, $recommendationId, $triggerMessageId);
            } catch (Throwable $e) {
                acep_ws_log('AiRouter pipeline error: ' . $e->getMessage());
            }
        });
    }

    public function runPipeline(string $roomId, string $recommendationId, string $triggerMessageId): void
    {
        if ($this->isDebounced($roomId, $recommendationId)) {
            return;
        }

        acep_ws_publish_ai_update($roomId, $recommendationId, 'processing');
        $this->aiRecs->setProcessing($recommendationId);

        $room = $this->rooms->findById($roomId);
        if (!$room) {
            $this->fail($roomId, $recommendationId, 'Room not found');
            return;
        }

        $customer = $this->customers->findById((string)$room['customer_id']);
        $recent = $this->messages->listByRoom($roomId, 20, null);
        $recent = array_reverse($recent);

        $latestCustomer = '';
        foreach (array_reverse($recent) as $row) {
            if (($row['sender_type'] ?? '') === 'customer') {
                $latestCustomer = (string)$row['content'];
                break;
            }
        }
        if ($latestCustomer === '') {
            $msg = $this->messages->findById($triggerMessageId);
            $latestCustomer = $msg ? (string)$msg['content'] : '';
        }

        $maskedContext = ai_mask_pii($this->formatMessages($recent));
        $maskedLatest = ai_mask_pii($latestCustomer);

        $promptRow = $this->prompts->findActiveByRole('recommend');
        $system = $promptRow
            ? (string)$promptRow['content']
            : self::defaultRecommendSystemPrompt();
        $promptVersion = $promptRow ? (string)$promptRow['version'] : 'v1.0-fallback';

        $userPrompt = $this->buildUserPrompt(
            $roomId,
            (string)($room['inquiry_type'] ?? ''),
            (string)($customer['name'] ?? '고객'),
            $maskedContext,
            $maskedLatest,
        );

        $msgHash = hash('sha256', $roomId . '|' . $maskedLatest);
        $cacheKey = 'ai:rec:' . $roomId . ':' . $msgHash;

        $start = microtime(true);
        $result = $this->getCachedOrCall($cacheKey, self::RECOMMEND_TTL, function () use (
            $system,
            $userPrompt,
            $roomId,
            $recommendationId,
        ) {
            return ai_call($system, $userPrompt, [
                'feature'     => 'chat_recommend',
                'target_id'   => $roomId,
                'max_tokens'  => 1536,
                'json_schema' => self::recommendJsonSchema(),
            ]);
        });
        $latencyMs = (int)round((microtime(true) - $start) * 1000);

        if (!$result['ok']) {
            $this->fail($roomId, $recommendationId, (string)($result['error'] ?? 'AI call failed'));
            return;
        }

        $json = $result['json'];
        if (!is_array($json)) {
            $json = json_decode((string)($result['text'] ?? ''), true);
        }
        if (!is_array($json) || empty($json['recommendations'])) {
            $this->fail($roomId, $recommendationId, 'Invalid AI JSON response');
            return;
        }

        $contractProb = $this->deriveContractProbability($json);
        $sentiment = $this->deriveSentiment($json);
        $intent = isset($json['intent']) ? (string)$json['intent'] : null;
        $model = ai_config()['model'] ?? 'auto';

        $content = [
            'recommendations' => $json['recommendations'],
            'faq'             => $json['faq'] ?? [],
            'customerTags'    => $json['customerTags'] ?? [],
            'contextUsed'     => $json['contextUsed'] ?? ['conversation'],
        ];

        $this->aiRecs->complete(
            $recommendationId,
            $content,
            $contractProb,
            $sentiment,
            $intent,
            (string)$model,
            $promptVersion,
            $latencyMs,
        );

        if ($contractProb !== null) {
            $this->rooms->updatePriorityScore($roomId, $contractProb);
        }

        acep_ws_publish_ai_update($roomId, $recommendationId, 'completed', $contractProb);
        acep_ws_publish_broadcast('room:update', [
            'roomId'              => $roomId,
            'contractProbability' => $contractProb,
            'updatedAt'           => date('c'),
        ]);
    }

    public function retry(string $roomId, string $agentId, string $role): array
    {
        $latest = $this->aiRecs->findLatestByRoom($roomId);
        $recId = uuid_v4();
        $this->aiRecs->createPending($recId, $roomId);
        acep_ws_publish_ai_update($roomId, $recId, 'pending');

        $rows = $this->messages->listByRoom($roomId, 1, null);
        $triggerId = isset($rows[0]['id']) ? (string)$rows[0]['id'] : '';

        self::dispatchAfterResponse($this, $roomId, $recId, $triggerId);

        return [
            'roomId'           => $roomId,
            'recommendationId' => $recId,
            'status'           => 'pending',
            'retriedFrom'      => $latest['id'] ?? null,
        ];
    }

    private function fail(string $roomId, string $recommendationId, string $error): void
    {
        $this->aiRecs->fail($recommendationId, $error);
        acep_ws_publish_ai_update($roomId, $recommendationId, 'failed');
        acep_ws_log("AI failed room={$roomId} rec={$recommendationId}: {$error}");
    }

    private function isDebounced(string $roomId, string $currentRecId): bool
    {
        $recent = $this->aiRecs->findRecentProcessingByRoom($roomId, self::DEBOUNCE_SEC);
        foreach ($recent as $row) {
            if ($row['id'] !== $currentRecId && in_array($row['status'], ['processing', 'completed'], true)) {
                return true;
            }
        }
        return false;
    }

    /** @param callable(): array $callFn */
    private function getCachedOrCall(string $cacheKey, int $ttlSec, callable $callFn): array
    {
        $redis = acep_redis();
        if ($redis !== null) {
            try {
                $cached = $redis->get($cacheKey);
                if (is_string($cached) && $cached !== '') {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            } catch (Throwable) {
                /* cache miss */
            }
        }

        $result = $callFn();
        if ($result['ok'] && $redis !== null) {
            try {
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($encoded !== false) {
                    $redis->setex($cacheKey, $ttlSec, $encoded);
                }
            } catch (Throwable) {
                /* ignore */
            }
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $rows */
    private function formatMessages(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $who = ($row['sender_type'] ?? '') === 'customer' ? '고객' : '상담원';
            $lines[] = sprintf('[%s] %s: %s', $row['created_at'] ?? '', $who, $row['content'] ?? '');
        }
        return implode("\n", $lines);
    }

    private function buildUserPrompt(
        string $roomId,
        string $inquiryType,
        string $customerName,
        string $messages,
        string $latestMessage,
    ): string {
        return <<<TXT
상담방: {$roomId}
문의 카테고리: {$inquiryType}
고객 이름: {$customerName}

--- 대화 기록 (최근 20건) ---
{$messages}

--- 고객 최신 메시지 ---
{$latestMessage}

상담원이 전송할 답변 3개를 JSON으로 제시하세요.
contractProbability(0-100)와 sentiment(positive|neutral|negative)도 포함하세요.
TXT;
    }

    /** @param array<string,mixed> $json */
    private function deriveContractProbability(array $json): ?int
    {
        if (isset($json['contractProbability']) && is_numeric($json['contractProbability'])) {
            return max(0, min(100, (int)$json['contractProbability']));
        }
        $maxConf = 0.0;
        foreach ($json['recommendations'] as $rec) {
            if (!is_array($rec)) {
                continue;
            }
            $c = (float)($rec['confidence'] ?? 0);
            if ($c > $maxConf) {
                $maxConf = $c;
            }
        }
        return $maxConf > 0 ? (int)round($maxConf * 100) : null;
    }

    /** @param array<string,mixed> $json */
    private function deriveSentiment(array $json): ?string
    {
        $s = $json['sentiment'] ?? null;
        if (is_string($s) && in_array($s, ['positive', 'neutral', 'negative'], true)) {
            return $s;
        }
        return null;
    }

    private static function defaultRecommendSystemPrompt(): string
    {
        return <<<'SYS'
당신은 PlusTok Enterprise의 AI 답변 추천 전문가입니다.
고객의 최신 질문과 대화 맥락을 분석하여, 상담원이 즉시 전송 가능한 답변 후보 최대 3개를 JSON으로 제시합니다.
각 답변은 1~3문장, 친절한 존댓말, 서로 다른 접근 방식이어야 합니다.
JSON Schema 형식만 출력하세요.
SYS;
    }

    /** @return array<string,mixed> */
    private static function recommendJsonSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['recommendations', 'intent', 'contextUsed'],
            'properties' => [
                'recommendations' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'maxItems' => 3,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['id', 'text', 'confidence', 'approach'],
                        'properties' => [
                            'id'         => ['type' => 'string'],
                            'text'       => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'approach'   => ['type' => 'string'],
                        ],
                    ],
                ],
                'intent'              => ['type' => 'string'],
                'contextUsed'         => ['type' => 'array'],
                'contractProbability' => ['type' => 'integer'],
                'sentiment'           => ['type' => 'string'],
                'faq'                 => ['type' => 'array'],
                'customerTags'        => ['type' => 'array'],
            ],
        ];
    }
}
