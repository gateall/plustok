<?php
declare(strict_types=1);
/**
 * 임베드 상담폼 테스트 페이지. 서버에서 https://plustok.mycafe24.com/embed/demo.php?site=lg15441644 로 확인.
 * 실 사이트 연동 전 폼 동작을 점검하는 용도. 운영에서는 삭제하거나 접근 제한 권장.
 */
$site = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['site'] ?? 'lg15441644'));
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>상담폼 데모 · <?= htmlspecialchars($site, ENT_QUOTES) ?></title>
<style>body{background:#eef2f7;margin:0;padding:30px 16px;font-family:system-ui,sans-serif}</style>
</head>
<body>
  <div id="plustok-form"></div>
  <script src="/embed/embed.js?site=<?= htmlspecialchars($site, ENT_QUOTES) ?>" async></script>
</body>
</html>
