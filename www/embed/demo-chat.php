<?php
declare(strict_types=1);
/**
 * 상담폼 + 채팅 위젯 데모.
 * 접수 완료 후 "상담원과 바로 채팅하기" 버튼 → chat-widget.js iframe 채팅.
 */
$site = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['site'] ?? 'lg15441644'));
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>상담폼·채팅 데모 · <?= htmlspecialchars($site, ENT_QUOTES) ?></title>
<style>
body{background:#eef2f7;margin:0;padding:30px 16px;font-family:system-ui,sans-serif}
.note{max-width:480px;margin:0 auto 20px;padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;color:#4a5568;line-height:1.6}
.note strong{color:#2b6cb0}
</style>
</head>
<body>
  <div class="note">
    <strong>데모 흐름</strong><br>
    1. 아래 상담폼 작성 후 제출<br>
    2. 접수 완료 화면에서 <em>상담원과 바로 채팅하기</em> 클릭<br>
    3. plustok 도메인 iframe 채팅창에서 Socket.io + REST로 실시간 상담<br>
    <small>chat_rooms·ACEP customers·Render chat-server 설정 필요</small>
  </div>
  <div id="plustok-form"></div>
  <script src="/embed/embed.js?site=<?= htmlspecialchars($site, ENT_QUOTES) ?>" async></script>
</body>
</html>
