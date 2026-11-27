/*
 * WorkwearCenter – Bewegung der öffentlichen Website (10.20.0).
 * Alles ist ohne JavaScript lesbar; dieses Skript ergänzt nur Effekte.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  root.classList.add('w-js');

  // Kopfzeile bekommt beim Scrollen einen Schatten.
  var nav = document.querySelector('.w-nav');
  var ticking = false;
  function onScroll() {
    ticking = false;
    if (nav) nav.classList.toggle('is-scrolled', window.scrollY > 10);
    fillLines();
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(onScroll); }
  }, { passive: true });

  // Nahtlinie im Ablauf füllt sich beim Scrollen; erreichte Schritte werden orange.
  var lines = Array.prototype.slice.call(document.querySelectorAll('[data-fill]'));
  function fillLines() {
    var vh = window.innerHeight || 800;
    lines.forEach(function (line) {
      var box = line.getBoundingClientRect();
      var start = vh * 0.85, end = vh * 0.35;
      var p = reduce ? 1 : (start - box.top) / Math.max(1, box.height + start - end);
      p = Math.max(0, Math.min(1, p));
      line.style.setProperty('--fill', p.toFixed(3));
      var items = line.querySelectorAll('[data-fill-item]');
      items.forEach(function (item, i) {
        var mark = items.length > 1 ? i / (items.length - 1) : 0;
        item.classList.toggle('done', p >= mark - 0.001 && p > 0);
      });
    });
  }

  // Abschnitte gleiten beim Erscheinen ein.
  var reveals = document.querySelectorAll('.w-reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  }

  // Vorher-nachher-Regler: Vorschau im Shop gegen echten Stick.
  document.querySelectorAll('[data-compare]').forEach(function (box) {
    var input = box.querySelector('input[type=range]');
    if (!input) return;
    var set = function () { box.style.setProperty('--pos', input.value + '%'); };
    input.addEventListener('input', set);
    set();
  });

  // Kundenleiste anhalten und fortsetzen.
  document.querySelectorAll('[data-w-trust-toggle]').forEach(function (button) {
    var section = button.closest('.w-trust');
    var label = button.querySelector('span');
    button.addEventListener('click', function () {
      var paused = section.classList.toggle('is-paused');
      button.setAttribute('aria-pressed', paused ? 'true' : 'false');
      if (label) label.textContent = paused ? 'Weiterlaufen' : 'Anhalten';
    });
    if (reduce) button.click();
  });

  // Menü auf dem Handy schließt nach einem Klick auf einen Anker.
  document.querySelectorAll('.w-burger nav a').forEach(function (a) {
    a.addEventListener('click', function () { var d = a.closest('details'); if (d) d.open = false; });
  });

  // Ansichten aus dem Firmenshop als Reiter (ohne JS bleiben alle Bilder untereinander sichtbar).
  document.querySelectorAll('[data-showcase]').forEach(function (box) {
    var list = box.querySelector('.w-tabs');
    if (!list) return;
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role=tab]'));
    var show = function (tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    };
    list.hidden = false;
    box.classList.add('is-tabs');
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { show(tab, false); });
      tab.addEventListener('keydown', function (ev) {
        var n = null;
        if (ev.key === 'ArrowRight') n = tabs[(i + 1) % tabs.length];
        else if (ev.key === 'ArrowLeft') n = tabs[(i - 1 + tabs.length) % tabs.length];
        else if (ev.key === 'Home') n = tabs[0];
        else if (ev.key === 'End') n = tabs[tabs.length - 1];
        if (n) { ev.preventDefault(); show(n, true); }
      });
    });
    show(tabs[0], false);
  });

  // 10.27: Spam-Schutz im Anfrageformular. Der Browser löst unsichtbar eine kleine Rechenaufgabe (SHA-256),
  // sobald jemand das Formular benutzt. Kein Fremddienst, kein Cookie. Bots ohne JavaScript lösen sie nicht.
  var K = [0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2];
  var W = new Array(64);
  // Erstes 32-Bit-Wort des SHA-256-Hashes einer ASCII-Zeichenkette (reicht für die Prüfung führender Nullen bis 32 Bit).
  function sha256First(str) {
    var n = str.length, words = [], i, j;
    for (i = 0; i < n; i++) words[i >> 2] |= (str.charCodeAt(i) & 0xff) << (24 - (i % 4) * 8);
    words[n >> 2] |= 0x80 << (24 - (n % 4) * 8);
    var len = (((n + 8) >> 6) + 1) * 16;
    for (i = (n >> 2) + 1; i < len; i++) words[i] = words[i] || 0;
    words[len - 1] = n * 8;
    var h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a, h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;
    for (j = 0; j < len; j += 16) {
      var a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7, t1, t2, x, y;
      for (i = 0; i < 64; i++) {
        if (i < 16) W[i] = words[j + i] | 0;
        else {
          x = W[i - 15]; y = W[i - 2];
          W[i] = (((x >>> 7) | (x << 25)) ^ ((x >>> 18) | (x << 14)) ^ (x >>> 3)) + W[i - 7] + (((y >>> 17) | (y << 15)) ^ ((y >>> 19) | (y << 13)) ^ (y >>> 10)) + W[i - 16] | 0;
        }
        t1 = h + (((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7))) + ((e & f) ^ (~e & g)) + K[i] + W[i] | 0;
        t2 = (((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10))) + ((a & b) ^ (a & c) ^ (b & c)) | 0;
        h = g; g = f; f = e; e = d + t1 | 0; d = c; c = b; b = a; a = t1 + t2 | 0;
      }
      h0 = h0 + a | 0; h1 = h1 + b | 0; h2 = h2 + c | 0; h3 = h3 + d | 0; h4 = h4 + e | 0; h5 = h5 + f | 0; h6 = h6 + g | 0; h7 = h7 + h | 0;
    }
    return h0 >>> 0;
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[data-yw-guard]'), function (ticket) {
    var form = ticket.form, out = form && form.querySelector('input[name="yw_guard_n"]');
    if (!form || !out) return;
    var parts = ticket.value.split('.');
    if (parts.length !== 5) return;
    var payload = parts.slice(0, 4).join('.') + ':', bits = Math.min(32, parseInt(parts[3], 10) || 0);
    var limit = bits >= 32 ? 0 : Math.pow(2, 32 - bits), nonce = 0, started = false, done = false, waiting = false;
    function step() {
      var stop = nonce + 4000;
      for (; nonce < stop; nonce++) {
        if (sha256First(payload + nonce) < limit) { out.value = String(nonce); done = true; if (waiting) form.submit(); return; }
      }
      window.setTimeout(step, 0);
    }
    function start() { if (!started) { started = true; window.setTimeout(step, 0); } }
    form.addEventListener('focusin', start);
    form.addEventListener('input', start);
    form.addEventListener('submit', function (ev) {
      if (done) return;
      ev.preventDefault();
      waiting = true;
      var btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }
      start();
    });
  });

  onScroll();
})();
