<?php
declare(strict_types=1);
/**
 * 상담 답변을 고객 이메일로 발송 (POST 전용)
 * AI 초안을 수정하거나 직접 작성한 내용을 고객에게 실제로 보내는 콜백 엔드포인트.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'POST 요청만 가능합니다.']);
    exit;
}

csrf_check();

if (!can_edit_consult()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '권한이 없습니다.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['consult_id'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => '잘못된 상담 ID입니다.']);
    exit;
}
if ($message === '') {
    echo json_encode(['ok' => false, 'error' => '보낼 내용을 입력하세요.']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT c.id, c.consult_no, cu.email, cu.name AS cust_name
     FROM consults c
     JOIN customers cu ON cu.id = c.customer_id
     WHERE c.id = :id LIMIT 1"
);
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();

if (!$c) {
    echo json_encode(['ok' => false, 'error' => '상담 정보를 찾을 수 없습니다.']);
    exit;
}
if (empty($c['email'])) {
    echo json_encode(['ok' => false, 'error' => '이 고객은 등록된 이메일이 없습니다.']);
    exit;
}

$subject = '[' . APP_BRAND . '] 문의하신 내용에 대한 답변 (' . $c['consult_no'] . ')';
$body = $c['cust_name'] . "님,\n\n" . $message . "\n\n감사합니다.\n" . APP_BRAND;

$sent = acep_send_mail($c['email'], $subject, $body, 'consult_reply');

if ($sent) {
    $mgr = current_manager();
    $mid = is_numeric($mgr['id'] ?? null) ? (int)$mgr['id'] : null;
    $pdo->prepare(
        'INSERT INTO consult_history (consult_id, from_status, to_status, manager_id, note)
         VALUES (:cid, NULL, :to, :mid, :note)'
    )->execute([
        ':cid' => $id,
        ':to' => 'reply_sent',
        ':mid' => $mid,
        ':note' => '고객 이메일 회신 발송',
    ]);
    log_activity('consult_reply_sent', 'consult:' . $c['consult_no']);
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => '메일 발송에 실패했습니다.']);
}
