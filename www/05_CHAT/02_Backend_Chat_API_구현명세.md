# ACEP (PlusTok Enterprise) — Backend Chat API 구현명세

**프로젝트:** PlusTok V1.0 → V3.0 상담채팅 플랫폼  
**Version:** 3.0  
**Status:** Draft v1.0 (STEP 4 Complete)  
**Created:** 2026-07-21  
**Last Updated:** 2026-07-21  
**Owner:** Backend Platform Team  
**Audience:** PHP Developers, QA, Frontend Developers  

**적용 위치:** `www/` (기존 PLUS톡 PHP 8.4 코드베이스 확장)  
**상위 문서:** [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md), [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)  
**DB 설계:** [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md)  
**AI Router:** [03_AI_Router_Service_구현명세.md](03_AI_Router_Service_구현명세.md)  
**Chat Server:** [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md)

---

## 문서 개요

| 항목 | 내용 |
|------|------|
| Runtime | PHP 8.4 (PLUS톡 V2.0 호환) |
| Pattern | Controller → Service → Repository → MariaDB |
| API Prefix | `/api/v1/` |
| MVP Scope | Chats(6) + Messages(3) + Files(2) + Auth P0 |
| 기존 연동 | `includes/db.php`, `config/app.php`, gnuboard-style bootstrap |

본 문서는 ACEP Chat Domain REST API의 **PHP Backend 구현 명세**이다. STEP 2 [02_API설계.md](../03_SYSTEM/02_API설계.md) 30개 REST 중 **Chat·Message·Read·File** 영역을 기존 `www/` 구조에 맞게 구현한다.

---

## 1. 아키텍처 개요

### 1.1 PLUS톡 www 기존 구조와 ACEP 확장

```
www/  (PLUS톡 V2.0 + ACEP Enterprise)
├── admin/                    # 레거시 Admin UI (consult, ai_*.php)
├── api/                      # ★ ACEP REST API (신규/확장)
│   └── v1/
│       ├── index.php         # Front router
│       ├── auth/
│       ├── chats/
│       ├── ai/
│       ├── customers/
│       ├── agents/
│       ├── files/
│       └── system/
├── embed/                    # 고객 위젯 (V1.5)
├── includes/
│   ├── db.php                # PDO singleton (기존)
│   ├── ai.php                # ai_call(), Failover (기존)
│   ├── bootstrap.php         # ★ 공통 bootstrap (신규)
│   ├── middleware/           # ★ Auth, RateLimit (신규)
│   ├── controllers/          # ★ HTTP handlers (신규)
│   ├── services/             # ★ Business logic (신규)
│   └── repositories/         # ★ DB CRUD (신규)
├── config/
│   ├── app.php               # BASE_PATH, ROLES (기존)
│   ├── ai.php                # AI fallback config (기존)
│   └── database.php          # DB credentials (기존)
├── uploads/                  # File storage local
└── logs/
```

**MVP V1.0:** Backend는 기존 `www/` PHP 확장. 별도 `backend/` 폴더 분리는 V2.0 옵션 ([03_시스템아키텍처.md](../03_SYSTEM/03_시스템아키텍처.md) §10).

### 1.2 계층 다이어그램

```
HTTP Request (/api/v1/chats/...)
    │
    ▼
api/v1/index.php (Router)
    │
    ├── CorsMiddleware
    ├── RateLimitMiddleware (Redis)
    ├── AuthMiddleware (JWT)
    └── RbacMiddleware (role + room scope)
    │
    ▼
Controller (ChatController, MessageController, ...)
    │  — 입력 검증, HTTP status, ResponseHelper
    ▼
Service (ChatRoomService, MessageService, ReadStatusService, ...)
    │  — 트랜잭션, 비즈니스 규칙, Redis publish, AI trigger
    ▼
Repository (ChatRoomRepository, ChatMessageRepository, ...)
    │  — Prepared Statement only
    ▼
MariaDB (chat_rooms, chat_messages, chat_read_status, attachments)
```

### 1.3 책임 분리 (MASTER §4.4)

| 계층 | 책임 | 금지 |
|------|------|------|
| Controller | Route, validate input, JSON response | SQL, AI call |
| Service | Business logic, transaction, orchestration | HTTP header 직접 출력 |
| Repository | CRUD, Prepared Statement | JWT 검증, Redis publish |
| Middleware | Auth, RBAC, Rate limit | Domain logic |

---

## 2. Bootstrap 및 Router

### 2.1 includes/bootstrap.php

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

// Autoload services/repositories (PSR-4 또는 require_once map)
require_once __DIR__ . '/util/ResponseHelper.php';
require_once __DIR__ . '/util/JwtHelper.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/middleware/RbacMiddleware.php';

