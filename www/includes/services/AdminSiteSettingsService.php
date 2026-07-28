<?php
declare(strict_types=1);

class AdminSiteSettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSettings(): array
    {
        if (!acep_table_exists($this->pdo, 'site_settings')) {
            return [
                'siteTitle' => 'PlusTok 통합 CRM',
                'logoUrl' => null,
                'adminNotifyEmail' => null,
                'updatedAt' => null,
            ];
        }
        $row = $this->pdo->query('SELECT * FROM site_settings WHERE id = 1 LIMIT 1')->fetch();
        if (!$row) {
            return [
                'siteTitle' => 'PlusTok 통합 CRM',
                'logoUrl' => null,
                'adminNotifyEmail' => null,
                'updatedAt' => null,
            ];
        }
        return [
            'siteTitle' => (string)$row['site_title'],
            'logoUrl' => $row['logo_url'] !== null ? (string)$row['logo_url'] : null,
            'adminNotifyEmail' => $row['admin_notify_email'] !== null ? (string)$row['admin_notify_email'] : null,
            'updatedAt' => $row['updated_at'] ? date('c', strtotime((string)$row['updated_at'])) : null,
        ];
    }

    public function updateSettings(array $data): array
    {
        if (!acep_table_exists($this->pdo, 'site_settings')) {
            acep_error('NOT_FOUND', 'site_settings 테이블이 아직 마이그레이션되지 않았습니다.', 404);
        }

        $siteTitle = trim((string)($data['siteTitle'] ?? ''));
        if ($siteTitle === '') {
            acep_error('VALIDATION_ERROR', '사이트 제목을 입력해주세요.', 400);
        }
        $logoUrl = isset($data['logoUrl']) ? trim((string)$data['logoUrl']) : '';
        $notifyEmail = isset($data['adminNotifyEmail']) ? trim((string)$data['adminNotifyEmail']) : '';
        if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            acep_error('VALIDATION_ERROR', '이메일 형식을 확인해주세요.', 400);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE site_settings
             SET site_title = :title, logo_url = :logo, admin_notify_email = :email, updated_at = NOW()
             WHERE id = 1'
        );
        $stmt->execute([
            ':title' => mb_substr($siteTitle, 0, 120, 'UTF-8'),
            ':logo' => $logoUrl !== '' ? mb_substr($logoUrl, 0, 500, 'UTF-8') : null,
            ':email' => $notifyEmail !== '' ? $notifyEmail : null,
        ]);

        return $this->getSettings();
    }
}
