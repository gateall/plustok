<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/http_auth.php';
acep_restore_authorization_header();

/**
 * Frontend(JWT) 로그인 → Admin(PHP 세션) SSO 브릿지.
 * 통합 로그인 화면(/frontend/#/login)에서 admin/operator 역할로 로그인 성공 시
 * 이 엔드포인트를 호출해 PHP 세션을 세팅하고, 그 다음 /admin/dashboard.php로 이동한다.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/acep.users.php';
require_once __DIR__ . '/../includes/repositories/AgentRepository.php';
require_once __DIR__ . '/../includes/util/JwtHelper.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
}

$claims = JwtHelper::decode($m[1]);
if ($claims === null || empty($claims['sub'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$agentRepo = new AgentRepository(db());
$agent = $agentRepo->findById((string)$claims['sub']);
if (!$agent) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Agent not found']);
    exit;
}

$userManager = new AcepUserManager($agentRepo);
$publicUser = $userManager->toPublicUser($agent);

if (!in_array($publicUser['role'] ?? '', ['admin', 'operator'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

session_regenerate_id(true);
$_SESSION['acep_user'] = $publicUser;
$_SESSION['acep_jwt'] = $userManager->createAccessToken($publicUser);
$_SESSION['manager'] = AcepUserManager::legacyManagerSession($publicUser);
$_SESSION['login_fail'] = 0;

log_activity('login.sso', 'agent:' . $publicUser['userId']);

echo json_encode(['success' => true, 'role' => $publicUser['role']]);
