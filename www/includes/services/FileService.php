<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../util/Uuid.php';
require_once __DIR__ . '/../api_envelope.php';

final class FileService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(
        private AttachmentRepository $attachments,
        private ChatService $chatSvc,
        private AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function upload(string $agentId, string $role, array $file, string $roomId): array
    {
        $this->chatSvc->requireRoomAccess($roomId, $agentId, $role);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            acep_error('VALIDATION_ERROR', '파일 업로드에 실패했습니다.', 400);
        }
        if ((int)$file['size'] > UPLOAD_MAX_BYTES) {
            acep_error('FILE_TOO_LARGE', '최대 10MB까지 업로드할 수 있습니다.', 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            acep_error('INVALID_FILE_TYPE', '허용되지 않은 파일 형식입니다.', 400);
        }

        $origName = basename((string)$file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowedExt, true)) {
            acep_error('INVALID_FILE_TYPE', '허용되지 않은 파일 확장자입니다.', 400);
        }

        $subDir = 'acep/' . date('Y/m');
        $destDir = UPLOAD_PATH . '/' . $subDir;
        if (!is_dir($destDir) && !@mkdir($destDir, 0750, true) && !is_dir($destDir)) {
            acep_error('MSG_SEND_FAILED', '저장 폴더 생성에 실패했습니다.', 500);
        }

        $savedName = uuid_v4() . '.' . $ext;
        $storagePath = $subDir . '/' . $savedName;
        $absPath = UPLOAD_PATH . '/' . $storagePath;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            acep_error('MSG_SEND_FAILED', '파일 저장에 실패했습니다.', 500);
        }

        $attId = uuid_v4();
        $publicUrl = rtrim(BASE_URL, '/') . '/uploads/' . $storagePath;
        $checksum = hash_file('sha256', $absPath) ?: null;

        $this->attachments->create([
            ':id'              => $attId,
            ':room_id'         => $roomId,
            ':message_id'      => null,
            ':uploader_type'   => 'agent',
            ':uploader_id'     => $agentId,
            ':file_name'       => $origName,
            ':mime_type'       => $mime,
            ':file_size'       => (int)$file['size'],
            ':storage_path'    => $storagePath,
            ':public_url'      => $publicUrl,
            ':checksum_sha256' => $checksum,
        ]);

        $this->audit->agentAction($agentId, 'file.upload', 'attachment', $attId, ['roomId' => $roomId]);

        $type = str_starts_with($mime, 'image/') ? 'image' : 'pdf';

        return [
            'id'   => $attId,
            'url'  => $publicUrl,
            'type' => $type,
            'name' => $origName,
            'size' => (int)$file['size'],
        ];
    }

    /** @return array<string,mixed> */
    public function getById(string $id, string $agentId, string $role): array
    {
        $row = $this->attachments->findById($id);
        if (!$row) {
            acep_error('ROOM_NOT_FOUND', '파일을 찾을 수 없습니다.', 404);
        }

        $this->chatSvc->requireRoomAccess((string)$row['room_id'], $agentId, $role);

        return [
            'id'        => $row['id'],
            'fileName'  => $row['file_name'],
            'mimeType'  => $row['mime_type'],
            'fileSize'  => (int)$row['file_size'],
            'publicUrl' => $row['public_url'],
            'createdAt' => date('c', strtotime((string)$row['created_at'])),
        ];
    }
}
