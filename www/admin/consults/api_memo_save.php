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

$consult_id = (int)($_POST['consult_id'] ?? 0);
if ($consult_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '잘못된 상담 ID입니다.']);
    exit;
}

if (!can_edit_consult()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '권한 없음']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$content_html = trim((string)($_POST['content_html'] ?? ''));
if (!empty($_POST['is_base64'])) {
    $content_html = base64_decode($content_html);
}
$memo_type = clean_str($_POST['memo_type'] ?? 'general', 20);
$is_private = (int)($_POST['is_private'] ?? 1);

if ($content_html === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '메모 내용이 비어있습니다.']);
    exit;
}

$pdo = db();
$manager = current_manager();
$uid = $manager ? (int)$manager['id'] : 0;

try {
    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE consult_memos 
             SET content_html = :content, memo_type = :type, is_private = :private 
             WHERE id = :id AND consult_id = :cid"
        );
        $stmt->execute([
            ':content' => $content_html,
            ':type' => $memo_type,
            ':private' => $is_private,
            ':id' => $id,
            ':cid' => $consult_id
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO consult_memos (consult_id, manager_id, content_html, memo_type, is_private)
             VALUES (:cid, :mid, :content, :type, :private)"
        );
        $stmt->execute([
            ':cid' => $consult_id,
            ':mid' => $uid,
            ':content' => $content_html,
            ':type' => $memo_type,
            ':private' => $is_private
        ]);
        $id = (int)$pdo->lastInsertId();
    }
    
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB 저장 실패: ' . $e->getMessage()]);
}
