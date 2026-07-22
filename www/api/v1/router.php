<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * @param array<string,mixed>|null $container
 */
function acep_route(string $method, string $uri, ?array $container = null): void
{
    $c = $container ?? acep_bootstrap();
    $auth = $c['auth'];
    $customerSvc = $c['customerSvc'];
    $agentSvc = $c['agentSvc'];
    $chatSvc = $c['chatSvc'];
    $messageSvc = $c['messageSvc'];
    $aiSvc = $c['aiSvc'];
    $aiRouter = $c['aiRouter'];
    $fileSvc = $c['fileSvc'];
    $pdo = $c['pdo'];

    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    // --- Auth ---
    if ($method === 'POST' && $uri === '/auth/login') {
        $body = acep_read_json();
        $loginId = trim((string)($body['loginId'] ?? ''));
        $password = (string)($body['password'] ?? '');
        if ($loginId === '' || $password === '') {
            acep_error('VALIDATION_ERROR', 'loginId와 password가 필요합니다.', 400);
        }
        acep_success($auth->login($loginId, $password));
    }

    if ($method === 'POST' && $uri === '/auth/logout') {
        $claims = JwtMiddleware::requireAuth();
        $auth->logout((string)$claims['sub']);
        acep_success(['loggedOut' => true]);
    }

    if ($method === 'POST' && $uri === '/auth/refresh') {
        $token = $_COOKIE[ACEP_REFRESH_COOKIE] ?? '';
        if ($token === '') {
            acep_error('UNAUTHORIZED', 'Refresh 쿠키가 없습니다.', 401);
        }
        acep_success($auth->refresh($token));
    }

    if ($method === 'GET' && $uri === '/auth/me') {
        $claims = JwtMiddleware::requireAuth();
        acep_success($auth->me((string)$claims['sub']));
    }

    if ($method === 'POST' && $uri === '/auth/register') {
        $body = acep_read_json();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM agents WHERE deleted_at IS NULL')->fetchColumn();
        if ($count > 0) {
            JwtMiddleware::requireRole(['admin']);
        }
        $loginId = trim((string)($body['loginId'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $name = trim((string)($body['name'] ?? ''));
        $role = (string)($body['role'] ?? 'admin');
        if ($loginId === '' || $password === '' || $name === '') {
            acep_error('VALIDATION_ERROR', 'loginId, password, name이 필요합니다.', 400);
        }
        acep_success($auth->register($loginId, $password, $name, $role), 201);
    }

    // --- Chats (MVP) ---
    if ($method === 'GET' && $uri === '/chats/rooms') {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($chatSvc->listRooms((string)$claims['sub'], (string)$claims['role'], $_GET));
    }

    if ($method === 'POST' && $uri === '/chats/rooms') {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($chatSvc->createRoom((string)$claims['sub'], (string)$claims['role'], acep_read_json()), 201);
    }

    if ($method === 'GET' && preg_match("#^/chats/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($chatSvc->getRoom($m[1], (string)$claims['sub'], (string)$claims['role']));
    }

    if ($method === 'PUT' && preg_match("#^/chats/({$uuid})/close$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($chatSvc->closeRoom($m[1], (string)$claims['sub'], (string)$claims['role'], acep_read_json()));
    }

    if ($method === 'PUT' && preg_match("#^/chats/({$uuid})/read$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($chatSvc->markRead($m[1], (string)$claims['sub'], (string)$claims['role'], acep_read_json()));
    }

    // --- Messages (MVP + V1.5 aliases) ---
    if ($method === 'GET' && preg_match("#^/chats/({$uuid})/messages$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($messageSvc->listMessages($m[1], (string)$claims['sub'], (string)$claims['role'], $_GET));
    }

    if ($method === 'POST' && preg_match("#^/chats/({$uuid})/messages$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($messageSvc->createMessage($m[1], (string)$claims['sub'], (string)$claims['role'], acep_read_json()), 201);
    }

    if ($method === 'GET' && preg_match("#^/chat/rooms/({$uuid})/messages$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($messageSvc->listMessages($m[1], (string)$claims['sub'], (string)$claims['role'], $_GET));
    }

    if ($method === 'POST' && $uri === '/chat/messages') {
        $claims = JwtMiddleware::requireAuth();
        $body = acep_read_json();
        $roomId = trim((string)($body['roomId'] ?? $body['room_id'] ?? ''));
        if ($roomId === '') {
            acep_error('VALIDATION_ERROR', 'roomId가 필요합니다.', 400);
        }
        $payload = [
            'content' => (string)($body['content'] ?? ''),
            'source'  => (string)($body['source'] ?? 'manual'),
        ];
        acep_success($messageSvc->createMessage($roomId, (string)$claims['sub'], (string)$claims['role'], $payload), 201);
    }

    // --- AI ---
    if ($method === 'GET' && preg_match("#^/ai/recommendations/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($aiSvc->getByRoom($m[1], (string)$claims['sub'], (string)$claims['role']));
    }

    if ($method === 'POST' && preg_match("#^/ai/recommendations/({$uuid})/retry$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($aiSvc->retry($m[1], (string)$claims['sub'], (string)$claims['role'], $aiRouter), 202);
    }

    // --- Customers ---
    if ($method === 'GET' && preg_match("#^/customers/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($customerSvc->getById($m[1], (string)$claims['sub'], (string)$claims['role']));
    }

    if ($method === 'PUT' && preg_match("#^/customers/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($customerSvc->update($m[1], (string)$claims['sub'], (string)$claims['role'], acep_read_json()));
    }

    // --- Agents ---
    if ($method === 'PUT' && preg_match("#^/agents/({$uuid})/status$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        $body = acep_read_json();
        acep_success($agentSvc->updateStatus(
            $m[1],
            (string)$claims['sub'],
            (string)$claims['role'],
            (string)($body['status'] ?? '')
        ));
    }

    if ($method === 'PUT' && $uri === '/agents/me/profile') {
        $claims = JwtMiddleware::requireAuth();
        acep_success($agentSvc->updateProfile((string)$claims['sub'], acep_read_json()));
    }

    // --- Files ---
    if ($method === 'POST' && $uri === '/files/upload') {
        $claims = JwtMiddleware::requireAuth();
        if (!isset($_FILES['file'])) {
            acep_error('VALIDATION_ERROR', 'file 필드가 필요합니다.', 400);
        }
        $roomId = trim((string)($_POST['roomId'] ?? ''));
        if ($roomId === '') {
            acep_error('VALIDATION_ERROR', 'roomId가 필요합니다.', 400);
        }
        acep_success($fileSvc->upload((string)$claims['sub'], (string)$claims['role'], $_FILES['file'], $roomId), 201);
    }

    if ($method === 'GET' && preg_match("#^/files/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($fileSvc->getById($m[1], (string)$claims['sub'], (string)$claims['role']));
    }

    // --- V1.5 (loaded when services exist) ---
    if (isset($c['notificationSvc'], $c['settingsSvc'], $c['searchSvc'], $c['dashboardSvc'])) {
        acep_route_v15($method, $uri, $c);
    }

    // --- Phase 3: CRM ---
    if (isset($c['crmCloseSvc'])) {
        acep_route_crm($method, $uri, $c);
    }

    // --- Phase 3: Admin ---
    if (isset($c['adminStatsSvc'])) {
        acep_route_admin($method, $uri, $c);
    }

    // --- System / Health ---
    if ($method === 'GET' && $uri === '/health') {
        $dbOk = false;
        try {
            $pdo->query('SELECT 1');
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }
        acep_success([
            'status'    => $dbOk ? 'ok' : 'degraded',
            'timestamp' => date('c'),
            'version'   => '1.5',
        ]);
    }

    if ($method === 'GET' && $uri === '/system/health') {
        $dbOk = false;
        $latency = null;
        try {
            $t0 = microtime(true);
            $pdo->query('SELECT 1');
            $latency = (int)((microtime(true) - $t0) * 1000);
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }
        acep_success([
            'status'     => $dbOk ? 'healthy' : 'degraded',
            'version'    => '3.0.0-phase1',
            'components' => [
                'database' => ['status' => $dbOk ? 'up' : 'down', 'latencyMs' => $latency],
            ],
        ]);
    }

    acep_error('ROOM_NOT_FOUND', 'API 경로를 찾을 수 없습니다.', 404);
}

/** @param array<string,mixed> $c */
function acep_route_v15(string $method, string $uri, array $c): void
{
    $notificationSvc = $c['notificationSvc'];
    $settingsSvc = $c['settingsSvc'];
    $searchSvc = $c['searchSvc'];
    $dashboardSvc = $c['dashboardSvc'];
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    if ($method === 'GET' && $uri === '/notifications') {
        $claims = JwtMiddleware::requireAuth();
        acep_success($notificationSvc->listForAgent((string)$claims['sub'], $_GET));
    }

    if ($method === 'PUT' && preg_match("#^/notifications/({$uuid})/read$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireAuth();
        acep_success($notificationSvc->markRead($m[1], (string)$claims['sub']));
    }

    if ($method === 'GET' && $uri === '/settings') {
        $claims = JwtMiddleware::requireAuth();
        acep_success($settingsSvc->getForAgent((string)$claims['sub']));
    }

    if ($method === 'PUT' && $uri === '/settings') {
        $claims = JwtMiddleware::requireAuth();
        acep_success($settingsSvc->updateForAgent((string)$claims['sub'], acep_read_json()));
    }

    if ($method === 'GET' && $uri === '/search/customers') {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($searchSvc->searchCustomers((string)$claims['sub'], (string)$claims['role'], $_GET));
    }

    if ($method === 'GET' && $uri === '/search/chats') {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($searchSvc->searchChats((string)$claims['sub'], (string)$claims['role'], $_GET));
    }

    if ($method === 'GET' && $uri === '/dashboard/stats') {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($dashboardSvc->stats((string)$claims['sub'], (string)$claims['role']));
    }
}

/** @param array<string,mixed> $c */
function acep_route_crm(string $method, string $uri, array $c): void
{
    $crmCloseSvc = $c['crmCloseSvc'];
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    if ($method === 'POST' && $uri === '/consults/close') {
        $claims = JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        $body = acep_read_json();
        $roomId = trim((string)($body['roomId'] ?? $body['room_id'] ?? ''));
        if ($roomId === '') {
            acep_error('VALIDATION_ERROR', 'roomId가 필요합니다.', 400);
        }
        $agentId = trim((string)($body['agentId'] ?? $body['agent_id'] ?? $claims['sub']));
        $feedback = isset($body['feedback']) && is_array($body['feedback']) ? $body['feedback'] : null;
        acep_success($crmCloseSvc->execute(
            $roomId,
            $agentId,
            (string)$claims['role'],
            $feedback,
            (bool)($body['force'] ?? false),
            isset($body['summaryOverride']) ? (string)$body['summaryOverride'] : null,
            true,
        ));
    }

    if ($method === 'POST' && $uri === '/consults/close/retry') {
        $claims = JwtMiddleware::requireRole(['admin', 'operator']);
        $body = acep_read_json();
        $roomId = trim((string)($body['roomId'] ?? $body['room_id'] ?? ''));
        if ($roomId === '') {
            acep_error('VALIDATION_ERROR', 'roomId가 필요합니다.', 400);
        }
        acep_success($crmCloseSvc->execute(
            $roomId,
            (string)$claims['sub'],
            (string)$claims['role'],
            null,
            true,
            null,
            false,
        ));
    }

    if ($method === 'GET' && preg_match('#^/consults/([A-Za-z0-9\\-]+)$#', $uri, $m)) {
        JwtMiddleware::requireRole(['agent', 'admin', 'operator']);
        acep_success($crmCloseSvc->getConsult($m[1]));
    }
}

/** @param array<string,mixed> $c */
function acep_route_admin(string $method, string $uri, array $c): void
{
    $adminStatsSvc = $c['adminStatsSvc'];
    $adminAgentSvc = $c['adminAgentSvc'];
    $adminMonitorSvc = $c['adminMonitorSvc'];
    $adminConsultSvc = $c['adminConsultSvc'];
    $adminPromptSvc = $c['adminPromptSvc'];
    $adminFailoverSvc = $c['adminFailoverSvc'];
    $settingsSvc = $c['settingsSvc'];
    $audit = $c['audit'];
    $pdo = $c['pdo'];
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    $readRoles = ['admin', 'operator'];
    $adminWriteRoles = ['admin'];

    if ($method === 'GET' && $uri === '/admin/stats/overview') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminStatsSvc->overview($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/sentiment') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminStatsSvc->sentiment($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/funnel') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminStatsSvc->funnel($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/agents') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminStatsSvc->agents($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/trends') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminStatsSvc->hourlyTrends($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/dashboard/stats') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminStatsSvc->overview($_GET));
    }

    if ($method === 'GET' && $uri === '/admin/consults') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->list($_GET));
    }

    if ($method === 'GET' && $uri === '/admin/monitor/rooms') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminMonitorSvc->listRooms($_GET));
    }
    if ($method === 'GET' && preg_match("#^/admin/monitor/rooms/({$uuid})/insight$#i", $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminMonitorSvc->roomInsight($m[1]));
    }

    if ($method === 'GET' && $uri === '/admin/agents') {
        JwtMiddleware::requireRole(['admin']);
        acep_success($adminAgentSvc->list());
    }
    if ($method === 'POST' && $uri === '/admin/agents') {
        $claims = JwtMiddleware::requireRole(['admin']);
        acep_success($adminAgentSvc->create((string)$claims['sub'], acep_read_json()), 201);
    }
    if ($method === 'PATCH' && preg_match("#^/admin/agents/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['admin']);
        acep_success($adminAgentSvc->update((string)$claims['sub'], $m[1], (string)$claims['role'], acep_read_json()));
    }
    if ($method === 'POST' && preg_match("#^/admin/agents/({$uuid})/assignments$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['admin']);
        acep_success($adminAgentSvc->assignRoom((string)$claims['sub'], $m[1], acep_read_json()), 201);
    }
    if ($method === 'DELETE' && preg_match("#^/admin/agents/({$uuid})/assignments/(\d+)$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole(['admin']);
        acep_success($adminAgentSvc->releaseAssignment((string)$claims['sub'], $m[1], (int)$m[2]));
    }

    if ($method === 'GET' && $uri === '/admin/prompts') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminPromptSvc->list($_GET));
    }
    if ($method === 'POST' && $uri === '/admin/prompts') {
        $claims = JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminPromptSvc->create((string)$claims['sub'], acep_read_json()), 201);
    }
    if ($method === 'PATCH' && preg_match("#^/admin/prompts/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminPromptSvc->patch((string)$claims['sub'], $m[1], acep_read_json()));
    }
    if ($method === 'DELETE' && preg_match("#^/admin/prompts/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($adminWriteRoles);
        $adminPromptSvc->delete((string)$claims['sub'], $m[1], (string)$claims['role']);
        acep_success(['deleted' => true]);
    }

    if ($method === 'GET' && $uri === '/admin/failover-logs') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminFailoverSvc->list($_GET));
    }
    if ($method === 'GET' && preg_match('#^/admin/failover-logs/(\d+)$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminFailoverSvc->get((int)$m[1]));
    }

    if ($method === 'GET' && $uri === '/admin/settings') {
        $claims = JwtMiddleware::requireRole(['admin']);
        acep_success($settingsSvc->getForAgent((string)$claims['sub']));
    }
    if ($method === 'PUT' && $uri === '/admin/settings') {
        $claims = JwtMiddleware::requireRole(['admin']);
        acep_success($settingsSvc->updateForAgent((string)$claims['sub'], acep_read_json()));
    }

    if ($method === 'GET' && $uri === '/admin/audit-logs') {
        JwtMiddleware::requireRole(['admin']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $st = $pdo->query(
            'SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        );
        $total = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $data = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $data[] = [
                'id'        => $r['id'],
                'createdAt' => date('c', strtotime((string)$r['created_at'])),
                'action'    => $r['action'],
                'resource'  => $r['resource_type'] ?? '',
            ];
        }
        acep_success(['data' => $data, 'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total]]);
    }
}
