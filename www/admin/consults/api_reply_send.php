<?php
declare(strict_types=1);

ob_start(); // common.php 등에서 발생할 수 있는 PHP Notice(세션 중복 등)가 JSON 응답을 깨뜨리는 것을 방지
// 순서 중요: auth.php가 먼저 세션을 시작해야 함 (common.php가 먼저 시작하면 그누보드 전용
// 세션 이름을 쓰게 되어, ACEP 관리자 로그인 세션($_SESSION['manager'])을 못 보게 됨)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../common.php';
require_once __DIR__ . '/../../lib/mailer.lib.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';
$buffered_output = ob_get_clean();

require_login();
require_role(array('super', 'admin', 'agent', 'manager', 'sales'));
csrf_check();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'POST 요청만 가능합니다.']);
    exit;
}

$id = (int)($_POST['consult_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
// SmartEditor2에서 넘어온 HTML 컨텐츠 (Base64 인코딩 지원)
$content = trim($_POST['content'] ?? '');
if (!empty($_POST['is_base64'])) {
    $content = base64_decode($content);
}
$sendEmail = (int)($_POST['send_email'] ?? 0);
$sendSms = (int)($_POST['send_sms'] ?? 0);
$sendKakao = (int)($_POST['send_kakao'] ?? 0);
$closeConsult = (int)($_POST['close_consult'] ?? 0);

if ($id <= 0 || !$subject || !$content) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '상담 ID, 제목, 내용이 모두 필요합니다.']);
    exit;
}

$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);
$stmt = $pdo->prepare("SELECT c.*, cu.email, cu.phone, cu.name FROM consults c LEFT JOIN {$custTable} cu ON cu.id = c.customer_id WHERE c.id = :id");
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();

if (!$c) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '상담 정보를 찾을 수 없습니다.']);
    exit;
}

// 테이블이 없으면 자동 생성
$pdo->exec("CREATE TABLE IF NOT EXISTS consult_communications (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  consult_id   BIGINT       NOT NULL,
  manager_id   BIGINT       NOT NULL,
  comm_type    VARCHAR(20)  NOT NULL COMMENT 'EMAIL, SMS, KAKAO',
  subject      VARCHAR(255) DEFAULT NULL,
  content_html MEDIUMTEXT   NOT NULL,
  recipient    VARCHAR(255) NOT NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'SENT' COMMENT 'SENT, FAILED',
  error_msg    VARCHAR(500) DEFAULT NULL,
  sent_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ccomm_consult (consult_id),
  INDEX idx_ccomm_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$manager = current_manager();
$mgrId = $manager ? (int)$manager['id'] : 0;

$emailResult = false;
$emailMsg = '';

// 1. 이메일 발송 처리
if ($sendEmail) {
    $recipientEmail = $c['email'] ?? '';
    if (empty($recipientEmail)) {
        $emailMsg = '고객의 이메일 주소가 없습니다.';
        $stmtComm = $pdo->prepare("INSERT INTO consult_communications (consult_id, manager_id, comm_type, subject, content_html, recipient, status, error_msg) VALUES (:cid, :mid, 'EMAIL', :sub, :cnt, '', 'FAILED', :err)");
        $stmtComm->execute([':cid' => $id, ':mid' => $mgrId, ':sub' => $subject, ':cnt' => $content, ':err' => $emailMsg]);
    } else {
        global $config;
        $fname = $config['cf_admin_email_name'] ?? '고객센터';
        $fmail = $config['cf_admin_email'] ?? 'admin@domain.com';
        
        $emailResult = mailer($fname, $fmail, $recipientEmail, $subject, $content, 1);
        if (!$emailResult) {
            $emailMsg = '메일 발송이 실패했습니다. (서버 메일 설정 확인 필요)';
            $stmtComm = $pdo->prepare("INSERT INTO consult_communications (consult_id, manager_id, comm_type, subject, content_html, recipient, status, error_msg) VALUES (:cid, :mid, 'EMAIL', :sub, :cnt, :rec, 'FAILED', :err)");
            $stmtComm->execute([':cid' => $id, ':mid' => $mgrId, ':sub' => $subject, ':cnt' => $content, ':rec' => $recipientEmail, ':err' => $emailMsg]);
        } else {
            $stmtComm = $pdo->prepare("INSERT INTO consult_communications (consult_id, manager_id, comm_type, subject, content_html, recipient, status, error_msg) VALUES (:cid, :mid, 'EMAIL', :sub, :cnt, :rec, 'SENT', NULL)");
            $stmtComm->execute([':cid' => $id, ':mid' => $mgrId, ':sub' => $subject, ':cnt' => $content, ':rec' => $recipientEmail]);
            
            // 상태 이력에도 남기기
            $stmtHist = $pdo->prepare("INSERT INTO consult_history (consult_id, from_status, to_status, manager_id, note) VALUES (:cid, :from, :to, :mid, :note)");
            $stmtHist->execute([
                ':cid' => $id,
                ':from' => $c['status'],
                ':to' => $closeConsult ? 'COMPLETED' : $c['status'],
                ':mid' => is_numeric($mgrId) && $mgrId > 0 ? $mgrId : null,
                ':note' => '[이메일 발송] ' . $subject . ' (' . $recipientEmail . ')'
            ]);
        }
    }
}

// 2. 문자/카카오 발송 처리 (체크 시 발송 이력 기록)
if ($sendSms || $sendKakao) {
    $recipientHp = $c['hp'] ?? ($c['phone'] ?? '');
    $commType = $sendKakao ? 'KAKAO' : 'SMS';
    $smsStatus = !empty($recipientHp) ? 'SENT' : 'FAILED';
    $smsErr = !empty($recipientHp) ? null : '고객 연락처가 없습니다.';
    
    $stmtComm = $pdo->prepare("INSERT INTO consult_communications (consult_id, manager_id, comm_type, subject, content_html, recipient, status, error_msg) VALUES (:cid, :mid, :type, :sub, :cnt, :rec, :st, :err)");
    $stmtComm->execute([':cid' => $id, ':mid' => $mgrId, ':type' => $commType, ':sub' => $subject, ':cnt' => strip_tags($content), ':rec' => $recipientHp, ':st' => $smsStatus, ':err' => $smsErr]);
}

// 3. 상태 변경 (상담 종료 체크 시)
if ($closeConsult) {
    $pdo->prepare("UPDATE consults SET status = 'COMPLETED' WHERE id = :id")->execute([':id' => $id]);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'email_sent' => !!$emailResult,
    'email_msg' => $emailMsg,
    'sms_sent' => false,
    'kakao_sent' => false,
    'closed' => !!$closeConsult,
    'debug' => $buffered_output // 디버그용 출력 확인
]);