// Error handler — production: log only, no stack trace
set_exception_handler(function (Throwable $e) {
    error_log('[ACEP] ' . $e->getMessage());
    ResponseHelper::error('Internal server error', 'INTERNAL_ERROR', 500);
});
```

### 2.2 api/v1/index.php (Front Controller)

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = '/api/v1';
$path   = substr($uri, strlen($base)) ?: '/';

// Route table
$routes = [
    'GET'    => [
        '/chats/rooms'                    => [ChatController::class, 'listRooms'],
        '#^/chats/([a-f0-9-]+)$#'         => [ChatController::class, 'getRoom'],
        '#^/chats/([a-f0-9-]+)/messages$#' => [MessageController::class, 'listMessages'],
    ],
    'POST'   => [
        '/chats/rooms'                    => [ChatController::class, 'createRoom'],
        '#^/chats/([a-f0-9-]+)/messages$#' => [MessageController::class, 'storeMessage'],
        '/files/upload'                   => [FileController::class, 'upload'],
    ],
    'PUT'    => [
        '#^/chats/([a-f0-9-]+)/read$#'    => [ReadController::class, 'markRead'],
        '#^/chats/([a-f0-9-]+)/close$#'   => [ChatController::class, 'closeRoom'],
        '#^/chats/([a-f0-9-]+)/assign$#'  => [ChatController::class, 'assignRoom'],
    ],
    'DELETE' => [
        '#^/chats/([a-f0-9-]+)/messages/([a-f0-9-]+)$#' => [MessageController::class, 'deleteMessage'],
    ],
];

// Middleware pipeline
$request = RequestContext::fromGlobals();
RateLimitMiddleware::handle($request);
AuthMiddleware::handle($request);  // public routes skip internally

// Dispatch...
```

### 2.3 .htaccess / Nginx rewrite

```apache
# www/api/v1/.htaccess (Apache)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [Q,L]
```

```nginx
location /api/v1/ {
    try_files $uri /api/v1/index.php?$query_string;
    fastcgi_pass acep-backend:9000;
    fastcgi_param SCRIPT_FILENAME $document_root/api/v1/index.php;
}
```

---

## 3. Middleware

### 3.1 AuthMiddleware (JWT)

```php
<?php
declare(strict_types=1);

final class AuthMiddleware
{
    /** @var string[] Public paths (no JWT) */
    private const PUBLIC = [
        '/auth/login',
        '/auth/refresh',
        '/system/health',
    ];

    public static function handle(RequestContext $req): void
    {
        if (in_array($req->path, self::PUBLIC, true)) {
            return;
        }

        $header = $req->header('Authorization') ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            ResponseHelper::error('인증이 필요합니다', 'UNAUTHORIZED', 401);
        }

        try {
            $payload = JwtHelper::decode($m[1]);
            $req->user = [
                'sub'  => $payload->sub,
                'role' => $payload->role,
                'name' => $payload->name ?? '',
            ];
        } catch (Throwable $e) {
            ResponseHelper::error('토큰이 유효하지 않습니다', 'UNAUTHORIZED', 401);
        }
    }
}
```

**JWT Payload (API §1.4):**

```json
{
  "sub": "agent-uuid",
  "role": "agent",
  "name": "김상담",
  "iat": 1721540100,
  "exp": 1721626500
}
```

ACEP `agents.role` ENUM: `agent`, `admin`, `operator` — PLUS톡 `config/app.php` ROLES와 매핑 테이블 필요 (V1.0: agents 테이블 우선).

### 3.2 RbacMiddleware + Room IDOR 방지

```php
final class RbacMiddleware
{
    public static function assertRoomAccess(
        string $roomId,
        array $user,
        ChatRoomRepository $repo
    ): void {
        $role = $user['role'];
        $sub  = $user['sub'];

        if ($role === 'admin' || $role === 'operator') {
            return; // operator: read-only enforced per endpoint
        }

        $room = $repo->findById($roomId);
        if (!$room) {
            ResponseHelper::error('상담방을 찾을 수 없습니다', 'ROOM_NOT_FOUND', 404);
        }

        if ($role === 'agent') {
            $allowed = ($room['agent_id'] === $sub) || ($room['status'] === 'new');
            if (!$allowed) {
                AuditService::logForbidden($sub, 'room', $roomId);
                ResponseHelper::error('접근 권한이 없습니다', 'FORBIDDEN', 403);
            }
        }

        if ($role === 'customer') {
            if ($room['customer_id'] !== $sub) {
                ResponseHelper::error('접근 권한이 없습니다', 'FORBIDDEN', 403);
            }
        }
    }
}
```

### 3.3 RateLimitMiddleware (Rule-005)

```php
final class RateLimitMiddleware
{
    public static function handle(RequestContext $req): void
    {
        $ip   = $req->ip();
        $sub  = $req->user['sub'] ?? 'anon';
        $limits = [
            ['key' => "rl:ip:{$ip}", 'limit' => (int)(getenv('RATE_LIMIT_IP') ?: 100)],
            ['key' => "rl:user:{$sub}", 'limit' => (int)(getenv('RATE_LIMIT_USER') ?: 50)],
        ];
        foreach ($limits as $l) {
            if (!RedisRateLimiter::allow($l['key'], $l['limit'], 60)) {
                header('Retry-After: 60');
                ResponseHelper::error('요청 한도를 초과했습니다', 'RATE_LIMIT_EXCEEDED', 429);
            }
        }
    }
}
```

---

## 4. Controller Layer

