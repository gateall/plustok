<?php
declare(strict_types=1);
/** 담당자관리 (SPEC.md B-6) — super/admin만 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $loginId = clean_str($_POST['login_id'] ?? '', 50);
        $name = clean_str($_POST['name'] ?? '', 60);
        $phone = normalize_phone((string)($_POST['phone'] ?? ''));
        $role = clean_str($_POST['role'] ?? 'manager', 20);
        $pw = (string)($_POST['password'] ?? '');
        if (!in_array($role, ROLES, true)) { $role = 'manager'; }
        if ($loginId === '' || $name === '' || strlen($pw) < 8) {
            $flash = 'ID·이름·비밀번호(8자 이상)를 입력하세요.';
        } else {
            try {
                $pdo->prepare('INSERT INTO managers (login_id, password, name, phone, role) VALUES (:id,:pw,:name,:phone,:role)')
                    ->execute([':id' => $loginId, ':pw' => password_hash($pw, PASSWORD_ALGO), ':name' => $name, ':phone' => $phone ?: null, ':role' => $role]);
                log_activity('user_create', 'manager:' . $loginId);
                $flash = '담당자를 추가했습니다.';
            } catch (Throwable $ex) { $flash = '추가 실패(중복 ID일 수 있음).'; }
        }
    } elseif ($action === 'resetpw') {
        $id = (int)($_POST['id'] ?? 0);
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) >= 8) {
            $pdo->prepare('UPDATE managers SET password = :pw WHERE id = :id')
                ->execute([':pw' => password_hash($pw, PASSWORD_ALGO), ':id' => $id]);
            log_activity('user_resetpw', 'manager:' . $id);
            $flash = '비밀번호를 변경했습니다.';
        } else { $flash = '비밀번호는 8자 이상.'; }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id !== (int)current_manager()['id']) {
            $pdo->prepare('UPDATE managers SET status = 1 - status WHERE id = :id')->execute([':id' => $id]);
            $flash = '상태를 변경했습니다.';
        } else { $flash = '본인 계정은 비활성화할 수 없습니다.'; }
    }
}

$rows = $pdo->query("SELECT id, login_id, name, phone, role, status, last_login FROM managers ORDER BY id")->fetchAll();
$page_title = '담당자관리'; $active = 'users';
require INC_DIR . '/header.php';
?>
<h1 class="page">담당자관리</h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>
<div class="tablewrap" style="margin-bottom:20px">
  <table>
    <thead><tr><th>ID</th><th>이름</th><th>연락처</th><th>권한</th><th>상태</th><th>최근 로그인</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= e($r['login_id']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td class="mono"><?= e($r['phone'] ?? '-') ?></td>
          <td><?= e($r['role']) ?></td>
          <td><?= (int)$r['status'] === 1 ? '사용' : '<span class="muted">중지</span>' ?></td>
          <td class="muted"><?= e($r['last_login'] ?? '-') ?></td>
          <td>
            <form method="post" style="display:inline" onsubmit="var p=prompt('새 비밀번호(8자 이상)'); if(!p)return false; this.password.value=p; return true;">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="resetpw">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="password" value="">
              <button class="btn sub" style="padding:5px 8px;font-size:12px">비번변경</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn sub" style="padding:5px 8px;font-size:12px"><?= (int)$r['status'] === 1 ? '중지' : '사용' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="card">
  <h3 style="margin-top:0">담당자 추가</h3>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div><label>로그인 ID</label><input name="login_id" required></div>
      <div><label>이름</label><input name="name" required></div>
      <div><label>연락처</label><input name="phone"></div>
      <div><label>권한</label>
        <select name="role">
          <?php foreach (ROLES as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>비밀번호(8자 이상)</label><input type="password" name="password" minlength="8" required></div>
    </div>
    <button class="btn" style="margin-top:12px">추가</button>
  </form>
  <p class="muted" style="font-size:13px;margin-top:10px">권한: super/admin=전체·관리, manager=상담처리, sales=상담조회·처리, viewer=조회전용</p>
</div>
<?php require INC_DIR . '/footer.php'; ?>
