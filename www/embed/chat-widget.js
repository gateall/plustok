/*!
 * PlusTok 고객 채팅 위젯 — iframe 오버레이
 * PlusTokChat.open({ base, roomId, accessToken, wsUrl })
 */
(function () {
  'use strict';

  var overlay = null;
  var iframe = null;
  var msgHandler = null;

  var CSS = '' +
    '#pt-chat-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2147483000;' +
    'display:none;align-items:flex-end;justify-content:center;padding:16px}' +
    '#pt-chat-frame{width:100%;max-width:420px;height:min(560px,calc(100vh - 32px));border:0;' +
    'border-radius:16px;background:#fff;box-shadow:0 20px 50px rgba(0,0,0,.25)}' +
    '@media(min-width:480px){#pt-chat-overlay{align-items:center}}';

  function injectStyle() {
    if (document.getElementById('pt-chat-style')) return;
    var s = document.createElement('style');
    s.id = 'pt-chat-style';
    s.textContent = CSS;
    document.head.appendChild(s);
  }

  function ensureOverlay() {
    if (overlay) return;
    injectStyle();
    overlay = document.createElement('div');
    overlay.id = 'pt-chat-overlay';
    iframe = document.createElement('iframe');
    iframe.id = 'pt-chat-frame';
    iframe.title = 'PlusTok 채팅';
    iframe.setAttribute('allow', 'clipboard-write');
    overlay.appendChild(iframe);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) window.PlusTokChat.close();
    });
    document.body.appendChild(overlay);
  }

  window.PlusTokChat = {
    open: function (opts) {
      opts = opts || {};
      var base = (opts.base || '').replace(/\/$/, '');
      var roomId = opts.roomId;
      var accessToken = opts.accessToken;
      var wsUrl = opts.wsUrl || '';
      if (!base || !roomId || !accessToken) return;

      ensureOverlay();
      if (msgHandler) window.removeEventListener('message', msgHandler);

      msgHandler = function (ev) {
        var d = ev.data;
        if (!d || !d.type) return;
        if (d.type === 'plustok-chat-ready') {
          iframe.contentWindow.postMessage({
            type: 'plustok-chat-init',
            roomId: roomId,
            accessToken: accessToken,
            wsUrl: wsUrl,
            apiBase: base + '/api/v1'
          }, '*');
        }
        if (d.type === 'plustok-chat-close') {
          window.PlusTokChat.close();
        }
      };
      window.addEventListener('message', msgHandler);

      iframe.src = base + '/embed/chat-frame.php';
      overlay.style.display = 'flex';
    },
    close: function () {
      if (overlay) overlay.style.display = 'none';
      if (iframe) iframe.src = 'about:blank';
      if (msgHandler) {
        window.removeEventListener('message', msgHandler);
        msgHandler = null;
      }
    }
  };
})();
