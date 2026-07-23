<?php
declare(strict_types=1);
/** 상담 목록 + 필터 (SPEC.md B-3) */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';
require_once __DIR__ . '/../../includes/util/ConsultMeta.php';
require_login();

$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);
$hasConsultMeta = ConsultMeta::tableExists($pdo);

$flash = ''; $flashErr = '';
// 선택 삭제 (super/admin 전용)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    require_role(['super', 'admin']);
    if (($_POST['action'] ?? '') === 'bulk_delete') {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0));
        $n = 0;
        foreach ($ids as $id) {
            try { delete_consult($pdo, $id); log_activity('consult_delete', 'consult:' . $id); $n++; }
            catch (Throwable $ex) { log_error('consults_bulk_delete', $ex->getMessage()); }
        }
        if ($n > 0)  { $flash = $n . '건의 상담을 삭제했습니다.'; }
        if (!$ids)   { $flashErr = '선택된 상담이 없습니다.'; }
    }
}

// 필터값
$fSite   = clean_str($_GET['site'] ?? '', 50);
$fStatus = clean_str($_GET['status'] ?? '', 20);
$fManager= (int)($_GET['manager'] ?? 0);
$fPriority = clean_str($_GET['priority'] ?? '', 20);
$fSort   = clean_str($_GET['sort'] ?? '', 20);
$fFrom   = clean_str($_GET['from'] ?? '', 10);
$fTo     = clean_str($_GET['to'] ?? '', 10);
$q       = clean_str($_GET['q'] ?? '', 50);
$fMetaKey   = clean_str($_GET['meta_key'] ?? '', 60);
$fMetaValue = clean_str($_GET['meta_value'] ?? '', 255);

