<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../util/SiteSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class SiteService
{
    public function __construct(
        private SiteRepository $sites,
        private SiteValidator $validator,
        private AuditService $audit,
        private PDO $pdo,
    ) {
    }

    /** @param array<string,mixed> $query */
    public function list(array $query): array
    {
        $data = [];
        foreach ($this->sites->findAll($query) as $row) {
            $data[] = $this->mapSite($row, true);
        }
        return ['data' => $data];
    }

    public function get(int $id): array
    {
        return $this->mapSite($this->requireSite($id), true);
    }

    /** @param array<string,mixed> $body */
    public function create(string $actorId, array $body): array
    {
        $payload = $this->validator->validatePayload($body);
        $payload['api_key'] = bin2hex(random_bytes(32));
        $payload['is_active'] = $this->normalizeStatus($body['status'] ?? $body['isActive'] ?? true);

        try {
            $id = $this->sites->create($payload);
        } catch (Throwable $e) {
            $this->handleWriteFailure($e, '사이트를 생성할 수 없습니다. site_code 중복 여부를 확인해 주세요.');
        }

        $this->audit->agentAction($actorId, 'site.create', 'site', (string)$id, [
            'siteCode' => $payload['site_code'],
            'domain' => $payload['domain'],
        ]);

        return [
            'id' => $id,
            'apiKey' => $payload['api_key'],
            'message' => 'API Key는 지금만 표시됩니다. 안전한 곳에 저장하세요.',
        ];
    }

    /** @param array<string,mixed> $body */
    public function update(string $actorId, int $id, array $body): array
    {
        $this->requireSite($id);
        $payload = $this->validator->validatePayload($body);

        try {
            $this->sites->update($id, $payload);
        } catch (Throwable $e) {
            $this->handleWriteFailure($e, '사이트를 수정할 수 없습니다.');
        }

        $this->audit->agentAction($actorId, 'site.update', 'site', (string)$id, [
            'siteCode' => $payload['site_code'],
        ]);

        return $this->get($id);
    }

    public function delete(string $actorId, int $id): array
    {
        $site = $this->requireSite($id);
        if ($this->sites->consultCount($id) > 0) {
            acep_error('SITE_IN_USE', '상담 이력이 있는 사이트는 삭제할 수 없습니다.', 409);
        }

        $this->sites->delete($id);
        $this->audit->agentAction($actorId, 'site.delete', 'site', (string)$id, [
            'siteCode' => $site['site_code'],
        ]);

        return ['deleted' => true, 'id' => $id];
    }

    public function regenerateKey(string $actorId, int $id): array
    {
        $site = $this->requireSite($id);
        $apiKey = bin2hex(random_bytes(32));
        $this->sites->updateApiKey($id, $apiKey);
        $this->audit->agentAction($actorId, 'site.regenerate_key', 'site', (string)$id, [
            'siteCode' => $site['site_code'],
        ]);

        return [
            'id' => $id,
            'apiKey' => $apiKey,
            'message' => '새 API Key는 지금만 표시됩니다. 안전한 곳에 저장하세요.',
        ];
    }

    public function toggle(string $actorId, int $id): array
    {
        $before = $this->requireSite($id);
        $this->sites->toggleActive($id);
        $after = $this->requireSite($id);
        $this->audit->agentAction($actorId, 'site.toggle', 'site', (string)$id, [
            'from' => $this->siteIsActive($before),
            'to' => $this->siteIsActive($after),
        ]);
        return [
            'id' => $id,
            'status' => $this->siteIsActive($after),
        ];
    }

    public function health(int $id): array
    {
        $this->requireSite($id);
        $rows = [];
        foreach ($this->sites->healthHistory($id) as $row) {
            $rows[] = $this->mapHealthRow($row);
        }
        return ['data' => $rows];
    }

    public function runHealthCheck(string $actorId, int $id): array
    {
        $site = $this->requireSite($id);
        $result = $this->executeHealthCheck($site);
        $this->sites->recordHealthCheck($id, $result);
        $this->audit->agentAction($actorId, 'site.health_check', 'site', (string)$id, [
            'siteCode' => $site['site_code'],
            'statusCode' => $result['status_code'],
            'healthy' => $result['is_healthy'],
        ]);

        return $this->mapHealthRow($result);
    }

    public function stats(int $id): array
    {
        $this->requireSite($id);
        return [
            'id' => $id,
            'todayConsultCount' => $this->sites->todayConsultCount($id),
            'totalConsultCount' => $this->sites->consultCount($id),
            'lastConsultedAt' => $this->toIso($this->sites->lastConsultAt($id)),
            'lastCommunicationAt' => $this->toIso($this->sites->lastCommunicationAt($id)),
        ];
    }

    private function requireSite(int $id): array
    {
        $site = $this->sites->findById($id);
        if (!$site) {
            acep_error('SITE_NOT_FOUND', '사이트를 찾을 수 없습니다.', 404);
        }
        return $site;
    }

    /** @param array<string,mixed> $row */
    private function mapSite(array $row, bool $includeStats = false): array
    {
        $siteId = (int)$row['id'];
        $latestHealth = $this->sites->latestHealth($siteId);

        $payload = [
            'id' => $siteId,
            'siteCode' => (string)$row['site_code'],
            'siteName' => (string)$row['site_name'],
            'domain' => (string)($row['domain'] ?? ''),
            'brand' => (string)($row['brand'] ?? ''),
            'division' => (string)($row['division'] ?? ''),
            'persona' => $row['persona'] ?? null,
            'status' => $this->siteIsActive($row),
            'createdAt' => $this->toIso($row['created_at'] ?? null),
            'updatedAt' => $this->toIso($row['updated_at'] ?? $row['created_at'] ?? null),
            'lastHealthCheck' => $latestHealth ? $this->mapHealthRow($latestHealth) : null,
        ];

        if ($includeStats) {
            $payload['todayConsultCount'] = $this->sites->todayConsultCount($siteId);
            $payload['totalConsultCount'] = $this->sites->consultCount($siteId);
        }

        return $payload;
    }

    /** @param array<string,mixed> $row */
    private function mapHealthRow(array $row): array
    {
        return [
            'checkedAt' => $this->toIso($row['checked_at'] ?? null),
            'isHealthy' => (bool)($row['is_healthy'] ?? false),
            'responseMs' => isset($row['response_ms']) ? (int)$row['response_ms'] : null,
            'statusCode' => isset($row['status_code']) ? (int)$row['status_code'] : null,
            'errorMessage' => $row['error_message'] ?? null,
            'targetUrl' => $row['target_url'] ?? null,
        ];
    }

    /** @param array<string,mixed> $site */
    private function executeHealthCheck(array $site): array
    {
        $domain = trim((string)($site['domain'] ?? ''));
        $checkedAt = date('Y-m-d H:i:s');
        $targetUrl = $this->buildHealthUrl($domain);

        if (acep_is_test_mode()) {
            return [
                'checked_at' => $checkedAt,
                'is_healthy' => true,
                'response_ms' => 1,
                'status_code' => 200,
                'error_message' => null,
                'target_url' => $targetUrl,
            ];
        }

        $start = microtime(true);
        $statusCode = null;
        $error = null;
        $headers = @get_headers($targetUrl, true);
        $responseMs = (int)round((microtime(true) - $start) * 1000);

        if ($headers === false) {
            $error = 'request_failed';
        } else {
            $firstLine = is_array($headers) ? (array_key_exists(0, $headers) ? (string)$headers[0] : '') : (string)$headers;
            if (preg_match('/\s(\d{3})\s/', $firstLine, $m)) {
                $statusCode = (int)$m[1];
            }
        }

        $healthy = $statusCode !== null && $statusCode >= 200 && $statusCode < 400;
        if (!$healthy && $error === null && $statusCode === null) {
            $error = 'invalid_response';
        }

        return [
            'checked_at' => $checkedAt,
            'is_healthy' => $healthy,
            'response_ms' => $responseMs,
            'status_code' => $statusCode,
            'error_message' => $error,
            'target_url' => $targetUrl,
        ];
    }

    private function buildHealthUrl(string $domain): string
    {
        if ($domain === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $domain)) {
            return $domain;
        }
        return 'https://' . $domain;
    }

    private function normalizeStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'active', 'enabled', 'yes', 'y'], true) ? 1 : 0;
    }

    private function siteIsActive(array $row): bool
    {
        $column = SiteSchema::activeColumn($this->pdo);
        if ($column === '') {
            return true;
        }
        return (int)($row[$column] ?? 0) === 1;
    }

    private function toIso(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return date('c', strtotime($value));
    }

    private function handleWriteFailure(Throwable $e, string $message): void
    {
        if ($e instanceof AcepHttpResponse) {
            throw $e;
        }
        acep_error('SITE_WRITE_FAILED', $message, 409);
    }
}
