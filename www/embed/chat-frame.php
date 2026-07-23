<?php
declare(strict_types=1);
/**
 * 고객 채팅 iframe UI — plustok 동일 오리진에서 REST/WS 사용.
 * 부모(chat-widget.js)가 postMessage로 roomId·accessToken·wsUrl 전달.
 */
require_once __DIR__ . '/../config/app.php';
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PlusTok 채팅</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,"Malgun Gothic",sans-serif;background:#f8fafc;height:100vh;display:flex;flex-direction:column;color:#1f2933}
.ptc-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#2b6cb0;color:#fff;font-weight:600}
.ptc-close{background:transparent;border:0;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:0 4px}
.ptc-status{font-size:12px;font-weight:400;opacity:.85}
.ptc-msgs{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px}
.ptc-msg{max-width:82%;padding:10px 14px;border-radius:12px;font-size:15px;line-height:1.45;word-break:break-word}
.ptc-msg.me{align-self:flex-end;background:#2b6cb0;color:#fff;border-bottom-right-radius:4px}
.ptc-msg.them{align-self:flex-start;background:#fff;border:1px solid #e2e8f0;border-bottom-left-radius:4px}
.ptc-meta{font-size:11px;opacity:.7;margin-top:4px}
.ptc-foot{display:flex;gap:8px;padding:12px;background:#fff;border-top:1px solid #e2e8f0}
.ptc-foot input{flex:1;padding:12px;border:1px solid #cbd5e0;border-radius:8px;font-size:15px}
.ptc-foot button{padding:0 18px;border:0;border-radius:8px;background:#2b6cb0;color:#fff;font-size:15px;cursor:pointer}
.ptc-foot button:disabled{background:#a0aec0;cursor:not-allowed}
.ptc-empty{text-align:center;color:#718096;font-size:14px;margin:auto}
.ptc-err{padding:8px 16px;background:#fed7d7;color:#c53030;font-size:13px;display:none}
</style>
</head>
<body>
<div class="ptc-head">
  <div>
    <div>상담원과 채팅</div>
    <div class="ptc-status" id="ptc-status">연결 중…</div>
  </div>
  <button type="button" class="ptc-close" id="ptc-close" title="닫기">&times;</button>
</div>
<div class="ptc-err" id="ptc-err"></div>
<div class="ptc-msgs" id="ptc-msgs"><div class="ptc-empty">메시지를 불러오는 중…</div></div>
<form class="ptc-foot" id="ptc-form">
  <input type="text" id="ptc-input" placeholder="메시지를 입력하세요" autocomplete="off" maxlength="2000">
  <button type="submit" id="ptc-send">전송</button>
</form>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script>
(function () {
  'use strict';

  var cfg = null;
  var socket = null;
  var msgsEl = document.getElementById('ptc-msgs');
  var statusEl = document.getElementById('ptc-status');
  var errEl = document.getElementById('ptc-err');
  var inputEl = document.getElementById('ptc-input');
  var sendBtn = document.getElementById('ptc-send');
  var formEl = document.getElementById('ptc-form');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function showErr(m) {
    if (!m) { errEl.style.display = 'none'; errEl.textContent = ''; return; }
    errEl.style.display = 'block';
    errEl.textContent = m;
  }

  function setStatus(t) { statusEl.textContent = t; }

  /** chat_messages.content — API/WS standard field */
  function msgContent(m) {
    if (!m) return '';
    if (m.content != null && String(m.content) !== '') return String(m.content);
    if (m.message != null && String(m.message) !== '') return String(m.message);
    return '';
  }

  function renderMessages(list) {
    if (!list.length) {
      msgsEl.innerHTML = '<div class="ptc-empty">메시지가 없습니다. 문의 내용을 입력해주세요.</div>';
      return;
    }
    msgsEl.innerHTML = list.map(function (m) {
      var mine = m.senderType === 'customer';
      return '<div class="ptc-msg ' + (mine ? 'me' : 'them') + '">' +
        esc(msgContent(m)) +
        '<div class="ptc-meta">' + (mine ? '나' : '상담원') + '</div></div>';
    }).join('');
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  function appendMessage(m) {
    var empty = msgsEl.querySelector('.ptc-empty');
    if (empty) msgsEl.innerHTML = '';
    var mine = m.senderType === 'customer';
    var div = document.createElement('div');
    div.className = 'ptc-msg ' + (mine ? 'me' : 'them');
    div.innerHTML = esc(msgContent(m)) + '<div class="ptc-meta">' + (mine ? '나' : '상담원') + '</div>';
    msgsEl.appendChild(div);
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  function apiHeaders() {
    return { 'Authorization': 'Bearer ' + cfg.accessToken, 'Content-Type': 'application/json' };
  }

  function loadHistory() {
    return fetch(cfg.apiBase + '/chats/' + encodeURIComponent(cfg.roomId) + '/messages?limit=50', {
      headers: apiHeaders()
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res || !res.success) throw new Error((res && res.error && res.error.message) || '메시지 로드 실패');
      renderMessages(res.data.messages || []);
    });
  }

  function connectSocket() {
    if (!cfg.wsUrl) {
      setStatus('실시간 연결 불가 (REST만 사용)');
      return;
    }
    socket = io(cfg.wsUrl, {
      path: '/socket.io',
      auth: { token: cfg.accessToken },
      reconnection: true,
      transports: ['websocket', 'polling']
    });
    socket.on('connect', function () {
      setStatus('연결됨');
      socket.emit('room:join', { roomId: cfg.roomId });
    });
    socket.on('disconnect', function () { setStatus('연결 끊김'); });
    socket.on('connect_error', function (e) {
      setStatus('연결 오류');
      showErr(e.message || '채팅 서버 연결 실패');
    });
    socket.on('message:receive', function (msg) {
      if (msg.roomId !== cfg.roomId) return;
      appendMessage({ senderType: msg.senderType, content: msgContent(msg) });
    });
    socket.on('error', function (p) { showErr(p.message || '오류'); });
  }

  function sendMessage(text) {
    var tempId = 't' + Date.now();
    appendMessage({ senderType: 'customer', content: text });
    if (socket && socket.connected) {
      socket.emit('message:send', { roomId: cfg.roomId, content: text, tempId: tempId });
      return Promise.resolve();
    }
    return fetch(cfg.apiBase + '/chats/' + encodeURIComponent(cfg.roomId) + '/messages', {
      method: 'POST',
      headers: apiHeaders(),
      body: JSON.stringify({ content: text, source: 'manual' })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res || !res.success) throw new Error((res && res.error && res.error.message) || '전송 실패');
    });
  }

  function init(c) {
    cfg = c;
    showErr('');
    sendBtn.disabled = false;
    loadHistory().catch(function (e) { showErr(e.message); }).finally(function () {
      connectSocket();
    });
  }

  formEl.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = inputEl.value.trim();
    if (!text || !cfg) return;
    inputEl.value = '';
    sendBtn.disabled = true;
    sendMessage(text).catch(function (err) {
      showErr(err.message || '전송 실패');
    }).finally(function () { sendBtn.disabled = false; inputEl.focus(); });
  });

  document.getElementById('ptc-close').onclick = function () {
    if (socket) socket.disconnect();
    window.parent.postMessage({ type: 'plustok-chat-close' }, '*');
  };

  window.addEventListener('message', function (ev) {
    var d = ev.data;
    if (!d || d.type !== 'plustok-chat-init') return;
    init(d);
  });

  window.parent.postMessage({ type: 'plustok-chat-ready' }, '*');
})();
</script>
</body>
</html>
