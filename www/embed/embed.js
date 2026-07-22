/*!
 * PlusTok 통합 CRM — 상담폼 임베드 로더
 * 사용: <div id="plustok-form"></div>
 *       <script src="https://plustok.mycafe24.com/embed/embed.js?site=lg15441644" async></script>
 * SPEC.md A 참조. API Key는 서버 프록시(form.php)가 처리하므로 클라이언트에 노출되지 않는다.
 */
(function () {
  'use strict';

  // 이 스크립트의 src에서 base와 site 파라미터 추출
  var current = document.currentScript || (function () {
    var s = document.getElementsByTagName('script');
    return s[s.length - 1];
  })();
  var src = current ? current.src : '';
  var base = src.replace(/\/embed\/embed\.js.*$/, '');
  var siteCode = (src.match(/[?&]site=([^&]+)/) || [])[1] || '';
  var mount = document.getElementById('plustok-form');
  if (!mount) { return; }
  if (!siteCode) { mount.innerHTML = '상담폼 설정 오류: site 파라미터가 없습니다.'; return; }

  // 상품명 키워드 → 추가 입력 항목 (SPEC A-4)
  var EXTRA = [
    { m: /인터넷/, fields: [{ k: 'internet_speed', label: '인터넷 속도', opts: ['100M', '500M', '1G', '2.5G', '5G', '10G'] }] },
    { m: /070/, fields: [{ k: 'line_count', label: '회선수', type: 'number' }, { k: 'recording', label: '녹취서비스', opts: ['필요', '불필요'] }] },
    { m: /대표번호/, fields: [{ k: 'rep_number_type', label: '번호 유형', opts: ['1544', '1644', '1661', '1800', '기타'] }] },
    { m: /홈페이지|쇼핑몰|랜딩/, fields: [{ k: 'web_type', label: '유형', opts: ['기업형', '쇼핑몰', '랜딩페이지', '유지관리'] }] },
    { m: /판촉/, fields: [{ k: 'qty', label: '수량', type: 'number' }, { k: 'budget', label: '예산' }, { k: 'event_date', label: '행사일', type: 'date' }] },
    { m: /이사/, fields: [{ k: 'from_addr', label: '출발지' }, { k: 'to_addr', label: '도착지' }, { k: 'move_date', label: '이사날짜', type: 'date' }, { k: 'pyeong', label: '평수' }] }
  ];

  var CSS = '' +
    '#pt-wrap{max-width:480px;margin:0 auto;font-family:system-ui,"Malgun Gothic",sans-serif;color:#1f2933}' +
    '#pt-wrap *{box-sizing:border-box}' +
    '.pt-head{font-size:18px;font-weight:700;margin:0 0 4px}' +
    '.pt-steps{display:flex;gap:6px;margin:12px 0}' +
    '.pt-steps span{flex:1;height:5px;border-radius:3px;background:#e2e8f0}' +
    '.pt-steps span.on{background:#2b6cb0}' +
    '.pt-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px}' +
    '.pt-label{display:block;margin:12px 0 4px;font-size:14px;color:#4a5568}' +
    '.pt-req{color:#e53e3e}' +
    '#pt-wrap input,#pt-wrap select,#pt-wrap textarea{width:100%;padding:12px;font-size:16px;border:1px solid #cbd5e0;border-radius:8px}' +
    '.pt-opts{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}' +
    '.pt-opt{flex:1 0 30%;padding:12px;border:1px solid #cbd5e0;border-radius:8px;text-align:center;cursor:pointer;background:#fff;font-size:15px}' +
    '.pt-opt.sel{border-color:#2b6cb0;background:#ebf4ff;color:#2b6cb0;font-weight:600}' +
    '.pt-btn{margin-top:16px;width:100%;padding:14px;font-size:16px;border:0;border-radius:8px;background:#2b6cb0;color:#fff;cursor:pointer}' +
    '.pt-btn.sub{background:#a0aec0;margin-top:8px}' +
    '.pt-err{color:#e53e3e;font-size:14px;margin-top:8px}' +
    '.pt-done{text-align:center;padding:20px}' +
    '.pt-no{font-size:22px;font-weight:700;color:#2b6cb0;margin:10px 0}' +
    '.pt-agree-wrap{margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;padding:12px;background:#f8fafc}' +
    '.pt-agree{display:flex;align-items:flex-start;gap:8px;font-size:14px;color:#1f2933;cursor:pointer}' +
    '#pt-wrap .pt-agree input[type="checkbox"]{width:18px;height:18px;flex:0 0 auto;margin-top:1px}' +
    '.pt-privacy{margin-top:8px}' +
    '.pt-privacy summary{cursor:pointer;font-size:13px;color:#2b6cb0}' +
    '.pt-privacy-body{margin-top:8px;font-size:12px;color:#4a5568;line-height:1.6}' +
    '.pt-privacy-body table{width:100%;border-collapse:collapse;margin-bottom:6px}' +
    '.pt-privacy-body th,.pt-privacy-body td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;vertical-align:top}' +
    '.pt-privacy-body th{background:#f1f5f9;white-space:nowrap;width:90px}' +
    '.pt-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}' +
    '.pt-sel{display:flex;justify-content:space-between;align-items:center;background:#ebf4ff;color:#2b6cb0;border-radius:8px;padding:10px 12px;font-size:14px;margin-bottom:4px}' +
    '.pt-change{font-size:13px;color:#2b6cb0;text-decoration:underline;cursor:pointer}' +
    '.pt-row-addr{display:flex;gap:8px}' +
    '.pt-row-addr input{flex:1}' +
    '#pt-wrap .pt-addr-btn{width:auto;flex:0 0 auto;white-space:nowrap;background:#4a5568;color:#fff;border:0;border-radius:8px;padding:0 16px;font-size:14px;cursor:pointer}';

  // 개인정보 수집·이용 동의 내용
  var PRIVACY =
    '<table>' +
    '<tr><th>수집 항목</th><td>이름, 휴대폰번호, 이메일, 회사명, 지역, 상담 내용</td></tr>' +
    '<tr><th>수집·이용 목적</th><td>상담 신청 접수 및 상담 답변, 서비스 안내</td></tr>' +
    '<tr><th>보유·이용 기간</th><td>상담 완료 후 3년 보관 후 파기 (관련 법령에 따름)</td></tr>' +
    '</table>' +
    '<p>귀하는 개인정보 수집·이용 동의를 거부할 권리가 있으며, 동의를 거부하실 경우 상담 신청이 제한됩니다.</p>';

  var cfg = null, state = { step: 1, product: '', extra: {} };

  function el(html) { var d = document.createElement('div'); d.innerHTML = html; return d.firstChild; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function extraFor(product) {
    for (var i = 0; i < EXTRA.length; i++) { if (EXTRA[i].m.test(product)) return EXTRA[i].fields; }
    return [];
  }

  // 카카오(다음) 우편번호 서비스 로드 후 주소 검색 팝업 실행
  function loadPostcode(cb) {
    if (window.daum && window.daum.Postcode) { cb(); return; }
    var s = document.getElementById('pt-daum-postcode');
    if (s) { s.addEventListener('load', cb); return; }
    s = document.createElement('script');
    s.id = 'pt-daum-postcode';
    s.src = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
    s.onload = cb;
    s.onerror = function () { err('주소 검색을 불러오지 못했습니다. 상세주소를 직접 입력해주세요.'); };
    document.head.appendChild(s);
  }

  function openPostcode() {
    loadPostcode(function () {
      new daum.Postcode({
        oncomplete: function (data) {
          state.zipcode = data.zonecode || '';
          state.address = data.roadAddress || data.jibunAddress || '';
          var z = document.getElementById('pt-zip'); if (z) z.value = state.zipcode;
          var a = document.getElementById('pt-addr'); if (a) a.value = state.address;
          var d = document.getElementById('pt-addr-detail'); if (d) d.focus();
        }
      }).open();
    });
  }

  function render() {
    var s = state.step;
    var stepsBar = '<div class="pt-steps">' +
      [1, 2, 3].map(function (n) { return '<span class="' + (n <= s ? 'on' : '') + '"></span>'; }).join('') + '</div>';
    var html = '<div id="pt-wrap"><p class="pt-head">' + esc(cfg.persona) + '</p>' + stepsBar + '<div class="pt-card">';

    if (s === 1) {
      html += '<div class="pt-label">신청 상품을 선택하세요 <span class="pt-req">*</span></div><div class="pt-opts">';
      cfg.products.forEach(function (p) {
        html += '<div class="pt-opt' + (state.product === p.name ? ' sel' : '') + '" data-p="' + esc(p.name) + '">' + esc(p.name) + '</div>';
      });
      html += '</div><button class="pt-btn" data-next="2">다음</button>';
    } else if (s === 2) {
      html += '<div class="pt-sel"><span>신청 상품: <b>' + esc(state.product || '-') + '</b></span><span class="pt-change" data-next="1">변경</span></div>' +
        '<div class="pt-row2">' +
          '<div><label class="pt-label">회사명</label><input id="pt-company" value="' + esc(state.company || '') + '"></div>' +
          '<div><label class="pt-label">이름 <span class="pt-req">*</span></label><input id="pt-name" value="' + esc(state.name || '') + '"></div>' +
        '</div>' +
        '<div class="pt-row2">' +
          '<div><label class="pt-label">휴대폰/연락처 <span class="pt-req">*</span></label><input id="pt-phone" type="tel" inputmode="numeric" value="' + esc(state.phone || '') + '"></div>' +
          '<div><label class="pt-label">이메일</label><input id="pt-email" type="email" inputmode="email" value="' + esc(state.email || '') + '"></div>' +
        '</div>' +
        '<label class="pt-label">주소 (법정동/도로명)</label>' +
        '<div class="pt-row-addr">' +
          '<input id="pt-zip" placeholder="우편번호" value="' + esc(state.zipcode || '') + '" readonly>' +
          '<button type="button" class="pt-addr-btn" id="pt-addr-search">주소 검색</button>' +
        '</div>' +
        '<input id="pt-addr" placeholder="검색한 주소" value="' + esc(state.address || '') + '" readonly style="margin-top:8px">' +
        '<input id="pt-addr-detail" placeholder="상세주소 직접 입력" value="' + esc(state.addressDetail || '') + '" style="margin-top:8px">';
      extraFor(state.product).forEach(function (f) {
        html += '<label class="pt-label">' + esc(f.label) + '</label>';
        if (f.opts) {
          html += '<select id="pt-x-' + f.k + '"><option value="">선택</option>' +
            f.opts.map(function (o) { return '<option' + (state.extra[f.k] === o ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select>';
        } else {
          html += '<input id="pt-x-' + f.k + '" type="' + (f.type || 'text') + '" value="' + esc(state.extra[f.k] || '') + '">';
        }
      });
      html += '<button class="pt-btn" data-next="3">다음</button><button class="pt-btn sub" data-next="1">이전</button>';
    } else if (s === 3) {
      html += '<label class="pt-label">추가 요청사항</label><textarea id="pt-memo" rows="4">' + esc(state.memo || '') + '</textarea>' +
        '<div class="pt-agree-wrap">' +
          '<label class="pt-agree"><input type="checkbox" id="pt-agree"' + (state.agree ? ' checked' : '') + '>' +
            '<span>개인정보 수집·이용에 동의합니다 <span class="pt-req">*</span></span></label>' +
          '<details class="pt-privacy"><summary>수집·이용 동의 내용 보기</summary>' +
            '<div class="pt-privacy-body">' + PRIVACY + '</div></details>' +
        '</div>' +
        '<button class="pt-btn" data-submit="1">상담 신청하기</button><button class="pt-btn sub" data-next="2">이전</button>';
    }
    html += '<div class="pt-err" id="pt-err"></div></div></div>';

    mount.innerHTML = '';
    var style = document.getElementById('pt-style');
    if (!style) { style = el('<style id="pt-style">' + CSS + '</style>'); document.head.appendChild(style); }
    mount.appendChild(el(html));
    bind();
  }

  function saveStep2() {
    state.name = val('pt-name'); state.phone = val('pt-phone');
    state.email = val('pt-email'); state.company = val('pt-company');
    state.zipcode = val('pt-zip'); state.address = val('pt-addr'); state.addressDetail = val('pt-addr-detail');
    extraFor(state.product).forEach(function (f) { state.extra[f.k] = val('pt-x-' + f.k); });
  }
  function val(id) { var n = document.getElementById(id); return n ? n.value.trim() : ''; }

  function bind() {
    mount.querySelectorAll('.pt-opt').forEach(function (o) {
      o.onclick = function () { state.product = o.getAttribute('data-p'); render(); };
    });
    mount.querySelectorAll('[data-next]').forEach(function (b) {
      b.onclick = function () {
        var to = parseInt(b.getAttribute('data-next'), 10);
        if (state.step === 2 && to === 3) saveStep2();
        if (state.step === 2 && to === 1) saveStep2();
        if (state.step === 1 && to === 2 && !state.product) { err('상품을 선택하세요.'); return; }
        state.step = to; render();
      };
    });
    var sub = mount.querySelector('[data-submit]');
    if (sub) { sub.onclick = submit; }
    var addrBtn = mount.querySelector('#pt-addr-search');
    if (addrBtn) { addrBtn.onclick = openPostcode; }
  }

  function err(m) { var n = document.getElementById('pt-err'); if (n) n.textContent = m || ''; }

  function submit() {
    state.memo = val('pt-memo');
    state.agree = document.getElementById('pt-agree').checked;
    if (!state.name) { err('이름을 입력하세요.'); return; }
    if (state.phone.replace(/\D/g, '').length < 9) { err('휴대폰 번호를 확인하세요.'); return; }
    if (state.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email)) { err('이메일 형식을 확인하세요.'); return; }
    if (!state.agree) { err('개인정보 수집에 동의해야 합니다.'); return; }
    err('');

    var fullAddr = [state.address, state.addressDetail].filter(function (v) { return v; }).join(' ');
    var payload = {
      site_code: cfg.site_code, product: state.product, category: state.product,
      customer_name: state.name, phone: state.phone, email: state.email, company: state.company,
      zipcode: state.zipcode, address: fullAddr, memo: state.memo, detail: state.extra, agree: true
    };
    var btn = mount.querySelector('[data-submit]'); if (btn) { btn.disabled = true; btn.textContent = '접수 중…'; }

    fetch(base + '/embed/form.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res && res.result === 'success') { done(res.data.consult_no); }
      else { err((res && res.message) || '접수에 실패했습니다.'); if (btn) { btn.disabled = false; btn.textContent = '상담 신청하기'; } }
    }).catch(function () {
      err('네트워크 오류가 발생했습니다.'); if (btn) { btn.disabled = false; btn.textContent = '상담 신청하기'; }
    });
  }

  function done(no) {
    mount.innerHTML = '<div id="pt-wrap"><div class="pt-card pt-done">' +
      '<p style="font-size:16px">접수가 완료되었습니다.</p>' +
      '<div class="pt-no">' + esc(no) + '</div>' +
      '<p style="color:#718096">담당자가 연락드립니다.</p></div></div>';
    var style = document.getElementById('pt-style');
    if (!style) { document.head.appendChild(el('<style id="pt-style">' + CSS + '</style>')); }
  }

  // 설정 로드 후 렌더
  fetch(base + '/embed/form.php?site=' + encodeURIComponent(siteCode) + '&action=config')
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res || res.result !== 'success') { mount.innerHTML = '상담폼을 불러오지 못했습니다.'; return; }
      cfg = res.data; render();
    })
    .catch(function () { mount.innerHTML = '상담폼을 불러오지 못했습니다.'; });
})();
