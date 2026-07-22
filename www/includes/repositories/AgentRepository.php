<?php
declare(strict_types=1);

final class AgentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByLoginId(string $loginId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM agents WHERE login_id = :lid AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':lid' => $loginId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findById(string $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM agents WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function updateLoginSuccess(string $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE agents SET failed_login_count = 0, locked_until = NULL,
             last_login_at = CURRENT_TIMESTAMP(3), updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id'
        );
        $st->execute([':id' => $id]);
    }

    public function incrementFailedLogin(string $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE agents SET failed_login_count = failed_login_count + 1, updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id'
        );
        $st->execute([':id' => $id]);
    }

    public function lockAccount(string $id, int $minutes): void
    {
        $st = $this->pdo->prepare(
            'UPDATE agents SET locked_until = DATE_ADD(CURRENT_TIMESTAMP(3), INTERVAL :m MINUTE),
             updated_at = CURRENT_TIMESTAMP(3) WHERE id = :id'
        );
        $st->execute([':id' => $id, ':m' => $minutes]);
    }

    public function updateStatus(string $id, string $status): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE agents SET status = :st, updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND deleted_at IS NULL'
        );
        $st->execute([':id' => $id, ':st' => $status]);
        return $st->rowCount() > 0;
    }

    public function updateProfile(string $id, array $fields): bool
    {
        $sets = [];
        $params = [':id' => $id];
        foreach (['name', 'avatar_url', 'email', 'phone'] as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[":{$col}"] = $fields[$col];
            }
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP(3)';
        $sql = 'UPDATE agents SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount() > 0;
    }

    public function create(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO agents (id, login_id, password_hash, name, email, phone, role, status)
             VALUES (:id, :login_id, :password_hash, :name, :email, :phone, :role, :status)'
        );
        $st->execute($data);
    }

    /** @param array<string,mixed> $settings */
    public function updateSettingsJson(string $id, array $settings): void
    {
        $st = $this->pdo->prepare(
            'UPDATE agents SET settings_json = :json, updated_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id AND deleted_at IS NULL'
        );
        $st->execute([
            ':id'   => $id,
            ':json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
