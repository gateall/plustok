# 03 — Admin 모듈 구현 명세

> **PlusTok ACEP** · STEP 6 · PHP Admin + ACEP Extensions  
> **버전**: 1.0.0 · **작성일**: 2026-07-21  
> **참조 구현**: `admin/settings/ai.php`, `admin/consults/`

---

## 목차

1. [개요](#1-개요)
2. [폴더 맵](#2-폴더-맵)
3. [인증·세션 (Auth)](#3-인증세션-auth)
4. [Admin 레이아웃·메뉴](#4-admin-레이아웃메뉴)
5. [Consult Management](#5-consult-management)
6. [AI Settings (ai.php)](#6-ai-settings-aiphp)
7. [AI Endpoint Integration](#7-ai-endpoint-integration)
8. [Live Chat Monitor (신규)](#8-live-chat-monitor-신규)
9. [Prompt Editor (신규)](#9-prompt-editor-신규)
10. [Failover Log Viewer (신규)](#10-failover-log-viewer-신규)
11. [Agent Assignment UI (신규)](#11-agent-assignment-ui-신규)
12. [CSRF·Activity Log](#12-csrfactivity-log)
13. [Admin Menu Sitemap JSON](#13-admin-menu-sitemap-json)
14. [마이그레이션·배포](#14-마이그레이션배포)
15. [테스트 체크리스트](#15-테스트-체크리스트)
16. [부록](#16-부록)

---

## 1. 개요

### 1.1 목적

기존 PlusTok **PHP Admin** 코드베이스를 ACEP 아키텍처에 맞게 확장하는 모듈별 구현 명세.  
`ai.php` 및 `admin/consults/` 패턴을 **참조 구현**으로 문서화하고, STEP 6 신규 모듈 4종을 정의한다.

### 1.2 설계 원칙

| 원칙 | 설명 |
|------|------|
| Extend, don't rewrite | 기존 CRM admin 점진 확장 |
| API-first for new features | 신규 모듈은 REST + PHP view |
| Same auth everywhere | `includes/auth.php` + `require_role` |
| Audit everything sensitive | prompt, API key, assignment changes |

### 1.3 STEP 6 Deliverables

| Module | Status | Priority |
|--------|--------|----------|
| Admin layout + menu | extend | P0 |
| Consult list (ACEP) | extend | P0 |
| Dashboard | new page | P1 |
| ai.php documentation | reference | P0 |
| Live monitor | new | P2 |
| Prompt editor | new | P1 |
| Failover viewer | new | P1 |
| Agent management | new | P2 |

---

## 2. 폴더 맵

### 2.1 Current + Target Tree

```
www/
├── includes/
│   ├── auth.php                    # 세션·JWT·require_role()
│   ├── admin_layout_header.php     # STEP 6: unified header
│   ├── admin_layout_footer.php
│   ├── csrf.php                    # token generate/validate
│   └── activity_log.php            # user action log helper
│
├── admin/
│   ├── index.php                   # Dashboard (STEP 6)
│   │
│   ├── consults/                   # ★ 기존 — ACEP 확장
│   │   ├── index.php               # 목록 (chat_rooms 통합)
│   │   ├── view.php                # 상세 타임라인
│   │   ├── ai_summary.php          # ★ 참조: AI 요약
│   │   ├── ai_reply.php            # ★ 참조: AI 답변 추천
│   │   ├── ai_analyze.php          # ★ 참조: 감정·계약 분석
│   │   └── api/
│   │       └── list.php            # DataTables AJAX source
│   │
│   ├── settings/                   # ★ 기존 — ACEP 확장
│   │   ├── ai.php                  # ★ 참조 구현: AI 설정
│   │   ├── prompts.php             # STEP 6 신규
│   │   ├── prompts_edit.php
│   │   ├── failover.php            # STEP 6 신규
│   │   ├── general.php
│   │   └── audit.php
│   │
│   ├── monitor/                    # STEP 6 신규
│   │   ├── index.php
│   │   └── api/
│   │       ├── rooms.php
│   │       └── messages.php
│   │
│   ├── agents/                     # STEP 6 신규 (future full)
│   │   ├── index.php
│   │   ├── edit.php
│   │   └── api/
│   │       ├── list.php
│   │       ├── save.php
│   │       └── assign.php
│   │
│   ├── partials/                   # Dashboard partials
│   │   ├── dashboard_kpi.php
│   │   ├── dashboard_charts.php
│   │   └── dashboard_widgets.php
│   │
│   ├── assets/
│   │   ├── js/
│   │   │   ├── dashboard.js
│   │   │   ├── monitor.js
│   │   │   └── prompts.js
│   │   └── css/
│   │       └── admin.css
│   │
│   └── config/
│       └── menu.json               # Admin sitemap
│
├── api/v1/admin/                   # REST handlers (or router)
│   ├── stats/
│   ├── consults/
│   ├── prompts/
│   └── ...
│
└── 07_ADMIN/                       # 본 문서 SSOT
```

### 2.2 File Responsibility Matrix

| Path | HTTP | Role Gate | Primary DB |
|------|------|-----------|------------|
| admin/index.php | GET | super, admin, operator | stats aggregate |
| admin/consults/* | GET/POST | super, admin (+read operator) | chat_rooms |
| admin/settings/ai.php | GET/POST | super, admin | settings |
| admin/settings/prompts.php | GET/POST | super, admin | ai_prompts |
| admin/settings/failover.php | GET | super, admin, operator | ai_failover_log |
| admin/monitor/* | GET | super, admin, operator | chat_rooms, messages |
| admin/agents/* | GET/POST | super, admin | agents |

---

## 3. 인증·세션 (Auth)

### 3.1 includes/auth.php 패턴

```php
<?php
// includes/auth.php — 기존 패턴 (의사코드, 실제 코드와 정합 유지)

session_start();

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_role(array $allowed_roles): void {
    require_login();
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../admin/errors/403.php';
        exit;
    }
}

function current_user(): array {
    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'name' => $_SESSION['display_name'] ?? '',
    ];
}

function issue_admin_jwt(): string {
    // For AJAX/REST calls from PHP pages — short-lived 15min
    // Claims: sub, role, aud=admin, exp
}
```

### 3.2 Page Entry Pattern

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super', 'admin']);  // page-specific roles

require_once __DIR__ . '/../../includes/csrf.php';
// ... page logic
```

### 3.3 REST API Auth

- `Authorization: Bearer {jwt}`
- JWT validated in API middleware
- Same role claims as session
- Operator: GET only on admin stats/monitor/consults list

### 3.4 Login Flow

```
POST /admin/login.php
  → validate credentials (agents/users table)
  → session regenerate_id(true)
  → set role from agents.role column
  → redirect to /admin/ or ?redirect=
```

---

## 4. Admin 레이아웃·메뉴

### 4.1 admin_layout_header.php

```php
<?php
// includes/admin_layout_header.php
$user = current_user();
$menu = json_decode(file_get_contents(__DIR__ . '/../admin/config/menu.json'), true);
$menu = filter_menu_by_role($menu, $user['role']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($page_title ?? 'PlusTok Admin') ?></title>
  <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body class="admin-body">
  <?php include __DIR__ . '/../admin/partials/sidebar.php'; ?>
  <main class="admin-main">
    <?php include __DIR__ . '/../admin/partials/header_bar.php'; ?>
    <div class="admin-content">
```

### 4.2 Sidebar Role Filter

```php
function filter_menu_by_role(array $menu, string $role): array {
    return array_values(array_filter($menu, function ($item) use ($role) {
        return in_array($role, $item['roles'] ?? [], true);
    }));
}
```

### 4.3 Active Menu Highlight

```php
// sidebar.php
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
foreach ($menu as $item) {
    $active = str_starts_with($current_path, $item['path']) ? 'active' : '';
    // render <a class="nav-item {$active}" ...
}
```

---

## 5. Consult Management

### 5.1 Legacy CRM → ACEP Migration

**Background**: 기존 `consults` 테이블(CRM)과 ACEP `chat_rooms` 병행 운영 기간.

| Source | Identifier | Badge |
|--------|------------|-------|
| Legacy CRM | `consults.id` | CRM |
| ACEP | `chat_rooms.id` (UUID) | ACEP |

**Mapping table** (optional):

```sql
CREATE TABLE consult_migration_map (
  legacy_consult_id INT PRIMARY KEY,
  chat_room_id CHAR(36) NOT NULL,
  migrated_at DATETIME NOT NULL
);
```

### 5.2 consults/index.php — Unified List

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super', 'admin', 'operator']);

$can_ai_actions = in_array($_SESSION['role'], ['super', 'admin']);
$read_only = $_SESSION['role'] === 'operator';

include __DIR__ . '/../../includes/admin_layout_header.php';
?>

<div class="page-header">
  <h1>상담 목록</h1>
  <?php if (!$read_only): ?>
    <a href="create.php" class="btn btn-primary">+ 수동 상담 생성</a>
  <?php endif; ?>
</div>

<div class="filters">
  <!-- search, date, status, agent, source=CRM|ACEP|all -->
</div>

<table id="consults-table" class="data-table"
       data-api="/admin/consults/api/list.php"
       data-read-only="<?= $read_only ? '1' : '0' ?>">
  <thead>...</thead>
</table>

<?php include __DIR__ . '/../../includes/admin_layout_footer.php'; ?>
```

### 5.3 api/list.php — DataTables Source

```php
<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_role(['super', 'admin', 'operator']);

header('Content-Type: application/json');

$page = (int)($_GET['page'] ?? 1);
$limit = min(100, (int)($_GET['limit'] ?? 20));

// UNION query: ACEP chat_rooms + legacy consults (if not migrated)
$rows = ConsultRepository::fetchUnifiedList($_GET);

echo json_encode([
    'data' => $rows,
    'total' => ConsultRepository::countUnified($_GET),
    'page' => $page,
]);
```

### 5.4 view.php — Detail Timeline

- Messages from `messages` ordered by `created_at`
- AI sidebar: latest `ai_logs`, recommendations count
- Actions bar (admin+): AI 요약, AI 분석 links

---

## 6. AI Settings (ai.php)

### 6.1 Reference Implementation

> **중요**: 아래는 기존 `admin/settings/ai.php` 동작 문서화. 구현 변경 시 본 문서 동기화 필수.

**File**: `admin/settings/ai.php`

### 6.2 Request Flow

```
GET  ai.php
  → require_role(['super', 'admin'])
  → load settings from DB (key: ai_config JSON)
  → mask API keys unless role=super AND ?reveal=1
  → render form

POST ai.php
  → validate CSRF
  → validate fields (temperature 0-2, max_tokens 256-8192)
  → if role!=super: strip api_key fields from payload
  → encrypt api keys (AES-256-GCM)
  → save settings
  → activity_log('ai_settings.update', ...)
  → audit_logs INSERT (see §12)
  → redirect with flash success
```

### 6.3 Form Fields (Existing)

| Field | Name | Validation | super | admin |
|-------|------|------------|:-----:|:-----:|
| Primary Provider | `primary_provider` | enum | edit | edit |
| Primary Model | `primary_model` | string | edit | edit |
| Primary API Key | `primary_api_key` | string | edit | hidden/mask |
| Fallback Provider | `fallback_provider` | enum | edit | edit |
| Fallback Model | `fallback_model` | string | edit | edit |
| Fallback API Key | `fallback_api_key` | string | edit | hidden/mask |
| Temperature | `temperature` | float 0-2 | edit | edit |
| Max Tokens | `max_tokens` | int | edit | edit |
| Timeout | `timeout_sec` | int 5-120 | edit | edit |
| AI Recommend Enabled | `ai_recommend_enabled` | bool | edit | edit |
| Sentiment Analysis | `sentiment_enabled` | bool | edit | edit |

### 6.4 Test Connection AJAX

```php
// admin/settings/ai_test.php
require_role(['super', 'admin']);

$provider = $_POST['provider'];
$model = $_POST['model'];
$key = resolve_api_key($_POST, $_SESSION['role']); // super: new key; admin: DB key

$result = AiConnectionTester::ping($provider, $model, $key);

echo json_encode([
    'success' => $result->ok,
    'latency_ms' => $result->latency_ms,
    'message' => $result->message,
]);
```

### 6.5 ACEP Additive Fields (STEP 6)

```php
// Extend ai_config JSON — backward compatible
[
  // ... existing fields ...
  'failover_threshold' => 3,           // consecutive failures
  'recommendation_display' => 'inline', // inline|sidebar
  'ai_log_retention_days' => 90,       // super only
]
```

---

## 7. AI Endpoint Integration

### 7.1 admin/consults/ai_summary.php

**Purpose**: Trigger AI summary for consult/chat room

**Flow**:

```
GET/POST ai_summary.php?room_id={uuid}
  → require_role(['super', 'admin'])
  → validate room exists
  → call internal ACEP AI service OR POST /api/v1/ai/summary
  → store result in ai_logs (type=summary)
  → display summary in modal or redirect view.php#summary
```

**Pseudo-implementation**:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super', 'admin']);
require_once __DIR__ . '/../../includes/csrf.php';
csrf_validate();

$roomId = $_POST['room_id'] ?? '';
$messages = MessageRepository::getForRoom($roomId);
$prompt = PromptRepository::getActive('summary');

$summary = AiService::summarize($messages, $prompt);

AiLogRepository::insert([
    'chat_room_id' => $roomId,
    'log_type' => 'summary',
    'output' => $summary,
    'created_by' => $_SESSION['user_id'],
]);

activity_log('consult.ai_summary', ['room_id' => $roomId]);
header('Location: view.php?id=' . urlencode($roomId) . '&summary=1');
```

### 7.2 admin/consults/ai_reply.php

**Purpose**: Generate recommended agent reply

```php
$recommendation = AiService::recommendReply($roomId, $latestCustomerMessage);
AiRecommendationRepository::insert([
    'chat_room_id' => $roomId,
    'content' => $recommendation['text'],
    'confidence' => $recommendation['confidence'],
    'used' => false,
]);
// Agent UI picks up via API — admin view shows preview
```

### 7.3 admin/consults/ai_analyze.php

**Purpose**: Sentiment + contract probability analysis

```php
$analysis = AiService::analyze($roomId);
AiLogRepository::insert([
    'chat_room_id' => $roomId,
    'log_type' => 'analyze',
    'sentiment_label' => $analysis['sentiment'],
    'sentiment_score' => $analysis['sentiment_score'],
    'contract_probability' => $analysis['contract_probability'],
]);
// Dashboard funnel/sentiment charts consume ai_logs
```

### 7.4 Error Handling

| Error | User Message | Log |
|-------|--------------|-----|
| AI timeout | "AI 응답 시간 초과. Failover 로그를 확인하세요." | ai_failover_log |
| Rate limit | "API 호출 한도 초과. 잠시 후 재시도." | warning log |
| Invalid room | "상담을 찾을 수 없습니다." | 404 |

---

## 8. Live Chat Monitor (신규)

### 8.1 admin/monitor/index.php

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super', 'admin', 'operator']);

$read_only = true; // always for monitor module

include __DIR__ . '/../../includes/admin_layout_header.php';
?>

<div class="monitor-layout">
  <aside class="monitor-rooms" id="room-list">
    <!-- populated by monitor.js -->
  </aside>
  <section class="monitor-stream" id="message-stream">
    <div class="monitor-room-header"></div>
    <div class="monitor-messages"></div>
    <!-- NO input field — read-only -->
  </section>
  <aside class="monitor-insight" id="ai-insight-panel"></aside>
</div>

<script src="/admin/assets/js/monitor.js"></script>
<script>
  Monitor.init({
    roomsApi: '/admin/monitor/api/rooms.php',
    messagesApi: '/admin/monitor/api/messages.php',
    pollIntervalMs: 30000,
    preselectedRoom: '<?= htmlspecialchars($_GET['room'] ?? '') ?>'
  });
</script>

<?php include __DIR__ . '/../../includes/admin_layout_footer.php'; ?>
```

### 8.2 monitor.js Polling Logic

```javascript
const Monitor = {
  selectedRoomId: null,

  async init(config) {
    this.config = config;
    await this.refreshRooms();
    if (config.preselectedRoom) this.selectRoom(config.preselectedRoom);
    setInterval(() => this.tick(), config.pollIntervalMs);
  },

  async tick() {
    await this.refreshRooms();
    if (this.selectedRoomId) await this.refreshMessages(this.selectedRoomId);
  },

  async selectRoom(roomId) {
    this.selectedRoomId = roomId;
    await this.refreshMessages(roomId);
    await this.refreshInsight(roomId);
  }
};
```

### 8.3 api/rooms.php

```php
<?php
require_role(['super', 'admin', 'operator']);
header('Content-Type: application/json');

$rooms = db()->query("
  SELECT cr.id, cr.status, cr.updated_at,
         c.name_masked, a.display_name AS agent_name
  FROM chat_rooms cr
  LEFT JOIN customers c ON cr.customer_id = c.id
  LEFT JOIN chat_room_assignments cra ON cra.chat_room_id = cr.id AND cra.released_at IS NULL
  LEFT JOIN agents a ON cra.agent_id = a.id
  WHERE cr.status IN ('active', 'waiting')
  ORDER BY cr.updated_at DESC
  LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['rooms' => $rooms]);
```

### 8.4 V1.5 WebSocket Upgrade Path

```javascript
// monitor.js V1.5 addition
if (config.wsEnabled) {
  const socket = io('/admin', { auth: { token: config.token } });
  socket.emit('subscribe:monitor');
  socket.on('room:update', () => this.refreshRooms());
  socket.on('message:new', (msg) => this.appendMessage(msg));
}
```

---

## 9. Prompt Editor (신규)

### 9.1 admin/settings/prompts.php — List

```php
<?php
require_role(['super', 'admin']);
$can_delete = $_SESSION['role'] === 'super';

$prompts = PromptRepository::listAll($_GET);
// table: key, name, version, type, is_active, updated_at
// actions: edit, preview, activate, delete (super only, non-active)
```

### 9.2 prompts_edit.php — CRUD

```php
<?php
require_role(['super', 'admin']);

$id = $_GET['id'] ?? null;
$prompt = $id ? PromptRepository::find($id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $data = validate_prompt_input($_POST);

    if ($id) {
        // Save creates NEW version row, prompt_key immutable
        PromptRepository::createVersion($id, $data, $_SESSION['user_id']);
    } else {
        PromptRepository::create($data, $_SESSION['user_id']);
    }

    audit_log('prompt.update', 'ai_prompts/' . $data['prompt_key']);
    flash('success', '프롬프트가 저장되었습니다.');
    redirect('prompts.php');
}
```

### 9.3 Prompt Validation

```php
function validate_prompt_input(array $post): array {
    $allowed_types = ['system', 'greeting', 'summary', 'reply', 'analyze'];
    $type = $post['type'] ?? '';
    if (!in_array($type, $allowed_types, true)) {
        throw new ValidationException('Invalid prompt type');
    }
    if (strlen($post['template'] ?? '') > 32000) {
        throw new ValidationException('Template too long');
    }
    // Variable whitelist: {{customer_name}}, {{product_line}}, {{context}}
    return [...];
}
```

### 9.4 Activate Flow

```php
function activate_prompt(string $promptKey, int $version, int $userId): void {
    db()->beginTransaction();
    db()->exec("UPDATE ai_prompts SET is_active=0 WHERE prompt_key=" . q($promptKey));
    db()->exec("UPDATE ai_prompts SET is_active=1 WHERE prompt_key=" . q($promptKey) . " AND version=$version");
    audit_log('prompt.activate', "ai_prompts/{$promptKey}/v{$version}", $userId);
    db()->commit();
}
```

### 9.5 Preview AJAX

```php
// admin/settings/prompts_preview.php
$template = $_POST['template'];
$context = json_decode($_POST['sample_context'], true) ?? PromptRepository::defaultSampleContext();
echo json_encode(['rendered' => PromptRenderer::render($template, $context)]);
```

---

## 10. Failover Log Viewer (신규)

### 10.1 admin/settings/failover.php

```php
<?php
require_role(['super', 'admin', 'operator']);

$filters = [
    'start' => $_GET['start'] ?? date('Y-m-d', strtotime('-1 day')),
    'end' => $_GET['end'] ?? date('Y-m-d'),
    'provider' => $_GET['provider'] ?? '',
];

$logs = FailoverLogRepository::search($filters);
$stats = FailoverLogRepository::stats24h();

include admin_layout...
// render summary banner + table + expandable rows
```

### 10.2 Expandable Row Detail

```javascript
document.querySelectorAll('.failover-expand').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const id = e.target.dataset.id;
    const detail = await fetch(`/api/v1/admin/failover-logs/${id}`).then(r => r.json());
    renderDetailRow(id, detail);
  });
});
```

### 10.3 CSV Export (admin+)

```php
// admin/settings/failover_export.php
require_role(['super', 'admin']);
// stream CSV headers: created_at, room_id, from_provider, to_provider, reason, latency_ms
```

---

## 11. Agent Assignment UI (신규)

### 11.1 admin/agents/index.php

- CRUD agents (`agents` table)
- Columns: name, email, role, status, max_concurrent, today_stats
- Link to edit.php

### 11.2 admin/agents/edit.php

```php
// Assignment section
$assignments = AssignmentRepository::activeForAgent($agentId);

if ($_POST['action'] === 'release') {
    csrf_validate();
    require_role(['super', 'admin']);
    AssignmentRepository::release($_POST['assignment_id'], $_SESSION['user_id']);
    audit_log('assignment.release', ...);
}
```

### 11.3 api/assign.php

```php
<?php
require_role(['super', 'admin']);
csrf_validate();

$roomId = $_POST['room_id'];
$agentId = $_POST['agent_id'];

// Business rules
if (AssignmentRepository::hasActiveAssignment($roomId)) {
    http_response_code(409);
    echo json_encode(['error' => 'Room already assigned']);
    exit;
}

$activeCount = AssignmentRepository::countActiveForAgent($agentId);
$max = AgentRepository::getMaxConcurrent($agentId);
if ($activeCount >= $max) {
    http_response_code(422);
    echo json_encode(['error' => 'Agent at max concurrent limit']);
    exit;
}

AssignmentRepository::assign($roomId, $agentId, $_SESSION['user_id']);
audit_log('assignment.create', "chat_room_assignments/{$roomId}");
echo json_encode(['success' => true]);
```

### 11.4 chat_room_assignments Schema Usage

| Column | Usage |
|--------|-------|
| chat_room_id | FK |
| agent_id | FK |
| assigned_at | NOW() |
| released_at | NULL = active |
| assigned_by | admin user id |

---

## 12. CSRF·Activity Log

### 12.1 includes/csrf.php

```php
<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_validate(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('CSRF token mismatch');
    }
}
```

### 12.2 All POST Endpoints Requiring CSRF

| Endpoint | Method |
|----------|--------|
| ai.php | POST |
| prompts_edit.php | POST |
| agents/edit.php | POST |
| api/assign.php | POST |
| consults/create.php | POST |
| ai_summary.php | POST |

### 12.3 includes/activity_log.php

```php
function activity_log(string $action, array $context = []): void {
    db()->prepare("
        INSERT INTO activity_log (user_id, action, context_json, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ")->execute([
        $_SESSION['user_id'],
        $action,
        json_encode($context, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}
```

### 12.4 audit_logs (Sensitive Actions)

**Required for** (see [`04_Admin_API_및_권한_명세.md`](./04_Admin_API_및_권한_명세.md)):

| Action | audit action code |
|--------|-------------------|
| API key change | `ai_key.update` |
| Prompt activate | `prompt.activate` |
| Prompt delete | `prompt.delete` |
| Agent role change | `agent.role.update` |
| Assignment create/release | `assignment.create`, `assignment.release` |
| System maintenance mode | `system.maintenance` |

```php
function audit_log(string $action, string $resource, ?int $userId = null): void {
    db()->prepare("
        INSERT INTO audit_logs (actor_id, action, resource, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([
        $userId ?? $_SESSION['user_id'],
        $action,
        $resource,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
}
```

---

## 13. Admin Menu Sitemap JSON

### 13.1 admin/config/menu.json

```json
[
  {
    "id": "dashboard",
    "label": "대시보드",
    "icon": "layout-dashboard",
    "path": "/admin/index.php",
    "roles": ["super", "admin", "operator"]
  },
  {
    "id": "consults",
    "label": "상담 운영",
    "icon": "messages-square",
    "roles": ["super", "admin", "operator"],
    "children": [
      {
        "id": "monitor",
        "label": "실시간 모니터",
        "path": "/admin/monitor/index.php",
        "roles": ["super", "admin", "operator"]
      },
      {
        "id": "consult-list",
        "label": "상담 목록",
        "path": "/admin/consults/index.php",
        "roles": ["super", "admin", "operator"]
      }
    ]
  },
  {
    "id": "agents",
    "label": "에이전트 관리",
    "icon": "users",
    "path": "/admin/agents/index.php",
    "roles": ["super", "admin"]
  },
  {
    "id": "ai",
    "label": "AI 설정",
    "icon": "bot",
    "roles": ["super", "admin"],
    "children": [
      {
        "id": "ai-settings",
        "label": "AI 모델·파라미터",
        "path": "/admin/settings/ai.php",
        "roles": ["super", "admin"]
      },
      {
        "id": "prompts",
        "label": "프롬프트 관리",
        "path": "/admin/settings/prompts.php",
        "roles": ["super", "admin"]
      },
      {
        "id": "failover",
        "label": "Failover 로그",
        "path": "/admin/settings/failover.php",
        "roles": ["super", "admin", "operator"]
      }
    ]
  },
  {
    "id": "system",
    "label": "시스템",
    "icon": "settings",
    "roles": ["super", "admin"],
    "children": [
      {
        "id": "general",
        "label": "일반 설정",
        "path": "/admin/settings/general.php",
        "roles": ["super", "admin"]
      },
      {
        "id": "audit",
        "label": "감사 로그",
        "path": "/admin/settings/audit.php",
        "roles": ["super", "admin"]
      }
    ]
  }
]
```

### 13.2 Menu Versioning

- `menu.json` version in comment header
- Invalid JSON → fallback hardcoded minimum menu + error log

---

## 14. 마이그레이션·배포

### 14.1 DB Migrations (STEP 6)

```sql
-- migrations/006_admin_acep.sql
-- ai_prompts, ai_failover_log if not exist
-- chat_room_assignments if not exist
-- audit_logs if not exist
-- indexes for dashboard (see 02_Admin_Dashboard_구현명세.md)
```

### 14.2 Deploy Checklist

- [ ] Run migrations on staging
- [ ] Verify ai.php unchanged behavior (regression)
- [ ] Seed sample prompts (system, greeting, summary, reply, analyze)
- [ ] Create operator test account
- [ ] nginx: /admin/* session cookie secure, httponly
- [ ] API rate limits on stats endpoints

### 14.3 Rollback Plan

- Feature flags in `settings`: `acep_admin_monitor_enabled`, `acep_prompt_editor_enabled`
- Disable menu items via menu.json without code deploy

---

## 15. 테스트 체크리스트

### 15.1 Module Smoke Tests

| Module | Test |
|--------|------|
| Auth | agent → 403 on /admin/ |
| ai.php | save temperature, key masked for admin |
| consults | ACEP filter, ai_summary generates log |
| monitor | no input field in DOM |
| prompts | activate deactivates previous |
| failover | list loads, expand detail |
| agents | assign at max → 422 |
| CSRF | POST without token → 419 |
| audit | prompt save creates audit row |

### 15.2 Regression (Legacy)

| Test | Expected |
|------|----------|
| CRM consult list row | Still visible with CRM badge |
| ai.php test connection | Works with existing keys |
| ai_reply from consult view | Recommendation row created |

---

## 16. 부록

### 16.1 Related Documents

- [01_관리자화면_UIUX_설계.md](./01_관리자화면_UIUX_설계.md)
- [02_Admin_Dashboard_구현명세.md](./02_Admin_Dashboard_구현명세.md)
- [04_Admin_API_및_권한_명세.md](./04_Admin_API_및_권한_명세.md)
- [03_SYSTEM/01_DB설계.md](../03_SYSTEM/01_DB설계.md)

### 16.2 변경 이력

| 버전 | 날짜 | 변경 |
|------|------|------|
| 1.0.0 | 2026-07-21 | STEP 6 초판 — PHP modules, ai.php ref, ACEP extensions |

---

*End of document*
