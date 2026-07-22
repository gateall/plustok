<?php
declare(strict_types=1);
/**
 * 앱 상수. 값(사이트/상태 코드 등)은 SPEC.md·DB.md와 일치시킨다.
 */

date_default_timezone_set('Asia/Seoul');

// 경로
define('BASE_PATH', dirname(__DIR__));            // /www
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH', BASE_PATH . '/logs');

// 서버/URL
define('APP_NAME', 'PlusTok 통합 CRM');
define('APP_BRAND', 'SmartTokTok CRM');
define('BASE_URL', 'https://plustok.mycafe24.com');
define('API_BASE', BASE_URL . '/api/v1');

// 관리자 알림메일 (TASK_MAIL_NOTIFY.md)
define('ADMIN_NOTIFY_EMAIL', 'adfull@naver.com'); // 상담접수 알림 수신자. 필요시 이 값만 변경
define('MAIL_FROM', 'noreply@plustok.mycafe24.com'); // 발신자. Cafe24 mail() 발신 도메인과 일치 권장(스팸 방지)

// 상담 상태 (SPEC.md C)
const CONSULT_STATUSES = [
    'new'        => '신규(접수)',
    'progress'   => '진행중',
    'consulting' => '상담중',
    'quoted'     => '견적발송',
    'contracted' => '계약완료',
    'installed'  => '설치완료',
    'hold'       => '보류',
    'canceled'   => '취소',
];

// 권한 (SPEC.md D)
const ROLES = ['super', 'admin', 'manager', 'sales', 'viewer'];

// 업로드 (STYLEGUIDE 4 / API.md 2)
const UPLOAD_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'hwp', 'hwpx'];
const UPLOAD_MAX_BYTES = 10 * 1024 * 1024; // 10MB
const UPLOAD_FILE_TYPES = ['사업자등록증', '사진', '견적서', '도면', 'PDF'];

// Rate Limit (API.md / STYLEGUIDE 4)
const RATE_LIMIT_PER_MIN = 30; // IP+site 기준 분당 허용 건수
