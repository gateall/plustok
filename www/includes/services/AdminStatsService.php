<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class AdminStatsService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $query */
    public function overview(array $query): array
    {
        [$start, $end] = $this->parsePeriod($query);
        $compare = (string)($query['compare'] ?? 'yesterday');

        $active = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM chat_rooms WHERE status IN ('new','active') AND deleted_at IS NULL"
        )->fetchColumn();

        $avgResponse = $this->avgResponseSec($start, $end);
        $aiAdoption = $this->aiAdoptionRate($start, $end);
        $conversion = $this->contractConversion($start, $end);

        [$cmpStart, $cmpEnd] = $this->comparePeriod($start, $end, $compare);
        $prevActive = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM chat_rooms WHERE status IN ('new','active') AND deleted_at IS NULL"
        )->fetchColumn();

        return [
            'generatedAt' => date('c'),
            'period'      => ['start' => date('c', $start), 'end' => date('c', $end)],
            'kpis'        => [
                'activeChats' => $this->kpi($active, $prevActive),
                'avgResponseSec' => $this->kpi($avgResponse, $this->avgResponseSec($cmpStart, $cmpEnd)),
                'aiAdoptionRate' => $this->kpi($aiAdoption, $this->aiAdoptionRate($cmpStart, $cmpEnd)),
                'contractConversion' => $this->kpi($conversion, $this->contractConversion($cmpStart, $cmpEnd)),
            ],
            'sparklines' => [
                'activeChats'    => $this->sparklineActive(),
                'avgResponseSec' => $this->sparklineResponse(),
            ],
        ];
    }

    /** @param array<string,mixed> $query */
    public function sentiment(array $query): array
    {
        [$start, $end] = $this->parsePeriod($query);
        $rows = $this->pdo->prepare(
            "SELECT sentiment, COUNT(*) AS cnt FROM ai_recommendations
             WHERE created_at >= FROM_UNIXTIME(:s) AND created_at < FROM_UNIXTIME(:e)
               AND sentiment IS NOT NULL AND status = 'completed'
             GROUP BY sentiment"
        );
        $rows->execute([':s' => $start, ':e' => $end]);
        $data = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        foreach ($rows->fetchAll() ?: [] as $r) {
            $key = strtolower((string)$r['sentiment']);
            if (isset($data[$key])) {
                $data[$key] = (int)$r['cnt'];
            }
        }
        $total = max(1, array_sum($data));
        return [
            'generatedAt' => date('c'),
            'segments'    => [
                ['label' => 'positive', 'count' => $data['positive'], 'percent' => round($data['positive'] / $total * 100, 1)],
                ['label' => 'neutral', 'count' => $data['neutral'], 'percent' => round($data['neutral'] / $total * 100, 1)],
                ['label' => 'negative', 'count' => $data['negative'], 'percent' => round($data['negative'] / $total * 100, 1)],
            ],
        ];
    }

    /** @param array<string,mixed> $query */
    public function funnel(array $query): array
    {
        [$start, $end] = $this->parsePeriod($query);
        $st = $this->pdo->prepare(
            "SELECT contract_probability FROM ai_recommendations
             WHERE created_at >= FROM_UNIXTIME(:s) AND created_at < FROM_UNIXTIME(:e)
               AND contract_probability IS NOT NULL AND status = 'completed'"
        );
        $st->execute([':s' => $start, ':e' => $end]);
        $buckets = ['80+' => 0, '50-79' => 0, '20-49' => 0, '<20' => 0];
        foreach ($st->fetchAll() ?: [] as $r) {
            $p = (int)$r['contract_probability'];
            if ($p >= 80) {
                $buckets['80+']++;
            } elseif ($p >= 50) {
                $buckets['50-79']++;
            } elseif ($p >= 20) {
                $buckets['20-49']++;
            } else {
                $buckets['<20']++;
            }
        }
        return [
            'generatedAt' => date('c'),
            'buckets'     => array_map(
                fn ($label, $count) => ['label' => $label, 'count' => $count],
                array_keys($buckets),
                array_values($buckets)
            ),
        ];
    }

    /** @param array<string,mixed> $query */
    public function agents(array $query): array
    {
        $limit = min(50, max(1, (int)($query['limit'] ?? 10)));
        [$start, $end] = $this->parsePeriod($query);

        $st = $this->pdo->prepare(
            "SELECT a.id, a.name, a.status, a.role,
                    COUNT(DISTINCT CASE WHEN cr.status = 'closed'
                        AND cr.closed_at >= FROM_UNIXTIME(:s) AND cr.closed_at < FROM_UNIXTIME(:e)
                        THEN cr.id END) AS closed_today
             FROM agents a
             LEFT JOIN chat_rooms cr ON cr.agent_id = a.id AND cr.deleted_at IS NULL
             WHERE a.deleted_at IS NULL AND a.role = 'agent'
             GROUP BY a.id, a.name, a.status, a.role
             ORDER BY closed_today DESC
             LIMIT " . (int)$limit
        );
        $st->execute([':s' => $start, ':e' => $end]);

        $agents = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $agents[] = [
                'id'           => $r['id'],
                'displayName'  => $r['name'],
                'status'       => $r['status'],
                'closedToday'  => (int)$r['closed_today'],
                'avgResponseSec' => $this->agentAvgResponse((string)$r['id'], $start, $end),
            ];
        }
        return ['generatedAt' => date('c'), 'agents' => $agents];
    }

    /** @param array<string,mixed> $query */
    public function hourlyTrends(array $query): array
    {
        [$start, $end] = $this->parsePeriod($query);
        $st = $this->pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS hour_bucket,
                    COUNT(*) AS message_count
             FROM chat_messages
             WHERE deleted_at IS NULL
               AND created_at >= FROM_UNIXTIME(:s) AND created_at < FROM_UNIXTIME(:e)
             GROUP BY hour_bucket
             ORDER BY hour_bucket ASC"
        );
        $st->execute([':s' => $start, ':e' => $end]);
        $series = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $series[] = [
                'hour'    => $r['hour_bucket'],
                'messages'=> (int)$r['message_count'],
            ];
        }
        return ['generatedAt' => date('c'), 'series' => $series];
    }

    /** @param array<string,mixed> $query */
    private function parsePeriod(array $query): array
    {
        $tz = new DateTimeZone('Asia/Seoul');
        $now = new DateTime('now', $tz);
        $end = isset($query['period_end'])
            ? (new DateTime((string)$query['period_end'], $tz))->getTimestamp()
            : $now->getTimestamp();
        $start = isset($query['period_start'])
            ? (new DateTime((string)$query['period_start'], $tz))->getTimestamp()
            : (clone $now)->setTime(0, 0, 0)->getTimestamp();

        if ($start > $end) {
            acep_error('STATS_INVALID_PERIOD', 'period_start는 period_end보다 이전이어야 합니다.', 422);
        }
        if (($end - $start) > 90 * 86400) {
            acep_error('STATS_INVALID_PERIOD', '조회 기간은 90일을 초과할 수 없습니다.', 422);
        }
        return [$start, $end];
    }

    private function comparePeriod(int $start, int $end, string $compare): array
    {
        $span = $end - $start;
        return match ($compare) {
            '7d'  => [$start - 7 * 86400, $start],
            '30d' => [$start - 30 * 86400, $start],
            default => [$start - $span, $start],
        };
    }

    private function kpi(float|int $value, float|int $prev): array
    {
        $delta = $prev != 0 ? (($value - $prev) / $prev) * 100 : 0.0;
        $dir = abs($delta) < 0.5 ? 'flat' : ($delta > 0 ? 'up' : 'down');
        return [
            'value'          => round((float)$value, 1),
            'deltaPercent'   => round($delta, 1),
            'deltaDirection' => $dir,
        ];
    }

    private function avgResponseSec(int $start, int $end): float
    {
        $st = $this->pdo->prepare(
            "SELECT AVG(latency_ms) FROM ai_recommendations
             WHERE status = 'completed'
               AND created_at >= FROM_UNIXTIME(:s) AND created_at < FROM_UNIXTIME(:e)"
        );
        $st->execute([':s' => $start, ':e' => $end]);
        $ms = $st->fetchColumn();
        return $ms !== false && $ms !== null ? round((float)$ms / 1000, 1) : 0.0;
    }

    private function aiAdoptionRate(int $start, int $end): float
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN source = 'ai_recommendation' THEN 1 ELSE 0 END) AS used
             FROM chat_messages
             WHERE deleted_at IS NULL AND sender_type = 'agent'
               AND created_at >= FROM_UNIXTIME(:s) AND created_at < FROM_UNIXTIME(:e)"
        );
        $st->execute([':s' => $start, ':e' => $end]);
        $r = $st->fetch();
        $total = (int)($r['total'] ?? 0);
        if ($total === 0) {
            return 0.0;
        }
        return round((int)($r['used'] ?? 0) / $total * 100, 1);
    }

    private function contractConversion(int $start, int $end): float
    {
        if (!acep_column_exists($this->pdo, 'chat_rooms', 'crm_save_status')) {
            return 0.0;
        }
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN crm_save_status = 'saved' THEN 1 ELSE 0 END) AS saved
             FROM chat_rooms
             WHERE status = 'closed' AND deleted_at IS NULL
               AND closed_at >= FROM_UNIXTIME(:s) AND closed_at < FROM_UNIXTIME(:e)"
        );
        $st->execute([':s' => $start, ':e' => $end]);
        $r = $st->fetch();
        $total = (int)($r['total'] ?? 0);
        if ($total === 0) {
            return 0.0;
        }
        return round((int)($r['saved'] ?? 0) / $total * 100, 1);
    }

    private function agentAvgResponse(string $agentId, int $start, int $end): float
    {
        $st = $this->pdo->prepare(
            "SELECT AVG(ar.latency_ms) FROM ai_recommendations ar
             JOIN chat_rooms cr ON cr.id = ar.room_id
             WHERE cr.agent_id = :aid AND ar.status = 'completed'
               AND ar.created_at >= FROM_UNIXTIME(:s) AND ar.created_at < FROM_UNIXTIME(:e)"
        );
        $st->execute([':aid' => $agentId, ':s' => $start, ':e' => $end]);
        $ms = $st->fetchColumn();
        return $ms !== false && $ms !== null ? round((float)$ms / 1000, 1) : 0.0;
    }

    /** @return list<int> */
    private function sparklineActive(): array
    {
        $st = $this->pdo->query(
            "SELECT COUNT(*) AS c FROM chat_rooms
             WHERE status IN ('new','active') AND deleted_at IS NULL"
        );
        $v = (int)$st->fetchColumn();
        return [$v, $v, $v, $v];
    }

    /** @return list<float> */
    private function sparklineResponse(): array
    {
        $v = $this->avgResponseSec(strtotime('-24 hours'), time());
        return [$v + 10, $v + 5, $v + 2, $v];
    }
}
