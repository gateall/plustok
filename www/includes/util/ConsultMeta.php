<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

/**
 * consult_meta(consult_id, meta_key, meta_value) — detail_json의 조회/필터 전용 파생 인덱스.
 * detail_json이 원본(full-fidelity)이고 이 테이블은 대체가 아니라 부가 인덱스일 뿐이다.
 */
final class ConsultMeta
{
    public static function tableExists(PDO $pdo): bool
    {
        return acep_table_exists($pdo, 'consult_meta');
    }

    /** detail 배열을 (consult_id, key, value) 행들로 flatten 저장. 테이블 없으면 조용히 무시. */
    public static function store(PDO $pdo, int $consultId, ?array $detail): void
    {
        if (!$detail || !self::tableExists($pdo)) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO consult_meta (consult_id, meta_key, meta_value) VALUES (:cid, :k, :v)'
        );
        foreach ($detail as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $strVal = is_scalar($value) ? (string)$value : (string)json_encode($value, JSON_UNESCAPED_UNICODE);
            $ins->execute([
                ':cid' => $consultId,
                ':k'   => mb_substr($key, 0, 60, 'UTF-8'),
                ':v'   => mb_substr($strVal, 0, 255, 'UTF-8'),
            ]);
        }
    }
}
