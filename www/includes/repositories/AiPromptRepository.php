<?php
declare(strict_types=1);

final class AiPromptRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findActiveByRole(string $role): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM ai_prompts WHERE role = :role AND is_active = 1 ORDER BY updated_at DESC LIMIT 1'
        );
        $st->execute([':role' => $role]);
        $row = $st->fetch();
        return $row ?: null;
    }
}
