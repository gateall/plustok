<?php
declare(strict_types=1);
/**
 * 관리자 인증·권한·CSRF·활동로그. 모든 admin 페이지의 진입에서 include 한다.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

define('INC_DIR', __DIR__);

secure_session_start();

/** 현재 로그인 관리자 (없으면 null) */
function current_manager(): ?array
{
    return $_SESSION['manager'] ?? null;
}

/** 로그인 필수. 아니면 로그인 페이지로. */
function require_login(): void
{
    if (!current_manager()) {
        header('Location: /admin/');
        exit;
    }
}

/** 권한 필수. 허용 role 목록에 없으면 403. */
function require_role(array $roles): void
{
    $m = current_manager();
    if (!$m || !in_array($m['role'], $roles, true)) {
        http_response_code(403);
        echo '접근 권한이 없습니다.';
        exit;
    }
}

/** 관리(사이트/상품/담당자/설정) 권한 여부 */
function can_manage(): bool
{
    $m = current_manager();
    return $m && in_array($m['role'], ['super', 'admin'], true);
}

/** 상담 처리(상태변경/배정) 권한 여부 */
function can_edit_consult(): bool
{
    $m = current_manager();
    return $m && in_array($m['role'], ['super', 'admin', 'manager', 'sales'], true);
}

/** CSRF 토큰 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

/** CSRF 검증 (실패 시 403 종료) */
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
        http_response_code(403);
        echo '잘못된 요청입니다. 새로고침 후 다시 시도하세요.';
        exit;
    }
}

/** 활동 로그 기록 */
function log_activity(string $action, ?string $target = null, ?string $detail = null): void
{
    $m = current_manager();
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (manager_id, action, target, detail, ip)
             VALUES (:mid, :action, :target, :detail, :ip)'
        );
        $mid = $m['id'] ?? null;
        if (is_string($mid) || !is_numeric($mid)) {
            $mid = null;
        }
        $stmt->execute([
            ':mid' => $mid,
            ':action' => $action,
            ':target' => $target,
            ':detail' => $detail,
            ':ip' => client_ip(),
        ]);
    } catch (Throwable $e) {
        log_error('log_activity', $e->getMessage());
    }
}

/** ACEP 통합 사용자 (agents JWT 세션) */
function current_acep_user(): ?array
{
    return $_SESSION['acep_user'] ?? null;
}

function acep_access_token(): ?string
{
    $t = $_SESSION['acep_jwt'] ?? null;
    if (is_string($t) && $t !== '') {
        require_once __DIR__ . '/util/JwtHelper.php';
        $claims = JwtHelper::decode($t);
        if (
            $claims !== null
            && !empty($claims['sub'])
            && !empty($claims['role'])
            && ($claims['typ'] ?? '') !== 'refresh'
        ) {
            return $t;
        }
    }

    return acep_refresh_access_token();
}

/** 세션 acep_user(또는 legacy manager→agents 매칭)로 access JWT 재발급 */
function acep_refresh_access_token(): ?string
{
    $user = current_acep_user() ?? acep_resolve_user_from_manager();
    if ($user === null) {
        return null;
    }

    require_once __DIR__ . '/../config/acep.users.php';
    require_once __DIR__ . '/repositories/AgentRepository.php';
    $token = (new AcepUserManager(new AgentRepository(db())))->createAccessToken($user);
    $_SESSION['acep_jwt'] = $token;
    if (current_acep_user() === null) {
        $_SESSION['acep_user'] = $user;
    }

    return $token;
}

/** legacy managers 세션 → agents 테이블 login_id 매칭 시 ACEP public user */
function acep_resolve_user_from_manager(): ?array
{
    $m = current_manager();
    if (!$m || empty($m['login_id'])) {
        return null;
    }

    require_once __DIR__ . '/../migrations/lib.php';
    $pdo = db();
    if (!acep_table_exists($pdo, 'agents')) {
        return null;
    }

    require_once __DIR__ . '/repositories/AgentRepository.php';
    require_once __DIR__ . '/../config/acep.users.php';
    $repo = new AgentRepository($pdo);
    $agent = $repo->findByLoginId((string)$m['login_id']);
    if (!$agent) {
        return null;
    }

    return (new AcepUserManager($repo))->toPublicUser($agent);
}
