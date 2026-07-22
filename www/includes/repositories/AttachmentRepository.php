<?php
declare(strict_types=1);

final class AttachmentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(string $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM attachments WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO attachments
             (id, room_id, message_id, uploader_type, uploader_id, file_name, mime_type,
              file_size, storage_path, public_url, checksum_sha256)
             VALUES
             (:id, :room_id, :message_id, :uploader_type, :uploader_id, :file_name, :mime_type,
              :file_size, :storage_path, :public_url, :checksum_sha256)'
        );
        $st->execute($data);
    }
}
