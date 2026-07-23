<?php
declare(strict_types=1);
/** 상담 상세 + 상태변경 + 담당자 배정 + 메모 (Phase 1 고도화) */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';
require_login();

$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);
$no = clean_str($_GET['no'] ?? '', 20);

// 대상 상담 로드
$stmt = $pdo->prepare(
    "SELECT c.*, cu.name AS cust_name, cu.phone, cu.company, cu.email, cu.region,
            cu.address, cu.zipcode, cu.customer_no, s.site_name, s.brand, s.division
     FROM consults c
     LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
     LEFT JOIN sites s ON s.id = c.site_id
     WHERE c.consult_no = :no LIMIT 1"
);
$stmt->execute([':no' => $no]);
$c = $stmt->fetch();
if (!$c) { http_response_code(404); echo '상담을 찾을 수 없습니다.'; exit; }

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // 삭제는 super/admin 전용 → 처리 후 목록으로
    if ($action === 'delete') {
        require_role(['super', 'admin']);
        try {
            delete_consult($pdo, (int)$c['id']);
            log_activity('consult_delete', 'consult:' . $c['consult_no']);
        } catch (Throwable $ex) {
            log_error('consult_view_delete', $ex->getMessage());
        }
        header('Location: /admin/consults/');
        exit;
    }

    if (!can_edit_consult()) { http_response_code(403); echo '권한 없음'; exit; }

    if ($action === 'status') {
        $to = clean_str($_POST['status'] ?? '', 20);
        if (isset(CONSULT_STATUSES[$to]) && $to !== $c['status']) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE consults SET status = :st WHERE id = :id')
                    ->execute([':st' => $to, ':id' => (int)$c['id']]);
                $curMgrId = current_manager()['id'] ?? null;
                $pdo->prepare(
                    'INSERT INTO consult_history (consult_id, from_status, to_status, manager_id, note)
                     VALUES (:cid, :from, :to, :mid, :note)'
                )->execute([
                    ':cid' => (int)$c['id'], ':from' => $c['status'], ':to' => $to,
                    ':mid' => is_numeric($curMgrId) ? (int)$curMgrId : null,
                    ':note' => clean_str($_POST['note'] ?? '', 255) ?: null,
                ]);
                $pdo->commit();
                log_activity('update_status', 'consult:' . $c['consult_no'], $c['status'] . '→' . $to);
                $flash = '상태를 변경했습니다.';
                $c['status'] = $to;
            } catch (Throwable $ex) {
                $pdo->rollBack(); log_error('consult_view', $ex->getMessage());
                $flash = '상태 변경 실패';
            }
        }
    } elseif ($action === 'assign') {
        $mid = (int)($_POST['manager_id'] ?? 0) ?: null;
        $pdo->prepare('UPDATE consults SET manager_id = :mid WHERE id = :id')
            ->execute([':mid' => $mid, ':id' => (int)$c['id']]);
        log_activity('assign', 'consult:' . $c['consult_no'], 'manager:' . ($mid ?? 0));
        $c['manager_id'] = $mid;
        $flash = '담당자를 배정했습니다.';
    }
}

// 부가 데이터
$managers = $pdo->query("SELECT id, name FROM managers WHERE status = 1 ORDER BY name")->fetchAll();
$hst = $pdo->prepare(
    "SELECT h.*, m.name AS mgr FROM consult_history h
     LEFT JOIN managers m ON m.id = h.manager_id
     WHERE h.consult_id = :id ORDER BY h.id DESC"
);
$hst->execute([':id' => (int)$c['id']]);
$history = $hst->fetchAll();

$detail = $c['detail_json'] ? json_decode($c['detail_json'], true) : null;

// ACEP 채팅방 ID
$roomId = null;
if (is_array($detail) && !empty($detail['room_id'])) {
    $roomId = (string)$detail['room_id'];
}
if (!$roomId) {
    try {
        $rs = $pdo->prepare('SELECT id FROM chat_rooms WHERE legacy_consult_id = :cid AND deleted_at IS NULL LIMIT 1');
        $rs->execute([':cid' => (int)$c['id']]);
        $rid = $rs->fetchColumn();
        if ($rid) $roomId = (string)$rid;
    } catch (Throwable) {}
}

require_once __DIR__ . '/../../config/acep.php';
$acepJwt = acep_access_token();
$wsUrl = acep_chat_ws_url();
if ($wsUrl === '') {
    $wsUrl = getenv('ACEP_WS_URL') ?: 'wss://plustok.onrender.com';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === 'localhost' || str_contains($host, '127.0.0.1')) {
        $wsUrl = getenv('ACEP_WS_URL') ?: 'http://localhost:3001';
    }
}

$page_title = '상담 ' . $c['consult_no']; $active = 'consults';
require INC_DIR . '/header.php';

$priorityBg = ['URGENT' => '#ef4444', 'HIGH' => '#f97316', 'NORMAL' => '#3b82f6', 'LOW' => '#6b7280'];
$priorityText = ['URGENT' => '🚨 긴급(URGENT)', 'HIGH' => '🔥 높음(HIGH)', 'NORMAL' => '⚡ 보통(NORMAL)', 'LOW' => '🌱 낮음(LOW)'];
$curPrio = !empty($c['priority']) ? strtoupper((string)$c['priority']) : 'NORMAL';
$sentimentIcons = ['POSITIVE' => '😊 호의/칭찬', 'NEUTRAL' => '😐 일반/중립', 'NEGATIVE' => '😡 불만/급함'];
$curSent = !empty($c['sentiment']) ? strtoupper((string)$c['sentiment']) : 'NEUTRAL';
$leadScore = (int)($c['lead_score'] ?? 0);
$starsCount = (int)round($leadScore / 20);
$starsStr = str_repeat('★', $starsCount) . str_repeat('☆', 5 - $starsCount);
?>
<!-- SmartEditor2 -->
<script src="/plugin/editor/smarteditor2/js/service/HuskyEZCreator.js" charset="utf-8"></script>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <div>
    <a href="/admin/consults/" style="color:#6b7280;text-decoration:none;font-weight:bold;margin-right:12px;">← 목록</a>
    <h1 style="display:inline;margin:0;font-size:22px;">
      <?= e($c['consult_no']) ?>
      <span class="badge st-<?= e($c['status']) ?>"><?= e(CONSULT_STATUSES[$c['status']] ?? $c['status']) ?></span>
    </h1>
  </div>
  <?php if (can_manage()): ?>
    <form method="post" onsubmit="return confirm('이 상담을 완전히 삭제할까요? (되돌릴 수 없음)')">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <button class="btn danger" style="padding:6px 12px;font-size:13px">상담 삭제</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>

