<?php
declare(strict_types=1);
/** 고객 목록 (SPEC.md B-2) */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';
require_login();
$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);

$q = clean_str($_GET['q'] ?? '', 50);
$where = ''; $params = [];
if ($q !== '') {
    $where = 'WHERE cu.name LIKE :q OR cu.phone LIKE :q OR cu.company LIKE :q';
    $params[':q'] = '%' . $q . '%';
}
$sql = "SELECT cu.id, cu.customer_no, cu.name, cu.phone, cu.company,
               COUNT(c.id) AS consult_cnt, MAX(c.created_at) AS last_at
        FROM {$custTable} cu
        LEFT JOIN consults c ON c.customer_id = cu.id
        $where
        GROUP BY cu.id ORDER BY last_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = '고객관리'; $active = 'customers';
require INC_DIR . '/header.php';
?>
<h1 class="page">고객관리 <span class="muted" style="font-size:14px">(<?= count($rows) ?>명)</span></h1>
<form method="get" class="filters">
  <input type="text" name="q" placeholder="이름·전화·회사" value="<?= e($q) ?>">
  <button class="btn">검색</button>
  <a href="/admin/customers/" class="btn sub">초기화</a>
</form>
<div class="tablewrap">
  <table>
    <thead><tr><th>고객번호</th><th>이름</th><th>연락처</th><th>회사</th><th class="right">상담수</th><th>최근 상담</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">결과가 없습니다.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= e($r['customer_no']) ?></td>
          <td><a href="/admin/customers/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a></td>
          <td class="mono"><?= e($r['phone']) ?></td>
          <td><?= e($r['company'] ?? '-') ?></td>
          <td class="right"><?= (int)$r['consult_cnt'] ?></td>
          <td class="muted"><?= e($r['last_at'] ? substr((string)$r['last_at'], 0, 16) : '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require INC_DIR . '/footer.php'; ?>
