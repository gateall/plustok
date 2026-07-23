<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role(['super', 'admin', 'agent']);
csrf_check();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST 요청만 가능합니다.']);
    exit;
}

$id = (int)($_POST['consult_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '잘못된 상담 ID입니다.']);
    exit;
}

if (!can_edit_consult()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '권한 없음']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '파일 업로드 실패']);
    exit;
}

$file = $_FILES['file'];

// 용량 제한 (20MB)
if ($file['size'] > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '파일 크기는 최대 20MB까지 허용됩니다.']);
    exit;
}

$pdo = db();

// 최대 개수 제한 (5개)
$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM consult_files WHERE consult_id = :cid');
$stmtCount->execute([':cid' => $id]);
$current_files_count = (int)$stmtCount->fetchColumn();

if ($current_files_count >= 5) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '첨부파일은 최대 5개까지만 업로드할 수 있습니다.']);
    exit;
}
$category = clean_str($_POST['file_category'] ?? 'etc', 50);

$allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'txt'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_exts)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '허용되지 않는 확장자입니다.']);
    exit;
}

$upload_dir = __DIR__ . '/../../uploads/consults/' . date('Y/m');
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = uniqid('file_', true) . '.' . $ext;
$saved_path = $upload_dir . '/' . $filename;
$relative_path = 'uploads/consults/' . date('Y/m') . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $saved_path)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '파일 저장 실패']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO consult_files (consult_id, file_category, orig_name, saved_path, file_size, file_ext, uploader_id)
     VALUES (:cid, :cat, :orig, :path, :size, :ext, :uid)"
);

$manager = current_manager();
$uid = $manager ? (int)$manager['id'] : 0;

try {
    $stmt->execute([
        ':cid' => $id,
        ':cat' => $category,
        ':orig' => $file['name'],
        ':path' => $relative_path,
        ':size' => $file['size'],
        ':ext' => $ext,
        ':uid' => $uid,
    ]);
    
    $file_id = $pdo->lastInsertId();
    
    echo json_encode([
        'ok' => true,
        'file' => [
            'id' => $file_id,
            'category' => $category,
            'orig_name' => $file['name'],
            'saved_path' => '/' . $relative_path,
            'size' => $file['size'],
            'ext' => $ext,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Throwable $e) {
    unlink($saved_path);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB 저장 실패: ' . $e->getMessage()]);
}
