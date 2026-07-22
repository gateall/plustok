<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';

final class ConsultRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByRoomId(string $roomId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM consults
             WHERE JSON_UNQUOTE(JSON_EXTRACT(detail_json, '$.room_id')) = :rid
             LIMIT 1"
        );
        $st->execute([':rid' => $roomId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByConsultNo(string $consultNo): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM consults WHERE consult_no = :no LIMIT 1');
        $st->execute([':no' => $consultNo]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getConsultNo(int $consultId): string
    {
        $st = $this->pdo->prepare('SELECT consult_no FROM consults WHERE id = :id');
        $st->execute([':id' => $consultId]);
        return (string)$st->fetchColumn();
    }

    /**
     * @param array<string,mixed> $room
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $analysis
     * @param array<string,mixed>|null $feedback
     */
    public function upsertFromRoom(
        array $room,
        int $legacyCustomerId,
        array $summary,
        array $analysis,
        ?array $feedback
    ): int {
        $roomId = (string)$room['id'];
        $existing = $this->findByRoomId($roomId);
        $detail = [
            'room_id'     => $roomId,
            'agent_id'    => $room['agent_id'] ?? null,
            'channel'     => $room['channel'] ?? 'web',
            'closed_at'   => $room['closed_at'] ?? date('Y-m-d H:i:s'),
        ];
        $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE);
        $memo = $this->buildMemo($feedback, $room);

        if ($existing) {
            $this->pdo->prepare(
                'UPDATE consults SET
                    customer_id = :cid, category = :cat, product_name = :pname,
                    status = :status, detail_json = :detail, memo = :memo,
                    ai_summary = :summary, ai_summary_at = NOW(),
                    category_ai = :cat_ai, lead_score = :score, priority = :prio,
                    sentiment = :sent, tags = :tags, ai_analyzed_at = NOW(),
                    device = :device, updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                ':cid'     => $legacyCustomerId,
                ':cat'     => (string)($room['inquiry_type'] ?? '기타'),
                ':pname'   => (string)($room['subject'] ?? ''),
                ':status'  => 'consulting',
                ':detail'  => $detailJson,
                ':memo'    => $memo,
                ':summary' => (string)$summary['text'],
                ':cat_ai'  => (string)$analysis['category_ai'],
                ':score'   => (int)$analysis['lead_score'],
                ':prio'    => (string)$analysis['priority'],
                ':sent'    => (string)$analysis['sentiment'],
                ':tags'    => (string)$analysis['tags'],
                ':device'  => $this->mapDevice((string)($room['channel'] ?? 'web')),
                ':id'      => (int)$existing['id'],
            ]);
            return (int)$existing['id'];
        }

        $consultNo = next_consult_no($this->pdo);
        $this->pdo->prepare(
            'INSERT INTO consults
             (consult_no, customer_id, site_id, category, product_name, status, detail_json, memo,
              ai_summary, ai_summary_at, category_ai, lead_score, priority, sentiment, tags,
              ai_analyzed_at, device)
             VALUES
             (:no, :cid, 1, :cat, :pname, :status, :detail, :memo,
              :summary, NOW(), :cat_ai, :score, :prio, :sent, :tags, NOW(), :device)'
        )->execute([
            ':no'      => $consultNo,
            ':cid'     => $legacyCustomerId,
            ':cat'     => (string)($room['inquiry_type'] ?? '기타'),
            ':pname'   => (string)($room['subject'] ?? ''),
            ':status'  => 'consulting',
            ':detail'  => $detailJson,
            ':memo'    => $memo,
            ':summary' => (string)$summary['text'],
            ':cat_ai'  => (string)$analysis['category_ai'],
            ':score'   => (int)$analysis['lead_score'],
            ':prio'    => (string)$analysis['priority'],
            ':sent'    => (string)$analysis['sentiment'],
            ':tags'    => (string)$analysis['tags'],
            ':device'  => $this->mapDevice((string)($room['channel'] ?? 'web')),
        ]);
        $consultId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare(
            'INSERT INTO consult_history (consult_id, from_status, to_status, note)
             VALUES (:cid, NULL, :to, :note)'
        )->execute([
            ':cid'  => $consultId,
            ':to'   => 'consulting',
            ':note' => 'ACEP chat close auto-save',
        ]);

        return $consultId;
    }

    /** @param array<string,mixed>|null $feedback */
    private function buildMemo(?array $feedback, array $room): ?string
    {
        $parts = [];
        if (!empty($room['memo'])) {
            $parts[] = (string)$room['memo'];
        }
        if ($feedback !== null && !empty($feedback['memo'])) {
            $parts[] = '[Agent] ' . trim((string)$feedback['memo']);
        }
        if ($feedback !== null && isset($feedback['rating'])) {
            $parts[] = '[Rating] ' . (int)$feedback['rating'] . '/5';
        }
        return $parts === [] ? null : implode("\n", $parts);
    }

    private function mapDevice(string $channel): string
    {
        return match ($channel) {
            'kakao' => 'kakao',
            'mobile', 'app' => 'mobile',
            default => 'web',
        };
    }
}
