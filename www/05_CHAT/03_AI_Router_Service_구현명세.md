# ACEP (PlusTok Enterprise) — AI Router Service 구현명세

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 4 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** AI Platform Team  
**Audience:** Backend PHP Developers  

**적용 위치:** `www/includes/services/`, `www/includes/ai/`  
**입력 명세:** [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md)  
**기존 구현:** [includes/ai.php](../includes/ai.php)  
**Backend API:** [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md)  
**Prompt 설계:** [04_AI/02_Prompt설계.md](../04_AI/02_Prompt설계.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| 핵심 클래스 | `AiRecommendationService`, `PromptLoader`, `AiJobQueue` |
| AI 호출 | 기존 `ai_call()` + `ai_mask_pii()` **재사용** (변경 최소) |
| 트리거 | `MessageService::send()` — `senderType=customer` |
| 알림 | Redis PUBLISH → Chat Server → `ai:update` |
| MVP Worker | **Redis List Queue + cron** (권장) |

STEP 3 [03_AI엔진구현.md](../04_AI/03_AI엔진구현.md)를 **PHP 구현 수준**으로 확장한다.

---

## 1. 아키텍처

### 1.1 계층 구조

```
MessageService::send()
    │
    └─► AiRecommendationService::onCustomerMessage($roomId, $messageId)
            │
            ├─ RateLimitService (Redis Rule-005)
            ├─ PromptLoader::load('chat_recommend')
            ├─ ContextBuilder + ai_mask_pii()
            ├─ ai_call()  ← includes/ai.php
            ├─ AiRecommendationRepository::complete()
            ├─ ChatRoomRepository::updatePriorityScore()
            └─ RedisEventPublisher::publishRoom('ai:update')
```

### 1.2 설계 원칙

1. **`includes/ai.php` 변경 최소** — Failover·logging 이미 구현됨 ([01_AI전략.md](../04_AI/01_AI전략.md))
2. **REST 응답 블로킹 금지** — AI는 async worker
3. **Single Writer** — `ai_recommendations.is_latest` 플래그로 room당 최신 1건
4. **Idempotent** — 동일 messageId 중복 처리 skip

---

## 2. 클래스 설계

### 2.1 파일 배치

```
www/includes/
├── ai.php                              # 기존 — ai_call, ai_mask_pii, ai_check_rate_limit
├── services/
│   ├── AiRecommendationService.php     # ★ NEW — 파이프라인 오케스트레이션
│   ├── AiContextBuilder.php            # ★ NEW — 대화 컨텍스트
│   └── RateLimitService.php            # ★ NEW — Redis Rule-005
├── ai/
│   ├── PromptLoader.php                # ★ NEW — ai_prompts 테이블
│   └── AiJobQueue.php                  # ★ NEW — async dispatch
└── repositories/
    ├── AiRecommendationRepository.php
    └── AiPromptRepository.php
```

### 2.2 AiRecommendationService (Skeleton)

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../ai.php';
require_once __DIR__ . '/../ai/PromptLoader.php';
require_once __DIR__ . '/../repositories/AiRecommendationRepository.php';
require_once __DIR__ . '/../repositories/ChatMessageRepository.php';
require_once __DIR__ . '/../repositories/ChatRoomRepository.php';
require_once __DIR__ . '/AiContextBuilder.php';
require_once __DIR__ . '/RateLimitService.php';
require_once __DIR__ . '/RedisEventPublisher.php';

final class AiRecommendationService
{
    public function __construct(
        private AiRecommendationRepository $aiRepo,
        private ChatMessageRepository $messageRepo,
        private ChatRoomRepository $roomRepo,
        private PromptLoader $promptLoader,
        private AiContextBuilder $contextBuilder,
        private RateLimitService $rateLimit,
        private RedisEventPublisher $redis,
    ) {}

    /**
     * 고객 메시지 수신 시 호출 (async queue enqueue)
     * @return bool AI job enqueued 여부
     */
    public function onCustomerMessage(string $roomId, string $messageId): bool
    {
        if (!$this->rateLimit->allowAiRoom($roomId)) {
            return false;
        }

        $recId = $this->aiRepo->insertPending($roomId, $messageId);

        AiJobQueue::push([
            'type'      => 'customer_message',
            'roomId'    => $roomId,
            'messageId' => $messageId,
            'recId'     => $recId,
        ]);

        $this->redis->publishRoom($roomId, 'ai:update', [
            'roomId'           => $roomId,
            'recommendationId' => $recId,
            'status'           => 'pending',
            'contractProbability' => null,
        ]);

        return true;
    }

    /**
     * Worker에서 실행 — 실제 AI 호출
     */
    public function processCustomerMessage(string $roomId, string $messageId, string $recId): void
    {
        $this->aiRepo->markProcessing($recId);

        $this->redis->publishRoom($roomId, 'ai:update', [
            'roomId'           => $roomId,
            'recommendationId' => $recId,
            'status'           => 'processing',
        ]);

        try {
            $messages = $this->messageRepo->getRecent($roomId, 20);
            $maskedContext = ai_mask_pii($this->contextBuilder->format($messages));

            $prompt = $this->promptLoader->load('chat_recommend');
            $schema = $this->promptLoader->getJsonSchema('PROMPT_RECOMMEND_v1.0');

            $result = ai_call(
                $prompt['system'],
                $this->contextBuilder->buildUserPrompt($maskedContext, $roomId),
                [
                    'feature'     => 'chat_recommend',
                    'target_id'   => $roomId,
                    'max_tokens'  => 1536,
                    'json_schema' => $schema,
                ]
            );

            if ($result['ok'] && is_array($result['json'])) {
                $this->completeSuccess($roomId, $recId, $result);
            } else {
                $this->completeFailure($roomId, $recId, $result['error'] ?? 'AI_ALL_FAILED');
            }
        } catch (Throwable $e) {
            $this->completeFailure($roomId, $recId, $e->getMessage());
        }
    }

    private function completeSuccess(string $roomId, string $recId, array $result): void
    {
        $json = $result['json'];
        $contractProb = (int)($json['contractProbability'] ?? 0);

        $this->aiRepo->complete($recId, [
            'content'               => $json,
            'contract_probability'  => $contractProb,
            'sentiment'             => $json['sentiment'] ?? null,
            'intent'                => $json['intent'] ?? null,
            'ai_model'              => $result['model'] ?? 'unknown',
            'latency_ms'            => $result['duration_ms'] ?? null,
            'prompt_version'        => 'PROMPT_RECOMMEND_v1.0',
        ]);

        $this->roomRepo->updatePriorityScore($roomId, $contractProb);

        if (!empty($json['customerTags'])) {
            // CustomerRepository::mergeTags($roomId, $json['customerTags']);
        }

        $this->redis->publishRoom($roomId, 'ai:update', [
            'roomId'              => $roomId,
            'recommendationId'  => $recId,
            'status'              => 'completed',
            'contractProbability' => $contractProb,
        ]);
    }

    private function completeFailure(string $roomId, string $recId, string $error): void
    {
        $this->aiRepo->fail($recId, $error);
        $this->redis->publishRoom($roomId, 'ai:update', [
            'roomId'           => $roomId,
            'recommendationId' => $recId,
            'status'           => 'failed',
        ]);
    }

    /**
     * API-015: 수동 재시도
     */
    public function retry(string $roomId): string
    {
        $lastMsg = $this->messageRepo->getLastCustomerMessage($roomId);
        if (!$lastMsg) {
            ResponseHelper::error('고객 메시지가 없습니다', 'VALIDATION_ERROR', 400);
        }
        $recId = $this->aiRepo->insertPending($roomId, $lastMsg['id']);
        AiJobQueue::push([
            'type'      => 'customer_message',
            'roomId'    => $roomId,
            'messageId' => $lastMsg['id'],
            'recId'     => $recId,
        ]);
        return $recId;
    }

    /**
     * 상담 종료 시 summarize + crm (async)
     */
    public function onRoomClose(string $roomId): void
    {
        foreach (['summarize', 'appointment', 'crm_record'] as $feature) {
            AiJobQueue::push(['type' => 'room_close', 'roomId' => $roomId, 'feature' => $feature]);
        }
    }
}
```

---

## 3. PromptLoader

### 3.1 ai_prompts 테이블

[01_DB설계.md](../03_SYSTEM/01_DB설계.md) §5.11 — `prompt_id`, `role`, `version`, `system_prompt`, `user_template`, `json_schema`, `is_active`.

### 3.2 PromptLoader 클래스

```php
<?php
declare(strict_types=1);

final class PromptLoader
{
    public function __construct(
        private AiPromptRepository $repo,
    ) {}

    /**
     * feature key → prompt_id 매핑
     * @see 04_AI/03_AI엔진구현.md §7 Feature Keys
     */
    private const FEATURE_MAP = [
        'chat_recommend' => 'PROMPT_RECOMMEND_v1.0',
        'summarize'      => 'PROMPT_SUMMARY_v1.0',
        'analyze'        => 'PROMPT_CUSTOMER_ANALYSIS_v1.0',
        'sentiment'      => 'PROMPT_SENTIMENT_v1.0',
        'contract_prob'  => 'PROMPT_CONTRACT_PROB_v1.0',
        'faq_search'     => 'PROMPT_FAQ_SEARCH_v1.0',
        'reply'          => 'PROMPT_RECOMMEND_v1.0',  // legacy alias
    ];

    public function load(string $feature): array
    {
        $promptId = self::FEATURE_MAP[$feature] ?? null;
        if (!$promptId) {
            throw new InvalidArgumentException("Unknown feature: {$feature}");
        }

        $row = $this->repo->findActiveByPromptId($promptId);
        if (!$row) {
            // Fallback: 04_AI/02_Prompt설계.md embedded default
            return $this->getEmbeddedFallback($promptId);
        }

        return [
            'system'       => $row['system_prompt'],
            'userTemplate' => $row['user_template'],
            'version'      => $row['version'],
            'promptId'     => $promptId,
        ];
    }

    public function getJsonSchema(string $promptId): array
    {
        $row = $this->repo->findActiveByPromptId($promptId);
        if ($row && !empty($row['json_schema'])) {
            return json_decode($row['json_schema'], true);
        }
        return $this->getDefaultRecommendSchema();
    }

    private function getDefaultRecommendSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['recommendations', 'contractProbability', 'sentiment', 'intent'],
            'properties' => [
                'recommendations'     => ['type' => 'array', 'maxItems' => 3],
                'contractProbability' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'sentiment'           => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']],
                'intent'              => ['type' => 'string'],
                'faq'                 => ['type' => 'array'],
                'customerTags'        => ['type' => 'array'],
            ],
        ];
    }
}
```

### 3.3 AiPromptRepository

```php
final class AiPromptRepository
{
    public function findActiveByPromptId(string $promptId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM ai_prompts WHERE prompt_id = :pid AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':pid' => $promptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
```

---

## 4. AiContextBuilder

```php
final class AiContextBuilder
{
    public function format(array $messages): string
    {
        $lines = [];
        foreach ($messages as $m) {
            $role = $m['sender_type'] === 'customer' ? '고객' : '상담원';
            $lines[] = "[{$role}] {$m['content']}";
        }
        return implode("\n", $lines);
    }

    public function buildUserPrompt(string $maskedContext, string $roomId): string
    {
        return <<<USER
다음은 상담방 {$roomId}의 최근 대화입니다. JSON 스키마에 맞게 분석하세요.

---
{$maskedContext}
---
USER;
    }
}
```

---

## 5. Async Worker 옵션

### 5.1 옵션 비교

| 방식 | 장점 | 단점 | MVP 권장 |
|------|------|------|:--------:|
| **Redis List Queue + cron** | 단순, Cafe24 호환, 무추가 프로세스 | 1분 latency 가능 | ✅ **V1.0** |
| PHP `exec()` background | 즉시 실행 | 호스팅 exec 제한 | △ |
| Redis BRPOP daemon | 실시간 | 별도 systemd service | V1.5 |
| BullMQ (Node) | Enterprise | 새 스택 | V2.0 |

### 5.2 MVP 권장: Redis List + Cron

```
MessageService
    └─ AiJobQueue::push()  → RPUSH acep:ai:jobs

Cron (매 1분):
    php cli/ai_worker.php
        └─ LPOP acep:ai:jobs (batch max 10)
        └─ AiRecommendationService::processCustomerMessage()
```

### 5.3 AiJobQueue

```php
final class AiJobQueue
{
    private const QUEUE_KEY = 'acep:ai:jobs';

    public static function push(array $job): void
    {
        redis()->rPush(self::QUEUE_KEY, json_encode($job, JSON_UNESCAPED_UNICODE));
    }

    public static function popBatch(int $max = 10): array
    {
        $jobs = [];
        for ($i = 0; $i < $max; $i++) {
            $raw = redis()->lPop(self::QUEUE_KEY);
            if ($raw === false || $raw === null) break;
            $jobs[] = json_decode($raw, true);
        }
        return $jobs;
    }
}
```

### 5.4 cli/ai_worker.php

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$service = container()->get(AiRecommendationService::class);
$jobs = AiJobQueue::popBatch(10);

foreach ($jobs as $job) {
    match ($job['type'] ?? '') {
        'customer_message' => $service->processCustomerMessage(
            $job['roomId'],
            $job['messageId'],
            $job['recId']
        ),
        'room_close' => $service->processRoomCloseFeature(
            $job['roomId'],
            $job['feature']
        ),
        default => error_log('[AI Worker] Unknown job type'),
    };
}
```

**Crontab:**

```cron
* * * * * cd /var/www/acep && php cli/ai_worker.php >> logs/ai_worker.log 2>&1
```

### 5.5 V1.5 Upgrade Path

Redis `BRPOP` blocking worker (systemd):

```php
while (true) {
    $raw = redis()->brPop(['acep:ai:jobs'], 5);
    if ($raw) process(json_decode($raw[1], true));
}
```

---

## 6. onCustomerMessage() 파이프라인 (Step-by-Step)

| Step | Action | Component |
|:----:|--------|-----------|
| 1 | MessageService::send commits | chat_messages INSERT |
| 2 | Rate limit check | RateLimitService::allowAiRoom |
| 3 | Skip if debounced | return aiTriggered=false |
| 4 | INSERT ai_recommendations pending | AiRecommendationRepository |
| 5 | PUBLISH ai:update pending | Redis → Chat Server |
| 6 | AiJobQueue::push | Redis list |
| 7 | **Worker** mark processing | status=processing |
| 8 | Load 20 recent messages | ChatMessageRepository |
| 9 | ai_mask_pii(context) | includes/ai.php |
| 10 | PromptLoader::load('chat_recommend') | ai_prompts |
| 11 | ai_call(system, user, opts) | Failover chain |
| 12 | Parse JSON, validate schema | BR-AI-002 max 3 recs |
| 13 | UPDATE ai_recommendations completed | is_latest=1 |
| 14 | UPDATE chat_rooms.contract_probability | priority sort |
| 15 | PUBLISH ai:update completed | Agent UI refresh |

**Timing target:** Step 7~15 ≤ 2초 (NFR-002)

---

## 7. ai_call() / ai_mask_pii() 통합

### 7.1 ai_call() 옵션 (chat_recommend)

```php
$result = ai_call($systemPrompt, $userPrompt, [
    'feature'     => 'chat_recommend',
    'target_id'   => $roomId,
    'max_tokens'  => 1536,
    'json_schema' => $recommendSchema,
]);
```

**반환 형식 (includes/ai.php):**

```php
[
    'ok'    => true|false,
    'text'  => string,
    'json'  => array|null,
    'error' => string|null,
    'usage' => ['input_tokens' => int, 'output_tokens' => int],
]
```

### 7.2 ai_mask_pii() — 호출 전 필수

```php
// includes/ai.php — 외부 AI 전송 전 반드시 적용
$safe = ai_mask_pii($rawContext);
```

마스킹 규칙: 이메일, 전화번호, 상세주소 ([02_Prompt설계.md](../04_AI/02_Prompt설계.md) §7).

### 7.3 Failover

`ai_call()` 내부 Auto mode: anthropic → openai → gemini → grok → deepseek  
Rule-001: [01_AI전략.md](../04_AI/01_AI전략.md)

---

## 8. ai_recommendations 영속화

### 8.1 AiRecommendationRepository

```php
final class AiRecommendationRepository
{
    public function insertPending(string $roomId, string $triggerMessageId): string
    {
        $id = Uuid::v4();
        // 이전 is_latest=0
        db()->prepare('UPDATE ai_recommendations SET is_latest = 0 WHERE room_id = :rid')
            ->execute([':rid' => $roomId]);

        db()->prepare(
            'INSERT INTO ai_recommendations
             (id, room_id, trigger_message_id, status, is_latest, created_at, updated_at)
             VALUES (:id, :room_id, :msg_id, \'pending\', 1, NOW(3), NOW(3))'
        )->execute([
            ':id'      => $id,
            ':room_id' => $roomId,
            ':msg_id'  => $triggerMessageId,
        ]);
        return $id;
    }

    public function complete(string $id, array $data): void
    {
        db()->prepare(
            'UPDATE ai_recommendations SET
             status = \'completed\',
             content = :content,
             contract_probability = :prob,
             sentiment = :sentiment,
             intent = :intent,
             ai_model = :model,
             latency_ms = :latency,
             prompt_version = :pver,
             updated_at = NOW(3)
             WHERE id = :id'
        )->execute([
            ':content'   => json_encode($data['content'], JSON_UNESCAPED_UNICODE),
            ':prob'      => $data['contract_probability'],
            ':sentiment' => $data['sentiment'],
            ':intent'    => $data['intent'],
            ':model'     => $data['ai_model'],
            ':latency'   => $data['latency_ms'],
            ':pver'      => $data['prompt_version'],
            ':id'        => $id,
        ]);
    }

    public function findLatestByRoom(string $roomId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM ai_recommendations WHERE room_id = :rid AND is_latest = 1 LIMIT 1'
        );
        $stmt->execute([':rid' => $roomId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
```

### 8.2 chat_rooms.contract_probability

```php
// ChatRoomRepository::updatePriorityScore
UPDATE chat_rooms SET contract_probability = :score, updated_at = NOW(3) WHERE id = :id
```

ChatList 정렬: status > contract_probability > updated_at

---

## 9. Redis Event (ai:update)

```php
$this->redis->publishRoom($roomId, 'ai:update', [
    'roomId'              => $roomId,
    'recommendationId'  => $recId,
    'status'              => 'completed',
    'contractProbability' => 87,
    'timestamp'           => date('c'),
]);
```

Chat Server relay → React `useSocket` → `fetchAiRecommendations(roomId)`

---

## 10. Debounce / Rate Limit

### 10.1 Production: Redis Rule-005

```php
final class RateLimitService
{
    public function allowAiRoom(string $roomId): bool
    {
        $key = "rl:ai:room:{$roomId}";
        $limit = (int)(getenv('RATE_LIMIT_AI_ROOM') ?: 10);
        $window = (int)(getenv('RATE_LIMIT_WINDOW_SEC') ?: 60);

        $count = (int)redis()->incr($key);
        if ($count === 1) {
            redis()->expire($key, $window);
        }
        return $count <= $limit;
    }
}
```

### 10.2 Legacy: ai_check_rate_limit() (Session 30s)

V1.0 transitional — Redis 미구성 환경:

```php
if (!RateLimitService::isRedisAvailable()) {
    return ai_check_rate_limit('chat_recommend', (int)$roomId);
}
```

### 10.3 BR-AI-003 Debounce

`ai_settings.ai_debounce_sec` = 60 — 동일 room 연속 고객 메시지 시 **queue merge** (V1.5):

```php
// pending job exists for room → update trigger_message_id only, skip new ai_call
```

---

## 11. 레거시 Bridge (admin/consults/ai_*.php)

### 11.1 매핑表

| 레거시 | feature | ACEP 대체 |
|--------|---------|-----------|
| `admin/ai_summary.php` | summarize | `ChatRoomService::close()` → AiJobQueue |
| `admin/ai_reply.php` | reply / chat_recommend | `MessageService` auto trigger |
| `admin/ai_analyze.php` | analyze | pipeline parallel (3+ msgs) |

### 11.2 Transitional Wrapper

```php
// admin/ai_reply.php — deprecated, redirect to ACEP API
header('Content-Type: application/json');
$roomId = $_POST['consult_id'] ?? '';
// Map consult_id → room_id via migration table
$service = container()->get(AiRecommendationService::class);
$service->retry($roomId);
echo json_encode(['ok' => true, 'deprecated' => true, 'use' => 'POST /api/v1/chats/{id}/messages']);
```

### 11.3 Feature Key 통일

| Legacy | ACEP Standard |
|--------|---------------|
| `reply` | `chat_recommend` |
| `ai_summary` | `summarize` |
| `ai_analyze` | `analyze` |

`ai_call(['feature' => 'chat_recommend'])` — ai_logs 일관성

---

## 12. GET /api/v1/ai/recommendations/{id}

```php
final class AiController
{
    public function getRecommendations(RequestContext $req, string $roomId): void
    {
        RbacMiddleware::assertRoomAccess($roomId, $req->user, $this->roomRepo);
        $rec = $this->aiRepo->findLatestByRoom($roomId);

        if (!$rec) {
            ResponseHelper::success([
                'roomId' => $roomId,
                'status' => 'pending',
                'recommendations' => [],
            ]);
            return;
        }

        $content = json_decode($rec['content'] ?? '{}', true);

        ResponseHelper::success([
            'roomId'              => $roomId,
            'contractProbability' => (int)$rec['contract_probability'],
            'contractLabel'       => ContractLabel::fromScore((int)$rec['contract_probability']),
            'sentiment'           => $rec['sentiment'],
            'intent'              => $rec['intent'],
            'customerTags'        => $content['customerTags'] ?? [],
            'recommendations'     => $content['recommendations'] ?? [],
            'faq'                 => $content['faq'] ?? [],
            'aiModel'             => $rec['ai_model'],
            'status'              => $rec['status'],
            'updatedAt'           => $rec['updated_at'],
        ]);
    }
}
```

---

## 13. 캐싱 (Optional V1.5)

```php
$cacheKey = "ai:rec:{$roomId}:" . md5($lastMessageHash);
$cached = redis()->get($cacheKey);
if ($cached) return json_decode($cached, true);

$result = ai_call(...);
if ($result['ok']) {
    redis()->setex($cacheKey, 3600, json_encode($result));
}
```

계약확률·감정: **캐시 없음** (MASTER 5.5)

---

## 14. V1.0 구현 체크리스트

| # | Task | Status |
|---|------|:------:|
| 1 | AiRecommendationService skeleton | 📋 |
| 2 | PromptLoader + ai_prompts seed | 📋 |
| 3 | AiJobQueue + cli/ai_worker.php | 📋 |
| 4 | MessageService hook | 📋 |
| 5 | Redis RateLimitService | 📋 |
| 6 | ai:update publish | 📋 |
| 7 | GET ai/recommendations | 📋 |
| 8 | POST ai/recommendations/retry | 📋 |
| 9 | Legacy admin bridge notes | 📋 |

---

## 15. 테스트 시나리오

| ID | Scenario | Expected |
|----|----------|----------|
| TC-AI-001 | Customer msg → worker | ai_rec completed ≤2s |
| TC-AI-002 | Rate limit 11th call/min | skip enqueue |
| TC-AI-003 | ai_call all fail | status=failed, ai:update |
| TC-AI-004 | Failover anthropic→openai | ai_failover_log |
| TC-AI-005 | PII in message | masked before ai_call |
| TC-AI-006 | Retry after failed | new rec pending |

---

## 부록 A. ai_recommendations content JSON

```json
{
  "recommendations": [
    { "id": "rec-1", "text": "설치비는 무료입니다.", "confidence": 0.92 }
  ],
  "faq": [{ "question": "설치비?", "answer": "무료" }],
  "customerTags": ["신규", "긍정"]
}
```

## 부록 B. 관련 문서

- [04_AI/03_AI엔진구현.md](../04_AI/03_AI엔진구현.md)
- [02_Backend_Chat_API_구현명세.md](02_Backend_Chat_API_구현명세.md)
- [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md)
- [_CHAT_INDEX.md](_CHAT_INDEX.md)

## 부록 C. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 4 — AI Router Service 구현명세 |

---

**문서 끝 — `includes/ai.php`는 STEP 3 명세대로 유지하고 본 Service 레이어만 신규 추가한다.**
