<?php
declare(strict_types=1);
/** 상담 상세 + 상태변경 + 담당자 배정 + 메모 (SPEC.md B-3 / C) */
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pdo = db();
$no = clean_str($_GET['no'] ?? '', 20);

// 대상 상담 로드
$stmt = $pdo->prepare(
    "SELECT c.*, cu.name AS cust_name, cu.phone, cu.company, cu.email, cu.region,
            cu.address, cu.zipcode, cu.customer_no, s.site_name, s.brand, s.division
     FROM consults c
     JOIN customers cu ON cu.id = c.customer_id
     JOIN sites s ON s.id = c.site_id
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
                $pdo->prepare(
                    'INSERT INTO consult_history (consult_id, from_status, to_status, manager_id, note)
                     VALUES (:cid, :from, :to, :mid, :note)'
                )->execute([
                    ':cid' => (int)$c['id'], ':from' => $c['status'], ':to' => $to,
                    ':mid' => current_manager()['id'], ':note' => clean_str($_POST['note'] ?? '', 255) ?: null,
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
    } elseif ($action === 'memo') {
        $memo = clean_str($_POST['memo'] ?? '', 2000);
        $pdo->prepare('UPDATE consults SET memo = :m WHERE id = :id')
            ->execute([':m' => $memo ?: null, ':id' => (int)$c['id']]);
        $c['memo'] = $memo;
        $flash = '메모를 저장했습니다.';
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
$att = $pdo->prepare("SELECT * FROM attachments WHERE consult_id = :id ORDER BY id DESC");
$att->execute([':id' => (int)$c['id']]);
$files = $att->fetchAll();

$detail = $c['detail_json'] ? json_decode($c['detail_json'], true) : null;

// ACEP 채팅방 ID (detail_json.room_id 또는 chat_rooms.legacy_consult_id)
$roomId = null;
if (is_array($detail) && !empty($detail['room_id'])) {
    $roomId = (string)$detail['room_id'];
}
if (!$roomId) {
    try {
        $rs = $pdo->prepare(
            'SELECT id FROM chat_rooms WHERE legacy_consult_id = :cid AND deleted_at IS NULL LIMIT 1'
        );
        $rs->execute([':cid' => (int)$c['id']]);
        $rid = $rs->fetchColumn();
        if ($rid) {
            $roomId = (string)$rid;
        }
    } catch (Throwable) {
        // chat_rooms 미존재 레거시 설치
    }
}

$acepJwt = acep_access_token();
$wsUrl = getenv('ACEP_WS_URL') ?: 'wss://plustok.onrender.com';
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if ($host === 'localhost' || str_contains($host, '127.0.0.1')) {
    $wsUrl = getenv('ACEP_WS_URL') ?: 'http://localhost:3001';
}

$page_title = '상담 ' . $c['consult_no']; $active = 'consults';
require INC_DIR . '/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <a href="/admin/consults/">← 목록</a>
  <?php if (can_manage()): ?>
    <form method="post" onsubmit="return confirm('이 상담을 완전히 삭제할까요? (첨부·이력 포함, 되돌릴 수 없음)')">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <button class="btn danger" style="padding:6px 12px;font-size:13px">상담 삭제</button>
    </form>
  <?php endif; ?>
</div>
<h1 class="page"><?= e($c['consult_no']) ?>
  <span class="badge st-<?= e($c['status']) ?>"><?= e(CONSULT_STATUSES[$c['status']] ?? $c['status']) ?></span>
</h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="grid2">
  <div class="card">
    <h3 style="margin-top:0">고객 정보</h3>
    <table><tbody>
      <tr><th>고객번호</th><td class="mono"><?= e($c['customer_no']) ?></td></tr>
      <tr><th>이름</th><td><a href="/admin/customers/view.php?id=<?= (int)$c['customer_id'] ?>"><?= e($c['cust_name']) ?></a></td></tr>
      <tr><th>연락처</th><td class="mono"><?= e($c['phone']) ?></td></tr>
      <tr><th>회사</th><td><?= e($c['company'] ?? '-') ?></td></tr>
      <tr><th>이메일</th><td><?= e($c['email'] ?? '-') ?></td></tr>
      <tr><th>지역/주소</th><td><?= e(trim(($c['region'] ?? '') . ' ' . ($c['address'] ?? ''))) ?: '-' ?></td></tr>
    </tbody></table>
  </div>
  <div class="card">
    <h3 style="margin-top:0">상담 정보</h3>
    <table><tbody>
      <tr><th>사이트</th><td><?= e($c['site_name']) ?> (<?= e($c['brand']) ?>)</td></tr>
      <tr><th>사업부</th><td><?= e($c['division']) ?></td></tr>
      <tr><th>상품</th><td><?= e($c['product_name'] ?? '-') ?><?= $c['category'] ? ' / ' . e($c['category']) : '' ?></td></tr>
      <tr><th>접수시각</th><td class="muted"><?= e($c['created_at']) ?></td></tr>
      <tr><th>유입</th><td class="muted"><?= e($c['referer'] ?? '-') ?> / <?= e($c['device'] ?? '-') ?></td></tr>
    </tbody></table>
    <?php if ($detail): ?>
      <h4>상품별 상세</h4>
      <table><tbody>
        <?php foreach ($detail as $k => $v): ?>
          <tr><th><?= e((string)$k) ?></th><td><?= e(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
</div>

<?php
$priorityBg = ['URGENT' => '#ef4444', 'HIGH' => '#f97316', 'NORMAL' => '#3b82f6', 'LOW' => '#6b7280'];
$priorityText = ['URGENT' => '🚨 긴급(URGENT)', 'HIGH' => '🔥 높음(HIGH)', 'NORMAL' => '⚡ 보통(NORMAL)', 'LOW' => '🌱 낮음(LOW)'];
$curPrio = !empty($c['priority']) ? strtoupper((string)$c['priority']) : 'NORMAL';

$sentimentIcons = ['POSITIVE' => '😊 호의/칭찬', 'NEUTRAL' => '😐 일반/중립', 'NEGATIVE' => '😡 불만/급함'];
$curSent = !empty($c['sentiment']) ? strtoupper((string)$c['sentiment']) : 'NEUTRAL';

$leadScore = (int)($c['lead_score'] ?? 0);
$starsCount = (int)round($leadScore / 20);
$starsStr = str_repeat('★', $starsCount) . str_repeat('☆', 5 - $starsCount);
?>
<div class="card" style="margin-top:16px;border:2px solid #6366f1;background:#fefefe">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f3f4f6">
    <h3 style="margin:0;display:flex;align-items:center;gap:8px;color:#4f46e5">
      🤖 AI 종합 분석 결과 (STEP 9)
      <span class="badge" style="background:#4f46e5;color:#fff;font-size:11px;font-weight:normal">Claude 1회 자동분석</span>
    </h3>
    <div style="display:flex;align-items:center;gap:10px">
      <span id="ai-analyze-time" class="muted" style="font-size:12px"><?= $c['ai_analyzed_at'] ? e($c['ai_analyzed_at']) . ' 분석됨' : '미분석' ?></span>
      <button type="button" id="btn-ai-analyze" class="btn" style="padding:6px 12px;font-size:12px;background:#4f46e5;color:#fff;font-weight:bold">
        <?= $c['ai_analyzed_at'] ? '⚡ 종합 분석 재실행' : '⚡ AI 종합 분석 실행' ?>
      </button>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:12px;margin-bottom:12px">
    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0;text-align:center">
      <div class="muted" style="font-size:11px;margin-bottom:4px">AI 추천 분류</div>
      <div id="ai-val-category" style="font-weight:bold;font-size:14px;color:#1e293b"><?= e($c['category_ai'] ?: '미분석') ?></div>
    </div>
    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0;text-align:center">
      <div class="muted" style="font-size:11px;margin-bottom:4px">긴급도 (Priority)</div>
      <div id="ai-val-priority">
        <?php if (!empty($c['ai_analyzed_at']) || !empty($c['priority'])): ?>
          <span class="badge" style="background:<?= $priorityBg[$curPrio] ?? '#3b82f6' ?>;color:#fff;padding:3px 8px;font-size:12px">
            <?= $priorityText[$curPrio] ?? e($curPrio) ?>
          </span>
        <?php else: ?>
          <span class="muted">미분석</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0;text-align:center">
      <div class="muted" style="font-size:11px;margin-bottom:4px">계약 가능성 (Lead Score)</div>
      <div id="ai-val-score" style="font-weight:bold;font-size:14px;color:#059669">
        <?php if (!empty($c['ai_analyzed_at']) || $leadScore > 0): ?>
          <?= $leadScore ?>점 <span style="color:#f59e0b;font-size:13px"><?= $starsStr ?></span>
        <?php else: ?>
          <span class="muted" style="color:#64748b">미분석</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0;text-align:center">
      <div class="muted" style="font-size:11px;margin-bottom:4px">고객 감정 분석</div>
      <div id="ai-val-sentiment" style="font-weight:bold;font-size:14px;color:#334155">
        <?= !empty($c['ai_analyzed_at']) ? ($sentimentIcons[$curSent] ?? e($curSent)) : '<span class="muted" style="color:#64748b">미분석</span>' ?>
      </div>
    </div>
  </div>

  <div style="background:#f8fafc;padding:10px 12px;border-radius:6px;border:1px solid #e2e8f0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span class="muted" style="font-size:12px;font-weight:bold">추출 해시태그:</span>
    <span id="ai-val-tags" style="display:flex;gap:6px;flex-wrap:wrap">
      <?php if (!empty($c['tags'])): ?>
        <?php foreach (array_filter(explode(' ', $c['tags'])) as $t): ?>
          <span style="background:#e0e7ff;color:#4338ca;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600"><?= e($t) ?></span>
        <?php endforeach; ?>
      <?php else: ?>
        <span class="muted" style="font-size:12px">생성된 태그가 없습니다.</span>
      <?php endif; ?>
    </span>
  </div>
</div>

<div class="card" style="margin-top:16px;border:1px solid #e5e7eb">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <h3 style="margin:0;display:flex;align-items:center;gap:8px">
      ✨ AI 상담 요약
      <span class="badge" style="background:#4b5563;color:#fff;font-size:11px;font-weight:normal">AI 생성 — 검토 필요</span>
    </h3>
    <div style="display:flex;align-items:center;gap:10px">
      <span id="ai-summary-time" class="muted" style="font-size:12px"><?= $c['ai_summary_at'] ? e($c['ai_summary_at']) . ' 생성' : '' ?></span>
      <button type="button" id="btn-ai-summary" class="btn" style="padding:4px 10px;font-size:12px">
        <?= $c['ai_summary'] ? '🔄 재생성' : '✨ AI 요약 생성' ?>
      </button>
    </div>
  </div>
  <div id="ai-summary-text" style="white-space:pre-wrap;line-height:1.6;color:#111827;background:#f9fafb;padding:12px;border-radius:6px;min-height:36px">
    <?= $c['ai_summary'] ? e($c['ai_summary']) : '<span class="muted">아직 생성된 AI 요약이 없습니다. 상단 버튼을 클릭하여 요약을 생성해보세요.</span>' ?>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">고객 요청 내용</h3>
  <p style="white-space:pre-wrap"><?= e($c['memo'] ?? '') ?: '<span class="muted">내용 없음</span>' ?></p>
</div>

<?php if ($roomId): ?>
<div class="card" style="margin-top:16px;border:1px solid #e5e7eb" id="consult-messaging"
     data-room-id="<?= e($roomId) ?>">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <h3 style="margin:0;display:flex;align-items:center;gap:8px">
      💬 실시간 상담 메시지
      <span class="badge" style="background:#059669;color:#fff;font-size:11px;font-weight:normal">Socket.io</span>
    </h3>
    <span id="msg-connection-status" class="muted" style="font-size:12px">준비 중…</span>
  </div>
  <?php if (!$acepJwt): ?>
    <div class="msg" style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;margin-bottom:12px">
      ACEP 통합 계정(agents)으로 다시 로그인해야 실시간 메시지를 사용할 수 있습니다.
      레거시 managers 계정은 JWT가 없습니다.
    </div>
  <?php endif; ?>
  <div id="msg-list"
       style="max-height:360px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;padding:12px;background:#f9fafb;min-height:120px">
    <p class="muted" style="margin:0">메시지를 불러오는 중…</p>
  </div>
  <?php if (can_edit_consult() && $acepJwt): ?>
  <form id="msg-form" style="margin-top:12px;display:flex;gap:8px;align-items:flex-end">
    <textarea id="msg-input" rows="2" maxlength="2000" placeholder="메시지를 입력하세요…"
              style="flex:1;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-family:inherit;line-height:1.5;resize:vertical"></textarea>
    <button type="submit" class="btn" id="msg-send-btn" style="white-space:nowrap">전송</button>
  </form>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">💬 실시간 상담 메시지</h3>
  <p class="muted" style="margin:0">
    이 상담에 연결된 ACEP 채팅방이 없습니다. 채팅 상담 종료 후 CRM에 저장된 상담만 메시지를 주고받을 수 있습니다.
  </p>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px;border:1px solid #e5e7eb">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <h3 style="margin:0;display:flex;align-items:center;gap:8px">
      ✨ AI 답변 초안 생성
      <span class="badge" style="background:#4b5563;color:#fff;font-size:11px;font-weight:normal">AI 생성 — 검토 필요</span>
    </h3>
    <div>
      <button type="button" id="btn-ai-reply" class="btn" style="padding:4px 10px;font-size:12px">✨ 답변 초안 생성</button>
    </div>
  </div>
  <div style="margin-bottom:8px">
    <textarea id="ai-reply-text" rows="6" placeholder="버튼을 클릭하면 AI가 문의내용과 브랜드 페르소나를 분석하여 답변 초안을 작성합니다. 생성 후 직접 수정하여 사용할 수 있습니다." style="width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-family:inherit;line-height:1.5"></textarea>
  </div>
  <div style="text-align:right">
    <button type="button" id="btn-copy-reply" class="btn" style="padding:4px 12px;font-size:12px;background:#6b7280;color:#fff" disabled>📋 복사하기</button>
  </div>
</div>

<?php if ($files): ?>
<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">첨부파일</h3>
  <ul>
    <?php foreach ($files as $f): ?>
      <li><a href="/<?= e($f['saved_path']) ?>" target="_blank"><?= e($f['orig_name']) ?></a>
          <span class="muted">(<?= e($f['file_type'] ?? '') ?>, <?= number_format((int)$f['size_bytes']) ?> bytes)</span></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (can_edit_consult()): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px" class="grid2">
  <div class="card">
    <h3 style="margin-top:0">상태 변경</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="status">
      <select name="status">
        <?php foreach (CONSULT_STATUSES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <label>변경 메모(선택)</label>
      <input type="text" name="note" maxlength="255">
      <button class="btn" style="margin-top:12px">변경</button>
    </form>
  </div>
  <div class="card">
    <h3 style="margin-top:0">담당자 배정</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="assign">
      <select name="manager_id">
        <option value="0">미배정</option>
        <?php foreach ($managers as $mg): ?>
          <option value="<?= (int)$mg['id'] ?>" <?= (int)$c['manager_id'] === (int)$mg['id'] ? 'selected' : '' ?>><?= e($mg['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" style="margin-top:12px">배정</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">담당자 메모</h3>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="memo">
    <textarea name="memo"><?= e($c['memo'] ?? '') ?></textarea>
    <button class="btn" style="margin-top:8px">저장</button>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">상태 이력</h3>
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

<?php if ($roomId && $acepJwt): ?>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js" crossorigin="anonymous"></script>
<?php endif; ?>
<script>
(function() {
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  const consultId = <?= (int)$c['id'] ?>;

  const btnSummary = document.getElementById('btn-ai-summary');
  const summaryText = document.getElementById('ai-summary-text');
  const summaryTime = document.getElementById('ai-summary-time');

  const btnAnalyze = document.getElementById('btn-ai-analyze');
  const analyzeTime = document.getElementById('ai-analyze-time');
  const valCat = document.getElementById('ai-val-category');
  const valPrio = document.getElementById('ai-val-priority');
  const valScore = document.getElementById('ai-val-score');
  const valSent = document.getElementById('ai-val-sentiment');
  const valTags = document.getElementById('ai-val-tags');

  const priorityBgMap = { 'URGENT': '#ef4444', 'HIGH': '#f97316', 'NORMAL': '#3b82f6', 'LOW': '#6b7280' };
  const priorityTextMap = { 'URGENT': '🚨 긴급(URGENT)', 'HIGH': '🔥 높음(HIGH)', 'NORMAL': '⚡ 보통(NORMAL)', 'LOW': '🌱 낮음(LOW)' };
  const sentimentIconMap = { 'POSITIVE': '😊 호의/칭찬', 'NEUTRAL': '😐 일반/중립', 'NEGATIVE': '😡 불만/급함' };

  if (btnAnalyze) {
    btnAnalyze.addEventListener('click', async function() {
      btnAnalyze.disabled = true;
      const origText = btnAnalyze.innerText;
      btnAnalyze.innerText = '⏳ 종합분석 중...';
      try {
        const formData = new URLSearchParams();
        formData.append('_csrf', csrfToken);
        formData.append('consult_id', consultId);

        const res = await fetch('/admin/consults/ai_analyze.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData
        });
        const resp = await res.json();
        if (resp.ok && resp.data) {
          const d = resp.data;
          valCat.innerText = d.category_ai || '기타';
          
          const prio = d.priority || 'NORMAL';
          const bg = priorityBgMap[prio] || '#3b82f6';
          const txt = priorityTextMap[prio] || prio;
          valPrio.innerHTML = `<span class="badge" style="background:${bg};color:#fff;padding:3px 8px;font-size:12px">${txt}</span>`;

          const score = parseInt(d.lead_score || 0, 10);
          const starsCount = Math.round(score / 20);
          const starsStr = '★'.repeat(starsCount) + '☆'.repeat(5 - starsCount);
          valScore.innerHTML = `${score}점 <span style="color:#f59e0b;font-size:13px">${starsStr}</span>`;

          const sent = d.sentiment || 'NEUTRAL';
          valSent.innerText = sentimentIconMap[sent] || sent;

          const tagsStr = (d.tags || '').trim();
          valTags.innerHTML = '';
          if (tagsStr) {
            const tagsList = tagsStr.split(/\s+/).filter(Boolean);
            tagsList.forEach(t => {
              const span = document.createElement('span');
              span.style.cssText = 'background:#e0e7ff;color:#4338ca;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600';
              span.textContent = t; // AI가 생성한 텍스트이므로 innerHTML에 직접 삽입 금지(XSS 방지)
              valTags.appendChild(span);
            });
          } else {
            const span = document.createElement('span');
            span.className = 'muted';
            span.style.fontSize = '12px';
            span.textContent = '생성된 태그가 없습니다.';
            valTags.appendChild(span);
          }

          analyzeTime.innerText = (d.analyzed_at || '') + ' 분석됨';
          btnAnalyze.innerText = '⚡ 종합 분석 재실행';
        } else {
          alert('AI 종합분석을 실행하지 못했습니다: ' + (resp.error || '알 수 없는 오류'));
          btnAnalyze.innerText = origText;
        }
      } catch (e) {
        alert('AI 종합분석을 가져오지 못했습니다 (네트워크/서버 오류)');
        btnAnalyze.innerText = origText;
      } finally {
        btnAnalyze.disabled = false;
      }
    });
  }

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
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData
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

  const btnReply = document.getElementById('btn-ai-reply');
  const replyText = document.getElementById('ai-reply-text');
  const btnCopy = document.getElementById('btn-copy-reply');

  if (btnReply) {
    btnReply.addEventListener('click', async function() {
      btnReply.disabled = true;
      const origText = btnReply.innerText;
      btnReply.innerText = '⏳ 생성 중...';
      try {
        const formData = new URLSearchParams();
        formData.append('_csrf', csrfToken);
        formData.append('consult_id', consultId);

        const res = await fetch('/admin/consults/ai_reply.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData
        });
        const data = await res.json();
        if (data.ok) {
          replyText.value = data.text;
          if (btnCopy) btnCopy.disabled = false;
          btnReply.innerText = '🔄 다시 생성';
        } else {
          alert('AI 응답을 가져오지 못했습니다: ' + (data.error || '알 수 없는 오류'));
          btnReply.innerText = origText;
        }
      } catch (e) {
        alert('AI 응답을 가져오지 못했습니다 (네트워크/서버 오류)');
        btnReply.innerText = origText;
      } finally {
        btnReply.disabled = false;
      }
    });
  }

  if (btnCopy) {
    btnCopy.addEventListener('click', function() {
      if (!replyText.value.trim()) return;
      navigator.clipboard.writeText(replyText.value).then(function() {
        const orig = btnCopy.innerText;
        btnCopy.innerText = '✅ 복사 완료!';
        setTimeout(() => btnCopy.innerText = orig, 2000);
      }).catch(function() {
        replyText.select();
        document.execCommand('copy');
        alert('클립보드에 복사되었습니다.');
      });
    });
  }

  /*
   * 실시간 상담 메시지 — SSOT: 05_CHAT/01_WebSocket설계.md
   * Events: room:join, message:send (C→S), message:receive (S→C)
   * REST: GET/POST /api/v1/chats/{roomId}/messages
   * E2E: 단일/다중 클라이언트 send+receive, DevTools console 0 errors
   */
  const msgConfig = {
    roomId: <?= json_encode($roomId) ?>,
    jwt: <?= json_encode($acepJwt) ?>,
    wsUrl: <?= json_encode($wsUrl) ?>,
    apiBase: '/api/v1',
    managerName: <?= json_encode(current_manager()['name'] ?? '상담원') ?>,
    customerName: <?= json_encode($c['cust_name']) ?>,
  };

  if (!msgConfig.roomId || !msgConfig.jwt || typeof io === 'undefined') {
    return;
  }

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

  function addMessageToList(msg) {
    const id = msg.messageId || msg.id;
    if (id && seenMessageIds.has(id)) {
      return;
    }
    if (id) {
      seenMessageIds.add(id);
    }

    if (msgList.querySelector('.muted') && msgList.children.length === 1) {
      msgList.innerHTML = '';
    }

    const isAgent = msg.senderType === 'agent';
    const row = document.createElement('div');
    row.style.cssText = 'margin-bottom:10px;display:flex;flex-direction:column;align-items:' +
      (isAgent ? 'flex-end' : 'flex-start');

    const bubble = document.createElement('div');
    bubble.style.cssText = 'max-width:85%;padding:8px 12px;border-radius:8px;font-size:14px;line-height:1.5;white-space:pre-wrap;word-break:break-word;' +
      (isAgent ? 'background:#dbeafe;color:#1e3a8a;' : 'background:#fff;border:1px solid #e5e7eb;color:#111827;');

    const meta = document.createElement('div');
    meta.style.cssText = 'font-size:11px;color:#6b7280;margin-bottom:4px;';
    meta.textContent = senderLabel(msg) + ' · ' + formatMsgTime(msg.createdAt || msg.timestamp);

    const body = document.createElement('div');
    body.textContent = msg.content || '';

    bubble.appendChild(meta);
    bubble.appendChild(body);
    row.appendChild(bubble);
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
        msgList.innerHTML = '<p class="muted" style="margin:0">메시지를 불러오지 못했습니다.</p>';
        return;
      }
      msgList.innerHTML = '';
      if (json.data.messages.length === 0) {
        msgList.innerHTML = '<p class="muted" style="margin:0">아직 메시지가 없습니다.</p>';
        return;
      }
      json.data.messages.forEach(function(m) {
        addMessageToList({
          messageId: m.id,
          content: m.content,
          senderType: m.senderType,
          senderId: m.senderId,
          createdAt: m.createdAt,
        });
      });
    } catch (e) {
      msgList.innerHTML = '<p class="muted" style="margin:0">메시지 로드 실패 (네트워크 오류)</p>';
    }
  }

  function setConnectionStatus(text, ok) {
    if (!msgStatus) return;
    msgStatus.textContent = text;
    msgStatus.style.color = ok ? '#059669' : (ok === false ? '#dc2626' : '#6b7280');
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
})();
</script>
<?php require INC_DIR . '/footer.php'; ?>
