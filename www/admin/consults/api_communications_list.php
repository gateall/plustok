<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(array('super', 'admin', 'agent', 'manager', 'sales'));

header('Content-Type: application/json; charset=utf-8');

$consult_id = (int)($_GET['consult_id'] ?? 0);
if ($consult_id <= 0) {
    echo json_encode(['ok' => false, 'error' => '상담 ID가 필요합니다.']);
    exit;
}

$pdo = db();

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

$stmt = $pdo->prepare("
    SELECT c.*, m.name AS manager_name
    FROM consult_communications c
    LEFT JOIN managers m ON m.id = c.manager_id
    WHERE c.consult_id = :cid
    ORDER BY c.id DESC
");
$stmt->execute([':cid' => $consult_id]);
$items = $stmt->fetchAll();

echo json_encode(['ok' => true, 'items' => $items]);
