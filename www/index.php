<?php
declare(strict_types=1);
/**
 * PlusTok 통합 CRM — 루트 랜딩 페이지.
 * 클릭 시 관리자(/admin/)로 이동. 그누보드 원본 첫 페이지는 index.gnuboard.php 로 백업됨.
 */
$brands = [
    ['통신사업', 'SmartTokTok', 'smarttoktok.com', '대표번호·070·기업인터넷'],
    ['통신가입', 'LG15441644', 'lg15441644.kr', '인터넷·070·IPTV·CCTV·결합'],
    ['웹제작', 'HompyShop', 'hompyshop.com', '홈페이지·쇼핑몰·SEO'],
    ['AI 플랫폼', 'ShowForm', 'showform.kr', 'AI 랜딩페이지·설문'],
    ['광고플랫폼', 'CallMap', 'callmap.kr', '플레이스·지도상위·지역광고'],
    ['판촉사업', 'HongPansa', 'hongpansa.kr', '판촉물·체험단·상위노출'],
    ['중개서비스', 'Oncap24', 'oncap24.com', '이사·공사·역경매'],
    ['플랫폼 사업', 'nuguupso', 'nuguupso.com', '역경매(인테리어·청소·설비)'],
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SmartTokTok CRM · PlusTok 통합 CRM</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
<link rel="stylesheet" href="/assets/css/plustok-tokens-v2.css?v=20260723">
<link rel="stylesheet" href="/assets/css/landing-premium-v2.css?v=20260723">
</head>
<body>

  <div class="landing-top" style="display: flex; flex-wrap: nowrap; align-items: center; justify-content: space-between;">
    <div class="landing-logo" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: 10px;">SmartTokTok CRM</div>
    <a class="login" href="/admin/" style="white-space: nowrap; flex-shrink: 0;">관리자 로그인</a>
  </div>

  <header class="hero">
    <div class="eyebrow">통합 상담관리 플랫폼</div>
    <h1>여러 사이트의 상담을<br>한 곳에서 관리합니다</h1>
    <p>각 사이트는 상담을 접수만 하고, 모든 데이터는 하나의 CRM으로 모입니다.
       고객·상담·사이트·통계를 통합 운영하세요.</p>
    <a class="cta" href="/admin/">관리자 화면으로 <span class="arrow">→</span></a>
    <span class="sub">서버 상태 확인: <a href="/api/v1/health.php" target="_blank">/api/v1/health.php</a></span>
  </header>

  <div class="wrap">
    <section class="section">
      <h2>핵심 기능</h2>
      <p class="desc">V1.0 — 상담 접수 · 통합 CRM · 데이터베이스</p>
      <div class="feat">
        <div class="box"><div class="ico">📥</div><h3>통합 상담 접수</h3><p>모든 사이트의 상담을 하나의 API로 수집하고 자동 분류합니다.</p></div>
        <div class="box"><div class="ico">🗂️</div><h3>실시간 CRM 관리</h3><p>고객·상담·상태·담당자를 한 화면에서 처리하고 이력을 남깁니다.</p></div>
        <div class="box"><div class="ico">🌐</div><h3>사이트 무한 확장</h3><p>새 사이트는 등록·API Key 발급만 하면 즉시 연동됩니다.</p></div>
      </div>
    </section>

    <section class="section">
      <h2>연동 브랜드</h2>
      <p class="desc">사업부 → 브랜드 → 사이트(도메인) 구조로 통합 운영</p>
      <div class="brands">
        <?php foreach ($brands as $b): ?>
          <div class="brand">
            <div class="div"><?= htmlspecialchars($b[0], ENT_QUOTES) ?></div>
            <div class="name"><?= htmlspecialchars($b[1], ENT_QUOTES) ?></div>
            <div class="dom"><?= htmlspecialchars($b[2], ENT_QUOTES) ?></div>
            <div class="prod"><?= htmlspecialchars($b[3], ENT_QUOTES) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <footer class="landing-footer">
    <span>© <?= date('Y') ?> SmartTokTok CRM · PlusTok 통합 CRM</span>
    <span><a href="/admin/">관리자</a> · <a href="/api/v1/health.php" target="_blank">API 상태</a></span>
  </footer>

</body>
</html>
