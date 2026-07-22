<?php
declare(strict_types=1);

final class AuditService
{
    public function __construct(private AuditLogRepository $repo)
    {
    }

    public function agentAction(string $agentId, string $action, ?string $resourceType = null, ?string $resourceId = null, ?array $meta = null): void
    {
        $this->repo->log('agent', $agentId, $action, $resourceType, $resourceId, $meta);
    }
}
