<?php
declare(strict_types=1);

final class AiRecommendationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findLatestByRoom(string $roomId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM ai_recommendations
             WHERE room_id = :rid ORDER BY created_at DESC LIMIT 1'
        );
        $st->execute([':rid' => $roomId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function createPending(string $id, string $roomId): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO ai_recommendations (id, room_id, type, content, status)
             VALUES (:id, :room_id, \'answer\', :content, \'pending\')'
        );
        $st->execute([
            ':id'      => $id,
            ':room_id' => $roomId,
            ':content' => json_encode(['recommendations' => [], 'faq' => []], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function setProcessing(string $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE ai_recommendations SET status = \'processing\' WHERE id = :id'
        );
        $st->execute([':id' => $id]);
    }

    /** @param array<string,mixed> $content */
    public function complete(
        string $id,
        array $content,
        ?int $contractProbability,
        ?string $sentiment,
        ?string $intent,
        string $aiModel,
        string $promptVersion,
        int $latencyMs,
    ): void {
        $st = $this->pdo->prepare(
            'UPDATE ai_recommendations
             SET content = :content,
                 contract_probability = :prob,
                 sentiment = :sentiment,
                 intent = :intent,
                 status = \'completed\',
                 ai_model = :model,
                 prompt_version = :pver,
                 latency_ms = :latency
             WHERE id = :id'
        );
        $st->execute([
            ':content'   => json_encode($content, JSON_UNESCAPED_UNICODE),
            ':prob'      => $contractProbability,
            ':sentiment' => $sentiment,
            ':intent'    => $intent,
            ':model'     => $aiModel,
            ':pver'      => $promptVersion,
            ':latency'   => $latencyMs,
            ':id'        => $id,
        ]);
    }

    public function fail(string $id, string $error): void
    {
        $st = $this->pdo->prepare(
            'UPDATE ai_recommendations
             SET status = \'failed\',
                 content = JSON_SET(COALESCE(content, JSON_OBJECT()), \'$.error\', :err)
             WHERE id = :id'
        );
        $st->execute([
            ':err' => substr($error, 0, 500),
            ':id'  => $id,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function findRecentProcessingByRoom(string $roomId, int $withinSec): array
    {
        $sec = max(1, $withinSec);
        $st = $this->pdo->prepare(
            'SELECT id, status FROM ai_recommendations
             WHERE room_id = :rid
               AND created_at >= DATE_SUB(NOW(3), INTERVAL ' . $sec . ' SECOND)
             ORDER BY created_at DESC'
        );
        $st->execute([':rid' => $roomId]);
        return $st->fetchAll() ?: [];
    }
}
