<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/http_auth.php';
acep_restore_authorization_header();

/**
 * ACEP API DI bootstrap — index.php & PHPUnit 공용
 * BUG-001 hotfix: Phase3(Admin/CRM) 파일 없어도 MVP API 기동
 * @return array<string,mixed>
 */
function acep_bootstrap(bool $forceReload = false): array
{
    static $container = null;
    if ($forceReload) {
        $container = null;
    }
    if ($container !== null) {
        return $container;
    }

    require_once __DIR__ . '/../../config/app.php';
    require_once __DIR__ . '/../../config/acep.php';
    require_once __DIR__ . '/../../includes/db.php';
    require_once __DIR__ . '/../../includes/api_envelope.php';
    require_once __DIR__ . '/../../includes/middleware/CorsMiddleware.php';
    require_once __DIR__ . '/../../includes/middleware/JwtMiddleware.php';
    require_once __DIR__ . '/../../includes/repositories/AgentRepository.php';
    require_once __DIR__ . '/../../includes/repositories/CustomerRepository.php';
    require_once __DIR__ . '/../../includes/repositories/ContractRepository.php';
    require_once __DIR__ . '/../../includes/services/AdminContractService.php';
    require_once __DIR__ . '/../../includes/services/AdminCustomerService.php';
    require_once __DIR__ . '/../../includes/repositories/AuditLogRepository.php';
    require_once __DIR__ . '/../../includes/repositories/ChatRoomRepository.php';
    require_once __DIR__ . '/../../includes/repositories/ChatMessageRepository.php';
    require_once __DIR__ . '/../../includes/repositories/ReadStatusRepository.php';
    require_once __DIR__ . '/../../includes/repositories/AiRecommendationRepository.php';
    require_once __DIR__ . '/../../includes/repositories/AiPromptRepository.php';
    require_once __DIR__ . '/../../includes/repositories/SiteRepository.php';
    require_once __DIR__ . '/../../includes/repositories/AttachmentRepository.php';
    require_once __DIR__ . '/../../includes/ai_router_service.php';
    require_once __DIR__ . '/../../includes/services/AuditService.php';
    require_once __DIR__ . '/../../includes/services/AuthService.php';
    require_once __DIR__ . '/../../includes/services/CustomerService.php';
    require_once __DIR__ . '/../../includes/services/AgentService.php';
    require_once __DIR__ . '/../../includes/services/ChatService.php';
    require_once __DIR__ . '/../../includes/services/MessageService.php';
    require_once __DIR__ . '/../../includes/services/AiRecommendationService.php';
    require_once __DIR__ . '/../../includes/services/FileService.php';
    require_once __DIR__ . '/../../includes/repositories/NotificationRepository.php';
    require_once __DIR__ . '/../../includes/services/NotificationService.php';
    require_once __DIR__ . '/../../includes/services/SettingsService.php';
    require_once __DIR__ . '/../../includes/services/SearchService.php';
    require_once __DIR__ . '/../../includes/services/DashboardService.php';
    require_once __DIR__ . '/../../includes/validation/SiteValidator.php';
    require_once __DIR__ . '/../../includes/services/SiteService.php';
    require_once __DIR__ . '/../../includes/controllers/SiteController.php';
    require_once __DIR__ . '/../../includes/repositories/ProductRepository.php';
    require_once __DIR__ . '/../../includes/services/AdminProductService.php';
    require_once __DIR__ . '/../../includes/controllers/AdminProductController.php';
    require_once __DIR__ . '/../../includes/services/AdminAiSettingsService.php';

    $pdo = db();
    $agents = new AgentRepository($pdo);
    $customers = new CustomerRepository($pdo);
    $rooms = new ChatRoomRepository($pdo);
    $messages = new ChatMessageRepository($pdo);
    $readStatus = new ReadStatusRepository($pdo);
    $aiRecs = new AiRecommendationRepository($pdo);
    $attachments = new AttachmentRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService($agents, $audit);
    $adminContractSvc = new AdminContractService(new ContractRepository($pdo), $audit, $pdo);
    $adminCustomerSvc = new AdminCustomerService($customers);
    $customerSvc = new CustomerService($customers, $audit);
    $agentSvc = new AgentService($agents, $audit);
    $dashboardSvc = new DashboardService($pdo);

    $crmCloseSvc = null;
    $crmClosePath = __DIR__ . '/../../includes/services/CrmCloseService.php';
    if (is_file($crmClosePath)) {
        try {
            require_once __DIR__ . '/../../includes/repositories/ConsultRepository.php';
            require_once __DIR__ . '/../../includes/repositories/ScheduleRepository.php';
            require_once __DIR__ . '/../../includes/repositories/CustomerBridgeRepository.php';
            require_once __DIR__ . '/../../includes/util/CrmSchema.php';
            require_once __DIR__ . '/../../includes/services/CrmAiPipeline.php';
            require_once $crmClosePath;
            $consultRepo = new ConsultRepository($pdo);
            $scheduleRepo = new ScheduleRepository($pdo);
            $bridgeRepo = new CustomerBridgeRepository($pdo);
            $crmAi = new CrmAiPipeline();
            $crmCloseSvc = new CrmCloseService(
                $rooms, $messages, $customers, $consultRepo, $bridgeRepo, $scheduleRepo, $crmAi, $pdo
            );
        } catch (Throwable $e) {
            $crmCloseSvc = null;
            log_api_error($e);
        }
    }

    $chatSvc = new ChatService(
        $rooms, $customers, $agents, $messages, $readStatus, $aiRecs, $audit, $crmCloseSvc
    );
    $aiRouter = new AiRouterService($aiRecs, new AiPromptRepository($pdo), $messages, $rooms, $customers);
    $messageSvc = new MessageService($rooms, $messages, $readStatus, $aiRecs, $chatSvc, $audit, $aiRouter);
    $aiSvc = new AiRecommendationService($aiRecs, $chatSvc);
    $fileSvc = new FileService($attachments, $chatSvc, $audit);
    $notificationSvc = new NotificationService(new NotificationRepository($pdo));
    $settingsSvc = new SettingsService($agents);
    $searchSvc = new SearchService($customers, $rooms);
    $siteSvc = new SiteService(new SiteRepository($pdo), new SiteValidator($pdo), $audit, $pdo);
    $siteController = new SiteController($siteSvc);
    $productRepo = new ProductRepository($pdo);
    $adminProductSvc = new AdminProductService($productRepo, $audit, $pdo);
    $productController = new AdminProductController($adminProductSvc);
    $adminAiSettingsSvc = new AdminAiSettingsService($pdo);

    $adminStatsSvc = null;
    $adminAgentSvc = null;
    $adminMonitorSvc = null;
    $adminConsultSvc = null;
    $adminPromptSvc = null;
    $adminFailoverSvc = null;
    $adminStatsPath = __DIR__ . '/../../includes/services/AdminStatsService.php';
    if (is_file($adminStatsPath)) {
        try {
            require_once $adminStatsPath;
            require_once __DIR__ . '/../../includes/services/AdminAgentService.php';
            require_once __DIR__ . '/../../includes/services/AdminMonitorService.php';
            require_once __DIR__ . '/../../includes/services/AdminConsultService.php';
            require_once __DIR__ . '/../../includes/services/AdminPromptService.php';
            require_once __DIR__ . '/../../includes/services/AdminFailoverService.php';
            if (!class_exists('ConsultRepository', false)) {
                require_once __DIR__ . '/../../includes/repositories/ConsultRepository.php';
            }
            $consultRepo = new ConsultRepository($pdo);
            $adminStatsSvc = new AdminStatsService($pdo);
            $adminAgentSvc = new AdminAgentService($agents, $rooms, $audit, $pdo);
            $adminMonitorSvc = new AdminMonitorService($rooms, $messages, $customers, $agents, $aiRecs, $pdo);
            $adminConsultSvc = new AdminConsultService($rooms, $customers, $agents, $consultRepo, $pdo);
            $adminPromptSvc = new AdminPromptService($pdo, $audit);
            $adminFailoverSvc = new AdminFailoverService($pdo);
        } catch (Throwable $e) {
            $adminStatsSvc = null;
            $adminAgentSvc = null;
            $adminMonitorSvc = null;
            $adminConsultSvc = null;
            $adminPromptSvc = null;
            $adminFailoverSvc = null;
            log_api_error($e);
        }
    }

    $container = compact(
        'pdo', 'agents', 'customers', 'rooms', 'messages', 'readStatus', 'aiRecs',
        'attachments', 'audit', 'auth', 'customerSvc', 'agentSvc', 'chatSvc',
        'aiRouter', 'messageSvc', 'aiSvc', 'fileSvc',
        'notificationSvc', 'settingsSvc', 'searchSvc', 'dashboardSvc', 'siteSvc', 'siteController',
        'crmCloseSvc', 'adminStatsSvc', 'adminAgentSvc', 'adminMonitorSvc', 'adminConsultSvc',
        'adminPromptSvc', 'adminFailoverSvc', 'adminContractSvc', 'adminCustomerSvc',
        'adminProductSvc', 'productController', 'adminAiSettingsSvc',
    );

    return $container;
}

function log_api_error(Throwable $e): void
{
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0750, true);
    }
    $line = sprintf("[%s] api: %s in %s:%d\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine());
    @file_put_contents(LOG_PATH . '/error-' . date('Ymd') . '.log', $line, FILE_APPEND | LOCK_EX);
}
