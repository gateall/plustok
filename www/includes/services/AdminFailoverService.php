<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class AdminFailoverService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $query */
    public function list(array $query): array
    {
        if (!acep_table_exists($this->pdo, 'ai_failover_log')) {
            return ['data' => [], 'meta' => ['page' => 1, 'limit' => 20, 'total' => 0], 'summary' => $this->emptySummary()];
        }

        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];
        if (!empty($query['room_id']) || !empty($query['roomId'])) {
            $where[] = 'f.room_id = :rid';
            $params[':rid'] = (string)($query['room_id'] ?? $query['roomId']);
        }
        if (!empty($query['period_start'])) {
            $where[] = 'f.created_at >= :ps';
            $params[':ps'] = date('Y-m-d H:i:s', strtotime((string)$query['period_start']));
        }
        if (!empty($query['period_end'])) {
            $where[] = 'f.created_at < :pe';
            $params[':pe'] = date('Y-m-d H:i:s', strtotime((string)$query['period_end']));
        }
        if (!empty($query['provider'])) {
            $where[] = 'f.primary_model LIKE :prov';
            $params[':prov'] = '%' . (string)$query['provider'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $countSt = $this->pdo->prepare("SELECT COUNT(*) FROM ai_failover_log f WHERE {$whereSql}");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql = "SELECT f.* FROM ai_failover_log f WHERE {$whereSql}
                ORDER BY f.created_at DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $data = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $data[] = $this->mapRow($r);
        }

        return [
            'data'    => $data,
            'meta'    => ['page' => $page, 'limit' => $limit, 'total' => $total],
            'summary' => $this->summary(),
        ];
    }

    public function get(int $id): array
    {
        $st = $this->pdo->prepare('SELECT * FROM ai_failover_log WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) {
            acep_error('ADMIN_RESOURCE_NOT_FOUND', 'Failover 로그를 찾을 수 없습니다.', 404);
        }
        return $this->mapRow($row);
    }

    /** @return array<string,mixed> */
    private function summary(): array
    {
        if (!acep_table_exists($this->pdo, 'ai_failover_log')) {
            return $this->emptySummary();
        }
        $last24 = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM ai_failover_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetchColumn();

        $failRate = 0.0;
        if (acep_table_exists($this->pdo, 'ai_logs')) {
            $total = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM ai_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn();
            if ($total > 0) {
                $failRate = round($last24 / $total * 100, 1);
            }
        }

        return [
            'last24hCount'              => $last24,
            'primaryFailureRatePercent' => $failRate,
        ];
    }

    /** @return array<string,mixed> */
    private function emptySummary(): array
    {
        return ['last24hCount' => 0, 'primaryFailureRatePercent' => 0.0];
    }

    /** @param array<string,mixed> $r */
    private function mapRow(array $r): array
    {
        [$fromProvider, $fromModel] = $this->splitModel((string)$r['primary_model']);
        [$toProvider, $toModel] = $this->splitModel((string)$r['failover_model']);

        return [
            'id'           => (int)$r['id'],
            'createdAt'    => date('c', strtotime((string)$r['created_at'])),
            'chatRoomId'   => $r['room_id'],
            'fromProvider' => $fromProvider,
            'fromModel'    => $fromModel,
            'toProvider'   => $toProvider,
            'toModel'      => $toModel,
            'primaryModel' => $r['primary_model'],
            'failoverModel'=> $r['failover_model'],
            'reason'       => $r['reason'],
            'latencyMs'    => isset($r['latency_ms']) ? (int)$r['latency_ms'] : null,
            'recommendationId' => $r['recommendation_id'],
        ];
    }

    /** @return array{0:string,1:string} */
    private function splitModel(string $model): array
    {
        if (str_contains($model, '/')) {
            $parts = explode('/', $model, 2);
            return [$parts[0], $parts[1]];
        }
        if (str_starts_with(strtolower($model), 'gpt')) {
            return ['openai', $model];
        }
        if (str_contains(strtolower($model), 'claude')) {
            return ['anthropic', $model];
        }
        return ['unknown', $model];
    }
}
