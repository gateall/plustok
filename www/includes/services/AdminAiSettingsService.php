<?php
declare(strict_types=1);

require_once __DIR__ . '/../ai.php';

class AdminAiSettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSettings(): array
    {
        $cfg = ai_config(true);
        $activeProvider = $cfg['provider'] ?? 'auto';
        $enabled = (bool)($cfg['enabled'] ?? false);

        $providers = ['anthropic', 'openai', 'gemini', 'grok', 'deepseek'];
        $providerSettings = [];

        foreach ($providers as $p) {
            $pCfg = ai_config_for_provider($p);
            $hasKey = !empty($pCfg['api_key']);
            $maskedKey = $hasKey ? 'sk-****' . substr($pCfg['api_key'], -4) : '';
            
            $stmt = $this->pdo->prepare('SELECT updated_at FROM ai_settings WHERE provider = :p LIMIT 1');
            $stmt->execute([':p' => $p]);
            $updatedAt = $stmt->fetchColumn() ?: null;

            $providerSettings[] = [
                'provider' => $p,
                'model' => $pCfg['model'] ?? '',
                'hasKey' => $hasKey,
                'maskedKey' => $maskedKey,
                'updatedAt' => $updatedAt ? date('c', strtotime($updatedAt)) : null,
            ];
        }

        return [
            'enabled' => $enabled,
            'activeProvider' => $activeProvider,
            'providers' => $providerSettings,
        ];
    }

    public function updateSettings(array $data): array
    {
        $enabled = (bool)($data['enabled'] ?? false);
        $activeProvider = (string)($data['activeProvider'] ?? 'auto');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE ai_provider_config SET enabled = :e, active_provider = :a, updated_at = NOW() WHERE id = 1');
            $stmt->execute([':e' => $enabled ? 1 : 0, ':a' => $activeProvider]);

            if (isset($data['providers']) && is_array($data['providers'])) {
                foreach ($data['providers'] as $p) {
                    $provider = strtolower((string)($p['provider'] ?? ''));
                    if (!$provider) continue;

                    $model = (string)($p['model'] ?? '');
                    $apiKey = (string)($p['apiKey'] ?? '');

                    $existsStmt = $this->pdo->prepare('SELECT 1 FROM ai_settings WHERE provider = :p');
                    $existsStmt->execute([':p' => $provider]);
                    $exists = $existsStmt->fetch();

                    if ($exists) {
                        if ($apiKey !== '' && !str_starts_with($apiKey, 'sk-****')) {
                            $stmt = $this->pdo->prepare('UPDATE ai_settings SET api_key = :k, model = :m, updated_at = NOW() WHERE provider = :p');
                            $stmt->execute([':p' => $provider, ':k' => $apiKey, ':m' => $model]);
                        } else {
                            $stmt = $this->pdo->prepare('UPDATE ai_settings SET model = :m, updated_at = NOW() WHERE provider = :p');
                            $stmt->execute([':p' => $provider, ':m' => $model]);
                        }
                    } else {
                        $actualKey = ($apiKey !== '' && !str_starts_with($apiKey, 'sk-****')) ? $apiKey : '';
                        $stmt = $this->pdo->prepare('INSERT INTO ai_settings (provider, api_key, model, updated_at) VALUES (:p, :k, :m, NOW())');
                        $stmt->execute([':p' => $provider, ':k' => $actualKey, ':m' => $model]);
                    }
                }
            }

            $this->pdo->commit();
            return $this->getSettings();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function testConnection(string $provider): array
    {
        $t0 = microtime(true);
        try {
            if ($provider === 'auto') {
                $res = ai_call('System Test', 'Hello, this is a connection test. Reply with "OK".');
                $providerUsed = $res['provider'] ?? 'unknown';
                $latency = (int)((microtime(true) - $t0) * 1000);
                return ['success' => true, 'provider' => $providerUsed, 'latencyMs' => $latency, 'message' => 'Connection successful'];
            } else {
                $res = ai_call_single_provider($provider, 'System Test', 'Hello, this is a connection test. Reply with "OK".');
                $latency = (int)((microtime(true) - $t0) * 1000);
                return ['success' => true, 'provider' => $provider, 'latencyMs' => $latency, 'message' => 'Connection successful'];
            }
        } catch (Throwable $e) {
            $latency = (int)((microtime(true) - $t0) * 1000);
            return ['success' => false, 'provider' => $provider, 'latencyMs' => $latency, 'error' => $e->getMessage()];
        }
    }

    public function deleteKey(string $provider): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ai_settings WHERE provider = :p');
        $stmt->execute([':p' => strtolower($provider)]);
    }
}
