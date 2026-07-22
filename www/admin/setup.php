<?php
declare(strict_types=1);
/**
 * 최초 super 관리자 생성 (1회용). managers가 비어 있을 때만 동작한다.
 * 계정 생성 후에는 자동으로 잠기므로(관리자 존재) 재실행되지 않는다.
 * 보안: 최초 세팅 직후 이 파일을 서버에서 삭제하는 것을 권장.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

secure_session_start();
$pdo = db();

// 이미 관리자가 있으면 차단
$exists = (int)$pdo->query('SELECT COUNT(*) FROM managers')->fetchColumn();
if ($exists > 0) {
    http_response_code(403);
    echo '이미 관리자가 존재합니다. 이 파일(admin/setup.php)을 삭제하세요.';
    exit;
}

$error = '';
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // CSRF
    if (!isset($_POST['_csrf'], $_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf'])) {
        $error = '잘못된 요청입니다. 새로고침 후 다시 시도하세요.';
    } else {
        $loginId = clean_str($_POST['login_id'] ?? '', 50);
        $name    = clean_str($_POST['name'] ?? '', 60);
        $pw      = (string)($_POST['password'] ?? '');

        if ($loginId === '' || $name === '' || strlen($pw) < 8) {
            $error = 'ID·이름·비밀번호(8자 이상)를 모두 입력하세요.';
        } else {
            $hash = password_hash($pw, PASSWORD_ALGO);
            $ins = $pdo->prepare(
                'INSERT INTO managers (login_id, password, name, role) VALUES (:id, :pw, :name, :role)'
            );
            $ins->execute([':id' => $loginId, ':pw' => $hash, ':name' => $name, ':role' => 'super']);
            $done = true;
        }
    }
}

// CSRF 토큰 발급
$_SESSION['_csrf'] = $_SESSION['_csrf'] ?? bin2hex(random_bytes(16));
$csrf = $_SESSION['_csrf'];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>최초 관리자 생성 · <?= e(APP_NAME) ?></title>
<style>
  body{font-family:system-ui,'Malgun Gothic',sans-serif;max-width:420px;margin:8vh auto;padding:0 20px;color:#222}
  h1{font-size:20px}
  label{display:block;margin:14px 0 4px;font-size:14px}
  input{width:100%;padding:12px;font-size:16px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
  button{margin-top:20px;width:100%;padding:14px;font-size:16px;border:0;border-radius:8px;background:#2b6cb0;color:#fff}
  .err{color:#c0392b;margin-top:12px}
  .ok{color:#1e7e34}
</style>
</head>
<body>
<h1><?= e(APP_NAME) ?> · 최초 관리자 생성</h1>
<?php if ($done): ?>
  <p class="ok">super 관리자 계정이 생성되었습니다.<br>보안을 위해 <b>admin/setup.php</b>를 서버에서 삭제한 뒤 <a href="/admin/">로그인</a>하세요.</p>
<?php else: ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>로그인 ID</label>
    <input type="text" name="login_id" required>
    <label>이름</label>
    <input type="text" name="name" required>
    <label>비밀번호 (8자 이상)</label>
    <input type="password" name="password" minlength="8" required>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <button type="submit">super 계정 생성</button>
  </form>
<?php endif; ?>
</body>
</html>
