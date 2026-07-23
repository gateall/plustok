<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role(['super', 'admin', 'agent']);

$consult_id = (int)($_GET['consult_id'] ?? 0);
if ($consult_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '잘못된 상담 ID입니다.']);
    exit;
}

$pdo = db();
try {
    $stmt = $pdo->prepare(
        "SELECT f.*, mgr.name AS uploader_name 
         FROM consult_files f 
         LEFT JOIN managers mgr ON mgr.id = f.uploader_id
         WHERE f.consult_id = :cid 
         ORDER BY f.id DESC"
    );
    $stmt->execute([':cid' => $consult_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['ok' => true, 'files' => $files]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB 조회 실패']);
}
