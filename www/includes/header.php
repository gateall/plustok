<?php
declare(strict_types=1);
/**
 * 관리자 공통 헤더. 페이지에서 auth.php include + require_login() 후 include.
 * 사용 변수: $page_title(제목), $active(현재 메뉴 키)
 */
if (!function_exists('current_manager')) {
    require_once __DIR__ . '/auth.php';
}
$m = current_manager();
$active = $active ?? '';
$page_title = $page_title ?? APP_NAME;

$menu = [
    'dashboard' => ['/admin/dashboard.php', '대시보드'],
    'consults'  => ['/admin/consults/', '상담관리'],
    'customers' => ['/admin/customers/', '고객관리'],
    'sites'     => ['/admin/sites/', '사이트관리'],
    'products'  => ['/admin/products/', '상품관리'],
    'users'     => ['/admin/users/', '담당자'],
    'stats'     => ['/admin/stats/', '통계'],
    'settings'  => ['/admin/settings/', '설정'],
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
<link rel="stylesheet" href="/assets/css/plustok-tokens-v2.css?v=20260723">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/plustok-premium-v2.css?v=20260723-topbar2">
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <button type="button" class="nav-toggle" aria-label="메뉴 열기" aria-expanded="false" aria-controls="admin-nav">
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
    </button>
    <div class="brand">
      <span class="brand-short">Smarttoktok</span>
      <span class="brand-full">PlusTok_crm</span>
    </div>
    <div class="me">
      <span class="user-name"><?= e($m['name'] ?? '') ?> (<?= e($m['role'] ?? '') ?>)</span>
      <div class="pc-links">
        <a href="/admin/account.php">내정보</a>
        <a href="/admin/logout.php">로그아웃</a>
      </div>
    </div>
  </div>
</div>
<nav class="nav" id="admin-nav" aria-hidden="true">
  <div class="nav-inner">
    <?php foreach ($menu as $key => [$url, $label]):
        // 관리 메뉴는 super/admin만 노출
        if (in_array($key, ['sites', 'products', 'users', 'settings'], true) && !can_manage()) continue; ?>
      <a href="<?= e($url) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="nav-footer">
    <div class="nav-footer-user"><?= e($m['name'] ?? '') ?></div>
    <a href="/admin/account.php">내정보</a>
    <a href="/admin/logout.php" class="nav-footer-logout">로그아웃</a>
  </div>
</nav>
<div class="nav-overlay" id="nav-overlay" hidden aria-hidden="true"></div>
<script>
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('admin-nav');
  var overlay = document.getElementById('nav-overlay');
  if (!toggle || !nav || !overlay) return;

  function closeNav() {
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', '메뉴 열기');
    nav.classList.remove('is-open');
    nav.setAttribute('aria-hidden', 'true');
    overlay.classList.remove('is-open');
    overlay.hidden = true;
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('nav-open');
  }

  function openNav() {
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', '메뉴 닫기');
    nav.classList.add('is-open');
    nav.setAttribute('aria-hidden', 'false');
    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.remove('is-open');
    requestAnimationFrame(function () { overlay.classList.add('is-open'); });
    document.body.classList.add('nav-open');
  }

  toggle.addEventListener('click', function () {
    if (toggle.getAttribute('aria-expanded') === 'true') closeNav();
    else openNav();
  });

  overlay.addEventListener('click', closeNav);

  nav.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', closeNav);
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) closeNav();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeNav();
  });
})();
</script>
<div class="wrap">
