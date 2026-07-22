<?php
declare(strict_types=1);
/** 설정 + 활동 로그 조회 (SPEC.md B-8) */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$logs = $pdo->query(
    "SELECT l.*, m.name AS mgr FROM activity_log l
     LEFT JOIN managers m ON m.id = l.manager_id
     ORDER BY l.id DESC LIMIT 100"
)->fetchAll();

$page_title = '설정'; $active = 'settings';
require INC_DIR . '/header.php';
?>
<h1 class="page">설정</h1>

<div class="card" style="margin-bottom:16px">
  <h3 style="margin-top:0">시스템 정보</h3>
  <table><tbody>
    <tr><th>시스템명</th><td><?= e(APP_NAME) ?></td></tr>
    <tr><th>브랜드명</th><td><?= e(APP_BRAND) ?></td></tr>
    <tr><th>API Base</th><td class="mono"><?= e(API_BASE) ?></td></tr>
    <tr><th>상담 API</th><td class="mono"><?= e(API_BASE) ?>/consult.php</td></tr>
    <tr><th>서버 확인</th><td><a href="/api/v1/health.php" target="_blank" class="mono"><?= e(API_BASE) ?>/health.php</a></td></tr>
    <tr><th>PHP</th><td><?= e(PHP_VERSION) ?></td></tr>
  </tbody></table>
  <p class="muted" style="font-size:13px">회사정보·알림·외부연동(SMS/카카오) 설정은 V1.5에서 추가됩니다.</p>
  <div style="margin-top:12px">
    <a href="/admin/settings/ai.php" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:#2b6cb0;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600">
      🤖 AI 설정 (API 키·모델·ON/OFF)
    </a>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0">활동 로그 (최근 100)</h3>
  <div class="tablewrap">
    <table>
      <thead><tr><th>시각</th><th>담당자</th><th>동작</th><th>대상</th><th>상세</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="muted"><?= e($l['created_at']) ?></td>
            <td><?= e($l['mgr'] ?? '-') ?></td>
            <td><?= e($l['action']) ?></td>
            <td class="mono"><?= e($l['target'] ?? '-') ?></td>
            <td><?= e($l['detail'] ?? '') ?></td>
            <td class="mono muted"><?= e($l['ip'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require INC_DIR . '/footer.php'; ?>