$where = [];
$params = [];
if ($fSite !== '')   { $where[] = 's.site_code = :site';   $params[':site'] = $fSite; }
if ($fStatus !== '' && isset(CONSULT_STATUSES[$fStatus])) { $where[] = 'c.status = :st'; $params[':st'] = $fStatus; }
if ($fManager > 0)   { $where[] = 'c.manager_id = :mid';   $params[':mid'] = $fManager; }
if ($fPriority !== '') { $where[] = 'c.priority = :prio';  $params[':prio'] = $fPriority; }
if ($fFrom !== '')    { $where[] = 'c.created_at >= :from'; $params[':from'] = $fFrom . ' 00:00:00'; }
if ($fTo !== '')      { $where[] = 'c.created_at <= :to';   $params[':to'] = $fTo . ' 23:59:59'; }
if ($q !== '')        { $where[] = '(cu.name LIKE :q OR cu.phone LIKE :q OR c.consult_no LIKE :q OR c.tags LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
if ($hasConsultMeta && $fMetaKey !== '') {
    if ($fMetaValue !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM consult_meta cm WHERE cm.consult_id = c.id AND cm.meta_key = :mkey AND cm.meta_value = :mval)';
        $params[':mval'] = $fMetaValue;
    } else {
        $where[] = 'EXISTS (SELECT 1 FROM consult_meta cm WHERE cm.consult_id = c.id AND cm.meta_key = :mkey)';
    }
    $params[':mkey'] = $fMetaKey;
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$orderBy = 'ORDER BY c.id DESC';
if ($fSort === 'score_desc') { $orderBy = 'ORDER BY c.lead_score DESC, c.id DESC'; }
elseif ($fSort === 'priority_desc') { $orderBy = "ORDER BY FIELD(c.priority, 'URGENT', 'HIGH', 'NORMAL', 'LOW'), c.id DESC"; }

$rows = [];
try {
    $sql = "SELECT c.id AS cid, c.consult_no, c.status, c.product_name, c.created_at,
                   c.priority, c.lead_score, c.category_ai, c.sentiment,
                   cu.name AS cust_name, cu.phone, s.site_name,
                   mg.name AS manager_name
            FROM consults c
            LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
            LEFT JOIN sites s ON s.id = c.site_id
            LEFT JOIN managers mg ON mg.id = c.manager_id
            $sqlWhere
            $orderBy LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    // DB 컬럼 추가 전 500 에러 방지 (기본 쿼리 폴백)
    $sqlFallback = "SELECT c.id AS cid, c.consult_no, c.status, c.product_name, c.created_at,
                           cu.name AS cust_name, cu.phone, s.site_name,
                           mg.name AS manager_name
                    FROM consults c
                    LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
                    LEFT JOIN sites s ON s.id = c.site_id
                    LEFT JOIN managers mg ON mg.id = c.manager_id
                    WHERE 1=1
                    ORDER BY c.id DESC LIMIT 200";
    $stmt = $pdo->prepare($sqlFallback);
    $stmt->execute();
    $rows = $stmt->fetchAll();
}

$sites = $pdo->query("SELECT site_code, site_name FROM sites ORDER BY site_name")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM managers WHERE status = 1 ORDER BY name")->fetchAll();

$page_title = '상담관리'; $active = 'consults';
require INC_DIR . '/header.php';
?>
<h1 class="page">상담관리 <span class="muted" style="font-size:14px">(<?= count($rows) ?>건)</span></h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="msg err"><?= e($flashErr) ?></div><?php endif; ?>

<?php
// 현재 필터를 export 링크에 그대로 전달
$exportQs = http_build_query(array_filter([
    'site' => $fSite, 'status' => $fStatus, 'manager' => $fManager ?: '',
    'priority' => $fPriority, 'sort' => $fSort,
    'from' => $fFrom, 'to' => $fTo, 'q' => $q,
    'meta_key' => $fMetaKey, 'meta_value' => $fMetaValue,
], fn($v) => $v !== '' && $v !== 0));
?>
<form method="get" class="filters">
  <select name="site">
    <option value="">전체 사이트</option>
    <?php foreach ($sites as $s): ?>
      <option value="<?= e($s['site_code']) ?>" <?= $fSite === $s['site_code'] ? 'selected' : '' ?>><?= e($s['site_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">전체 상태</option>
    <?php foreach (CONSULT_STATUSES as $k => $v): ?>
      <option value="<?= e($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="manager">
    <option value="0">전체 담당자</option>
    <?php foreach ($managers as $mg): ?>
      <option value="<?= (int)$mg['id'] ?>" <?= $fManager === (int)$mg['id'] ? 'selected' : '' ?>><?= e($mg['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="priority">
    <option value="">전체 긴급도</option>
    <option value="URGENT" <?= $fPriority === 'URGENT' ? 'selected' : '' ?>>🚨 긴급(URGENT)</option>
    <option value="HIGH" <?= $fPriority === 'HIGH' ? 'selected' : '' ?>>🔥 높음(HIGH)</option>
    <option value="NORMAL" <?= $fPriority === 'NORMAL' ? 'selected' : '' ?>>⚡ 보통(NORMAL)</option>
    <option value="LOW" <?= $fPriority === 'LOW' ? 'selected' : '' ?>>🌱 낮음(LOW)</option>
  </select>
  <select name="sort">
    <option value="">최신 접수순</option>
    <option value="priority_desc" <?= $fSort === 'priority_desc' ? 'selected' : '' ?>>🚨 긴급도 높은순</option>
    <option value="score_desc" <?= $fSort === 'score_desc' ? 'selected' : '' ?>>⭐ 계약확률 높은순</option>
  </select>
  <input type="date" name="from" value="<?= e($fFrom) ?>">
  <input type="date" name="to" value="<?= e($fTo) ?>">
  <input type="text" name="q" placeholder="이름·전화·상담번호·태그" value="<?= e($q) ?>">
  <?php if ($hasConsultMeta): ?>
    <input type="text" name="meta_key" placeholder="상세필드 key (예: internet_speed)" value="<?= e($fMetaKey) ?>" style="width:170px">
    <input type="text" name="meta_value" placeholder="값 (예: 1G)" value="<?= e($fMetaValue) ?>" style="width:110px">
  <?php endif; ?>
  <button type="submit" class="btn">검색</button>
  <a href="/admin/consults/" class="btn sub">초기화</a>
</form>

<!-- 선택 삭제 폼 (form 속성으로 체크박스 연결 → 테이블 중첩 폼 회피) -->
<form id="bulkform" method="post" onsubmit="return confirm('선택한 상담을 삭제할까요? 되돌릴 수 없습니다.')">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="bulk_delete">
</form>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
  <a href="/admin/consults/export.php<?= $exportQs ? '?' . e($exportQs) : '' ?>" class="btn sub" style="background:#276749">엑셀 내보내기(CSV)</a>
  <?php if (can_manage()): ?>
    <button type="submit" form="bulkform" class="btn danger">선택 삭제</button>
    <span class="muted" style="font-size:13px;align-self:center">체크한 상담을 삭제합니다(관리자 전용).</span>
  <?php endif; ?>
</div>

<div class="tablewrap">
  <table>
    <thead><tr>
      <?php if (can_manage()): ?><th style="width:28px"><input type="checkbox" id="checkall" style="width:auto"></th><?php endif; ?>
      <th>상담번호</th><th>긴급도</th><th>점수</th><th>AI분류</th><th>접수일</th><th>사이트</th><th>상품</th><th>고객</th><th>연락처</th><th>담당자</th><th>상태</th>
    </tr></thead>
    <tbody>
      <?php $colCount = can_manage() ? 12 : 11; ?>
      <?php if (!$rows): ?><tr><td colspan="<?= $colCount ?>" class="muted">결과가 없습니다.</td></tr><?php endif; ?>
      <?php
      $prioBg = ['URGENT' => '#ef4444', 'HIGH' => '#f97316', 'NORMAL' => '#3b82f6', 'LOW' => '#6b7280'];
      $prioTxt = ['URGENT' => '🚨 긴급', 'HIGH' => '🔥 높음', 'NORMAL' => '⚡ 보통', 'LOW' => '🌱 낮음'];
      $sentIcon = ['POSITIVE' => '😊', 'NEUTRAL' => '😐', 'NEGATIVE' => '😡'];
      $prevDate = null;
      $groupToggle = false;
      $groupBg = ['#ffffff', '#f8fafc']; // 날짜 그룹 교대 배경
      ?>
      <?php foreach ($rows as $r): ?>
        <?php
        $rowDate = substr((string)$r['created_at'], 0, 10);
        if ($rowDate !== $prevDate) {
            $prevDate = $rowDate;
            $groupToggle = !$groupToggle;
        ?>
        <tr class="date-sep">
          <td colspan="<?= $colCount ?>" style="background:#e2e8f0;color:#334155;font-weight:700;font-size:12px;padding:7px 10px;border-top:2px solid #94a3b8">
            📅 <?= e($rowDate) ?>
          </td>
        </tr>
        <?php } ?>
        <tr style="background:<?= $groupBg[$groupToggle ? 1 : 0] ?>">
          <?php if (can_manage()): ?><td><input type="checkbox" class="rowchk" name="ids[]" form="bulkform" value="<?= (int)$r['cid'] ?>" style="width:auto"></td><?php endif; ?>
          <td><a href="/admin/consults/view.php?no=<?= e($r['consult_no']) ?>" class="mono"><?= e($r['consult_no']) ?></a></td>
          <td>
            <?php if (!empty($r['priority']) && isset($prioBg[$r['priority']])): ?>
              <span class="badge" style="background:<?= $prioBg[$r['priority']] ?>;color:#fff;padding:2px 6px;font-size:11px"><?= $prioTxt[$r['priority']] ?></span>
            <?php else: ?>
              <span class="muted" style="font-size:11px">-</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:bold;color:#059669;font-size:13px">
            <?= ((int)$r['lead_score'] > 0 || !empty($r['category_ai'])) ? ((int)$r['lead_score'] . '점') : '-' ?>
          </td>
          <td style="font-size:13px">
            <?= e($r['category_ai'] ?? '-') ?> <?= $sentIcon[$r['sentiment'] ?? ''] ?? '' ?>
          </td>
          <td class="muted"><?= e(substr((string)$r['created_at'], 0, 16)) ?></td>
          <td><?= e($r['site_name'] ?? '(미등록 사이트)') ?></td>
          <td><?= e($r['product_name'] ?? '-') ?></td>
          <td><?= e($r['cust_name'] ?? '(미등록 고객)') ?></td>
          <td class="mono"><?= e($r['phone'] ?? '-') ?></td>
          <td><?= e($r['manager_name'] ?? '-') ?></td>
          <td><span class="badge st-<?= e($r['status']) ?>"><?= e(CONSULT_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
(function () {
  var all = document.getElementById('checkall');
  if (all) {
    all.addEventListener('change', function () {
      document.querySelectorAll('.rowchk').forEach(function (c) { c.checked = all.checked; });
    });
  }
})();
</script>
<?php require INC_DIR . '/footer.php'; ?>
