<?php
declare(strict_types=1);
/** 상품관리 (SPEC.md B-5) — 추가/수정/삭제/선택삭제/사용토글 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$flash = ''; $flashErr = '';

/** 상담에 연결된 상품인지 (연결되면 삭제 불가 → 중지 권장) */
function product_has_consults(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM consults WHERE product_id = :id');
    $st->execute([':id' => $id]);
    return ((int)$st->fetchColumn()) > 0;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $brand = clean_str($_POST['brand'] ?? '', 50);
        $category = clean_str($_POST['category'] ?? '', 60);
        $name = clean_str($_POST['product_name'] ?? '', 100);
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($brand && $category && $name) {
            $pdo->prepare('INSERT INTO products (brand, category, product_name, sort_order) VALUES (:b, :c, :n, :s)')
                ->execute([':b' => $brand, ':c' => $category, ':n' => $name, ':s' => $sort]);
            log_activity('product_create', 'product:' . $name);
            $flash = '상품을 추가했습니다.';
        } else { $flashErr = '필수 항목을 입력하세요.'; }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $brand = clean_str($_POST['brand'] ?? '', 50);
        $category = clean_str($_POST['category'] ?? '', 60);
        $name = clean_str($_POST['product_name'] ?? '', 100);
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($id > 0 && $brand && $category && $name) {
            $pdo->prepare('UPDATE products SET brand=:b, category=:c, product_name=:n, sort_order=:s WHERE id=:id')
                ->execute([':b' => $brand, ':c' => $category, ':n' => $name, ':s' => $sort, ':id' => $id]);
            log_activity('product_update', 'product:' . $id);
            $flash = '상품을 수정했습니다.';
        } else { $flashErr = '필수 항목을 입력하세요.'; }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE products SET use_yn = 1 - use_yn WHERE id = :id')->execute([':id' => $id]);
        log_activity('product_toggle', 'product:' . $id);
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
            if (product_has_consults($pdo, $id)) {
                $blocked[] = $id; // 상담 연결 → 삭제 금지
                continue;
            }
            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
            log_activity('product_delete', 'product:' . $id);
            $deleted++;
        }
        if ($deleted > 0) { $flash = $deleted . '개 상품을 삭제했습니다.'; }
        if ($blocked)     { $flashErr = '상담이 연결된 상품은 삭제할 수 없습니다(사용중지를 권장). id: ' . implode(', ', $blocked); }
        if (!$ids)        { $flashErr = '선택된 상품이 없습니다.'; }
    }
}

// 수정 대상 로드
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $st->execute([':id' => $editId]);
    $editRow = $st->fetch() ?: null;
}

$rows = $pdo->query("SELECT * FROM products ORDER BY brand, sort_order, id")->fetchAll();
$page_title = '상품관리'; $active = 'products';
require INC_DIR . '/header.php';
?>
<h1 class="page">상품관리</h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="msg err"><?= e($flashErr) ?></div><?php endif; ?>

<!-- 선택 삭제 폼 (form 속성으로 체크박스를 연결 → 테이블 중첩 폼 회피) -->
<form id="bulkform" method="post" onsubmit="return confirm('선택한 상품을 삭제할까요? (상담이 연결된 상품은 자동 제외)')">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="bulk_delete">
</form>
<div style="margin-bottom:8px">
  <button type="submit" form="bulkform" class="btn danger" style="padding:7px 12px;font-size:13px">선택 삭제</button>
  <span class="muted" style="font-size:13px;margin-left:6px">체크한 상품을 한 번에 삭제합니다.</span>
</div>

<div class="tablewrap" style="margin-bottom:20px">
  <table>
    <thead><tr>
      <th style="width:28px"><input type="checkbox" id="checkall" style="width:auto"></th>
      <th>브랜드</th><th>카테고리</th><th>상품명</th><th style="width:60px">순서</th><th>사용</th><th>관리</th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><input type="checkbox" class="rowchk" name="ids[]" form="bulkform" value="<?= (int)$r['id'] ?>" style="width:auto"></td>
          <td><?= e($r['brand']) ?></td>
          <td><?= e($r['category']) ?></td>
          <td><?= e($r['product_name']) ?></td>
          <td><?= (int)$r['sort_order'] ?></td>
          <td><?= (int)$r['use_yn'] === 1 ? '사용' : '<span class="muted">중지</span>' ?></td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= (int)$r['id'] ?>#productform" class="btn" style="padding:5px 8px;font-size:12px">수정</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn sub" style="padding:5px 8px;font-size:12px"><?= (int)$r['use_yn'] === 1 ? '중지' : '사용' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('이 상품을 삭제할까요?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger" style="padding:5px 8px;font-size:12px">삭제</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted" style="text-align:center;padding:18px">등록된 상품이 없습니다.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" id="productform">
  <?php if ($editRow): ?>
    <h3 style="margin-top:0">상품 수정 <span class="muted" style="font-size:13px">(<?= e($editRow['product_name']) ?>)</span></h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 80px;gap:10px">
        <div><label>브랜드 *</label><input name="brand" value="<?= e($editRow['brand']) ?>" required></div>
        <div><label>카테고리 *</label><input name="category" value="<?= e($editRow['category']) ?>" required></div>
        <div><label>상품명 *</label><input name="product_name" value="<?= e($editRow['product_name']) ?>" required></div>
        <div><label>순서</label><input name="sort_order" type="number" value="<?= (int)$editRow['sort_order'] ?>"></div>
      </div>
      <button class="btn" style="margin-top:12px">수정 저장</button>
      <a href="/admin/products/" class="btn sub" style="margin-top:12px">취소</a>
    </form>
  <?php else: ?>
    <h3 style="margin-top:0">상품 추가</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 80px;gap:10px">
        <div><label>브랜드 *</label><input name="brand" required></div>
        <div><label>카테고리 *</label><input name="category" required></div>
        <div><label>상품명 *</label><input name="product_name" required></div>
        <div><label>순서</label><input name="sort_order" type="number" value="0"></div>
      </div>
      <button class="btn" style="margin-top:12px">추가</button>
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
