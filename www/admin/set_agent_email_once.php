<?php
declare(strict_types=1);
/**
 * 1회용: agents.email PII 암호화 등록 (비밀번호 찾기 전 필수)
 *
 * === 배경 ===
 * agents.email 은 PiiEncryptor(AES-256-GCM)로 암호화되어 저장됩니다.
 * phpMyAdmin에서 평문 UPDATE 하면 forgotPassword() 복호화 실패 → 메일 미발송(조용히 return).
 *
 * === 사용법 (브라우저 1회 실행 후 FTP에서 즉시 삭제) ===
 *
 * 방법 A — 비밀키 (관리자 로그인 불필요):
 *   /admin/set_agent_email_once.php?key=CHANGE_ME&login_id=admin&email=you@example.com
 *
 * 방법 B — 관리자 세션 (super/admin 로그인 후):
 *   /admin/set_agent_email_once.php?login_id=admin&email=you@example.com
 *
 * key는 아래 SET_EMAIL_ONCE_KEY 와 일치해야 합니다.
 * 실행 성공 시 "OK" 출력 → 이 파일을 서버에서 삭제하세요.
 */
define('SET_EMAIL_ONCE_KEY', 'plustok-set-email-20260722');

$authorized = (($_GET['key'] ?? '') === SET_EMAIL_ONCE_KEY);
if (!$authorized) {
    require_once __DIR__ . '/../includes/auth.php';
    $authorized = current_manager() !== null && can_manage();
}

if (!$authorized) {
    http_response_code(403);
    exit('Forbidden');
}

$loginId = trim((string)($_GET['login_id'] ?? ''));
$email   = trim((string)($_GET['email'] ?? ''));

if ($loginId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('login_id and valid email required');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util/PiiEncryptor.php';

$pdo = db();
$enc = PiiEncryptor::encrypt($email);

$st = $pdo->prepare(
    'UPDATE agents SET email = :email, updated_at = CURRENT_TIMESTAMP(3)
     WHERE login_id = :login_id AND deleted_at IS NULL'
);
$st->execute([':email' => $enc, ':login_id' => $loginId]);

if ($st->rowCount() === 0) {
    http_response_code(404);
    exit('Agent not found: ' . htmlspecialchars($loginId));
}

header('Content-Type: text/plain; charset=utf-8');
echo "OK: email registered for login_id={$loginId}\n";
echo "Delete this file (set_agent_email_once.php) now.\n";
