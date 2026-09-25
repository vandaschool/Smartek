/* Campaign Loop — small progressive-enhancement layer (no framework). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var meta = function (n) { var m = $('meta[name="' + n + '"]'); return m ? m.getAttribute('content') : ''; };
  var CSRF = meta('csrf-token');
  var FA = '۰۱۲۳۴۵۶۷۸۹';
  var fa = function (s) { return String(s).replace(/[0-9]/g, function (d) { return FA[+d]; }).replace(/\./g, '٫'); };

  window.CL = {
    post: function (url, data) {
      return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: JSON.stringify(data || {}), credentials: 'same-origin' }).then(function (r) { return r.json(); });
    },
    get: function (url) {
      return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    },
    fa: fa
  };

  // popovers
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-toggle]');
    $$('.notif-pop:not([hidden]), .menu-pop:not([hidden])').forEach(function (p) {
      if (!t || $(t.getAttribute('data-toggle')) !== p) { if (!p.contains(ev.target)) p.hidden = true; }
    });
    if (t) { var p = $(t.getAttribute('data-toggle')); if (p) { p.hidden = !p.hidden; ev.preventDefault(); } }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      $$('.notif-pop, .menu-pop').forEach(function (p) { p.hidden = true; });
      var w = $('#welcome'); if (w) w.remove();
      endTour();
    }
  });

  // confirm
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var msg = f.getAttribute('data-confirm') || (ev.submitter && ev.submitter.getAttribute('data-confirm'));
    if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
    var typed = f.getAttribute('data-confirm-type');
    if (typed) { var v = window.prompt('برای تأیید، عبارت «' + typed + '» را بنویسید:'); if (v !== typed) { ev.preventDefault(); return; } }
    $$('button[type=submit], button:not([type])', f).forEach(function (b) { if (!b.hasAttribute('data-keep')) { setTimeout(function () { b.disabled = true; }, 0); } });
  });
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form && el.form.submit(); }); });

  // pill / segmented fallback for browsers without :has
  $$('.pill input, .seg input').forEach(function (inp) {
    var sync = function () {
      var lbl = inp.closest('label'); if (!lbl) return;
      if (inp.type === 'radio') { $$('input[name="' + inp.name + '"]').forEach(function (o) { var l = o.closest('label'); if (l) l.classList.toggle('on', o.checked); }); }
      else lbl.classList.toggle('on', inp.checked);
    };
    inp.addEventListener('change', sync); sync();
  });
  // keep at least one checkbox checked in a group
  $$('[data-min-one]').forEach(function (g) {
    g.addEventListener('change', function (ev) {
      var boxes = $$('input[type=checkbox]', g);
      if (!boxes.some(function (b) { return b.checked; })) { ev.target.checked = true; ev.target.dispatchEvent(new Event('change')); }
    });
  });

  // range labels
  $$('input[type=range][data-label]').forEach(function (r) {
    var out = $(r.getAttribute('data-label'));
    var fmt = r.getAttribute('data-format') || 'pct';
    var upd = function () {
      var v = +r.value;
      if (!out) return;
      if (fmt === 'signpct') out.textContent = (v >= 0 ? '+' : '−') + fa(Math.abs(v)) + '٪';
      else out.textContent = fa(v) + '٪';
    };
    r.addEventListener('input', upd); upd();
  });
  $$('[data-live-submit]').forEach(function (f) {
    var tm; $$('input, select', f).forEach(function (el) { el.addEventListener('change', function () { clearTimeout(tm); tm = setTimeout(function () { f.submit(); }, 250); }); });
  });

  // money helper under inputs
  $$('input[data-money]').forEach(function (inp) {
    var out = $(inp.getAttribute('data-money'));
    var upd = function () {
      var n = +String(inp.value).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }).replace(/[٬,\s]/g, '').replace('٫', '.');
      if (!out) return;
      if (!isFinite(n) || !n) { out.textContent = ''; return; }
      var a = Math.abs(n), s;
      if (a >= 1e9) s = fa((n / 1e9).toFixed(a >= 1e10 ? 0 : 1)) + ' میلیارد';
      else if (a >= 1e7) { var m = n / 1e6; s = fa(m.toFixed(Math.abs(m - Math.round(m)) < 0.05 ? 0 : 1)) + ' م'; }
      else s = fa(Math.round(n).toLocaleString('en-US').replace(/,/g, '٬'));
      out.textContent = s + ' تومان';
    };
    inp.addEventListener('input', upd); upd();
  });

  // jalali date selects: fix day count per month
  $$('[data-jdate]').forEach(function (g) {
    var d = $('select[data-part=d]', g), m = $('select[data-part=m]', g), y = $('select[data-part=y]', g), hid = $('input[type=hidden]', g);
    var esf = {}; try { esf = JSON.parse(g.getAttribute('data-jdate') || '{}'); } catch (e) { }
    var upd = function () {
      var mm = +m.value, yy = +y.value;
      var len = mm <= 6 ? 31 : mm <= 11 ? 30 : (esf[yy] || 29);
      var cur = +d.value;
      $$('option', d).forEach(function (o) { o.disabled = +o.value > len; });
      if (cur > len) d.value = String(len);
      hid.value = y.value + '/' + ('0' + m.value).slice(-2) + '/' + ('0' + d.value).slice(-2);
      var hintEl = g.parentNode.querySelector('[data-duration]');
      if (hintEl) hintEl.dispatchEvent(new Event('recalc'));
    };
    [d, m, y].forEach(function (s) { s.addEventListener('change', upd); }); upd();
  });

  // offline banner
  var ob = $('#offline-banner');
  if (ob) { var on = function () { ob.hidden = navigator.onLine !== false; }; window.addEventListener('online', on); window.addEventListener('offline', on); on(); }

  // toasts auto-hide
  setTimeout(function () { $$('.toast').forEach(function (t) { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 450); }); }, 5200);

  // tour
  var stepsEl = $('#tour-steps'), tour = $('#tour');
  var steps = stepsEl ? JSON.parse(stepsEl.textContent || '[]') : [];
  function store(k, v) { try { if (v === null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, v); } catch (e) { } }
  function read(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function showTour(i) {
    if (!tour || !steps[i]) return;
    $('[data-tour-n]', tour).textContent = fa(i + 1) + ' از ' + fa(steps.length);
    $('[data-tour-t]', tour).textContent = steps[i].title;
    $('[data-tour-x]', tour).textContent = steps[i].text;
    $('[data-tour-next]', tour).textContent = i === steps.length - 1 ? 'پایان تور' : 'بعدی ←';
    tour.hidden = false;
  }
  function goStep(i) {
    if (i >= steps.length) { endTour(); return; }
    store('cl_tour', String(i));
    var target = steps[i].href;
    var here = location.pathname + location.search;
    if (target && target.split('#')[0] !== here) { location.href = target; return; }
    showTour(i);
  }
  function endTour() { store('cl_tour', null); if (tour) tour.hidden = true; }
  $$('[data-tour-start]').forEach(function (b) { b.addEventListener('click', function () { goStep(0); }); });
  if (tour) {
    $('[data-tour-next]', tour).addEventListener('click', function () { goStep((+read('cl_tour') || 0) + 1); });
    $('[data-tour-end]', tour).addEventListener('click', endTour);
    var cur = read('cl_tour');
    if (cur !== null) showTour(+cur);
    if (document.body.getAttribute('data-start-tour') === '1') goStep(0);
  }

  // print
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  // copy
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-copy');
      var done = function () { var o = b.textContent; b.textContent = 'کپی شد'; setTimeout(function () { b.textContent = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(v).then(done, done); else { var t = document.createElement('textarea'); t.value = v; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) { } t.remove(); done(); }
    });
  });

  // AI: lazy "smart explanation" boxes
  $$('[data-ai-src]').forEach(function (box) {
    CL.get(box.getAttribute('data-ai-src')).then(function (d) {
      if (!d || !d.ok) { box.remove(); return; }
      var parts = [];
      (d.items || []).forEach(function (it) { parts.push('<div><div class="k">' + it.k + '</div><div>' + escapeHtml(it.v) + '</div></div>'); });
      box.innerHTML = '<div class="row gap8"><span class="ai-label">توضیح هوشمند</span>' + (d.ai && d.ai.fallback ? '' : '') + '</div>' + parts.join('');
      box.hidden = false;
    }).catch(function () { box.remove(); });
  });
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // AI: plan reason hint
  var reason = $('textarea[data-reason-hint]');
  if (reason) {
    var hint = $(reason.getAttribute('data-reason-hint')), tmr;
    reason.addEventListener('input', function () {
      clearTimeout(tmr);
      if (reason.value.trim().length < 10) { if (hint) hint.hidden = true; return; }
      tmr = setTimeout(function () {
        CL.post(reason.getAttribute('data-hint-url'), { reason: reason.value, perspective: reason.getAttribute('data-perspective') || '' }).then(function (d) {
          if (!hint) return;
          if (d && d.hint) { hint.textContent = d.hint; hint.hidden = false; } else hint.hidden = true;
        }).catch(function () { });
      }, 800);
    });
  }

  // ask
  var askForm = $('#ask-form');
  if (askForm) {
    var out = $('#ask-out');
    var run = function (q) {
      askForm.q.value = q;
      out.innerHTML = '<div class="card"><div class="small muted">در حال پاسخ…</div></div>';
      CL.post(askForm.action, { q: q }).then(function (d) {
        if (!d) return;
        if (d.grounded) {
          out.innerHTML = '<div class="card"><div style="font-size:15px;line-height:1.9">' + escapeHtml(d.text) + '</div>' +
            '<div class="row between"><div class="mono xs t3">source: ' + escapeHtml(d.src) + '</div>' + (d.ai && !d.ai.fallback ? '<span class="ai-label">توضیح هوشمند</span>' : '') + '</div></div>';
        } else {
          out.innerHTML = '<div class="card warn" style="font-size:13.5px;color:#4a3a12;line-height:1.9">' + escapeHtml(d.text) + '</div>';
        }
      }).catch(function () { out.innerHTML = '<div class="callout bad">پاسخ دریافت نشد. دوباره تلاش کنید.</div>'; });
    };
    askForm.addEventListener('submit', function (ev) { ev.preventDefault(); if (askForm.q.value.trim()) run(askForm.q.value.trim()); });
    $$('[data-ask]').forEach(function (b) { b.addEventListener('click', function () { run(b.getAttribute('data-ask')); }); });
  }

  // 2FA QR
  var qr = $('[data-qr]');
  if (qr && window.qrcode) { var q = window.qrcode(0, 'M'); q.addData(qr.getAttribute('data-qr')); q.make(); qr.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2 }); }
})();
