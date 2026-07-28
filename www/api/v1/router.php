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
    $adminContractSvc = $c['adminContractSvc'];

    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    // --- Auth ---
    if ($method === 'POST' && $uri === '/auth/login') {
        $body = acep_read_json();
        $loginId = trim((string)($body['loginId'] ?? $body['username'] ?? ''));
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

    if ($method === 'POST' && $uri === '/auth/forgot-id') {
        $body = acep_read_json();
        $name = trim((string)($body['name'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        if ($name === '' || $email === '') {
            acep_error('VALIDATION_ERROR', 'name과 email이 필요합니다.', 400);
        }
        $auth->forgotId($name, $email);
        acep_success(['sent' => true]);
    }

    if ($method === 'POST' && $uri === '/auth/forgot-password') {
        $body = acep_read_json();
        $loginId = trim((string)($body['loginId'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        if ($loginId === '' || $email === '') {
            acep_error('VALIDATION_ERROR', 'loginId와 email이 필요합니다.', 400);
        }
        $auth->forgotPassword($loginId, $email);
        acep_success(['sent' => true]);
    }

    if ($method === 'POST' && $uri === '/auth/reset-password') {
        $body = acep_read_json();
        $token = trim((string)($body['token'] ?? ''));
        $newPassword = (string)($body['newPassword'] ?? '');
        if ($token === '' || $newPassword === '') {
            acep_error('VALIDATION_ERROR', 'token과 newPassword가 필요합니다.', 400);
        }
        $auth->resetPassword($token, $newPassword);
        acep_success(['reset' => true]);
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
            'content' => (string)($body['content'] ?? $body['message'] ?? ''),
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

    // --- Admin: Contracts ---
    acep_route_contracts($method, $uri, $c);

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
        $agentsOk = false;
        $auditOk = false;
        try {
            $t0 = microtime(true);
            $pdo->query('SELECT 1');
            $latency = (int)((microtime(true) - $t0) * 1000);
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }
        if ($dbOk) {
            require_once __DIR__ . '/../../migrations/lib.php';
            if (acep_table_exists($pdo, 'agents')) {
                try {
                    $pdo->query('SELECT login_id FROM agents LIMIT 1');
                    $agentsOk = true;
                } catch (Throwable) {
                    $agentsOk = false;
                }
            }
            if (acep_table_exists($pdo, 'audit_logs')) {
                $auditOk = true;
            }
        }
        acep_success([
            'status'     => ($dbOk && $agentsOk) ? 'healthy' : 'degraded',
            'version'    => '3.0.0-phase1',
            'components' => [
                'database' => ['status' => $dbOk ? 'up' : 'down', 'latencyMs' => $latency],
                'agents'   => ['status' => $agentsOk ? 'up' : 'down'],
                'audit_logs' => ['status' => $auditOk ? 'up' : 'down'],
            ],
            'hint' => !$agentsOk
                ? 'Frontend login requires agents table — run migrations/V1.5.0__agents_ai_ops.sql'
                : null,
        ]);
    }

    acep_error('ROOM_NOT_FOUND', 'API 경로를 찾을 수 없습니다.', 404);
}

/** @param array<string,mixed> $c */
function acep_route_contracts(string $method, string $uri, array $c): void
{
    $contracts = $c['adminContractSvc'];
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    $readRoles = ['admin', 'operator'];
    $writeRoles = ['admin', 'operator'];
    $deleteRoles = ['admin'];

    // 정적 목록/생성 Route를 동적 {id} Route보다 먼저 등록한다.
    if ($method === 'GET' && $uri === '/admin/contracts') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($contracts->list($_GET));
        return;
    }
    if ($method === 'POST' && $uri === '/admin/contracts') {
        $claims = JwtMiddleware::requireRole($writeRoles);
        acep_success($contracts->create((string)$claims['sub'], acep_read_json()), 201);
        return;
    }

    // /status, /cancel, /archive 같은 하위 액션은 순수 {id} 패턴보다 먼저 매칭되도록 위에 둔다.
    if ($method === 'PATCH' && preg_match("#^/admin/contracts/({$uuid})/status$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($writeRoles);
        acep_success($contracts->updateStatus((string)$claims['sub'], $m[1], acep_read_json()));
        return;
    }
    if ($method === 'POST' && preg_match("#^/admin/contracts/({$uuid})/cancel$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($writeRoles);
        acep_success($contracts->cancel((string)$claims['sub'], $m[1], acep_read_json()));
        return;
    }
    if ($method === 'POST' && preg_match("#^/admin/contracts/({$uuid})/archive$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($writeRoles);
        acep_success($contracts->archive((string)$claims['sub'], $m[1]));
        return;
    }

    if ($method === 'GET' && preg_match("#^/admin/contracts/({$uuid})$#i", $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($contracts->get($m[1]));
        return;
    }
    if ($method === 'PUT' && preg_match("#^/admin/contracts/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($writeRoles);
        acep_success($contracts->update((string)$claims['sub'], $m[1], acep_read_json()));
        return;
    }
    if ($method === 'DELETE' && preg_match("#^/admin/contracts/({$uuid})$#i", $uri, $m)) {
        $claims = JwtMiddleware::requireRole($deleteRoles);
        acep_success($contracts->delete((string)$claims['sub'], $m[1]));
        return;
    }
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
    $adminCustomerSvc = $c['adminCustomerSvc'];
    $adminPromptSvc = $c['adminPromptSvc'];
    $adminFailoverSvc = $c['adminFailoverSvc'];
    $adminAiSettingsSvc = $c['adminAiSettingsSvc'] ?? null;
    $settingsSvc = $c['settingsSvc'];
    $siteController = $c['siteController'];
    $productController = $c['productController'];
    $audit = $c['audit'];
    $pdo = $c['pdo'];
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    $readRoles = ['admin', 'operator'];
    $adminWriteRoles = ['admin'];

    if ($siteController->route($method, $uri)) {
        return;
    }
    if ($productController->route($method, $uri)) {
        return;
    }

    if ($method === 'GET' && $uri === '/admin/stats/overview') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminStatsSvc->overview($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/sentiment') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminStatsSvc->sentiment($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/funnel') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminStatsSvc->funnel($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/agents') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminStatsSvc->agents($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/stats/trends') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminStatsSvc->hourlyTrends($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/dashboard/stats') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminStatsSvc->overview($_GET));
    }

    if ($method === 'GET' && $uri === '/admin/consults') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->list($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/consults/unread-count') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->unreadCount($_GET));
    }
    if ($method === 'GET' && $uri === '/admin/activity-feed') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->activityFeed($_GET));
    }
    // 정적 목록 Route를 동적 /admin/customers/{id} Route보다 먼저 등록한다.
    if ($method === 'GET' && $uri === '/admin/customers') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminCustomerSvc->list($_GET));
    }
    if ($method === 'GET' && preg_match('#^/admin/customers/([^/]+)$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->customer(rawurldecode($m[1])));
    }
    if ($method === 'GET' && preg_match('#^/admin/customers/([^/]+)/consults$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->customerConsultHistory(rawurldecode($m[1]), $_GET));
    }
    if ($method === 'POST' && $uri === '/admin/consults/bulk-delete') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->bulkDelete(acep_read_json()));
    }
    if ($method === 'POST' && $uri === '/admin/consults/bulk-status') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->bulkUpdateStatus(acep_read_json()));
    }
    if ($method === 'POST' && $uri === '/admin/consults/bulk-assign') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->bulkUpdateAssignee(acep_read_json()));
    }
    if ($method === 'POST' && $uri === '/admin/consults/bulk-tags') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->updateTags(acep_read_json()));
    }
    if ($method === 'PATCH' && preg_match('#^/admin/consults/([^/]+)/status$#', $uri, $m)) {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->updateStatus(rawurldecode($m[1]), acep_read_json()));
    }
    if ($method === 'PATCH' && preg_match('#^/admin/consults/([^/]+)/assign$#', $uri, $m)) {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->updateAssignee(rawurldecode($m[1]), acep_read_json()));
    }
    if ($method === 'GET' && preg_match('#^/admin/consults/([^/]+)/timeline$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->timeline(rawurldecode($m[1])));
    }
    if ($method === 'POST' && preg_match('#^/admin/consults/([^/]+)/timeline$#', $uri, $m)) {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->createTimelineEvent(rawurldecode($m[1]), acep_read_json()), 201);
    }
    if ($method === 'GET' && preg_match('#^/admin/consults/([^/]+)/attachments$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->attachments(rawurldecode($m[1])));
    }
    if ($method === 'POST' && preg_match('#^/admin/consults/([^/]+)/attachments$#', $uri, $m)) {
        JwtMiddleware::requireRole($adminWriteRoles);
        if (isset($_FILES['file']) && is_array($_FILES['file'])) {
            acep_success($adminConsultSvc->uploadAttachment(rawurldecode($m[1]), $_FILES['file'], $_POST), 201);
        }
        acep_success($adminConsultSvc->createAttachment(rawurldecode($m[1]), acep_read_json()), 201);
    }
    if ($method === 'GET' && preg_match('#^/admin/consults/([^/]+)/attachments/([^/]+)$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->attachment(rawurldecode($m[1]), rawurldecode($m[2])));
    }
    if ($method === 'DELETE' && preg_match('#^/admin/consults/([^/]+)/attachments/([^/]+)$#', $uri, $m)) {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->deleteAttachment(rawurldecode($m[1]), rawurldecode($m[2])));
    }
    if ($method === 'GET' && $uri === '/admin/consult-tags') {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->listTags($_GET));
    }
    if ($method === 'POST' && $uri === '/admin/consult-tags') {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->createTag(acep_read_json()), 201);
    }
    if ($method === 'DELETE' && preg_match('#^/admin/consult-tags/([^/]+)$#', $uri, $m)) {
        JwtMiddleware::requireRole($adminWriteRoles);
        acep_success($adminConsultSvc->deleteTag(rawurldecode($m[1])));
    }
    if ($method === 'GET' && preg_match('#^/admin/consults/([A-Za-z0-9\\-]+)$#', $uri, $m)) {
        JwtMiddleware::requireRole($readRoles);
        acep_success($adminConsultSvc->get($m[1]));
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

    if ($method === 'GET' && $uri === '/admin/settings/ai' && $adminAiSettingsSvc) {
        JwtMiddleware::requireRole(['admin', 'super']);
        acep_success($adminAiSettingsSvc->getSettings());
    }
    if ($method === 'PUT' && $uri === '/admin/settings/ai' && $adminAiSettingsSvc) {
        JwtMiddleware::requireRole(['admin', 'super']);
        acep_success($adminAiSettingsSvc->updateSettings(acep_read_json()));
    }
    if ($method === 'POST' && $uri === '/admin/settings/ai/test' && $adminAiSettingsSvc) {
        JwtMiddleware::requireRole(['admin', 'super']);
        $body = acep_read_json();
        $provider = (string)($body['provider'] ?? 'auto');
        acep_success($adminAiSettingsSvc->testConnection($provider));
    }
    if ($method === 'DELETE' && preg_match('#^/admin/settings/ai/providers/([^/]+)/key$#', $uri, $m) && $adminAiSettingsSvc) {
        JwtMiddleware::requireRole(['admin', 'super']);
        $adminAiSettingsSvc->deleteKey(rawurldecode($m[1]));
        acep_success(['deleted' => true]);
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