<!-- 레이아웃 컨테이너: 상단 2단 분할 -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;margin-bottom:20px;">
  
  <!-- 좌측 영역 -->
  <div>
    <!-- 상담내용 -->
    <div class="card" style="margin-bottom:20px;">
      <h3 style="margin-top:0">고객 요청 내용</h3>
      <div style="background:#f9fafb;padding:12px;border-radius:6px;border:1px solid #e5e7eb;white-space:pre-wrap;line-height:1.6;">
        <?= e($c['memo'] ?? '') ?: '<span class="muted">내용 없음</span>' ?>
      </div>
      
      <?php if ($detail): ?>
        <h4 style="margin-top:16px;margin-bottom:8px;">상품별 상세</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <tbody>
            <?php foreach ($detail as $k => $v): ?>
              <tr>
                <th style="border:1px solid #e5e7eb;padding:8px;background:#f3f4f6;text-align:left;width:120px;"><?= e((string)$k) ?></th>
                <td style="border:1px solid #e5e7eb;padding:8px;"><?= e(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- AI 요약 -->
    <div class="card" style="margin-bottom:20px;border:1px solid #e5e7eb">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h3 style="margin:0;display:flex;align-items:center;gap:8px">
          ✨ AI 상담 요약
        </h3>
        <div style="display:flex;align-items:center;gap:10px">
          <span id="ai-summary-time" class="muted" style="font-size:12px"><?= $c['ai_summary_at'] ? e($c['ai_summary_at']) . ' 생성' : '' ?></span>
          <button type="button" id="btn-ai-summary" class="btn" style="padding:4px 10px;font-size:12px">
            <?= $c['ai_summary'] ? '🔄 재생성' : '✨ AI 요약 생성' ?>
          </button>
        </div>
      </div>
      <div id="ai-summary-text" style="white-space:pre-wrap;line-height:1.6;color:#111827;background:#f9fafb;padding:12px;border-radius:6px;min-height:36px">
        <?= $c['ai_summary'] ? e($c['ai_summary']) : '<span class="muted">아직 생성된 AI 요약이 없습니다.</span>' ?>
      </div>
    </div>

    <!-- 실시간 메시지 -->
    <?php if ($roomId): ?>
    <style>
      #consult-messaging.consult-chat-card {
        margin-bottom: 20px;
        padding: 16px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
      }
      #consult-messaging .consult-chat-phone {
        max-width: 380px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        height: 480px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04);
        background: #fff;
      }
      #consult-messaging .consult-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        background: #2b6cb0;
        color: #fff;
        flex-shrink: 0;
      }
      #consult-messaging .consult-chat-header-title {
        font-weight: 600;
        font-size: 15px;
        line-height: 1.3;
      }
      #consult-messaging .consult-chat-header-status {
        font-size: 11px;
        font-weight: 400;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 5px;
      }
      #consult-messaging .consult-chat-header-status::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #a0aec0;
        flex-shrink: 0;
      }
      #consult-messaging .consult-chat-header-status.is-connected::before {
        background: #68d391;
        box-shadow: 0 0 0 2px rgba(104, 211, 145, 0.35);
      }
      #consult-messaging .consult-chat-header-status.is-error::before {
        background: #fc8181;
      }
      #consult-messaging .consult-chat-warn {
        padding: 8px 12px;
        background: #fef3c7;
        border-bottom: 1px solid #fcd34d;
        color: #92400e;
        font-size: 12px;
        flex-shrink: 0;
      }
      #consult-messaging .consult-chat-msgs {
        flex: 1;
        overflow-y: auto;
        padding: 14px 12px;
        background: #f0f2f5;
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-height: 0;
      }
      #consult-messaging .consult-chat-row {
        display: flex;
        flex-direction: column;
        max-width: 82%;
      }
      #consult-messaging .consult-chat-row.customer {
        align-self: flex-start;
        align-items: flex-start;
      }
      #consult-messaging .consult-chat-row.agent {
        align-self: flex-end;
        align-items: flex-end;
      }
      #consult-messaging .consult-chat-meta {
        font-size: 11px;
        color: #718096;
        margin-bottom: 4px;
        padding: 0 4px;
      }
      #consult-messaging .consult-chat-row.agent .consult-chat-meta {
        margin-bottom: 0;
        margin-top: 4px;
        text-align: right;
      }
      #consult-messaging .consult-chat-bubble {
        padding: 10px 14px;
        border-radius: 18px;
        font-size: 14px;
        line-height: 1.45;
        white-space: pre-wrap;
        word-break: break-word;
      }
      #consult-messaging .consult-chat-row.customer .consult-chat-bubble {
        background: #fff;
        color: #1f2933;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
      }
      #consult-messaging .consult-chat-row.agent .consult-chat-bubble {
        background: #2b6cb0;
        color: #fff;
        border-bottom-right-radius: 4px;
      }
      #consult-messaging .consult-chat-loading,
      #consult-messaging .consult-chat-empty {
        margin: auto;
        text-align: center;
        color: #718096;
        font-size: 13px;
        padding: 20px 12px;
      }
      #consult-messaging .consult-chat-skeleton {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 14px;
        align-items: flex-start;
      }
      #consult-messaging .consult-chat-skeleton-bubble {
        height: 36px;
        border-radius: 18px;
        background: linear-gradient(90deg, #e2e8f0 25%, #edf2f7 50%, #e2e8f0 75%);
        background-size: 200% 100%;
        animation: consult-chat-shimmer 1.4s ease-in-out infinite;
      }
      #consult-messaging .consult-chat-skeleton-bubble.left { width: 65%; }
      #consult-messaging .consult-chat-skeleton-bubble.right {
        width: 50%;
        align-self: flex-end;
      }
      #consult-messaging .consult-chat-skeleton-bubble.left.short { width: 45%; }
      @keyframes consult-chat-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
      }
      #consult-messaging .consult-chat-foot {
        display: flex;
        gap: 8px;
        padding: 10px 12px;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        flex-shrink: 0;
        align-items: flex-end;
      }
      #consult-messaging .consult-chat-foot textarea {
        flex: 1;
        padding: 10px 16px;
        border: 1px solid #cbd5e0;
        border-radius: 22px;
        resize: none;
        font-size: 14px;
        font-family: inherit;
        line-height: 1.4;
        max-height: 80px;
        background: #f7fafc;
      }
      #consult-messaging .consult-chat-foot textarea:focus {
        outline: none;
        border-color: #2b6cb0;
        background: #fff;
      }
      #consult-messaging .consult-chat-send {
        padding: 0 18px;
        height: 40px;
        border: 0;
        border-radius: 20px;
        background: #2b6cb0;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        flex-shrink: 0;
      }
      #consult-messaging .consult-chat-send:hover { background: #2c5282; }
      #consult-messaging .consult-chat-send:disabled {
        background: #a0aec0;
        cursor: not-allowed;
      }
    </style>
    <div class="card consult-chat-card" id="consult-messaging" data-room-id="<?= e($roomId) ?>">
      <div class="consult-chat-phone">
        <div class="consult-chat-header">
          <div class="consult-chat-header-title">💬 실시간 상담 메시지</div>
          <span id="msg-connection-status" class="consult-chat-header-status">준비 중…</span>
        </div>
        <?php if (!$acepJwt): ?>
          <div class="consult-chat-warn">ACEP 통합 계정으로 로그인해야 사용할 수 있습니다.</div>
        <?php endif; ?>
        <div id="msg-list" class="consult-chat-msgs">
          <div class="consult-chat-loading">
            <div class="consult-chat-skeleton" aria-hidden="true">
              <div class="consult-chat-skeleton-bubble left"></div>
              <div class="consult-chat-skeleton-bubble right"></div>
              <div class="consult-chat-skeleton-bubble left short"></div>
            </div>
            메시지를 불러오는 중…
          </div>
        </div>
        <?php if (can_edit_consult() && $acepJwt): ?>
        <form id="msg-form" class="consult-chat-foot">
          <textarea id="msg-input" rows="1" placeholder="메시지를 입력하세요…"></textarea>
          <button type="submit" class="consult-chat-send" id="msg-send-btn">전송</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- 우측 영역 -->
  <div>
    <!-- 상태 관리 -->
    <div class="card" style="margin-bottom:20px;background:#fefefe;border:2px solid #6366f1;">
      <h3 style="margin-top:0;color:#4f46e5;">진행 상태 변경</h3>
      <form method="post" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="status">
        <div style="display:flex;gap:10px;">
          <select name="status" style="flex:1;padding:8px;border-radius:6px;border:1px solid #d1d5db;">
            <?php foreach (CONSULT_STATUSES as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn" style="background:#4f46e5;color:#fff;border:none;">변경</button>
        </div>
        <div>
          <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">변경 메모 (선택)</label>
          <input type="text" name="note" maxlength="255" style="width:100%;box-sizing:border-box;padding:8px;border-radius:6px;border:1px solid #d1d5db;">
        </div>
      </form>

      <h3 style="margin-top:16px;color:#4f46e5;border-top:1px solid #e5e7eb;padding-top:16px;">담당자 배정</h3>
      <form method="post" style="display:flex;gap:10px;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="assign">
        <select name="manager_id" style="flex:1;padding:8px;border-radius:6px;border:1px solid #d1d5db;">
          <option value="0">미배정</option>
          <?php foreach ($managers as $mg): ?>
            <option value="<?= (int)$mg['id'] ?>" <?= (int)$c['manager_id'] === (int)$mg['id'] ? 'selected' : '' ?>><?= e($mg['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" style="background:#4b5563;color:#fff;border:none;">배정</button>
      </form>
    </div>

    <!-- 고객/상담 정보 -->
    <div class="card" style="margin-bottom:20px;">
      <h3 style="margin-top:0">고객 및 상담 정보</h3>
      <table style="width:100%;font-size:13px;"><tbody>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">이름</th><td><a href="/admin/customers/view.php?id=<?= (int)$c['customer_id'] ?>"><?= e($c['cust_name']) ?></a></td></tr>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">연락처</th><td class="mono"><?= e($c['phone']) ?></td></tr>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">이메일</th><td><?= e($c['email'] ?? '-') ?></td></tr>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">회사</th><td><?= e($c['company'] ?? '-') ?></td></tr>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">접수일시</th><td><?= e($c['created_at']) ?></td></tr>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">상품</th><td><?= e($c['product_name'] ?? '-') ?></td></tr>
        <tr><th style="text-align:left;color:#6b7280;padding:4px 0;">사이트</th><td><?= e($c['site_name']) ?></td></tr>
      </tbody></table>
    </div>

    <!-- AI 추천 (STEP 9) -->
    <div class="card" style="margin-bottom:20px;background:#f8fafc;border:1px solid #e2e8f0;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;font-size:15px;color:#1e293b;">🤖 AI 인사이트</h3>
        <button type="button" id="btn-ai-analyze" class="btn" style="padding:4px 8px;font-size:11px;">
          <?= $c['ai_analyzed_at'] ? '재분석' : '분석실행' ?>
        </button>
      </div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
        <div style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e2e8f0;">
          <div style="color:#64748b;font-size:11px;">계약 가능성</div>
          <div id="ai-val-score" style="font-weight:bold;color:#059669;"><?= $leadScore > 0 ? "{$leadScore}점" : "미분석" ?></div>
        </div>
        <div style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e2e8f0;">
          <div style="color:#64748b;font-size:11px;">긴급도</div>
          <div id="ai-val-priority"><?= $c['ai_analyzed_at'] ? e($priorityText[$curPrio] ?? $curPrio) : '미분석' ?></div>
        </div>
        <div style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e2e8f0;">
          <div style="color:#64748b;font-size:11px;">감정 분석</div>
          <div id="ai-val-sentiment"><?= $c['ai_analyzed_at'] ? e($sentimentIcons[$curSent] ?? $curSent) : '미분석' ?></div>
        </div>
        <div style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e2e8f0;">
          <div style="color:#64748b;font-size:11px;">추천 분류</div>
          <div id="ai-val-category"><?= e($c['category_ai'] ?: '미분석') ?></div>
        </div>
      </div>
      <div style="margin-top:8px;font-size:12px;color:#64748b;" id="ai-val-tags">
        <?= e($c['tags'] ?? '') ?>
      </div>
    </div>
  </div>
</div>

<!-- 하단 탭 영역 (메모, 첨부파일) -->
<div class="card" style="margin-bottom:20px;">
  <!-- 탭 네비게이션 -->
  <div style="display:flex;border-bottom:2px solid #e5e7eb;margin-bottom:16px;">
    <button type="button" class="tab-btn active" data-tab="tab-reply" style="padding:10px 20px;background:none;border:none;font-weight:bold;color:#4f46e5;border-bottom:2px solid #4f46e5;margin-bottom:-2px;cursor:pointer;">✉️ 고객 답변 발송</button>
    <button type="button" class="tab-btn" data-tab="tab-memo" style="padding:10px 20px;background:none;border:none;font-weight:bold;color:#6b7280;cursor:pointer;">📝 내부 메모</button>
    <button type="button" class="tab-btn" data-tab="tab-file" style="padding:10px 20px;background:none;border:none;font-weight:bold;color:#6b7280;cursor:pointer;">📎 첨부 파일</button>
    <button type="button" class="tab-btn" data-tab="tab-history" style="padding:10px 20px;background:none;border:none;font-weight:bold;color:#6b7280;cursor:pointer;">🕒 상태 이력</button>
  </div>

  <!-- 고객 답변 탭 -->
  <div id="tab-reply" class="tab-content" style="display:block;">
    <div style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:16px;">고객 답변 작성</h3>
      <button type="button" id="btn-ai-reply-draft" class="btn" style="background:#8b5cf6;color:#fff;font-size:12px;padding:6px 12px;border:none;border-radius:4px;font-weight:bold;">✨ AI 답변 초안 생성</button>
    </div>
    
    <div style="margin-bottom:12px;">
      <input type="text" id="reply-subject" placeholder="답변 제목을 입력하세요." style="width:100%;box-sizing:border-box;padding:10px;border-radius:4px;border:1px solid #d1d5db;">
    </div>

    <div style="margin-bottom:16px;">
      <textarea id="editor-reply" style="width:100%;height:300px;display:none;"></textarea>
    </div>
    
    <div style="display:flex;justify-content:space-between;align-items:center;background:#f9fafb;padding:12px;border-radius:6px;border:1px solid #e5e7eb;">
      <div style="display:flex;gap:16px;align-items:center;font-size:13px;">
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="checkbox" id="reply-chk-email" checked> 이메일 발송</label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="checkbox" id="reply-chk-sms"> 문자 발송</label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="checkbox" id="reply-chk-kakao"> 카카오 알림톡</label>
        <label style="display:flex;align-items:center;gap:4px;color:#ef4444;font-weight:bold;cursor:pointer;"><input type="checkbox" id="reply-chk-close"> 상담 종료</label>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="button" id="btn-reply-save" class="btn" style="background:#e5e7eb;color:#374151;">임시저장</button>
        <button type="button" id="btn-reply-send" class="btn" style="background:#2563eb;color:#fff;font-weight:bold;">고객에게 발송</button>
      </div>
    </div>
    
    <div id="comm-list-wrap" style="margin-top:24px;border-top:2px solid #e5e7eb;padding-top:16px;">
      <h4 style="margin:0 0 12px 0;font-size:15px;color:#374151;display:flex;align-items:center;gap:6px;">
        <span>📨 고객 발송 이력 (Email / SMS / 알림톡)</span>
      </h4>
      <div id="comm-list" style="display:flex;flex-direction:column;gap:10px;">
        <!-- 발송 이력 로드 영역 -->
      </div>
    </div>
  </div>

  <!-- 내부메모 탭 -->
  <div id="tab-memo" class="tab-content" style="display:none;">
    <div style="margin-bottom:16px;">
      <textarea id="editor-memo"></textarea>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
        <select id="memo-type" style="padding:6px;border-radius:4px;border:1px solid #d1d5db;">
          <option value="general">일반 메모</option>
          <option value="vip">VIP 노트</option>
          <option value="caution">주의사항</option>
        </select>
        <button type="button" id="btn-save-memo" class="btn" style="background:#059669;color:#fff;font-weight:bold;">메모 저장</button>
      </div>
    </div>
    <div id="memo-list" style="border-top:1px solid #e5e7eb;padding-top:16px;display:flex;flex-direction:column;gap:12px;">
      <!-- 메모가 로드될 영역 -->
    </div>
  </div>

  <!-- 첨부파일 탭 -->
  <div id="tab-file" class="tab-content" style="display:none;">
    <div id="drop-zone" style="border:2px dashed #9ca3af;border-radius:8px;padding:30px;text-align:center;background:#f9fafb;cursor:pointer;margin-bottom:16px;transition:background 0.2s;">
      <p style="margin:0;color:#6b7280;font-size:15px;">여기로 파일을 드래그하거나 클릭하여 업로드하세요</p>
      <input type="file" id="file-input" style="display:none;" multiple>
    </div>
    <div style="margin-bottom:16px;">
      <select id="file-category" style="padding:6px;border-radius:4px;border:1px solid #d1d5db;">
        <option value="etc">기타/일반</option>
        <option value="quote">견적서</option>
        <option value="contract">계약서</option>
        <option value="business_license">사업자등록증</option>
        <option value="photo">사진</option>
      </select>
    </div>
    <div id="file-list">
      <!-- 파일 목록 -->
    </div>
  </div>

  <!-- 상태이력 탭 -->
  <div id="tab-history" class="tab-content" style="display:none;">
    <div class="tablewrap">
      <table>
        <thead><tr><th>시각</th><th>변경</th><th>담당자</th><th>메모</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td class="muted"><?= e($h['created_at']) ?></td>
              <td><?= e($h['from_status'] ? (CONSULT_STATUSES[$h['from_status']] ?? $h['from_status']) : '-') ?>
                → <?= e(CONSULT_STATUSES[$h['to_status']] ?? $h['to_status']) ?></td>
              <td><?= e($h['mgr'] ?? '-') ?></td>
              <td><?= e($h['note'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($roomId && $acepJwt): ?>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js" crossorigin="anonymous"></script>
<?php endif; ?>

<script>
(function() {
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  const consultId = <?= (int)$c['id'] ?>;

  // 탭 네비게이션 스크립트
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => {
        b.classList.remove('active');
        b.style.color = '#6b7280';
        b.style.borderBottom = 'none';
      });
      tabContents.forEach(c => c.style.display = 'none');
      
      btn.classList.add('active');
      btn.style.color = '#4f46e5';
      btn.style.borderBottom = '2px solid #4f46e5';
      document.getElementById(btn.dataset.tab).style.display = 'block';
      if (btn.dataset.tab === 'tab-memo') loadMemos();
      if (btn.dataset.tab === 'tab-file') loadFiles();
      if (btn.dataset.tab === 'tab-reply') loadCommunications();
    });
  });

  // SmartEditor2 설정
  var oEditors = [];
  nhn.husky.EZCreator.createInIFrame({
      oAppRef: oEditors,
      elPlaceHolder: "editor-memo",
      sSkinURI: "/plugin/editor/smarteditor2/SmartEditor2Skin.html",
      fCreator: "createSEditor2"
  });
  
  nhn.husky.EZCreator.createInIFrame({
      oAppRef: oEditors,
      elPlaceHolder: "editor-reply",
      sSkinURI: "/plugin/editor/smarteditor2/SmartEditor2Skin.html",
      fCreator: "createSEditor2"
  });

  // 발송 이력 로드
  const commList = document.getElementById('comm-list');
  async function loadCommunications() {
    if (!commList) return;
    try {
      const res = await fetch(`/admin/consults/api_communications_list.php?consult_id=${consultId}`);
      const data = await res.json();
      if (data.ok) {
        if (!data.items || data.items.length === 0) {
          commList.innerHTML = '<div style="padding:16px;text-align:center;color:#9ca3af;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">아직 발송된 내역이 없습니다.</div>';
          return;
        }
        commList.innerHTML = data.items.map(item => {
          const badgeColor = item.status === 'SENT' ? '#10b981' : '#ef4444';
          const badgeText = item.status === 'SENT' ? '발송완료' : '발송실패';
          return `
            <div style="background:#f9fafb;padding:14px;border-radius:6px;border:1px solid #e5e7eb;border-left:4px solid ${badgeColor};">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:13px;">
                <div>
                  <span style="background:${badgeColor};color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:bold;margin-right:6px;">[${item.comm_type}] ${badgeText}</span>
                  <strong style="color:#1f2937;">${item.subject || '(제목 없음)'}</strong>
                  <span style="color:#6b7280;margin-left:6px;font-size:12px;">→ ${item.recipient || ''}</span>
                </div>
                <span style="color:#6b7280;font-size:12px;">${item.sent_at} (${item.manager_name || '관리자'})</span>
              </div>
              <div style="font-size:13px;color:#4b5563;line-height:1.5;background:#fff;padding:10px;border-radius:4px;border:1px solid #f3f4f6;margin-top:8px;">
                ${item.content_html}
              </div>
              ${item.error_msg ? `<div style="color:#ef4444;font-size:12px;margin-top:6px;">⚠️ 오류: ${item.error_msg}</div>` : ''}
            </div>
          `;
        }).join('');
      }
    } catch (e) {
      console.error(e);
    }
  }

  // 메모 저장 및 로드
  const memoList = document.getElementById('memo-list');
  
  async function loadMemos() {
    try {
      const res = await fetch(`/admin/consults/api_memo_list.php?consult_id=${consultId}`);
      const data = await res.json();
      if (data.ok) {
        memoList.innerHTML = data.memos.map(m => `
          <div style="background:#f9fafb;padding:16px;border-radius:6px;border:1px solid #e5e7eb;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;color:#6b7280;">
              <span><strong>${m.manager_name || '알 수 없음'}</strong> (${m.memo_type})</span>
              <span>${m.created_at}</span>
            </div>
            <div style="line-height:1.6;">${m.content_html}</div>
          </div>
        `).join('');
      }
    } catch (e) {
      console.error(e);
    }
  }

  document.getElementById('btn-save-memo').addEventListener('click', async () => {
    oEditors.getById["editor-memo"].exec("UPDATE_CONTENTS_FIELD", []);
    const content = document.getElementById('editor-memo').value;
    const memoType = document.getElementById('memo-type').value;
    if (!content.trim() || content === '<p><br></p>') return alert('메모 내용을 입력하세요.');

    const formData = new URLSearchParams();
    formData.append('_csrf', csrfToken);
    formData.append('consult_id', consultId);
    // Cafe24 웹방화벽 차단 방지를 위해 Base64 인코딩
    formData.append('content_html', btoa(unescape(encodeURIComponent(content))));
    formData.append('is_base64', '1');
    formData.append('memo_type', memoType);

    try {
      const res = await fetch('/admin/consults/api_memo_save.php', {
        method: 'POST', body: formData
      });
      const data = await res.json();
      if (data.ok) {
        oEditors.getById["editor-memo"].exec("SET_IR", [""]);
        loadMemos();
      } else {
        alert(data.error || '저장 실패');
      }
    } catch (e) {
      alert('오류 발생');
    }
  });

  // AI 답변 초안 생성
  const btnAiReply = document.getElementById('btn-ai-reply-draft');
  if (btnAiReply) {
    btnAiReply.addEventListener('click', async () => {
      btnAiReply.disabled = true;
      const origText = btnAiReply.innerText;
      btnAiReply.innerText = '⏳ AI 작성 중...';
      try {
        const formData = new URLSearchParams();
        formData.append('_csrf', csrfToken);
        formData.append('consult_id', consultId);
        
        const res = await fetch('/admin/consults/api_ai_reply.php', {
          method: 'POST', body: formData
        });
        const data = await res.json();
        if (data.ok) {
          document.getElementById('reply-subject').value = data.data.subject;
          oEditors.getById["editor-reply"].exec("SET_IR", [""]);
          oEditors.getById["editor-reply"].exec("PASTE_HTML", [data.data.content_html]);
          alert('AI 답변 초안이 작성되었습니다. 내용을 확인하고 수정 후 발송하세요.');
        } else {
          alert('AI 답변 생성 실패: ' + (data.error || '알 수 없는 오류'));
        }
      } catch (e) {
        alert('오류 발생: 네트워크 상태를 확인하세요.');
      } finally {
        btnAiReply.innerText = origText;
        btnAiReply.disabled = false;
      }
    });
  }

  // 고객 답변 발송 처리
  const btnReplySend = document.getElementById('btn-reply-send');
  if (btnReplySend) {
    btnReplySend.addEventListener('click', async () => {
      oEditors.getById["editor-reply"].exec("UPDATE_CONTENTS_FIELD", []);
      const subject = document.getElementById('reply-subject').value.trim();
      const content = document.getElementById('editor-reply').value.trim();
      
      if (!subject) { alert('답변 제목을 입력해주세요.'); return; }
      if (!content || content === '<p>&nbsp;</p>') { alert('답변 내용을 입력해주세요.'); return; }
      
      const sendEmail = document.getElementById('reply-chk-email').checked ? 1 : 0;
      const sendSms = document.getElementById('reply-chk-sms').checked ? 1 : 0;
      const sendKakao = document.getElementById('reply-chk-kakao').checked ? 1 : 0;
      const closeConsult = document.getElementById('reply-chk-close').checked ? 1 : 0;
      
      if (!sendEmail && !sendSms && !sendKakao) {
        alert('발송 수단을 하나 이상 선택해주세요.'); return;
      }
      
      if (!confirm('작성한 내용을 고객에게 발송하시겠습니까?')) return;
      
      btnReplySend.disabled = true;
      btnReplySend.innerText = '발송 중...';
      
      try {
        const formData = new URLSearchParams();
        formData.append('_csrf', csrfToken);
        formData.append('consult_id', consultId);
        formData.append('subject', subject);
        // Cafe24 웹방화벽(ModSecurity)이 HTML 태그(<p>, <style> 등) 전송 시 403 Forbidden으로 차단하는 것을 방지하기 위해 Base64 인코딩
        const encodedContent = btoa(unescape(encodeURIComponent(content)));
        formData.append('content', encodedContent);
        formData.append('is_base64', '1');
        formData.append('send_email', sendEmail);
        formData.append('send_sms', sendSms);
        formData.append('send_kakao', sendKakao);
        formData.append('close_consult', closeConsult);
        
        const res = await fetch('/admin/consults/api_reply_send.php', {
          method: 'POST', body: formData
        });
        const data = await res.json();
        if (data.ok) {
          let msg = '발송이 완료되었습니다.\\n';
          if (sendEmail) msg += '이메일: ' + (data.email_sent ? '성공' : '실패 ('+data.email_msg+')') + '\\n';
          alert(msg);
          loadCommunications();
          if (data.closed) location.reload();
        } else {
          alert('발송 실패: ' + (data.error || '알 수 없는 오류'));
        }
      } catch (e) {
        alert('오류 발생: 네트워크 상태를 확인하세요.');
      } finally {
        btnReplySend.disabled = false;
        btnReplySend.innerText = '고객에게 발송';
      }
    });
  }

  // 파일 업로드 및 목록
  const fileList = document.getElementById('file-list');
  
  async function loadFiles() {
    try {
      const res = await fetch(`/admin/consults/api_file_list.php?consult_id=${consultId}`);
      const data = await res.json();
      if (data.ok) {
        fileList.innerHTML = data.files.length > 0 ? `
          <table style="width:100%;text-align:left;border-collapse:collapse;font-size:13px;">
            <thead><tr style="border-bottom:2px solid #e5e7eb;">
              <th style="padding:8px;">종류</th>
              <th style="padding:8px;">파일명</th>
              <th style="padding:8px;">크기</th>
              <th style="padding:8px;">등록일</th>
              <th style="padding:8px;">관리</th>
            </tr></thead>
            <tbody>
              ${data.files.map(f => `
                <tr style="border-bottom:1px solid #e5e7eb;">
                  <td style="padding:8px;"><span class="badge" style="background:#e5e7eb;color:#374151;">${f.file_category}</span></td>
                  <td style="padding:8px;"><a href="${f.saved_path}" target="_blank" style="color:#4f46e5;text-decoration:none;">${f.orig_name}</a></td>
                  <td style="padding:8px;">${(f.file_size/1024).toFixed(1)} KB</td>
                  <td style="padding:8px;color:#6b7280;">${f.created_at}</td>
                  <td style="padding:8px;"><button class="btn btn-del-file" data-id="${f.id}" style="padding:4px 8px;font-size:11px;background:#ef4444;color:#fff;">삭제</button></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        ` : '<p class="muted">등록된 파일이 없습니다.</p>';

        document.querySelectorAll('.btn-del-file').forEach(b => {
          b.addEventListener('click', async (e) => {
            if (!confirm('파일을 삭제하시겠습니까?')) return;
            const fid = e.target.dataset.id;
            const fd = new URLSearchParams();
            fd.append('_csrf', csrfToken);
            fd.append('consult_id', consultId);
            fd.append('file_id', fid);
            const res = await fetch('/admin/consults/api_file_delete.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.ok) loadFiles();
            else alert(result.error || '삭제 실패');
          });
        });
      }
    } catch (e) {
      console.error(e);
    }
  }

  const dropZone = document.getElementById('drop-zone');
  const fileInput = document.getElementById('file-input');

  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.background = '#e0e7ff'; });
  dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); dropZone.style.background = '#f9fafb'; });
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.background = '#f9fafb';
    if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) uploadFiles(fileInput.files);
  });

  async function uploadFiles(files) {
    const category = document.getElementById('file-category').value;
    for (const file of files) {
      const fd = new FormData();
      fd.append('_csrf', csrfToken);
      fd.append('consult_id', consultId);
      fd.append('file_category', category);
      fd.append('file', file);

      try {
        const res = await fetch('/admin/consults/api_file_upload.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) alert(file.name + ' 업로드 실패: ' + data.error);
      } catch (e) {
        alert(file.name + ' 업로드 오류');
      }
    }
    loadFiles();
    fileInput.value = '';
  }

  // 초기 로드
  loadMemos();
  loadFiles();
  loadCommunications();

  // 이전 AI, Chat 소켓 로직은 동일하게 유지 (요약 생성, 채팅 등)
  const btnSummary = document.getElementById('btn-ai-summary');
  const summaryText = document.getElementById('ai-summary-text');
  const summaryTime = document.getElementById('ai-summary-time');
  
  if (btnSummary) {
    btnSummary.addEventListener('click', async function() {
      btnSummary.disabled = true;
      const origText = btnSummary.innerText;
      btnSummary.innerText = '⏳ 생성 중...';
      try {
        const formData = new URLSearchParams();
        formData.append('_csrf', csrfToken);
        formData.append('consult_id', consultId);
        const res = await fetch('/admin/consults/ai_summary.php', {
          method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData
        });
        const data = await res.json();
        if (data.ok) {
          summaryText.innerText = data.text;
          summaryTime.innerText = (data.summary_at || '') + ' 생성';
          btnSummary.innerText = '🔄 재생성';
        } else {
          alert('AI 응답을 가져오지 못했습니다: ' + (data.error || '알 수 없는 오류'));
          btnSummary.innerText = origText;
        }
      } catch (e) {
        alert('AI 응답을 가져오지 못했습니다 (네트워크/서버 오류)');
        btnSummary.innerText = origText;
      } finally {
        btnSummary.disabled = false;
      }
    });
  }

  const btnAnalyze = document.getElementById('btn-ai-analyze');
  const analyzeTime = document.getElementById('ai-analyze-time');
  if (btnAnalyze) {
    btnAnalyze.addEventListener('click', async function() {
      btnAnalyze.disabled = true;
      const origText = btnAnalyze.innerText;
      btnAnalyze.innerText = '⏳ 실행 중...';
      try {
        const formData = new URLSearchParams();
        formData.append('_csrf', csrfToken);
        formData.append('consult_id', consultId);
        const res = await fetch('/admin/consults/ai_analyze.php', {
          method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData
        });
        const resp = await res.json();
        if (resp.ok) {
          location.reload(); // 간단히 새로고침하여 업데이트 반영
        } else {
          alert('AI 종합분석 실패: ' + (resp.error || '알 수 없는 오류'));
          btnAnalyze.innerText = origText;
        }
      } catch (e) {
        alert('오류 발생');
        btnAnalyze.innerText = origText;
      } finally {
        btnAnalyze.disabled = false;
      }
    });
  }

  // 실시간 채팅 로직
  const msgConfig = {
    roomId: <?= json_encode($roomId) ?>,
    jwt: <?= json_encode($acepJwt) ?>,
    wsUrl: <?= json_encode($wsUrl) ?>,
    apiBase: '/api/v1',
    managerName: <?= json_encode(current_manager()['name'] ?? '상담원') ?>,
    customerName: <?= json_encode($c['cust_name']) ?>,
  };

  if (msgConfig.roomId && msgConfig.jwt && typeof io !== 'undefined') {
    const msgList = document.getElementById('msg-list');
    const msgStatus = document.getElementById('msg-connection-status');
    const msgForm = document.getElementById('msg-form');
    const msgInput = document.getElementById('msg-input');
    const msgSendBtn = document.getElementById('msg-send-btn');
    const seenMessageIds = new Set();
    let socket = null;

    function formatMsgTime(iso) {
      if (!iso) return '';
      try {
        const d = new Date(iso);
        return d.toLocaleString('ko-KR', { hour: '2-digit', minute: '2-digit', month: '2-digit', day: '2-digit' });
      } catch (e) {
        return String(iso);
      }
    }

    function senderLabel(msg) {
      if (msg.senderType === 'customer') {
        return msgConfig.customerName || '고객';
      }
      return msgConfig.managerName || '상담원';
    }

    /** chat_messages.content — ignore legacy "message" key when content is present */
    function msgContent(msg) {
      if (!msg) return '';
      if (msg.content != null && String(msg.content) !== '') {
        return String(msg.content);
      }
      if (msg.message != null && String(msg.message) !== '') {
        return String(msg.message);
      }
      return '';
    }

    function addMessageToList(msg) {
      const id = msg.messageId || msg.id;
      if (id && seenMessageIds.has(id)) {
        return;
      }
      if (id) {
        seenMessageIds.add(id);
      }

      if (msgList.querySelector('.consult-chat-loading, .consult-chat-empty')) {
        msgList.innerHTML = '';
      }

      const isAgent = msg.senderType === 'agent';
      const row = document.createElement('div');
      row.className = 'consult-chat-row ' + (isAgent ? 'agent' : 'customer');

      const meta = document.createElement('div');
      meta.className = 'consult-chat-meta';
      meta.textContent = senderLabel(msg) + ' · ' + formatMsgTime(msg.createdAt || msg.timestamp);

      const bubble = document.createElement('div');
      bubble.className = 'consult-chat-bubble';
      bubble.textContent = msgContent(msg);

      if (isAgent) {
        row.appendChild(bubble);
        row.appendChild(meta);
      } else {
        row.appendChild(meta);
        row.appendChild(bubble);
      }
      msgList.appendChild(row);
      msgList.scrollTop = msgList.scrollHeight;
    }

    async function loadInitialMessages() {
      try {
        const res = await fetch(
          msgConfig.apiBase + '/chats/' + encodeURIComponent(msgConfig.roomId) + '/messages?limit=50',
          { headers: { Authorization: 'Bearer ' + msgConfig.jwt, Accept: 'application/json' } }
        );
        const json = await res.json();
        if (!json.success || !json.data?.messages) {
          msgList.innerHTML = '<div class="consult-chat-empty">메시지를 불러오지 못했습니다.</div>';
          return;
        }
        msgList.innerHTML = '';
        if (json.data.messages.length === 0) {
          msgList.innerHTML = '<div class="consult-chat-empty">아직 메시지가 없습니다.</div>';
          return;
        }
        json.data.messages.forEach(function(m) {
          addMessageToList({
            messageId: m.id,
            content: msgContent(m),
            senderType: m.senderType,
            senderId: m.senderId,
            createdAt: m.createdAt,
          });
        });
      } catch (e) {
        msgList.innerHTML = '<div class="consult-chat-empty">메시지 로드 실패 (네트워크 오류)</div>';
      }
    }

    function setConnectionStatus(text, ok) {
      if (!msgStatus) return;
      msgStatus.textContent = text;
      msgStatus.classList.remove('is-connected', 'is-error');
      if (ok === true) {
        msgStatus.classList.add('is-connected');
      } else if (ok === false) {
        msgStatus.classList.add('is-error');
      }
    }

    function initMessagingSocket() {
      socket = io(msgConfig.wsUrl, {
        path: '/socket.io',
        auth: { token: msgConfig.jwt },
        reconnection: true,
        reconnectionDelay: 1000,
        reconnectionDelayMax: 5000,
        transports: ['websocket', 'polling'],
      });

      socket.on('connect', function() {
        setConnectionStatus('연결됨', true);
        socket.emit('room:join', { roomId: msgConfig.roomId });
      });

      socket.on('disconnect', function() {
        setConnectionStatus('연결 끊김', false);
      });

      socket.on('connect_error', function(err) {
        setConnectionStatus('연결 오류: ' + (err.message || 'unknown'), false);
      });

      socket.on('error', function(payload) {
        console.error('[consult-messaging]', payload);
        if (payload?.message) {
          setConnectionStatus(payload.message, false);
        }
      });

      socket.on('room:joined', function() {
        setConnectionStatus('채팅방 입장', true);
      });

      socket.on('message:receive', function(msg) {
        if (msg.roomId && msg.roomId !== msgConfig.roomId) {
          return;
        }
        addMessageToList(msg);
      });
    }

    loadInitialMessages();
    initMessagingSocket();

    if (msgForm && msgInput) {
      msgForm.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const text = msgInput.value.trim();
        if (!text || !socket?.connected) {
          return;
        }
        if (msgSendBtn) {
          msgSendBtn.disabled = true;
        }
        socket.emit('message:send', { roomId: msgConfig.roomId, content: text });
        msgInput.value = '';
        if (msgSendBtn) {
          msgSendBtn.disabled = false;
        }
        msgInput.focus();
      });
    }

    window.addEventListener('beforeunload', function() {
      if (socket?.connected) {
        socket.emit('room:leave', { roomId: msgConfig.roomId });
        socket.disconnect();
      }
    });
  }

})();
</script>
<?php require INC_DIR . '/footer.php'; ?>
