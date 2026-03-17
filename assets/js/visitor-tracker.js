// Lightweight visitor analytics tracker
(function () {
  function uuidv4() {
    // RFC4122 v4-ish
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (crypto.getRandomValues(new Uint8Array(1))[0] & 15);
      var v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function getCookie(name) {
    try {
      var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : '';
    } catch (e) {
      return '';
    }
  }

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + (days || 365) * 24 * 60 * 60 * 1000);
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + '; Expires=' + d.toUTCString() + '; Path=/; SameSite=Lax' + secure;
  }

  function getGuestId() {
    var gid = '';
    try { gid = localStorage.getItem('guest_id') || ''; } catch (e) {}
    if (!gid) gid = getCookie('guest_id');
    if (!gid) {
      gid = 'g_' + Math.random().toString(16).slice(2) + Date.now().toString(16);
    }
    try { localStorage.setItem('guest_id', gid); } catch (e) {}
    setCookie('guest_id', gid, 365);
    return gid;
  }

  function getSessionId() {
    var sid = '';
    try { sid = sessionStorage.getItem('vt_session_id') || ''; } catch (e) {}
    if (!sid) {
      sid = uuidv4();
      try { sessionStorage.setItem('vt_session_id', sid); } catch (e) {}
    }
    return sid;
  }

  var guestId = getGuestId();
  var sessionId = getSessionId();
  var start = Date.now();

  var endpoint = (window.TRACK_EVENT_URL || (window.TRACK_VISIT_URL ? window.TRACK_VISIT_URL.replace('track-visit', 'track-event') : '') || '/track-event');

  function send(event_type, payload, useBeacon) {
    payload = payload || {};
    payload.guest_id = guestId;
    payload.session_id = sessionId;
    payload.event_type = event_type;
    payload.page_url = location.href;
    payload.page_path = location.pathname + (location.search || '');
    payload.referrer = document.referrer || '';

    var body = JSON.stringify(payload);
    if (useBeacon !== false && navigator.sendBeacon) {
      try {
        var blob = new Blob([body], { type: 'application/json' });
        navigator.sendBeacon(endpoint, blob);
        return;
      } catch (e) {}
    }
    if (window.fetch) {
      try {
        fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: body, keepalive: true });
      } catch (e) {}
    }
  }

  // Page view
  send('page_view', {}, true);

  // Product view / Category view via data attributes
  var root = document.documentElement;
  var pid = root.getAttribute('data-product-id');
  if (pid) send('product_view', { product_id: parseInt(pid, 10) || null }, true);
  var cid = root.getAttribute('data-category-id');
  if (cid) send('category_view', { category_id: parseInt(cid, 10) || null }, true);

  // Search query
  try {
    var url = new URL(location.href);
    var q = url.searchParams.get('q');
    if (q) send('search', { search_term: q.slice(0, 255) }, true);
  } catch (e) {}

  // Button click tracking: add data-track="button_name" on important elements
  document.addEventListener('click', function (e) {
    var el = e.target;
    if (!el) return;
    var btn = el.closest ? el.closest('[data-track]') : null;
    if (!btn) return;
    var name = btn.getAttribute('data-track') || '';
    if (!name) return;
    send('button_click', { button_name: name }, false);
  }, { passive: true });

  // Forms: add-to-cart, remove-from-cart, checkout started
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.querySelector) return;

    if (form.querySelector('[name="add_to_cart"]') || form.querySelector('button[name="add_to_cart"]')) {
      var pidInp = form.querySelector('input[name="product_id"]');
      var qtyInp = form.querySelector('input[name="quantity"]');
      send('add_to_cart', { product_id: pidInp ? parseInt(pidInp.value, 10) : null, metadata: { quantity: qtyInp ? parseInt(qtyInp.value, 10) : 1 } }, true);
      return;
    }
    if (form.querySelector('button[name="remove_item"]') || form.querySelector('[name="remove_item"]')) {
      var pidInp2 = form.querySelector('input[name="product_id"]');
      send('remove_from_cart', { product_id: pidInp2 ? parseInt(pidInp2.value, 10) : null }, true);
      return;
    }
    if (form.classList && form.classList.contains('checkout-form')) {
      send('checkout_started', {}, true);
      return;
    }
    if (form.action && /contact/i.test(form.action)) {
      send('contact_submit', {}, true);
      return;
    }
  }, true);

  // Time on page
  function flushTime() {
    var sec = Math.round((Date.now() - start) / 1000);
    if (sec > 0) send('time_on_page', { duration_seconds: sec }, true);
  }
  window.addEventListener('pagehide', flushTime);
  window.addEventListener('beforeunload', flushTime);
})();