### 4.1 ChatController

| Method | Route | API ID | Handler |
|--------|-------|--------|---------|
| GET | `/chats/rooms` | API-005 | `listRooms()` |
| GET | `/chats/{id}` | API-006 | `getRoom()` |
| POST | `/chats/rooms` | API-007 | `createRoom()` |
| PUT | `/chats/{id}/close` | API-008 | `closeRoom()` |
| PUT | `/chats/{id}/assign` | API-010 | `assignRoom()` |

```php
final class ChatController
{
    public function __construct(
        private ChatRoomService $chatRoomService,
    ) {}

    public function listRooms(RequestContext $req): void
    {
        $query = [
            'status' => $req->query('status'),
            'search' => $req->query('search'),
            'page'   => max(1, (int)$req->query('page', 1)),
            'limit'  => min(50, max(1, (int)$req->query('limit', 20))),
            'sort'   => $req->query('sort', 'updated_at:desc'),
        ];
        $data = $this->chatRoomService->listForAgent($req->user, $query);
        ResponseHelper::success($data);
    }

    public function getRoom(RequestContext $req, string $roomId): void
    {
        RbacMiddleware::assertRoomAccess($roomId, $req->user, $this->chatRoomService->getRepo());
        $data = $this->chatRoomService->getDetail($roomId);
        ResponseHelper::success($data);
    }

    public function createRoom(RequestContext $req): void
    {
        $body = $req->json();
        $validated = ChatValidator::createRoom($body);
        $data = $this->chatRoomService->create($validated, $req->user);
        ResponseHelper::success($data, 201);
    }

    public function closeRoom(RequestContext $req, string $roomId): void
    {
        RbacMiddleware::assertRoomAccess($roomId, $req->user, $this->chatRoomService->getRepo());
        $body = $req->json();
        $data = $this->chatRoomService->close($roomId, $body, $req->user);
        ResponseHelper::success($data);
    }
}
```

### 4.2 MessageController

| Method | Route | API ID | Handler |
|--------|-------|--------|---------|
| GET | `/chats/{id}/messages` | API-011 | `listMessages()` |
| POST | `/chats/{id}/messages` | API-012 | `storeMessage()` |
| DELETE | `/chats/{id}/messages/{messageId}` | API-013 | `deleteMessage()` |

```php
final class MessageController
{
    public function __construct(
        private MessageService $messageService,
        private ChatRoomRepository $roomRepo,
    ) {}

    public function listMessages(RequestContext $req, string $roomId): void
    {
        RbacMiddleware::assertRoomAccess($roomId, $req->user, $this->roomRepo);
        $query = [
            'page'   => max(1, (int)$req->query('page', 1)),
            'limit'  => min(50, max(1, (int)$req->query('limit', 50))),
            'before' => $req->query('before'),
        ];
        $data = $this->messageService->listByRoom($roomId, $query);
        ResponseHelper::success($data);
    }

    public function storeMessage(RequestContext $req, string $roomId): void
    {
        RbacMiddleware::assertRoomAccess($roomId, $req->user, $this->roomRepo);
        $body = MessageValidator::store($req->json());
        $data = $this->messageService->send($roomId, $body, $req->user);
        ResponseHelper::success($data, 201);
    }
}
```

### 4.3 ReadController

| Method | Route | API ID |
|--------|-------|--------|
| PUT | `/chats/{id}/read` | API-009 |

### 4.4 FileController

| Method | Route | API ID |
|--------|-------|--------|
| POST | `/files/upload` | API-028 |
| GET | `/files/{id}` | API-029 |

### 4.5 ResponseHelper (표준 응답)

```php
final class ResponseHelper
{
    public static function success(mixed $data, int $http = 200): void
    {
        self::json([
            'success'   => true,
            'data'      => $data,
            'error'     => null,
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('c'),
        ], $http);
    }

    public static function error(string $message, string $code, int $http): void
    {
        self::json([
            'success'   => false,
            'data'      => null,
            'error'     => ['message' => $message, 'code' => $code],
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('c'),
        ], $http);
    }

    private static function json(array $payload, int $http): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
```

---

## 5. Service Layer

### 5.1 ChatRoomService

