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

$file_id = (int)($_POST['file_id'] ?? 0);
$consult_id = (int)($_POST['consult_id'] ?? 0);

if ($file_id <= 0 || $consult_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.']);
    exit;
}

if (!can_edit_consult()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '권한 없음']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM consult_files WHERE id = :id AND consult_id = :cid LIMIT 1");
$stmt->execute([':id' => $file_id, ':cid' => $consult_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '파일을 찾을 수 없습니다.']);
    exit;
}

$file_path = __DIR__ . '/../../' . $file['saved_path'];
if (file_exists($file_path)) {
    unlink($file_path);
}

try {
    $pdo->prepare("DELETE FROM consult_files WHERE id = :id")->execute([':id' => $file_id]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB 삭제 실패']);
}
