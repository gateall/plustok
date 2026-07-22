<?php
declare(strict_types=1);
/** 내 정보 변경 — 로그인한 본인의 이름·연락처·비밀번호 변경 (모든 권한 접근 가능) */
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = db();

$me = current_manager();
$myId = (int)$me['id'];

// 최신 정보 로드
$st = $pdo->prepare('SELECT id, login_id, name, phone, role, last_login FROM managers WHERE id = :id LIMIT 1');
$st->execute([':id' => $myId]);
$row = $st->fetch();
if (!$row) { http_response_code(404); echo '계정을 찾을 수 없습니다.'; exit; }

$flash = ''; $flashErr = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = clean_str($_POST['name'] ?? '', 60);
        $phone = normalize_phone((string)($_POST['phone'] ?? ''));
        if ($name === '') {
            $flashErr = '이름을 입력하세요.';
        } else {
            $pdo->prepare('UPDATE managers SET name = :n, phone = :p WHERE id = :id')
                ->execute([':n' => $name, ':p' => $phone ?: null, ':id' => $myId]);
            // 세션·화면에 반영
            $_SESSION['manager']['name'] = $name;
            $row['name'] = $name; $row['phone'] = $phone;
            log_activity('account_profile', 'manager:' . $myId);
            $flash = '내 정보를 저장했습니다.';
        }
    } elseif ($action === 'password') {
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        // 현재 비밀번호 확인
        $pw = $pdo->prepare('SELECT password FROM managers WHERE id = :id LIMIT 1');
        $pw->execute([':id' => $myId]);
        $hash = (string)$pw->fetchColumn();

        if (!password_verify($cur, $hash)) {
            $flashErr = '현재 비밀번호가 올바르지 않습니다.';
        } elseif (strlen($new) < 8) {
            $flashErr = '새 비밀번호는 8자 이상이어야 합니다.';
        } elseif ($new !== $confirm) {
            $flashErr = '새 비밀번호 확인이 일치하지 않습니다.';
        } else {
            $pdo->prepare('UPDATE managers SET password = :pw WHERE id = :id')
                ->execute([':pw' => password_hash($new, PASSWORD_ALGO), ':id' => $myId]);
            log_activity('account_password', 'manager:' . $myId);
            $flash = '비밀번호를 변경했습니다.';
        }
    }
}

$page_title = '내 정보'; $active = '';
require INC_DIR . '/header.php';
?>
<h1 class="page">내 정보 변경</h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="msg err"><?= e($flashErr) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="grid2">
  <div class="card">
    <h3 style="margin-top:0">기본 정보</h3>
    <table style="margin-bottom:8px"><tbody>
      <tr><th>로그인 ID</th><td class="mono"><?= e($row['login_id']) ?></td></tr>
      <tr><th>권한</th><td><?= e($row['role']) ?></td></tr>
      <tr><th>최근 로그인</th><td class="muted"><?= e($row['last_login'] ?? '-') ?></td></tr>
    </tbody></table>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="profile">
      <label>이름 *</label>
      <input name="name" value="<?= e($row['name']) ?>" required>
      <label>연락처</label>
      <input name="phone" type="tel" inputmode="numeric" value="<?= e($row['phone'] ?? '') ?>">
      <button class="btn" style="margin-top:12px">저장</button>
    </form>
    <p class="muted" style="font-size:13px;margin-top:10px">로그인 ID와 권한은 본인이 바꿀 수 없습니다. 권한 변경은 관리자(super/admin)에게 요청하세요.</p>
  </div>

  <div class="card">
    <h3 style="margin-top:0">비밀번호 변경</h3>
    <form method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="password">
      <label>현재 비밀번호 *</label>
      <input type="password" name="current_password" required>
      <label>새 비밀번호 (8자 이상) *</label>
      <input type="password" name="new_password" minlength="8" required>
      <label>새 비밀번호 확인 *</label>
      <input type="password" name="confirm_password" minlength="8" required>
      <button class="btn" style="margin-top:12px">비밀번호 변경</button>
    </form>
  </div>
</div>
<?php require INC_DIR . '/footer.php'; ?>
