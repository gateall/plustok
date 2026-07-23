<?php
declare(strict_types=1);
/** 사이트관리 + API Key 발급/재발급 + 추가/수정/삭제/선택삭제 (SPEC.md B-4) */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$flash = ''; $flashKey = ''; $flashErr = '';

/** 상담이 연결된 사이트인지 (연결되면 삭제 불가) */
function site_has_consults(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM consults WHERE site_id = :id');
    $st->execute([':id' => $id]);
    return ((int)$st->fetchColumn()) > 0;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = clean_str($_POST['site_code'] ?? '', 50);
        $name = clean_str($_POST['site_name'] ?? '', 100);
        $domain = clean_str($_POST['domain'] ?? '', 150);
        $brand = clean_str($_POST['brand'] ?? '', 50);
        $division = clean_str($_POST['division'] ?? '', 50);
        $persona = clean_str($_POST['persona'] ?? '', 255);
        if ($code === '' || $name === '' || $domain === '' || $brand === '' || $division === '') {
            $flashErr = '필수 항목을 모두 입력하세요.';
        } else {
            try {
                $key = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO sites (site_code, site_name, domain, brand, division, persona, api_key)
                     VALUES (:code, :name, :domain, :brand, :division, :persona, :key)'
                )->execute([
                    ':code' => $code, ':name' => $name, ':domain' => $domain, ':brand' => $brand,
                    ':division' => $division, ':persona' => $persona ?: null, ':key' => $key,
                ]);
                log_activity('site_create', 'site:' . $code);
                $flash = '사이트를 등록했습니다.'; $flashKey = $key;
            } catch (Throwable $ex) {
                $flashErr = '등록 실패(중복 site_code일 수 있음).';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $code = clean_str($_POST['site_code'] ?? '', 50);
        $name = clean_str($_POST['site_name'] ?? '', 100);
        $domain = clean_str($_POST['domain'] ?? '', 150);
        $brand = clean_str($_POST['brand'] ?? '', 50);
        $division = clean_str($_POST['division'] ?? '', 50);
        $persona = clean_str($_POST['persona'] ?? '', 255);
        if ($id <= 0 || $code === '' || $name === '' || $domain === '' || $brand === '' || $division === '') {
            $flashErr = '필수 항목을 모두 입력하세요.';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE sites SET site_code=:code, site_name=:name, domain=:domain,
                        brand=:brand, division=:division, persona=:persona WHERE id=:id'
                )->execute([
                    ':code' => $code, ':name' => $name, ':domain' => $domain, ':brand' => $brand,
                    ':division' => $division, ':persona' => $persona ?: null, ':id' => $id,
                ]);
                log_activity('site_update', 'site:' . $id);
                $flash = '사이트를 수정했습니다.';
            } catch (Throwable $ex) {
                $flashErr = '수정 실패(중복 site_code일 수 있음).';
            }
        }
    } elseif ($action === 'regen') {
        $id = (int)($_POST['id'] ?? 0);
        $key = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE sites SET api_key = :k WHERE id = :id')->execute([':k' => $key, ':id' => $id]);
        log_activity('site_regen_key', 'site:' . $id);
        $flash = 'API Key를 재발급했습니다.'; $flashKey = $key;
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE sites SET status = 1 - status WHERE id = :id')->execute([':id' => $id]);
        $flash = '사용여부를 변경했습니다.';
    } elseif ($action === 'delete' || $action === 'bulk_delete') {
        $ids = [];
        if ($action === 'delete') {
            $ids = [(int)($_POST['id'] ?? 0)];
        } else {
            foreach ((array)($_POST['ids'] ?? []) as $v) { $ids[] = (int)$v; }
        }
        $ids = array_values(array_filter(array_unique($ids), fn($v) => $v > 0));
        $deleted = 0; $blocked = [];
        foreach ($ids as $id) {
            if (site_has_consults($pdo, $id)) {
                $blocked[] = $id; // 상담 연결 → 삭제 금지
                continue;
            }
            $pdo->prepare('DELETE FROM sites WHERE id = :id')->execute([':id' => $id]);
            log_activity('site_delete', 'site:' . $id);
            $deleted++;
        }
        if ($deleted > 0) { $flash = $deleted . '개 사이트를 삭제했습니다.'; }
        if ($blocked)     { $flashErr = '상담이 연결된 사이트는 삭제할 수 없습니다(사용중지를 권장). id: ' . implode(', ', $blocked); }
        if (!$ids)        { $flashErr = '선택된 사이트가 없습니다.'; }
    }
}

// 수정 대상 로드
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
    $st->execute([':id' => $editId]);
    $editRow = $st->fetch() ?: null;
}

