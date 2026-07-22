<?php
declare(strict_types=1);
/** 통계 (SPEC.md B-7) */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$from = clean_str($_GET['from'] ?? date('Y-m-01'), 10);
$to   = clean_str($_GET['to'] ?? date('Y-m-d'), 10);
$params = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59'];

function stat_query(PDO $pdo, string $groupExpr, string $joinAlias, array $params): array {
    $sql = "SELECT $groupExpr AS k, COUNT(*) AS cnt
            FROM consults c
            JOIN sites s ON s.id = c.site_id
            LEFT JOIN managers m ON m.id = c.manager_id
            WHERE c.created_at BETWEEN :from AND :to
            GROUP BY k ORDER BY cnt DESC";
    $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchAll();
}

$bySite   = stat_query($pdo, 's.site_name', 's', $params);
$byStatus = stat_query($pdo, 'c.status', 'c', $params);
$byManager= stat_query($pdo, "COALESCE(m.name,'미배정')", 'm', $params);
$byProduct= stat_query($pdo, "COALESCE(c.product_name,'-')", 'c', $params);
$byDay = $pdo->prepare(
    "SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM consults
     WHERE created_at BETWEEN :from AND :to GROUP BY d ORDER BY d"
);
$byDay->execute($params); $days = $byDay->fetchAll();
$maxDay = 0; foreach ($days as $d) { $maxDay = max($maxDay, (int)$d['cnt']); }

$page_title = '통계'; $active = 'stats';
require INC_DIR . '/header.php';

function render_table(string $title, array $rows): void { ?>
  <div class="card">
    <h3 style="margin-top:0"><?= e($title) ?></h3>
    <table><tbody>
      <?php if (!$rows): ?><tr><td class="muted">데이터 없음</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e((string)$r['k']) ?></td><td class="right"><?= number_format((int)$r['cnt']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
<?php }
?>
<h1 class="page">통계</h1>
<form method="get" class="filters">
  <input type="date" name="from" value="<?= e($from) ?>">
  <input type="date" name="to" value="<?= e($to) ?>">
  <button class="btn">조회</button>
</form>

<div class="card" style="margin-bottom:16px">
  <h3 style="margin-top:0">일별 상담 추이</h3>
  <div style="display:flex;align-items:flex-end;gap:4px;height:140px;overflow-x:auto">
    <?php foreach ($days as $d): $h = $maxDay ? (int)round((int)$d['cnt'] / $maxDay * 120) : 0; ?>
      <div style="text-align:center;min-width:28px">
        <div style="background:#4299e1;border-radius:3px 3px 0 0;height:<?= $h ?>px" title="<?= e($d['cnt']) ?>"></div>
        <div class="muted" style="font-size:10px"><?= e(substr((string)$d['d'], 5)) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$days): ?><span class="muted">데이터 없음</span><?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <?php
  render_table('사이트별', $bySite);
  render_table('상태별', array_map(fn($r) => ['k' => CONSULT_STATUSES[$r['k']] ?? $r['k'], 'cnt' => $r['cnt']], $byStatus));
  render_table('담당자별', $byManager);
  render_table('상품별', $byProduct);
  ?>
</div>
<?php require INC_DIR . '/footer.php'; ?>