```php
final class ChatRoomService
{
    public function __construct(
        private ChatRoomRepository $roomRepo,
        private CustomerRepository $customerRepo,
        private ReadStatusRepository $readRepo,
        private AiRecommendationRepository $aiRepo,
        private RedisEventPublisher $redis,
        private MessageService $messageService,
    ) {}

    /**
     * API-005: ChatList
     * 정렬: status > contract_probability > updated_at (UI §4-2-A)
     */
    public function listForAgent(array $user, array $query): array
    {
        $rooms = $this->roomRepo->findForAgent(
            agentId: $user['sub'],
            role: $user['role'],
            filters: $query
        );

        $items = [];
        foreach ($rooms['rows'] as $room) {
            $items[] = [
                'id'                  => $room['id'],
                'customer'            => [
                    'id'          => $room['customer_id'],
                    'name'        => $room['customer_name'],
                    'phoneMasked' => PiiMasker::phone($room['customer_phone_enc']),
                ],
                'inquiryType'         => $room['inquiry_type'],
                'status'              => $room['status'],
                'unreadCount'         => $this->readRepo->countUnread($room['id'], 'agent', $user['sub']),
                'contractProbability' => $room['contract_probability'],
                'updatedAt'           => $room['updated_at'],
            ];
        }

        return [
            'rooms'      => $items,
            'pagination' => [
                'page'  => $query['page'],
                'limit' => $query['limit'],
                'total' => $rooms['total'],
            ],
        ];
    }

    public function getDetail(string $roomId): array
    {
        $room = $this->roomRepo->findDetailById($roomId);
        if (!$room) {
            ResponseHelper::error('상담방을 찾을 수 없습니다', 'ROOM_NOT_FOUND', 404);
        }
        return $this->mapRoomDetail($room);
    }

    /**
     * API-007: 상담방 생성
     * - phone_hash customer 매칭
     * - initialMessage → MessageService::send
     */
    public function create(array $input, array $user): array
    {
        return db()->transaction(function (PDO $pdo) use ($input, $user) {
            $customerId = $this->customerRepo->findOrCreateByPhone(
                $input['customerName'],
                $input['customerPhone'] ?? null
            );

            $roomId = Uuid::v4();
            $this->roomRepo->insert([
                'id'           => $roomId,
                'customer_id'  => $customerId,
                'inquiry_type' => $input['inquiryType'],
                'channel'      => $input['channel'] ?? 'web',
                'status'       => 'new',
                'subject'      => $input['subject'] ?? $input['inquiryType'],
            ]);

            if (!empty($input['initialMessage'])) {
                $this->messageService->sendInternal($roomId, [
                    'content'    => $input['initialMessage'],
                    'senderType' => 'customer',
                    'senderId'   => $customerId,
                ]);
            }

            return [
                'roomId'     => $roomId,
                'customerId' => $customerId,
                'status'     => 'new',
                'createdAt'  => date('c'),
            ];
        });
    }

    /**
     * API-008: 상담 종료
     * Side effect: AI summarize async, audit log
     */
    public function close(string $roomId, array $input, array $user): array
    {
        $room = $this->roomRepo->findById($roomId);
        if (!$room || $room['status'] === 'closed') {
            ResponseHelper::error('상담방을 찾을 수 없습니다', 'ROOM_NOT_FOUND', 404);
        }

        $this->roomRepo->updateStatus($roomId, 'closed', [
            'closed_at' => date('Y-m-d H:i:s'),
            'close_reason' => $input['reason'] ?? null,
        ]);

        // Async: AiRecommendationService::onRoomClose($roomId)
        AiJobQueue::dispatch('room_close', ['roomId' => $roomId]);

        $this->redis->publishRoom($roomId, 'room:update', [
            'roomId' => $roomId,
            'status' => 'closed',
        ]);

        return [
            'roomId'   => $roomId,
            'status'   => 'closed',
            'closedAt' => date('c'),
            'summary'  => null, // async fill
        ];
    }

    public function getRepo(): ChatRoomRepository
    {
        return $this->roomRepo;
    }
}
```

### 5.2 MessageService

