<?php
declare(strict_types=1);

final class AuditLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(
        string $actorType,
        ?string $actorId,
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $metadata = null
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO audit_logs (actor_type, actor_id, action, resource_type, resource_id,
             ip_address, user_agent, metadata)
             VALUES (:at, :aid, :action, :rt, :rid, :ip, :ua, :meta)'
        );
        $st->execute([
            ':at'     => $actorType,
            ':aid'    => $actorId,
            ':action' => $action,
            ':rt'     => $resourceType,
            ':rid'    => $resourceId,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'     => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            ':meta'   => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
