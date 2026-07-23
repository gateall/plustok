<?php
declare(strict_types=1);
/** 대시보드 (SPEC.md B-1) */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util/CrmSchema.php';
require_login();

$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);

// 요약 카드
$total     = (int)$pdo->query("SELECT COUNT(*) FROM consults")->fetchColumn();
$todayNew  = (int)$pdo->query("SELECT COUNT(*) FROM consults WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$inProg    = (int)$pdo->query("SELECT COUNT(*) FROM consults WHERE status IN ('progress','consulting','quoted')")->fetchColumn();
$contracted= (int)$pdo->query("SELECT COUNT(*) FROM consults WHERE status = 'contracted'")->fetchColumn();

// AI 운영 요약 (STEP 9 / STEP 14) — DB 스키마(AI 컬럼) 미생성 시 500 에러 방지를 위해 try/catch 격리
$aiAnalyzedCount = 0;
$urgentCount = 0;
$highLeadCount = 0;
$byCategoryAi = [];
try {
    $aiAnalyzedCount = (int)$pdo->query("SELECT COUNT(*) FROM consults WHERE ai_analyzed_at IS NOT NULL AND DATE(created_at) = CURDATE()")->fetchColumn();
    $urgentCount     = (int)$pdo->query("SELECT COUNT(*) FROM consults WHERE priority = 'URGENT' AND status IN ('new','progress','consulting')")->fetchColumn();
    $highLeadCount   = (int)$pdo->query("SELECT COUNT(*) FROM consults WHERE lead_score >= 80 AND status IN ('new','progress','consulting')")->fetchColumn();
    $byCategoryAi    = $pdo->query(
        "SELECT COALESCE(category_ai, '미분석') AS cat, COUNT(*) AS cnt
         FROM consults
         WHERE DATE(created_at) = CURDATE()
         GROUP BY category_ai ORDER BY cnt DESC"
    )->fetchAll();
} catch (Throwable $e) {
    // DB 컬럼 추가 전 500 에러 방지
}

// 사이트별 현황
$bySite = $pdo->query(
    "SELECT s.site_name, s.brand, COUNT(c.id) AS cnt
     FROM sites s LEFT JOIN consults c ON c.site_id = s.id
     GROUP BY s.id ORDER BY cnt DESC"
)->fetchAll();

// 최근 상담 20건 (AI 지표 포함, 컬럼 미생성 시 기본 쿼리로 폴백)
$recent = [];
try {
    $recent = $pdo->query(
        "SELECT c.consult_no, c.status, c.product_name, c.created_at, c.priority, c.lead_score, c.category_ai,
                cu.name AS cust_name, cu.phone, s.site_name
         FROM consults c
         JOIN {$custTable} cu ON cu.id = c.customer_id
         JOIN sites s ON s.id = c.site_id
         ORDER BY c.id DESC LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) {
    $recent = $pdo->query(
        "SELECT c.consult_no, c.status, c.product_name, c.created_at,
                cu.name AS cust_name, cu.phone, s.site_name
         FROM consults c
         JOIN {$custTable} cu ON cu.id = c.customer_id
         JOIN sites s ON s.id = c.site_id
         ORDER BY c.id DESC LIMIT 20"
    )->fetchAll();
}

$page_title = '대시보드'; $active = 'dashboard';
require INC_DIR . '/header.php';
?>
<h1 class="page">대시보드</h1>

<div class="cards cards--kpi">
  <div class="card card--glass"><div class="label">전체 상담</div><div class="value"><?= number_format($total) ?></div></div>
  <div class="card card--glass"><div class="label">오늘 신규</div><div class="value"><?= number_format($todayNew) ?></div></div>
  <div class="card card--glass"><div class="label">진행중</div><div class="value"><?= number_format($inProg) ?></div></div>
  <div class="card card--glass"><div class="label">계약완료</div><div class="value"><?= number_format($contracted) ?></div></div>
</div>

<h2 style="font-size:16px;margin-top:24px;display:flex;align-items:center;gap:6px;color:#4f46e5">
  🤖 AI 종합 운영 대시보드 (STEP 9 / 14 프리뷰)
</h2>
<div class="cards cards--kpi">
  <div class="card card--glass card--accent-indigo"><div class="label">오늘 AI 자동분석</div><div class="value"><?= number_format($aiAnalyzedCount) ?>건</div></div>
  <div class="card card--glass card--accent-red"><div class="label">🚨 긴급(URGENT) 대기</div><div class="value"><?= number_format($urgentCount) ?>건</div></div>
  <div class="card card--glass card--accent-green"><div class="label">⭐ 계약유력(Lead ≥80)</div><div class="value"><?= number_format($highLeadCount) ?>건</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px" class="grid2">
  <div class="tablewrap table-responsive">
    <h3 style="font-size:14px;margin:0 0 10px 0">📊 오늘 AI 자동분석 카테고리별 현황</h3>
    <table>
      <thead><tr><th>AI 분류 카테고리</th><th class="right">오늘 접수 건수</th></tr></thead>
      <tbody>
        <?php if (!$byCategoryAi): ?>
          <tr><td colspan="2" class="muted">오늘 접수된 상담이 없습니다.</td></tr>
        <?php endif; ?>
        <?php foreach ($byCategoryAi as $ca): ?>
          <tr><td style="font-weight:600"><?= e($ca['cat']) ?></td><td class="right"><?= number_format((int)$ca['cnt']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="tablewrap table-responsive">
    <h3 style="font-size:14px;margin:0 0 10px 0">🏢 사이트별 상담 현황</h3>
    <table>
      <thead><tr><th>사이트</th><th>브랜드</th><th class="right">상담 건수</th></tr></thead>
      <tbody>
        <?php foreach ($bySite as $r): ?>
          <tr><td><?= e($r['site_name']) ?></td><td><?= e($r['brand']) ?></td><td class="right"><?= number_format((int)$r['cnt']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<h2 style="font-size:16px">최근 상담</h2>
<div class="tablewrap table-responsive">
  <table>
    <thead><tr><th>상담번호</th><th>긴급도</th><th>점수/분류</th><th>사이트</th><th>상품</th><th>고객</th><th>연락처</th><th>상태</th><th>접수시각</th></tr></thead>
    <tbody>
      <?php if (!$recent): ?>
        <tr><td colspan="9" class="muted">아직 접수된 상담이 없습니다.</td></tr>
      <?php endif; ?>
      <?php
      $prioBg = ['URGENT' => '#ef4444', 'HIGH' => '#f97316', 'NORMAL' => '#3b82f6', 'LOW' => '#6b7280'];
      $prioTxt = ['URGENT' => '🚨 긴급', 'HIGH' => '🔥 높음', 'NORMAL' => '⚡ 보통', 'LOW' => '🌱 낮음'];
      ?>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><a href="/admin/consults/view.php?no=<?= e($r['consult_no']) ?>" class="mono"><?= e($r['consult_no']) ?></a></td>
          <td>
            <?php if (!empty($r['priority']) && isset($prioBg[$r['priority']])): ?>
              <span class="badge" style="background:<?= $prioBg[$r['priority']] ?>;color:#fff;padding:2px 6px;font-size:11px"><?= $prioTxt[$r['priority']] ?></span>
            <?php else: ?>
              <span class="muted" style="font-size:11px">-</span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px">
            <span style="font-weight:bold;color:#059669"><?= ((int)$r['lead_score'] > 0) ? ((int)$r['lead_score'] . '점') : '' ?></span>
            <?= e($r['category_ai'] ?? '-') ?>
          </td>
          <td><?= e($r['site_name']) ?></td>
          <td><?= e($r['product_name'] ?? '-') ?></td>
          <td><?= e($r['cust_name']) ?></td>
          <td class="mono"><?= e($r['phone']) ?></td>
          <td><span class="badge st-<?= e($r['status']) ?>"><?= e(CONSULT_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
          <td class="muted"><?= e($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require INC_DIR . '/footer.php'; ?>