```php
final class MessageService
{
    public function __construct(
        private ChatMessageRepository $messageRepo,
        private ChatRoomRepository $roomRepo,
        private ReadStatusRepository $readRepo,
        private AttachmentRepository $attachmentRepo,
        private RedisEventPublisher $redis,
        private AiRecommendationService $aiService,
    ) {}

    /**
     * API-012: 메시지 전송 — 핵심 트랜잭션
     */
    public function send(string $roomId, array $body, array $user): array
    {
        $room = $this->roomRepo->findById($roomId);
        if (!$room) {
            ResponseHelper::error('상담방을 찾을 수 없습니다', 'ROOM_NOT_FOUND', 404);
        }
        if ($room['status'] === 'closed') {
            ResponseHelper::error('종료된 상담방입니다', 'VALIDATION_ERROR', 400);
        }

        $senderType = $body['senderType'] ?? ($user['role'] === 'customer' ? 'customer' : 'agent');
        $senderId   = $body['senderId'] ?? $user['sub'];

        return db()->transaction(function () use ($roomId, $body, $senderType, $senderId, $room, $user) {
            $messageId = Uuid::v4();

            // Attachment link
            $attachmentUrl = null;
            if (!empty($body['attachmentId'])) {
                $att = $this->attachmentRepo->findById($body['attachmentId']);
                if ($att && $att['room_id'] === $roomId) {
                    $attachmentUrl = $att['public_url'];
                    $this->attachmentRepo->linkMessage($body['attachmentId'], $messageId);
                }
            } elseif (!empty($body['attachmentUrl'])) {
                $attachmentUrl = $body['attachmentUrl'];
            }

            $this->messageRepo->insert([
                'id'             => $messageId,
                'room_id'        => $roomId,
                'sender_type'    => $senderType,
                'sender_id'      => $senderId,
                'content'        => $body['content'],
                'attachment_url' => $attachmentUrl,
                'source'         => $body['source'] ?? 'manual',
                'ai_recommendation_id' => $body['aiRecommendationId'] ?? null,
            ]);

            $this->roomRepo->touchUpdatedAt($roomId);

            // new → active: agent 첫 응답
            if ($room['status'] === 'new' && $senderType === 'agent') {
                $this->roomRepo->assignAgent($roomId, $senderId);
            }

            // Read status: delivered
            $this->readRepo->markDelivered($messageId, $roomId);

            // Redis → Chat Server
            $this->redis->publishRoom($roomId, 'message:receive', [
                'messageId'     => $messageId,
                'roomId'        => $roomId,
                'content'       => $body['content'],
                'senderType'    => $senderType,
                'senderId'      => $senderId,
                'attachmentUrl' => $attachmentUrl,
                'timestamp'     => date('c'),
                'tempId'        => $body['tempId'] ?? null,
            ]);

            // AI trigger (async) — customer message only
            $aiTriggered = false;
            if ($senderType === 'customer') {
                $aiTriggered = $this->aiService->onCustomerMessage($roomId, $messageId);
            }

            return [
                'messageId'   => $messageId,
                'roomId'      => $roomId,
                'createdAt'   => date('c'),
                'aiTriggered' => $aiTriggered,
            ];
        });
    }

    /**
     * API-011: 메시지 목록 + readStatus
     */
    public function listByRoom(string $roomId, array $query): array
    {
        $rows = $this->messageRepo->findByRoom($roomId, $query);
        $messages = [];
        foreach ($rows['items'] as $row) {
            $messages[] = [
                'id'             => $row['id'],
                'senderType'     => $row['sender_type'],
                'senderId'       => $row['sender_id'],
                'content'        => $row['content'],
                'attachmentUrl'  => $row['attachment_url'],
                'attachmentType' => $row['attachment_type'],
                'source'         => $row['source'],
                'createdAt'      => $row['created_at'],
                'readStatus'     => $this->readRepo->getStatusForMessage($row['id']),
            ];
        }
        return ['messages' => $messages, 'hasMore' => $rows['hasMore']];
    }
}
```

### 5.3 ReadStatusService

```php
final class ReadStatusService
{
    public function __construct(
        private ReadStatusRepository $readRepo,
        private ChatRoomRepository $roomRepo,
        private RedisEventPublisher $redis,
    ) {}

    /**
     * API-009: 읽음 표시
     * BR-READ-001~004
     */
    public function markRead(string $roomId, array $body, array $user): array
    {
        $room = $this->roomRepo->findById($roomId);
        if (!$room) {
            ResponseHelper::error('상담방을 찾을 수 없습니다', 'ROOM_NOT_FOUND', 404);
        }
        if ($room['status'] === 'closed') {
            ResponseHelper::error('종료된 상담방입니다', 'VALIDATION_ERROR', 400);
        }

        $readerType = $body['readerType'] ?? ($user['role'] === 'customer' ? 'customer' : 'agent');
        $messageIds = $body['messageIds'] ?? [];

        if (empty($messageIds)) {
            ResponseHelper::error('messageIds가 필요합니다', 'VALIDATION_ERROR', 400);
        }

        $updated = 0;
        $readAt  = date('Y-m-d H:i:s');

        foreach ($messageIds as $msgId) {
            if ($this->readRepo->markRead($msgId, $roomId, $readerType, $user['sub'], $readAt)) {
                $updated++;
                $this->redis->publishRoom($roomId, 'read:update', [
                    'roomId'     => $roomId,
                    'messageId'  => $msgId,
                    'readerType' => $readerType,
                    'readAt'     => date('c'),
                ]);
            }
        }

        return ['updatedCount' => $updated];
    }
}
```

### 5.4 FileUploadService

```php
final class FileUploadService
{
    public function __construct(
        private AttachmentRepository $attachmentRepo,
    ) {}

    /**
     * API-028: multipart upload
     * config/app.php: UPLOAD_MAX_BYTES, UPLOAD_ALLOWED_EXT
     */
    public function upload(array $file, string $roomId, array $user): array
    {
        if ($file['size'] > UPLOAD_MAX_BYTES) {
            ResponseHelper::error('파일 크기가 10MB를 초과합니다', 'FILE_TOO_LARGE', 400);
        }

        $mime = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            ResponseHelper::error('허용되지 않는 파일 형식입니다', 'INVALID_FILE_TYPE', 400);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $attId = Uuid::v4();
        $storagePath = UPLOAD_PATH . '/' . date('Y/m') . "/{$attId}.{$ext}";
        // ... move_uploaded_file, generate public URL ...

        $this->attachmentRepo->insert([
            'id'            => $attId,
            'room_id'       => $roomId,
            'uploader_type' => $user['role'] === 'customer' ? 'customer' : 'agent',
            'uploader_id'   => $user['sub'],
            'file_name'     => $file['name'],
            'mime_type'     => $mime,
            'file_size'     => $file['size'],
            'storage_path'  => $storagePath,
            'public_url'    => CDN_BASE_URL . '/' . basename($storagePath),
        ]);

        return [
            'id'   => $attId,
            'url'  => CDN_BASE_URL . '/' . basename($storagePath),
            'type' => str_starts_with($mime, 'image/') ? 'image' : 'pdf',
            'name' => $file['name'],
            'size' => $file['size'],
        ];
    }
}
```

