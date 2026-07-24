<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../util/SiteSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';

final class SiteValidator
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $body */
    public function validatePayload(array $body): array
    {
        $siteCode = trim((string)($body['siteCode'] ?? $body['site_code'] ?? ''));
        $siteName = trim((string)($body['siteName'] ?? $body['site_name'] ?? ''));
        $domain = trim((string)($body['domain'] ?? ''));
        $brand = trim((string)($body['brand'] ?? ''));
        $division = trim((string)($body['division'] ?? ''));
        $persona = trim((string)($body['persona'] ?? ''));

        if ($siteCode === '' || $siteName === '' || $brand === '') {
            acep_error('VALIDATION_ERROR', 'siteCode, siteName, brand는 필수입니다.', 422);
        }
        if (SiteSchema::hasDomain($this->pdo) && $domain === '') {
            acep_error('VALIDATION_ERROR', 'domain은 필수입니다.', 422);
        }
        if (acep_column_exists($this->pdo, 'sites', 'division') && $division === '') {
            acep_error('VALIDATION_ERROR', 'division은 필수입니다.', 422);
        }
        if ($domain !== '' && !$this->isValidDomain($domain)) {
            acep_error('VALIDATION_ERROR', 'domain 형식이 올바르지 않습니다.', 422);
        }

        return [
            'site_code' => mb_substr($siteCode, 0, 50),
            'site_name' => mb_substr($siteName, 0, 100),
            'domain' => $domain !== '' ? mb_substr($domain, 0, 150) : '',
            'brand' => mb_substr($brand, 0, 50),
            'division' => $division !== '' ? mb_substr($division, 0, 50) : '',
            'persona' => $persona !== '' ? mb_substr($persona, 0, 255) : null,
        ];
    }

    private function isValidDomain(string $domain): bool
    {
        if (preg_match('#^https?://#i', $domain)) {
            return filter_var($domain, FILTER_VALIDATE_URL) !== false;
        }
        return (bool)preg_match('/^[A-Za-z0-9.-]+(?:\:[0-9]{1,5})?(?:\/.*)?$/', $domain);
    }
}
