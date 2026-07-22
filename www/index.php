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
<style>
  :root{
    --bg:#f5f7fa; --ink:#1f2933; --muted:#64748b; --line:#e2e8f0;
    --navy:#1a2733; --navy2:#22303c; --blue:#2b6cb0; --blue2:#4299e1; --card:#ffffff;
  }
  @media (prefers-color-scheme: dark){
    :root{ --bg:#0f1620; --ink:#e6edf3; --muted:#93a1b0; --line:#243244; --card:#161f2b; }
  }
  *{box-sizing:border-box}
  html,body{margin:0}
  body{font-family:system-ui,'Malgun Gothic','Apple SD Gothic Neo',sans-serif;background:var(--bg);color:var(--ink);line-height:1.6}
  a{color:inherit;text-decoration:none}

  /* 상단바 */
  .top{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;max-width:1080px;margin:0 auto}
  .logo{font-weight:800;font-size:18px;letter-spacing:-.3px}
  .logo small{font-weight:500;color:var(--muted);margin-left:8px;font-size:12px}
  .top .login{font-size:14px;background:var(--blue);color:#fff;padding:9px 16px;border-radius:999px}

  /* 히어로 */
  .hero{background:linear-gradient(160deg,var(--navy),var(--navy2));color:#fff;border-radius:22px;
        max-width:1080px;margin:8px auto 0;padding:56px 28px;text-align:center;position:relative;overflow:hidden}
  .hero:after{content:"";position:absolute;right:-80px;top:-80px;width:280px;height:280px;
        background:radial-gradient(circle,rgba(66,153,225,.35),transparent 70%)}
  .hero .eyebrow{color:#9ecbff;font-weight:600;font-size:13px;letter-spacing:1px}
  .hero h1{font-size:34px;margin:10px 0 8px;letter-spacing:-.8px;line-height:1.25}
  .hero p{color:#c7d2df;max-width:560px;margin:0 auto 26px;font-size:16px}
  .cta{display:inline-flex;align-items:center;gap:8px;background:var(--blue2);color:#fff;
        font-weight:700;font-size:17px;padding:15px 30px;border-radius:999px;box-shadow:0 8px 24px rgba(66,153,225,.35);transition:transform .12s}
  .cta:hover{transform:translateY(-2px)}
  .cta .arrow{font-size:20px}
  .hero .sub{display:block;margin-top:14px;color:#9fb0c2;font-size:13px}
  .hero .sub a{color:#9ecbff}

  /* 섹션 */
  .wrap{max-width:1080px;margin:0 auto;padding:0 20px}
  .section{margin:44px auto}
  .section h2{font-size:20px;margin:0 0 4px}
  .section .desc{color:var(--muted);font-size:14px;margin:0 0 18px}

  .feat{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  .feat .box{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
  .feat .box .ico{font-size:22px}
  .feat .box h3{font-size:16px;margin:8px 0 4px}
  .feat .box p{color:var(--muted);font-size:14px;margin:0}

  .brands{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
  .brand{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px}
  .brand .div{font-size:12px;color:var(--blue2);font-weight:700}
  .brand .name{font-size:16px;font-weight:700;margin:2px 0}
  .brand .dom{font-size:12px;color:var(--muted)}
  .brand .prod{font-size:12px;color:var(--muted);margin-top:6px;border-top:1px dashed var(--line);padding-top:6px}

  footer{max-width:1080px;margin:40px auto 30px;padding:0 20px;color:var(--muted);font-size:13px;
        display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;border-top:1px solid var(--line);padding-top:18px}
  footer a{color:var(--blue)}

  @media (max-width:760px){
    .hero h1{font-size:26px}
    .feat{grid-template-columns:1fr}
    .brands{grid-template-columns:1fr 1fr}
  }
</style>
</head>
<body>

  <div class="top">
    <div class="logo">SmartTokTok CRM <small>PlusTok 통합 CRM</small></div>
    <a class="login" href="/admin/">관리자 로그인</a>
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

  <footer>
    <span>© <?= date('Y') ?> SmartTokTok CRM · PlusTok 통합 CRM</span>
    <span><a href="/admin/">관리자</a> · <a href="/api/v1/health.php" target="_blank">API 상태</a></span>
  </footer>

</body>
</html>