---

## 6. Repository Layer

### 6.1 ChatRoomRepository

```php
final class ChatRoomRepository
{
    public function findById(string $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM chat_rooms WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findForAgent(string $agentId, string $role, array $filters): array
    {
        $where = ['cr.deleted_at IS NULL'];
        $params = [];

        if ($role === 'agent') {
            $where[] = '(cr.agent_id = :agent_id OR cr.status = \'new\')';
            $params[':agent_id'] = $agentId;
        }

        if (!empty($filters['status'])) {
            $statuses = explode(',', $filters['status']);
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $where[] = "cr.status IN ({$placeholders})";
            foreach ($statuses as $s) {
                $params[] = trim($s);
            }
        }

        $sql = '
            SELECT cr.*, c.name AS customer_name, c.phone_enc AS customer_phone_enc,
                   ar.contract_probability
            FROM chat_rooms cr
            INNER JOIN customers c ON c.id = cr.customer_id
            LEFT JOIN ai_recommendations ar ON ar.room_id = cr.id AND ar.is_latest = 1
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY
                FIELD(cr.status, \'new\', \'active\', \'closed\'),
                ar.contract_probability DESC,
                cr.updated_at DESC
            LIMIT :limit OFFSET :offset
        ';
        // ... execute, count query ...
        return ['rows' => $rows, 'total' => $total];
    }

    public function insert(array $data): void
    {
        $stmt = db()->prepare(
            'INSERT INTO chat_rooms (id, customer_id, inquiry_type, channel, status, subject, created_at, updated_at)
             VALUES (:id, :customer_id, :inquiry_type, :channel, :status, :subject, NOW(3), NOW(3))'
        );
        $stmt->execute([
            ':id'           => $data['id'],
            ':customer_id'  => $data['customer_id'],
            ':inquiry_type' => $data['inquiry_type'],
            ':channel'      => $data['channel'],
            ':status'       => $data['status'],
            ':subject'      => $data['subject'],
        ]);
    }

    public function touchUpdatedAt(string $roomId): void
    {
        db()->prepare('UPDATE chat_rooms SET updated_at = NOW(3) WHERE id = :id')
            ->execute([':id' => $roomId]);
    }

    public function updatePriorityScore(string $roomId, ?int $score): void
    {
        if ($score === null) return;
        db()->prepare(
            'UPDATE chat_rooms SET contract_probability = :score, updated_at = NOW(3) WHERE id = :id'
        )->execute([':score' => $score, ':id' => $roomId]);
    }
}
```

### 6.2 ChatMessageRepository

```php
final class ChatMessageRepository
{
    public function insert(array $data): void
    {
        $stmt = db()->prepare(
            'INSERT INTO chat_messages
             (id, room_id, sender_type, sender_id, content, attachment_url, source, ai_recommendation_id, created_at)
             VALUES (:id, :room_id, :sender_type, :sender_id, :content, :attachment_url, :source, :ai_rec_id, NOW(3))'
        );
        $stmt->execute([
            ':id'          => $data['id'],
            ':room_id'     => $data['room_id'],
            ':sender_type' => $data['sender_type'],
            ':sender_id'   => $data['sender_id'],
            ':content'     => $data['content'],
            ':attachment_url' => $data['attachment_url'],
            ':source'      => $data['source'],
            ':ai_rec_id'   => $data['ai_recommendation_id'],
        ]);
    }

    public function findByRoom(string $roomId, array $query): array
    {
        $limit = $query['limit'];
        $sql = '
            SELECT * FROM chat_messages
            WHERE room_id = :room_id AND deleted_at IS NULL
        ';
        $params = [':room_id' => $roomId];

        if (!empty($query['before'])) {
            $sql .= ' AND created_at < :before';
            $params[':before'] = $query['before'];
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);

        return ['items' => array_reverse($rows), 'hasMore' => $hasMore];
    }

    public function getRecent(string $roomId, int $limit = 20): array
    {
        $stmt = db()->prepare(
            'SELECT sender_type, content, created_at FROM chat_messages
             WHERE room_id = :room_id AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':room_id', $roomId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
```

### 6.3 ReadStatusRepository

```php
final class ReadStatusRepository
{
    public function markDelivered(string $messageId, string $roomId): void
    {
        // BR-READ-001: delivered_at on insert
    }

    public function markRead(string $messageId, string $roomId, string $readerType, string $readerId, string $readAt): bool
    {
        $stmt = db()->prepare(
            'INSERT INTO chat_read_status (id, room_id, message_id, reader_type, reader_id, read_at, created_at)
             VALUES (:id, :room_id, :msg_id, :rtype, :rid, :read_at, NOW(3))
             ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
        );
        return $stmt->execute([
            ':id'      => Uuid::v4(),
            ':room_id' => $roomId,
            ':msg_id'  => $messageId,
            ':rtype'   => $readerType,
            ':rid'     => $readerId,
            ':read_at' => $readAt,
        ]);
    }

    public function countUnread(string $roomId, string $readerType, string $readerId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM chat_read_status
             WHERE room_id = :room_id AND reader_type = :rtype AND reader_id = :rid AND read_at IS NULL'
        );
        $stmt->execute([':room_id' => $roomId, ':rtype' => $readerType, ':rid' => $readerId]);
        return (int)$stmt->fetchColumn();
    }
}
```

