<?php
declare(strict_types=1);
/** 사이트별 임베드 폼 필드 스키마 관리 (Phase 1 — site_field_schemas) */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util/SiteFieldSchema.php';
require_once __DIR__ . '/../../includes/util/ProductSchema.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$siteId = (int)($_GET['site_id'] ?? 0);
if ($siteId <= 0) {
    http_response_code(400);
    echo 'site_id가 필요합니다. <a href="/admin/sites/">사이트관리로</a>';
    exit;
}

$siteStmt = $pdo->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
$siteStmt->execute([':id' => $siteId]);
$site = $siteStmt->fetch();
if (!$site) {
    http_response_code(404);
    echo '사이트를 찾을 수 없습니다. <a href="/admin/sites/">사이트관리로</a>';
    exit;
}

if (!SiteFieldSchema::tableExists($pdo)) {
    http_response_code(500);
    echo 'site_field_schemas 테이블이 없습니다. migrations/migrate.php를 먼저 실행하세요.';
    exit;
}

$flash = ''; $flashErr = '';

/** schema_json 텍스트를 검증: [{key,label,...}] 형태여야 함 */
function parse_field_schema_json(string $raw): ?array
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    foreach ($decoded as $f) {
        if (!is_array($f) || !isset($f['key'], $f['label']) || $f['key'] === '' || $f['label'] === '') {
            return null;
        }
    }
    return $decoded;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $category = clean_str($_POST['category'] ?? '', 60);
        $productName = clean_str($_POST['product_name'] ?? '', 100);
        $sort = (int)($_POST['sort_order'] ?? 0);
        $rawJson = (string)($_POST['schema_json'] ?? '');
        $fields = parse_field_schema_json($rawJson);

        if ($fields === null) {
            $flashErr = 'schema_json 형식이 올바르지 않습니다. 각 항목은 {"key":"...","label":"..."} 을(를) 포함해야 합니다.';
        } else {
            $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);
            if ($action === 'create') {
                try {
                    $pdo->prepare(
                        'INSERT INTO site_field_schemas (site_id, category, product_name, schema_json, sort_order)
                         VALUES (:sid, :cat, :prod, :json, :sort)'
                    )->execute([
                        ':sid' => $siteId, ':cat' => $category, ':prod' => $productName,
                        ':json' => $fieldsJson, ':sort' => $sort,
                    ]);
                    log_activity('site_field_schema_create', 'site:' . $siteId);
                    $flash = '필드 스키마를 추가했습니다.';
                } catch (Throwable $ex) {
                    $flashErr = '추가 실패 (동일 category+product_name 조합이 이미 있을 수 있음).';
                }
            } else {
                $id = (int)($_POST['id'] ?? 0);
                try {
                    $pdo->prepare(
                        'UPDATE site_field_schemas SET category=:cat, product_name=:prod, schema_json=:json, sort_order=:sort
                         WHERE id=:id AND site_id=:sid'
                    )->execute([
                        ':cat' => $category, ':prod' => $productName, ':json' => $fieldsJson,
                        ':sort' => $sort, ':id' => $id, ':sid' => $siteId,
                    ]);
                    log_activity('site_field_schema_update', 'site_field_schema:' . $id);
                    $flash = '필드 스키마를 수정했습니다.';
                } catch (Throwable $ex) {
                    $flashErr = '수정 실패 (동일 category+product_name 조합이 이미 있을 수 있음).';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM site_field_schemas WHERE id = :id AND site_id = :sid')
            ->execute([':id' => $id, ':sid' => $siteId]);
        log_activity('site_field_schema_delete', 'site_field_schema:' . $id);
        $flash = '필드 스키마를 삭제했습니다.';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM site_field_schemas WHERE id = :id AND site_id = :sid LIMIT 1');
    $st->execute([':id' => $editId, ':sid' => $siteId]);
    $editRow = $st->fetch() ?: null;
}

$rows = $pdo->prepare('SELECT * FROM site_field_schemas WHERE site_id = :sid ORDER BY sort_order, id');
$rows->execute([':sid' => $siteId]);
$rows = $rows->fetchAll();

$productStmt = $pdo->prepare(
    'SELECT DISTINCT product_name FROM products WHERE brand = :b AND '
    . ProductSchema::activeSql($pdo) . ' AND ' . ProductSchema::siteScopeSql($pdo)
    . ' ORDER BY product_name'
);
$productStmt->execute(array_merge(
    [':b' => $site['brand']],
    ProductSchema::siteScopeParams($pdo, $siteId)
));
$productNames = $productStmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = '필드스키마 — ' . $site['site_name']; $active = 'sites';
require INC_DIR . '/header.php';
?>
<h1 class="page">필드스키마 <span class="muted" style="font-size:14px">(<?= e($site['site_name']) ?> / <?= e($site['site_code']) ?>)</span></h1>
<p><a href="/admin/sites/">&larr; 사이트관리로</a></p>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="msg err"><?= e($flashErr) ?></div><?php endif; ?>

<div class="tablewrap" style="margin-bottom:20px">
  <table>
    <thead><tr>
      <th>카테고리</th><th>상품명</th><th>필드 JSON</th><th style="width:60px">순서</th><th>관리</th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= $r['category'] !== '' ? e($r['category']) : '<span class="muted">(전체)</span>' ?></td>
          <td><?= $r['product_name'] !== '' ? e($r['product_name']) : '<span class="muted">(카테고리 단위)</span>' ?></td>
          <td class="mono" style="max-width:420px;overflow-wrap:break-word;font-size:12px"><?= e($r['schema_json']) ?></td>
          <td><?= (int)$r['sort_order'] ?></td>
          <td style="white-space:nowrap">
            <a href="?site_id=<?= $siteId ?>&edit=<?= (int)$r['id'] ?>#schemaform" class="btn" style="padding:5px 8px;font-size:12px">수정</a>
            <form method="post" style="display:inline" onsubmit="return confirm('이 필드 스키마를 삭제할까요?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger" style="padding:5px 8px;font-size:12px">삭제</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="muted" style="text-align:center;padding:18px">등록된 필드 스키마가 없습니다. 임베드 폼은 기본 EXTRA 규칙으로 동작합니다.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" id="schemaform">
  <?php if ($editRow): ?>
    <h3 style="margin-top:0">필드 스키마 수정</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr 80px;gap:10px">
        <div><label>카테고리 (비우면 전체 적용)</label><input name="category" value="<?= e($editRow['category']) ?>"></div>
        <div>
          <label>상품명 (비우면 카테고리 단위)</label>
          <select name="product_name">
            <option value="">(카테고리 단위 적용)</option>
            <?php if ($editRow['product_name'] !== '' && !in_array($editRow['product_name'], $productNames, true)): ?>
              <option value="<?= e($editRow['product_name']) ?>" selected><?= e($editRow['product_name']) ?> (목록에 없음)</option>
            <?php endif; ?>
            <?php foreach ($productNames as $pn): ?>
              <option value="<?= e($pn) ?>" <?= $editRow['product_name'] === $pn ? 'selected' : '' ?>><?= e($pn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>순서</label><input name="sort_order" type="number" value="<?= (int)$editRow['sort_order'] ?>"></div>
      </div>
      <div style="margin-top:10px">
        <label>필드 목록 *</label>
        <div id="fbRows" class="fb-rows"></div>
        <button type="button" id="fbAddRow" class="btn sub" style="padding:6px 10px;font-size:12px;margin-top:6px">+ 필드 추가</button>
        <input type="hidden" name="schema_json" id="fbHidden">
        <p class="muted" style="font-size:12px;margin-top:6px">key는 영문/숫자/언더스코어 권장. 옵션은 select 타입일 때만, 쉼표로 구분해서 입력.</p>
      </div>
      <button class="btn" style="margin-top:12px">수정 저장</button>
      <a href="/admin/site_fields/index.php?site_id=<?= $siteId ?>" class="btn sub" style="margin-top:12px">취소</a>
    </form>
  <?php else: ?>
    <h3 style="margin-top:0">필드 스키마 추가</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div style="display:grid;grid-template-columns:1fr 1fr 80px;gap:10px">
        <div><label>카테고리 (비우면 전체 적용)</label><input name="category"></div>
        <div>
          <label>상품명 (비우면 카테고리 단위)</label>
          <select name="product_name">
            <option value="">(카테고리 단위 적용)</option>
            <?php foreach ($productNames as $pn): ?>
              <option value="<?= e($pn) ?>"><?= e($pn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>순서</label><input name="sort_order" type="number" value="0"></div>
      </div>
      <div style="margin-top:10px">
        <label>필드 목록 *</label>
        <div id="fbRows" class="fb-rows"></div>
        <button type="button" id="fbAddRow" class="btn sub" style="padding:6px 10px;font-size:12px;margin-top:6px">+ 필드 추가</button>
        <input type="hidden" name="schema_json" id="fbHidden">
        <p class="muted" style="font-size:12px;margin-top:6px">key는 영문/숫자/언더스코어 권장. 옵션은 select 타입일 때만, 쉼표로 구분해서 입력.</p>
      </div>
      <button class="btn" style="margin-top:12px">추가</button>
    </form>
  <?php endif; ?>
</div>

<script>
(function () {
  var rowsWrap = document.getElementById('fbRows');
  var hidden = document.getElementById('fbHidden');
  var addBtn = document.getElementById('fbAddRow');
  if (!rowsWrap || !hidden) { return; }

  var initialFields = <?= json_encode($editRow ? (json_decode((string)$editRow['schema_json'], true) ?: []) : [], JSON_UNESCAPED_UNICODE) ?>;
  var TYPES = ['text', 'number', 'date', 'select'];

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function addRow(f) {
    f = f || {};
    var row = document.createElement('div');
    row.className = 'fb-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 100px 1fr 64px 56px;gap:6px;margin-bottom:6px;align-items:center';
    row.innerHTML =
      '<input class="fb-key" placeholder="key (예: internet_speed)" value="' + esc(f.key) + '">' +
      '<input class="fb-label" placeholder="라벨 (예: 인터넷 속도)" value="' + esc(f.label) + '">' +
      '<select class="fb-type">' +
        TYPES.map(function (t) { return '<option value="' + t + '"' + (f.type === t ? ' selected' : '') + '>' + t + '</option>'; }).join('') +
      '</select>' +
      '<input class="fb-options" placeholder="옵션(쉼표구분, select만)" value="' + esc(Array.isArray(f.options) ? f.options.join(',') : '') + '">' +
      '<label style="font-size:12px;display:flex;align-items:center;gap:4px;white-space:nowrap"><input type="checkbox" class="fb-required" style="width:auto"' + (f.required ? ' checked' : '') + '> 필수</label>' +
      '<button type="button" class="fb-del btn danger" style="padding:4px 6px;font-size:11px">삭제</button>';
    row.querySelector('.fb-del').addEventListener('click', function () { row.remove(); });
    rowsWrap.appendChild(row);
  }

  (initialFields.length ? initialFields : [{}]).forEach(addRow);
  addBtn.addEventListener('click', function () { addRow({}); });

  var form = rowsWrap.closest('form');
  form.addEventListener('submit', function (ev) {
    var fields = [];
    rowsWrap.querySelectorAll('.fb-row').forEach(function (row) {
      var key = row.querySelector('.fb-key').value.trim();
      var label = row.querySelector('.fb-label').value.trim();
      if (!key || !label) { return; }
      var type = row.querySelector('.fb-type').value;
      var field = { key: key, label: label, type: type };
      if (type === 'select') {
        field.options = row.querySelector('.fb-options').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      }
      if (row.querySelector('.fb-required').checked) { field.required = true; }
      fields.push(field);
    });
    if (!fields.length) {
      ev.preventDefault();
      alert('필드를 최소 1개 이상 입력하세요 (key, 라벨 필수).');
      return;
    }
    hidden.value = JSON.stringify(fields);
  });
})();
</script>

<?php require INC_DIR . '/footer.php'; ?>
