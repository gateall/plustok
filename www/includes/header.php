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
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <div class="brand"><?= e(APP_BRAND) ?><small><?= e(APP_NAME) ?></small></div>
    <div class="me">
      <?= e($m['name'] ?? '') ?> (<?= e($m['role'] ?? '') ?>)
      <a href="/admin/account.php">내정보</a>
      <a href="/admin/logout.php">로그아웃</a>
    </div>
  </div>
</div>
<nav class="nav">
  <div class="nav-inner">
    <?php foreach ($menu as $key => [$url, $label]):
        // 관리 메뉴는 super/admin만 노출
        if (in_array($key, ['sites', 'products', 'users', 'settings'], true) && !can_manage()) continue; ?>
      <a href="<?= e($url) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<div class="wrap">