### 6.4 AttachmentRepository

[01_DB설계.md](../03_SYSTEM/01_DB설계.md) §5.8 `attachments` 테이블 CRUD.

---

## 7. 트랜잭션 경계

### 7.1 Transaction Rules

| Operation | Tables | Isolation |
|-----------|--------|-----------|
| send message | chat_messages, chat_rooms, chat_read_status, attachments | READ COMMITTED |
| create room | customers, chat_rooms, chat_messages | SERIALIZABLE optional |
| mark read | chat_read_status | single row |
| close room | chat_rooms, audit_logs | READ COMMITTED |
| file upload | attachments | single INSERT |

### 7.2 db() Transaction Helper

```php
// includes/db.php extension
function db_transaction(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

### 7.3 Side Effect 순서 (Message Send)

```
BEGIN TRANSACTION
  1. INSERT chat_messages
  2. UPDATE chat_rooms.updated_at
  3. (optional) UPDATE chat_rooms agent/status
  4. INSERT chat_read_status (delivered)
  5. UPDATE attachments.message_id
COMMIT
--- after commit (non-transactional) ---
  6. Redis PUBLISH message:receive
  7. AiJobQueue dispatch (customer msg)
```

Redis/AI 실패는 **메시지 저장 롤백하지 않음** — at-least-once 알림.

---

## 8. Request/Response 예시 (API 정합)

### 8.1 API-005 GET /chats/rooms

**Request:**

```http
GET /api/v1/chats/rooms?status=new,active&page=1&limit=20 HTTP/1.1
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

**Response 200:** [02_API설계.md](../03_SYSTEM/02_API설계.md) API-005 JSON 동일.

### 8.2 API-012 POST /chats/{id}/messages

**Request:**

```json
{
  "content": "안녕하세요. 무엇을 도와드릴까요?",
  "source": "manual",
  "attachmentId": null,
  "aiRecommendationId": null
}
```

**Response 201:**

```json
{
  "success": true,
  "data": {
    "messageId": "msg-uuid-new",
    "roomId": "room-uuid-1",
    "createdAt": "2026-07-21T14:32:00+09:00",
    "aiTriggered": false
  },
  "error": null,
  "timestamp": "2026-07-21T14:32:00+09:00"
}
```

### 8.3 API-009 PUT /chats/{id}/read

**Request:**

```json
{
  "messageIds": ["msg-uuid-1", "msg-uuid-2"],
  "readerType": "agent"
}
```

**Response 200:**

```json
{
  "success": true,
  "data": { "updatedCount": 2 },
  "error": null,
  "timestamp": "2026-07-21T14:35:00+09:00"
}
```

### 8.4 API-028 POST /files/upload

**Request:** `multipart/form-data`

| Field | Value |
|-------|-------|
| file | (binary) |
| roomId | room-uuid-1 |

**Flow:**

```
1. POST /files/upload → attachmentId
2. POST /chats/{id}/messages { content, attachmentId }
3. message.attachment_url populated
4. WebSocket message:receive includes attachmentUrl
```

---

## 9. Redis Event Publisher

```php
final class RedisEventPublisher
{
    public function publishRoom(string $roomId, string $event, array $payload): void
    {
        $prefix  = getenv('REDIS_PREFIX') ?: 'acep:';
        $channel = "{$prefix}room:{$roomId}:events";
        $envelope = [
            'event'     => $event,
            'roomId'    => $roomId,
            'payload'   => $payload,
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('c'),
            'source'    => 'backend',
        ];
        redis()->publish($channel, json_encode($envelope, JSON_UNESCAPED_UNICODE));
    }

    public function publishBroadcast(string $event, array $payload): void
    {
        $prefix  = getenv('REDIS_PREFIX') ?: 'acep:';
        redis()->publish("{$prefix}events:broadcast", json_encode([
            'event'   => $event,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE));
    }
}
```

---

## 10. Internal API (Chat Server용)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/internal/v1/chats/{id}/access` | room:join RBAC |
| POST | `/internal/v1/chats/{id}/messages` | message:send proxy (optional) |

```php
// Internal auth: X-Internal-Secret header
final class InternalChatController
{
    public function checkAccess(RequestContext $req, string $roomId): void
    {
        if ($req->header('X-Internal-Secret') !== getenv('INTERNAL_API_SECRET')) {
            ResponseHelper::error('Forbidden', 'FORBIDDEN', 403);
        }
        $userId = $req->header('X-User-Id');
        $role   = $req->header('X-User-Role');
        $allowed = ChatRoomService::canAccess($roomId, $userId, $role);
        ResponseHelper::success(['allowed' => $allowed]);
    }
}
```

---

## 11. PLUS톡 V2.0 레거시 연동

