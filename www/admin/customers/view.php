<?php
declare(strict_types=1);
/** 고객 상세 + 상담 이력 (SPEC.md B-2) */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$cu = $stmt->fetch();
if (!$cu) { http_response_code(404); echo '고객을 찾을 수 없습니다.'; exit; }

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && can_edit_consult()) {
    csrf_check();
    $memo = clean_str($_POST['memo'] ?? '', 2000);
    $pdo->prepare('UPDATE customers SET memo = :m WHERE id = :id')->execute([':m' => $memo ?: null, ':id' => $id]);
    $cu['memo'] = $memo;
    $flash = '메모를 저장했습니다.';
}

$hs = $pdo->prepare(
    "SELECT c.consult_no, c.status, c.product_name, c.created_at, s.site_name
     FROM consults c JOIN sites s ON s.id = c.site_id
     WHERE c.customer_id = :id ORDER BY c.id DESC"
);
$hs->execute([':id' => $id]);
$consults = $hs->fetchAll();

$page_title = '고객 ' . $cu['name']; $active = 'customers';
require INC_DIR . '/header.php';
?>
<p><a href="/admin/customers/">← 목록</a></p>
<h1 class="page"><?= e($cu['name']) ?> <span class="mono muted" style="font-size:14px"><?= e($cu['customer_no']) ?></span></h1>
<?php if ($flash): ?><div class="msg ok"><?= e($flash) ?></div><?php endif; ?>

<div class="card">
  <table><tbody>
    <tr><th>연락처</th><td class="mono"><?= e($cu['phone']) ?></td></tr>
    <tr><th>회사</th><td><?= e($cu['company'] ?? '-') ?></td></tr>
    <tr><th>이메일</th><td><?= e($cu['email'] ?? '-') ?></td></tr>
    <tr><th>지역/주소</th><td><?= e(trim(($cu['region'] ?? '') . ' ' . ($cu['address'] ?? ''))) ?: '-' ?></td></tr>
    <tr><th>등록일</th><td class="muted"><?= e($cu['created_at']) ?></td></tr>
  </tbody></table>
</div>

<?php if (can_edit_consult()): ?>
<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">고객 메모</h3>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <textarea name="memo"><?= e($cu['memo'] ?? '') ?></textarea>
    <button class="btn" style="margin-top:8px">저장</button>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <h3 style="margin-top:0">상담 이력 (<?= count($consults) ?>)</h3>
  <div class="tablewrap">
    <table>
      <thead><tr><th>상담번호</th><th>사이트</th><th>상품</th><th>상태</th><th>접수일</th></tr></thead>
      <tbody>
        <?php foreach ($consults as $r): ?>
          <tr>
            <td><a href="/admin/consults/view.php?no=<?= e($r['consult_no']) ?>" class="mono"><?= e($r['consult_no']) ?></a></td>
            <td><?= e($r['site_name']) ?></td>
            <td><?= e($r['product_name'] ?? '-') ?></td>
            <td><span class="badge st-<?= e($r['status']) ?>"><?= e(CONSULT_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
            <td class="muted"><?= e(substr((string)$r['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require INC_DIR . '/footer.php'; ?>
