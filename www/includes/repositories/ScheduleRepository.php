<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

final class ScheduleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $analysis
     * @return list<int>
     */
    public function createFollowUps(int $consultId, array $analysis, string $consultNo, bool $customerIncomplete = false): array
    {
        if (!acep_table_exists($this->pdo, 'schedules')) {
            return [];
        }

        $leadScore = (int)($analysis['lead_score'] ?? 50);
        $sentiment = strtoupper((string)($analysis['sentiment'] ?? 'NEUTRAL'));
        $categoryAi = (string)($analysis['category_ai'] ?? '기타');
        $ids = [];

        if ($leadScore >= 70) {
            $ids[] = $this->insertSchedule(
                $consultId,
                'call',
                $this->scheduledAt(3, 10, 0),
                "[자동] 계약 follow-up 콜 — {$consultNo}"
            );
        } elseif ($leadScore >= 50) {
            $ids[] = $this->insertSchedule(
                $consultId,
                'email',
                $this->scheduledAt(7, 9, 0),
                '[자동] 견적 follow-up 이메일 검토'
            );
        } else {
            $ids[] = $this->insertSchedule(
                $consultId,
                'follow_up',
                $this->scheduledAt(30, 10, 0),
                "[자동] 재접촉 — {$categoryAi}"
            );
        }

        if ($sentiment === 'NEGATIVE') {
            $ids[] = $this->insertSchedule(
                $consultId,
                'satisfaction',
                $this->scheduledAt(1, 10, 0),
                '[자동] 만족도 확인'
            );
        }

        if ($customerIncomplete) {
            $ids[] = $this->insertSchedule(
                $consultId,
                'info_collect',
                $this->scheduledAt(2, 10, 0),
                '[자동] 고객 정보 보완'
            );
        }

        return array_values(array_filter($ids));
    }

    private function scheduledAt(int $daysFromNow, int $hour, int $minute): string
    {
        $dt = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $dt->modify("+{$daysFromNow} days");
        $dt->setTime($hour, $minute, 0);
        return $dt->format('Y-m-d H:i:s');
    }

    private function insertSchedule(int $consultId, string $type, string $scheduledAt, string $title): ?int
    {
        $date = substr($scheduledAt, 0, 10);
        if (acep_table_exists($this->pdo, 'schedules_dedup_guard')) {
            $chk = $this->pdo->prepare(
                'SELECT 1 FROM schedules_dedup_guard
                 WHERE consult_id = :cid AND schedule_type = :t AND scheduled_date = :d'
            );
            $chk->execute([':cid' => $consultId, ':t' => $type, ':d' => $date]);
            if ($chk->fetchColumn()) {
                return null;
            }
        }

        $this->pdo->prepare(
            'INSERT INTO schedules (consult_id, schedule_type, scheduled_at, title, created_by)
             VALUES (:cid, :type, :at, :title, \'system\')'
        )->execute([
            ':cid'   => $consultId,
            ':type'  => $type,
            ':at'    => $scheduledAt,
            ':title' => $title,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        if (acep_table_exists($this->pdo, 'schedules_dedup_guard')) {
            $this->pdo->prepare(
                'INSERT IGNORE INTO schedules_dedup_guard (consult_id, schedule_type, scheduled_date, schedule_id)
                 VALUES (:cid, :type, :d, :sid)'
            )->execute([
                ':cid'  => $consultId,
                ':type' => $type,
                ':d'    => $date,
                ':sid'  => $id,
            ]);
        }

        return $id;
    }
}
