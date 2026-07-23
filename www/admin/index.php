<?php
declare(strict_types=1);
/**
 * 관리자 로그인. (SPEC.md B / STYLEGUIDE 4)
 * 세션 + password_verify + 실패 잠금 + CSRF.
 */

require_once __DIR__ . '/../includes/auth.php';

// 이미 로그인 상태면 대시보드로
if (current_manager()) {
    header('Location: /admin/dashboard.php');
    exit;
}

// 관리자가 아직 없으면 setup으로 안내 (legacy managers 또는 ACEP agents)
require_once __DIR__ . '/../migrations/lib.php';
$pdo = db();
$hasManager = acep_table_exists($pdo, 'managers')
    ? (int)$pdo->query('SELECT COUNT(*) FROM managers')->fetchColumn()
    : 0;
$hasAgents = acep_table_exists($pdo, 'agents')
    ? (int)$pdo->query('SELECT COUNT(*) FROM agents WHERE deleted_at IS NULL')->fetchColumn()
    : 0;
if ($hasManager === 0 && $hasAgents === 0) {
    header('Location: /admin/setup.php');
    exit;
}

// 통합 로그인: 화면은 /frontend/#/login 하나만 사용, 이 페이지는 세션 처리(SSO)만 담당
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /frontend/#/login');
    exit;
}

$error = '';
$now = time();
$lockUntil = (int)($_SESSION['login_lock_until'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();

    if ($now < $lockUntil) {
        $error = '로그인 시도가 많아 잠시 잠겼습니다. ' . ($lockUntil - $now) . '초 후 다시 시도하세요.';
    } else {
        $loginId = clean_str($_POST['login_id'] ?? '', 50);
        $pw = (string)($_POST['password'] ?? '');
        $authOk = false;

        // 1) ACEP 통합 사용자 (agents — Frontend /api/v1/auth/login 과 동일)
        if (!$authOk && acep_table_exists($pdo, 'agents')) {
            require_once __DIR__ . '/../config/acep.users.php';
            $agentRepo = new AgentRepository($pdo);
            $userManager = new AcepUserManager($agentRepo);
            $agentRow = $agentRepo->findByLoginId($loginId);

            if ($agentRow && !empty($agentRow['locked_until']) && strtotime((string)$agentRow['locked_until']) > time()) {
                $error = '로그인 실패 횟수 초과로 계정이 잠겼습니다.';
                $authOk = true;
            } else {
                $publicUser = $userManager->authenticate($loginId, $pw);
                if ($publicUser && in_array($publicUser['role'], ['admin', 'operator', 'agent'], true)) {
                    session_regenerate_id(true);
                    $_SESSION['acep_user'] = $publicUser;
                    $_SESSION['acep_jwt'] = $userManager->createAccessToken($publicUser);
                    $_SESSION['manager'] = AcepUserManager::legacyManagerSession($publicUser);
                    $_SESSION['login_fail'] = 0;
                    $agentRepo->updateLoginSuccess($publicUser['userId']);
                    log_activity('login', 'agent:' . $publicUser['userId']);
                    header('Location: /admin/dashboard.php');
                    exit;
                }
                if ($agentRow) {
                    $agentRepo->incrementFailedLogin((string)$agentRow['id']);
                }
            }
        }

        // 2) Legacy managers (CRM 구버전 계정)
        if (!$authOk && acep_table_exists($pdo, 'managers')) {
            $stmt = $pdo->prepare('SELECT * FROM managers WHERE login_id = :id AND status = 1 LIMIT 1');
            $stmt->execute([':id' => $loginId]);
            $mgr = $stmt->fetch();

            if ($mgr && password_verify($pw, $mgr['password'])) {
                session_regenerate_id(true);
                $_SESSION['manager'] = [
                    'id' => (int)$mgr['id'],
                    'login_id' => $mgr['login_id'],
                    'name' => $mgr['name'],
                    'role' => $mgr['role'],
                ];
                // agents 테이블에 동일 login_id가 있으면 WebSocket/API용 JWT 세션도 세팅
                if (acep_table_exists($pdo, 'agents')) {
                    require_once __DIR__ . '/../config/acep.users.php';
                    $legacyAgentRepo = new AgentRepository($pdo);
                    $legacyAgent = $legacyAgentRepo->findByLoginId((string)$mgr['login_id']);
                    if ($legacyAgent) {
                        $legacyUserManager = new AcepUserManager($legacyAgentRepo);
                        $legacyPublicUser = $legacyUserManager->toPublicUser($legacyAgent);
                        $_SESSION['acep_user'] = $legacyPublicUser;
                        $_SESSION['acep_jwt'] = $legacyUserManager->createAccessToken($legacyPublicUser);
                    }
                }
                $_SESSION['login_fail'] = 0;
                $pdo->prepare('UPDATE managers SET last_login = NOW() WHERE id = :id')
                    ->execute([':id' => (int)$mgr['id']]);
                log_activity('login', 'manager:' . (int)$mgr['id']);
                header('Location: /admin/dashboard.php');
                exit;
            }
        }

        if ($error !== '') {
            // locked message already set
        } else {
        // 실패
        $fail = (int)($_SESSION['login_fail'] ?? 0) + 1;
        $_SESSION['login_fail'] = $fail;
        if ($fail >= LOGIN_MAX_FAIL) {
            $_SESSION['login_lock_until'] = $now + LOGIN_LOCK_SECONDS;
            $_SESSION['login_fail'] = 0;
            $error = '로그인 실패가 많아 ' . LOGIN_LOCK_SECONDS . '초간 잠깁니다.';
        } else {
            $error = 'ID 또는 비밀번호가 올바르지 않습니다. (' . $fail . '/' . LOGIN_MAX_FAIL . ')';
        }
        }
    }
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>로그인 · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="login">
  <h1><?= e(APP_BRAND) ?></h1>
  <p class="sub"><?= e(APP_NAME) ?> 관리자</p>
  <?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>로그인 ID</label>
    <input type="text" name="login_id" required autofocus>
    <label>비밀번호</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn" style="width:100%;margin-top:18px">로그인</button>
  </form>
</div>
</body>
</html>