$rows = $pdo->query("SELECT * FROM sites ORDER BY division, brand, site_name")->fetchAll();
$page_title = '사이트관리'; $active = 'sites';
require INC_DIR . '/header.php';
?>
<h1 class="page">사이트관리</h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?><?php if ($flashKey): ?><br>API Key: <span class="mono"><?= e($flashKey) ?></span> <b>(지금 복사하세요. 다시 표시되지 않습니다)</b><?php endif; ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="msg err"><?= e($flashErr) ?></div><?php endif; ?>

<!-- 선택 삭제 폼 (form 속성으로 체크박스를 연결 → 테이블 중첩 폼 회피) -->
<form id="bulkform" method="post" onsubmit="return confirm('선택한 사이트를 삭제할까요? (상담이 연결된 사이트는 자동 제외)')">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="bulk_delete">
</form>
<div style="margin-bottom:8px">
  <button type="submit" form="bulkform" class="btn danger" style="padding:7px 12px;font-size:13px">선택 삭제</button>
  <span class="muted" style="font-size:13px;margin-left:6px">체크한 사이트를 한 번에 삭제합니다.</span>
</div>

<div class="tablewrap" style="margin-bottom:20px">
  <table>
    <thead><tr>
      <th style="width:28px"><input type="checkbox" id="checkall" style="width:auto"></th>
      <th>사이트</th><th>도메인</th><th>브랜드</th><th>사업부</th><th>site_code</th><th>API Key</th><th>사용</th><th>관리</th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><input type="checkbox" class="rowchk" name="ids[]" form="bulkform" value="<?= (int)$r['id'] ?>" style="width:auto"></td>
          <td><?= e($r['site_name']) ?></td>
          <td><?= e($r['domain']) ?></td>
          <td><?= e($r['brand']) ?></td>
          <td><?= e($r['division']) ?></td>
          <td class="mono"><?= e($r['site_code']) ?></td>
          <td class="mono muted"><?= e(substr((string)$r['api_key'], 0, 8)) ?>…</td>
          <td><?= ((int)$r['status'] === 1) ? '사용' : '<span class="muted">중지</span>' ?></td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= (int)$r['id'] ?>#siteform" class="btn" style="padding:5px 8px;font-size:12px">수정</a>
            <a href="/admin/site_fields/index.php?site_id=<?= (int)$r['id'] ?>" class="btn sub" style="padding:5px 8px;font-size:12px">필드스키마</a>
            <form method="post" style="display:inline" onsubmit="return confirm('API Key를 재발급하면 기존 키는 즉시 무효화됩니다. 계속할까요?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="regen">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn sub" style="padding:5px 8px;font-size:12px">키 재발급</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn sub" style="padding:5px 8px;font-size:12px"><?= (int)$r['status'] === 1 ? '중지' : '사용' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('이 사이트를 삭제할까요?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger" style="padding:5px 8px;font-size:12px">삭제</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" id="siteform">
  <?php if ($editRow): ?>
    <h3 style="margin-top:0">사이트 수정 <span class="muted" style="font-size:13px">(<?= e($editRow['site_code']) ?>)</span></h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div><label>site_code *</label><input name="site_code" value="<?= e($editRow['site_code']) ?>" required></div>
        <div><label>사이트명 *</label><input name="site_name" value="<?= e($editRow['site_name']) ?>" required></div>
        <div><label>도메인 *</label><input name="domain" value="<?= e($editRow['domain']) ?>" required></div>
        <div><label>브랜드 *</label><input name="brand" value="<?= e($editRow['brand']) ?>" required></div>
        <div><label>사업부 *</label><input name="division" value="<?= e($editRow['division']) ?>" required></div>
        <div><label>첫인사(persona)</label><input name="persona" value="<?= e($editRow['persona'] ?? '') ?>"></div>
      </div>
      <button class="btn" style="margin-top:12px">수정 저장</button>
      <a href="/admin/sites/" class="btn sub" style="margin-top:12px">취소</a>
    </form>
  <?php else: ?>
    <h3 style="margin-top:0">사이트 추가 <span class="muted" style="font-size:13px">(한 브랜드에 도메인이 여러 개면 각각 등록)</span></h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div><label>site_code *</label><input name="site_code" placeholder="lg15441644_b" required></div>
        <div><label>사이트명 *</label><input name="site_name" required></div>
        <div><label>도메인 *</label><input name="domain" placeholder="example.kr" required></div>
        <div><label>브랜드 *</label><input name="brand" placeholder="LG15441644" required></div>
        <div><label>사업부 *</label><input name="division" placeholder="통신가입" required></div>
        <div><label>첫인사(persona)</label><input name="persona"></div>
      </div>
      <button class="btn" style="margin-top:12px">등록 + API Key 발급</button>
    </form>
  <?php endif; ?>
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
