# 사이트 임베드 설치 가이드 (사이트 확대)

각 사이트에 상담폼을 붙이는 방법. 백엔드(PlusTok CRM)는 8개 사이트 모두 등록·상품 세팅·CORS 허용이
완료되어 있으므로, **각 사이트에 스니펫만 넣으면** 즉시 접수가 PlusTok CRM으로 모입니다.

- 검증: 2026-07-17 기준 7개 사이트(smarttoktok·hompyshop·showform·callmap·hongpansa·oncap24·nuguupso)
  config 정상 확인. lg15441644는 실접수까지 완료.
- 원리: `site_code`만 다르면 브랜드·페르소나·상품·질문셋이 자동으로 바뀝니다. 폼 코드는 하나(`embed.js`).

---

## 1. 붙일 위치 2가지 방식

### 방식 A — 그누보드 사이트 (content/plustok.php)
LG처럼 그누보드5면, 각 사이트의 `content/` 폴더에 `plustok.php`를 올리고
`도메인/content/plustok.php`로 접근. (아래 [3. 그누보드 템플릿] 복사 → `site_code`만 교체)

### 방식 B — 아무 웹페이지 (스크립트 한 줄)
그누보드가 아니거나 특정 페이지에 바로 넣고 싶으면, 원하는 위치에 아래 두 줄만 삽입:

```html
<div id="plustok-form"></div>
<script src="https://plustok.mycafe24.com/embed/embed.js?site=SITECODE" async></script>
```

`SITECODE`를 해당 사이트 코드로 바꾸면 됩니다.

---

## 2. 사이트별 스니펫 (복사용)

| 브랜드 | 도메인 | site_code | 스니펫 |
|---|---|---|---|
| SmartTokTok | smarttoktok.com | `smarttoktok` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=smarttoktok" async></script>` |
| HompyShop | hompyshop.com | `hompyshop` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=hompyshop" async></script>` |
| ShowForm | showform.kr | `showform` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=showform" async></script>` |
| CallMap | callmap.kr | `callmap` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=callmap" async></script>` |
| HongPansa | hongpansa.kr | `hongpansa` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=hongpansa" async></script>` |
| Oncap24 | oncap24.com | `oncap24` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=oncap24" async></script>` |
| nuguupso | nuguupso.com | `nuguupso` | `<script src="https://plustok.mycafe24.com/embed/embed.js?site=nuguupso" async></script>` |
| LG15441644 | lg15441644.kr | `lg15441644` | (설치 완료) |

각 스니펫 앞에는 `<div id="plustok-form"></div>` 가 있어야 합니다(폼이 그려질 자리).

---

## 3. 그누보드 템플릿 (content/plustok.php)

각 그누보드 사이트의 `content/plustok.php` 로 저장하고, 아래 **★ 부분의 site만** 교체.

```php
<?php
// PlusTok 통합 CRM 임베드 상담폼
if (!defined('_GNUBOARD_')) {
    include_once('../common.php');
}
$g5['title'] = "온라인 상담신청";
include_once(G5_PATH.'/head.php');
?>
<div class="lg-content-container" style="margin-top:40px;margin-bottom:60px;">
  <div style="max-width:520px;margin:0 auto;padding:0 16px;">
    <h2 style="text-align:center;font-weight:800;margin-bottom:6px;">온라인 상담신청</h2>
    <p style="text-align:center;color:#64748b;margin:0 0 24px;">필요한 상품을 선택하고 정보를 남겨주시면 담당자가 연락드립니다.</p>

    <div id="plustok-form"></div>
    <!-- ★ site= 를 해당 사이트 코드로 교체 (예: smarttoktok, hompyshop ...) -->
    <script src="https://plustok.mycafe24.com/embed/embed.js?site=smarttoktok" async></script>
  </div>
</div>
<?php
include_once(G5_THEME_PATH.'/tail.php');
?>
```

> 테마에 따라 `G5_THEME_PATH.'/tail.php'` 대신 `G5_PATH.'/tail.php'` 를 쓰는 사이트도 있습니다.
> 오류 나면 해당 사이트의 다른 content 페이지(예: online.php) 마지막 줄을 참고해 맞추세요.

---

## 4. 설치 후 확인 (사이트마다)

1. 페이지 열어 폼이 뜨는지 (상단에 그 브랜드 페르소나 + 상품 버튼)
2. 상품 선택 → 정보 입력 → 동의 → 신청 → **접수번호** 뜨는지
3. PlusTok 관리자(`plustok.mycafe24.com/admin/consults/`)에 해당 사이트로 접수가 뜨는지

---

## 5. 문제 해결

- **폼이 안 뜸** → F12 콘솔 확인. 스크립트 로드 실패거나 `#plustok-form` div 누락.
- **제출 시 네트워크 오류(CORS)** → 그 사이트의 실제 도메인이 CRM 등록 도메인과 다른 경우.
  PlusTok 관리자 → **사이트관리 → 수정**에서 그 사이트의 `도메인`을 실제 서비스 도메인으로 맞추세요.
  (CORS는 등록 도메인의 http/https·www/non-www 4종을 자동 허용)
- **상품을 바꾸고 싶음** → 관리자 → **상품관리**에서 해당 브랜드 상품 추가/사용토글. 폼에 자동 반영.
- **첫인사 문구 변경** → 관리자 → 사이트관리 → 수정 → `persona`.

---

## 6. 권장 순서

리스크를 줄이려면 한 번에 하나씩:
1. **smarttoktok** 먼저(대표번호 핵심) → 접수 확인
2. **hompyshop** → 확인
3. 나머지(showform·callmap·hongpansa·oncap24) 순차
4. nuguupso는 사이트 오픈(준비 중) 후

각 사이트는 개인정보(이름·전화)를 받으므로, 가능하면 https 페이지에 설치하세요.