### 11.1 consult → chat_rooms 매핑

| PLUS톡 V2.0 | ACEP |
|-------------|------|
| `consults` table | `chat_rooms` |
| `consult_messages` | `chat_messages` |
| `admin/consults/view.php` | React ChatScreen |

마이그레이션 스크립트: `migrations/V1.0.0__mvp_core.sql`

### 11.2 gnuboard-style bootstrap

기존 PLUS톡 페이지는 `include/common.php` 패턴 유지. ACEP API만 `includes/bootstrap.php` 사용 — **공존**.

```php
// admin/consults/ — legacy, unchanged V1.0
// api/v1/ — ACEP REST only
```

### 11.3 config/app.php 활용

```php
// 기존 상수 재사용
UPLOAD_MAX_BYTES      // 10MB
UPLOAD_ALLOWED_EXT    // file validation
BASE_PATH             // storage paths
API_BASE              // https://host/api/v1
```

---

## 12. Validation Rules

| Field | Rule |
|-------|------|
| content | required, max 2000 chars |
| messageIds | array, max 100 ids |
| inquiryType | required on create, max 100 chars |
| customerPhone | optional, digits 10-11 |
| attachmentId | UUID, must belong to room |
| source | enum: manual, ai_recommendation, system |

```php
final class MessageValidator
{
    public static function store(array $body): array
    {
        $content = trim($body['content'] ?? '');
        if ($content === '' && empty($body['attachmentId'])) {
            ResponseHelper::error('메시지 내용이 필요합니다', 'VALIDATION_ERROR', 400);
        }
        if (mb_strlen($content) > 2000) {
            ResponseHelper::error('메시지는 2000자를 초과할 수 없습니다', 'VALIDATION_ERROR', 400);
        }
        return $body;
    }
}
```

---

## 13. Error Code Mapping

| code | HTTP | Service trigger |
|------|------|-----------------|
| ROOM_NOT_FOUND | 404 | roomRepo->findById null |
| MESSAGE_NOT_FOUND | 404 | delete message |
| FORBIDDEN | 403 | RBAC fail |
| VALIDATION_ERROR | 400 | Validator |
| MSG_SEND_FAILED | 500 | DB exception |
| FILE_TOO_LARGE | 400 | upload size |
| RATE_LIMIT_EXCEEDED | 429 | Middleware |

---

## 14. V1.0 구현 체크리스트

| Priority | Task | API | Status |
|----------|------|-----|:------:|
| P0 | Auth middleware + JWT | API-001~004 | 📋 |
| P0 | GET rooms (ChatList) | API-005 | 📋 |
| P0 | GET/POST messages | API-011, 012 | 📋 |
| P0 | PUT read | API-009 | 📋 |
| P0 | Redis publish | WS bridge | 📋 |
| P0 | AI trigger hook | API-012 side effect | 📋 |
| P1 | POST create room | API-007 | 📋 |
| P1 | PUT close | API-008 | 📋 |
| P1 | POST file upload | API-028 | 📋 |
| P1 | Internal access API | Chat Server | 📋 |
| P2 | PUT assign | API-010 | 📋 |
| P2 | DELETE message | API-013 | 📋 |

---

## 15. 테스트 시나리오

| ID | Test | Expected |
|----|------|----------|
| TC-API-001 | Agent list rooms | 200, pagination |
| TC-API-002 | IDOR wrong room | 403 FORBIDDEN |
| TC-API-003 | Send message | 201 + Redis publish |
| TC-API-004 | Customer msg AI trigger | aiTriggered=true |
| TC-API-005 | Mark read closed room | 400 |
| TC-API-006 | Upload 11MB | 400 FILE_TOO_LARGE |
| TC-API-007 | Rate limit 51 req | 429 |

---

## 부록 A. 파일 경로 매핑表

| Class | Path |
|-------|------|
| ChatController | `includes/controllers/ChatController.php` |
| MessageController | `includes/controllers/MessageController.php` |
| ChatRoomService | `includes/services/ChatRoomService.php` |
| MessageService | `includes/services/MessageService.php` |
| ReadStatusService | `includes/services/ReadStatusService.php` |
| ChatRoomRepository | `includes/repositories/ChatRoomRepository.php` |
| ChatMessageRepository | `includes/repositories/ChatMessageRepository.php` |
| ReadStatusRepository | `includes/repositories/ReadStatusRepository.php` |
| AttachmentRepository | `includes/repositories/AttachmentRepository.php` |

## 부록 B. 관련 문서

- [01_ChatServer_구현명세.md](01_ChatServer_구현명세.md)
- [03_AI_Router_Service_구현명세.md](03_AI_Router_Service_구현명세.md)
- [03_SYSTEM/02_API설계.md](../03_SYSTEM/02_API설계.md)
- [_CHAT_INDEX.md](_CHAT_INDEX.md)

## 부록 C. 변경 이력

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-07-21 | STEP 4 — Backend Chat API 구현명세 |

---

**문서 끝 — AI 파이프라인은 [03_AI_Router_Service_구현명세.md](03_AI_Router_Service_구현명세.md) 참조.**
