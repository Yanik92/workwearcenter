/*
 * WorkwearCenter – zentrale Oberflächenlogik.
 * Alle Skripte liegen bewusst in dieser Datei statt inline im HTML. Dadurch kann
 * die Content-Security-Policy "script-src 'self'" ohne 'unsafe-inline' setzen:
 * Selbst wenn irgendwo eine Ausgabe versehentlich nicht maskiert wäre, führt der
 * Browser eingeschleustes JavaScript nicht aus.
 * Jeder Block prüft selbst, ob seine Elemente auf der Seite vorhanden sind.
 */
(function () {
  'use strict';

  var euro = function (cents) {
    return (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  };

  /* ---------- Allgemein: Links kopieren, Details öffnen, Bestätigungen ---------- */
  document.addEventListener('click', function (event) {
    var copyButton = event.target.closest('[data-copy]');
    if (copyButton) {
      var text = copyButton.dataset.copy || '';
      var done = function () {
        var oldLabel = copyButton.textContent;
        copyButton.textContent = 'Kopiert ✓';
        window.setTimeout(function () { copyButton.textContent = oldLabel; }, 1400);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { window.prompt('Link kopieren:', text); });
      } else {
        window.prompt('Link kopieren:', text);
      }
      return;
    }

    var detailsButton = event.target.closest('[data-open-details]');
    if (detailsButton) {
      var target = document.getElementById(detailsButton.dataset.openDetails || '');
      if (target instanceof HTMLDetailsElement) {
        target.open = true;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var firstInput = target.querySelector('input:not([type="hidden"]), select, textarea');
        window.setTimeout(function () { if (firstInput) firstInput.focus(); }, 350);
      }
    }
  });

  // Ersetzt frühere onsubmit="return confirm(...)"-Attribute.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var submitter = event.submitter;
    var message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
  });

  // Sprungziele wie #mitarbeiter öffnen den passenden Aufklappbereich.
  // 10.15: auch wenn das Ziel in einem zugeklappten Bereich liegt (z. B. #profil-3, #budget-12); nach dem
  // Speichern steht die Rückmeldung direkt am Ziel statt oben auf der Seite.
  var openHashTarget = function (afterSave) {
    if (!location.hash) return;
    var el = null;
    try { el = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch (e) { return; }
    if (!el) return;
    var opened = false;
    for (var d = el.closest('details'); d; d = d.parentElement ? d.parentElement.closest('details') : null) { if (!d.open) opened = true; d.open = true; }
    var flash = afterSave === true ? document.querySelector('[data-flash]') : null;
    if (flash && !el.contains(flash) && el.parentNode) {
      // Rückmeldung direkt über das Ziel; der Abstand beim Springen schließt sie ein (auch beim Sprung des Browsers selbst).
      // Liegt das Ziel in einem mehrspaltigen Raster, einer Zeile oder Tabelle (z. B. Probeshop-Kacheln), steht sie über dem Ganzen statt als schmale Kachel daneben.
      var ref = el;
      for (var p = el.parentElement; p && p !== document.body; p = p.parentElement) {
        var ps = window.getComputedStyle(p);
        if ((ps.display.indexOf('grid') !== -1 && ps.gridTemplateColumns.trim().split(/\s+/).length > 1) || (ps.display.indexOf('flex') !== -1 && ps.flexDirection.indexOf('row') === 0) || ps.display.indexOf('table') === 0) ref = p; else break;
      }
      ref.parentNode.insertBefore(flash, ref);
      var gap = Math.max(0, el.getBoundingClientRect().top - flash.getBoundingClientRect().top);
      // Abstand zur festen Kopfzeile: der größere Wert von Ziel und Meldung (z. B. #grenze-12 hat selbst keinen).
      var margin = Math.max(parseFloat(window.getComputedStyle(el).scrollMarginTop) || 0, parseFloat(window.getComputedStyle(flash).scrollMarginTop) || 0);
      el.style.scrollMarginTop = (margin + gap) + 'px';
      el.scrollIntoView({ block: 'start' });
    } else if (opened || el instanceof HTMLDetailsElement) el.scrollIntoView({ block: 'start' });
  };
  openHashTarget(true);
  window.addEventListener('hashchange', openHashTarget);

  // 10.15: Lexware – nach abgelaufenem Zeitbudget die übrigen Rechnungsentwürfe automatisch weiter senden (app/modules/lexware.php).
  document.querySelectorAll('form[data-lexware-weiter]').forEach(function (form) {
    var button = form.querySelector('button');
    if (button) { button.disabled = true; button.textContent = 'Wird automatisch weiter gesendet …'; }
    window.setTimeout(function () { form.submit(); }, 1200);
  });

  document.querySelectorAll('[data-select-all]').forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      document.querySelectorAll('input[name="order_ids[]"]').forEach(function (box) { box.checked = toggle.checked; });
    });
  });

  // 10.15: „Alle auf dieser Seite“ – setzt alle Kästchen mit dem genannten Namen im selben Formular
  // (auch Kästchen, die per form="…" außerhalb des Formulars stehen).
  document.querySelectorAll('[data-check-all]').forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      var form = toggle.form || toggle.closest('form');
      if (!form) return;
      Array.prototype.forEach.call(form.elements, function (box) {
        if (box.type === 'checkbox' && box.name === toggle.dataset.checkAll) box.checked = toggle.checked;
      });
    });
  });

  // 10.15: Ungespeicherte Häkchen (z. B. Kollektion) nicht durch Blättern oder Bearbeiten verlieren.
  document.querySelectorAll('form[data-unsaved-warning]').forEach(function (form) {
    var dirty = false;
    document.addEventListener('change', function (event) { if (event.target === form || event.target.form === form) dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  });

  /* ---------- Firmenshop: Vorder-/Rückansicht ---------- */
  document.querySelectorAll('[data-product-visual]').forEach(function (visual) {
    var buttons = visual.querySelectorAll('[data-show-side]');
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        var side = button.dataset.showSide;
        buttons.forEach(function (b) { b.classList.toggle('active', b === button); });
        visual.querySelectorAll('[data-side]').forEach(function (image) {
          image.classList.toggle('active', image.dataset.side === side);
        });
      });
    });
  });

  /* Firmenshop (Filter, Mengen, Auswahl, Detailansicht): assets/shop.js */

  /* ---------- Admin: einfacher Rich-Text-Editor ---------- */
  var toolbars = document.querySelectorAll('.rich-toolbar');
  if (toolbars.length) {
    var wrapTag = function (editor, tagName) {
      editor.focus();
      var sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return;
      var range = sel.getRangeAt(0);
      if (!editor.contains(range.commonAncestorContainer)) return;
      var tag = document.createElement(tagName);
      if (range.collapsed) {
        tag.appendChild(document.createTextNode('​'));
        range.insertNode(tag);
        range.setStart(tag.firstChild, 1);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        return;
      }
      try { range.surroundContents(tag); }
      catch (e) { tag.appendChild(range.extractContents()); range.insertNode(tag); }
      sel.removeAllRanges();
      var newRange = document.createRange();
      newRange.selectNodeContents(tag);
      sel.addRange(newRange);
    };
    toolbars.forEach(function (toolbar) {
      toolbar.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-cmd]');
        if (!btn) return;
        e.preventDefault();
        var field = toolbar.closest('.rich-field');
        var editor = field ? field.querySelector('.rich-editor') : null;
        if (!editor) return;
        var tags = { bold: 'strong', italic: 'em', underline: 'u' };
        if (tags[btn.dataset.cmd]) wrapTag(editor, tags[btn.dataset.cmd]);
      });
    });
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        form.querySelectorAll('.rich-field').forEach(function (field) {
          var editor = field.querySelector('.rich-editor');
          var hidden = field.querySelector('.rich-hidden');
          if (editor && hidden) hidden.value = editor.innerHTML.replace(/​/g, '');
        });
      });
    });
  }

  /* ---------- Admin: QR-Code für die Zwei-Faktor-Einrichtung ---------- */
  var renderQr = function () {
    document.querySelectorAll('[data-qr]').forEach(function (box) {
      if (box.dataset.rendered || typeof window.qrcode !== 'function') return;
      var qr = window.qrcode(0, 'M');
      qr.addData(box.dataset.qr);
      qr.make();
      box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 3, scalable: true });
      box.dataset.rendered = '1';
    });
  };
  if (document.querySelector('[data-qr]')) {
    if (typeof window.qrcode === 'function') renderQr();
    else window.addEventListener('load', renderQr);
  }
  document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () { window.print(); });
  });
  document.querySelectorAll('.code-input').forEach(function (input) {
    input.addEventListener('input', function () {
      var digits = input.value.replace(/\s+/g, '');
      if (/^\d{4,6}$/.test(digits) && digits.length === 6) input.value = digits.slice(0, 3) + ' ' + digits.slice(3);
    });
  });

  /* ---------- Öffentlicher Mustershop (reine Demo, speichert nichts) ---------- */
  var demoToast = document.getElementById('demo-toast');
  if (demoToast) {
    var count = 0, budget = 21870;
    var countEl = document.getElementById('demo-count');
    var budgetEl = document.getElementById('demo-budget-value');
    var progress = document.getElementById('demo-progress');
    var toast = function (text, ms) {
      demoToast.textContent = text;
      demoToast.classList.add('show');
      window.setTimeout(function () { demoToast.classList.remove('show'); }, ms || 2400);
    };
    document.querySelectorAll('.demo-filter').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.demo-filter').forEach(function (x) { x.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.demo-product').forEach(function (card) {
          card.hidden = btn.dataset.filter !== 'Alle' && card.dataset.category !== btn.dataset.filter;
        });
      });
    });
    document.querySelectorAll('.demo-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var price = parseInt(btn.dataset.price, 10);
        if (price > budget) { toast('Das Beispielbudget reicht für diesen Artikel nicht aus.'); return; }
        count++;
        budget -= price;
        if (countEl) countEl.textContent = String(count);
        if (budgetEl) budgetEl.textContent = euro(budget);
        if (progress) progress.style.width = Math.max(4, Math.round(budget / 30000 * 100)) + '%';
        toast(btn.dataset.name + ' wurde zur Demo-Auswahl hinzugefügt.');
      });
    });
    var demoCart = document.querySelector('.demo-cart');
    if (demoCart) demoCart.addEventListener('click', function () {
      toast(count ? 'Demo-Auswahl: ' + count + ' Artikel. Es wird keine Bestellung gespeichert.' : 'Wählen Sie zunächst einen Artikel aus.', 2600);
    });
    document.querySelectorAll('[data-demo-nav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('active')) return;
        toast(btn.dataset.demoNav + ': Diese Ansicht ist im echten Firmen-Shop vollständig verfügbar.', 2600);
      });
    });
    /* 10.11 Probeshop: Textilfarbe hell/dunkel umschalten (nur Darstellung) */
    var demoRoot = document.querySelector('.demo-probe');
    document.querySelectorAll('[data-probe-tone]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!demoRoot) return;
        var tone = btn.dataset.probeTone === 'light' ? 'light' : 'dark';
        demoRoot.classList.remove('tone-light', 'tone-dark');
        demoRoot.classList.add('tone-' + tone);
        document.querySelectorAll('[data-probe-tone]').forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
      });
    });
  }



  /* ---------- 10.9: Lieferantenkatalog – Auswahl und Preisvorschau ---------- */
  document.querySelectorAll('[data-katalog]').forEach(function (form) {
    var colors = Array.prototype.slice.call(form.querySelectorAll('input[name="colors[]"]'));
    var sizes = Array.prototype.slice.call(form.querySelectorAll('input[name="sizes[]"]'));
    var factor = form.querySelector('[data-katalog-factor]');
    var fixed = form.querySelector('[data-katalog-fixed]');
    var submit = form.querySelector('[data-katalog-submit]');
    var out = {};
    form.querySelectorAll('[data-katalog-out]').forEach(function (el) { out[el.dataset.katalogOut] = el; });
    function eur(v) { return v.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; }
    function num(el) { return parseFloat(String(el.value || '').replace(',', '.')) || 0; }
    function update() {
      var chosen = colors.filter(function (c) { return c.checked && !c.disabled; });
      // Wie beim Anlegen: je Farbe gilt der höchste Einkaufspreis der gewählten Größen (Übergrößen-Aufpreis).
      var checkedSizes = sizes.filter(function (s) { return s.checked; }).map(function (s) { return s.value; });
      function colorEk(c) {
        var base = parseFloat(c.dataset.ek) || 0, ps = {}, sz = [];
        try { ps = JSON.parse(c.dataset.ps || '{}') || {}; sz = JSON.parse(c.dataset.sz || '[]') || []; } catch (e) { return base; }
        var used = sz.filter(function (s) { return checkedSizes.indexOf(s) !== -1; });
        if (!used.length) used = sz;
        return used.reduce(function (m, s) { return Math.max(m, parseFloat(ps[s]) || 0); }, base);
      }
      var ek = chosen.length ? Math.max.apply(null, chosen.map(colorEk)) : parseFloat(form.dataset.ek) || 0;
      var mode = form.querySelector('input[name="price_mode"]:checked').value;
      if (factor) factor.disabled = mode !== 'factor';
      if (fixed) fixed.disabled = mode !== 'fixed';
      var vk = mode === 'fixed' ? num(fixed) : Math.ceil(ek * num(factor) * 10 - 1e-9) / 10;
      out.ek && (out.ek.textContent = eur(ek));
      out.vk && (out.vk.textContent = vk > 0 ? eur(vk) : '–');
      out.brutto && (out.brutto.textContent = vk > 0 ? eur(Math.round(Math.round(vk * 100) * 119 / 100) / 100) : '–'); // wie gross_from_net: in Cent runden
      out.marge && (out.marge.textContent = vk > 0 ? eur(vk - ek) : '–');
      var n = chosen.length;
      out.count && (out.count.textContent = n === 1 ? '1 Produkt' : n + ' Produkte');
      var anySize = sizes.some(function (s) { return s.checked; });
      if (submit) submit.disabled = !(n > 0 && anySize && vk > 0);
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    form.querySelectorAll('[data-katalog-select]').forEach(function (b) {
      b.addEventListener('click', function () {
        var mode = b.dataset.katalogSelect;
        colors.forEach(function (c) {
          if (c.disabled) return;
          c.checked = mode === 'all' || (mode === 'image' && c.closest('.kcolor').dataset.hasImage === '1');
        });
        update();
      });
    });
    form.querySelectorAll('[data-katalog-sizes]').forEach(function (b) {
      b.addEventListener('click', function () {
        var mode = b.dataset.katalogSizes;
        sizes.forEach(function (s) { s.checked = mode === 'all' || !s.closest('.ksize').classList.contains('has-extra'); });
        update();
      });
    });
    update();
  });

  /* ---------- 10.12.1: Katalogbilder in Paketen hochladen (große Auswahl sprengt sonst post_max_size) ---------- */
  document.querySelectorAll('[data-katalog-upload]').forEach(function (form) {
    if (!window.fetch || !window.FormData) return;
    var input = form.querySelector('input[type="file"]');
    var status = form.querySelector('[data-katalog-upload-status]');
    var button = form.querySelector('button[type="submit"]');
    var maxBytes = parseInt(form.dataset.maxBytes, 10) || 7000000;
    var maxFiles = parseInt(form.dataset.maxFiles, 10) || 20;
    form.addEventListener('submit', function (ev) {
      var files = Array.prototype.slice.call(input.files || []);
      if (!files.length) return;
      ev.preventDefault();
      var tooBig = files.filter(function (f) { return f.size > maxBytes; });
      files = files.filter(function (f) { return f.size <= maxBytes; });
      var batches = [], cur = [], curSize = 0;
      files.forEach(function (f) {
        if (cur.length && (cur.length >= maxFiles || curSize + f.size > maxBytes)) { batches.push(cur); cur = []; curSize = 0; }
        cur.push(f); curSize += f.size;
      });
      if (cur.length) batches.push(cur);
      var done = 0, uploaded = 0, assigned = 0, problems = tooBig.map(function (f) { return f.name + ': zu groß für den Upload, bitte per FTP ablegen'; });
      button.disabled = true;
      function finish() {
        var msg = uploaded + ' Bilddatei(en) übernommen, ' + assigned + ' Produkt(e) zugeordnet.';
        if (problems.length) msg += ' Nicht übernommen: ' + problems.slice(0, 6).join(' · ') + (problems.length > 6 ? ' …' : '');
        status.textContent = msg + ' Zuordnung wird geprüft …';
        // Abschluss als normales Formular: der Server zeigt dann die vollständige Rückmeldung (fehlende und unbekannte Dateien).
        var f = document.createElement('form');
        f.method = 'post'; f.action = form.getAttribute('action') || window.location.pathname + window.location.search;
        [['csrf', form.querySelector('input[name="csrf"]').value], ['action', 'katalog_images_assign'], ['upload_summary', msg]].forEach(function (kv) {
          var i = document.createElement('input'); i.type = 'hidden'; i.name = kv[0]; i.value = kv[1]; f.appendChild(i);
        });
        document.body.appendChild(f); f.submit();
      }
      function next() {
        if (done >= batches.length) { finish(); return; }
        status.textContent = 'Paket ' + (done + 1) + ' von ' + batches.length + ' wird übertragen …';
        var fd = new FormData();
        fd.append('csrf', form.querySelector('input[name="csrf"]').value);
        fd.append('action', 'katalog_images_upload');
        fd.append('ajax', '1');
        batches[done].forEach(function (f) { fd.append('katalog_images[]', f, f.name); });
        fetch(form.getAttribute('action') || window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.ok) { uploaded += j.files; assigned += j.assigned; problems = problems.concat(j.problems || []); }
            else problems.push(j.error);
          })
          .catch(function () { problems.push('Paket ' + (done + 1) + ' konnte nicht übertragen werden'); })
          .then(function () { done++; next(); });
      }
      if (!batches.length) { finish(); return; }
      next();
    });
  });
  /* ---------- 10.15: Bilder aus dem Daiber-Shop laden – Aufrufe nacheinander, bis nichts mehr offen ist ---------- */
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { if (el.form) el.form.submit(); });
  });
  document.querySelectorAll('[data-katalog-fetch]').forEach(function (form) {
    if (!window.fetch || !window.FormData) return;
    var status = document.querySelector('[data-katalog-fetch-status="' + form.id + '"]');
    var buttons = Array.prototype.slice.call(document.querySelectorAll('button[form="' + form.id + '"]')).concat(Array.prototype.slice.call(form.querySelectorAll('button[type="submit"]')));
    var running = false;
    function say(text) { if (status) status.textContent = text; }
    function post(extra) {
      var fd = new FormData(form);
      fd.append('ajax', '1');
      Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
      return fetch(form.getAttribute('action') || window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function run() {
      if (running) return;
      running = true;
      buttons.forEach(function (b) { b.disabled = true; });
      var loaded = 0, assigned = 0, rounds = 0, idle = 0, lastRemaining = -1, notfound = [], offline = false, error = '';
      say('Bilder werden aus dem Daiber-Shop geladen …');
      function finish() {
        var msg = error ? error : (offline && !loaded ? 'Der Daiber-Shop ist gerade nicht erreichbar. Der Cronjob versucht es in 15 Minuten erneut.' : (!loaded && !assigned && lastRemaining === 0 && !notfound.length ? 'Alles aktuell: Alle Katalogprodukte haben ihre Bilder schon.' : loaded + ' Bild(er) aus dem Daiber-Shop geladen, ' + assigned + ' Produkt(e) zugeordnet.'));
        if (notfound.length) msg += ' Im Daiber-Shop nicht gefunden: ' + notfound.slice(0, 10).join(', ') + ' – diese Bilder bitte selbst hochladen.';
        say(msg + ' Seite wird aktualisiert …');
        post({ finish: msg, finish_type: error || (offline && !loaded) ? 'error' : 'success' }).catch(function () {}).then(function () {
          var target = window.location.pathname + window.location.search.replace(/([?&])bilder=laden(&|$)/, '$1').replace(/[?&]$/, '');
          if (form.id === 'katalog-fetch-all') window.location.hash = 'bilder';
          // Nur der Anker ändert sich: dann neu laden, sonst springt der Browser nur zur Stelle
          if (target === window.location.pathname + window.location.search) window.location.reload(); else window.location.href = target;
        });
      }
      function next() {
        rounds++;
        post({}).then(function (j) {
          if (!j.ok) { error = j.error || 'Bilder konnten nicht geladen werden.'; finish(); return; }
          if (j.busy) {
            // Cronjob oder anderes Fenster gleicht gerade ab: kurz warten und erneut versuchen
            if (rounds >= 80) { error = j.message; finish(); return; }
            say('Gerade läuft schon ein Abgleich (Cronjob) – es geht gleich weiter …'); setTimeout(next, 8000); return;
          }
          loaded += j.loaded; assigned += j.assigned; offline = j.offline;
          (j.notfound || []).forEach(function (s) { if (notfound.indexOf(s) < 0) notfound.push(s); });
          // Ohne Fortschritt (nichts geladen, nicht weniger offen) nach drei Runden aufhören – der Cronjob macht weiter
          idle = (j.loaded === 0 && lastRemaining >= 0 && j.remaining >= lastRemaining) ? idle + 1 : 0; lastRemaining = j.remaining;
          if (j.remaining > 0 && !j.offline && rounds < 80 && idle < 3) { say(loaded + ' Bild(er) geladen, ' + assigned + ' Produkt(e) zugeordnet – noch ' + j.remaining + ' Artikel …'); next(); }
          else finish();
        }).catch(function () { error = 'Die Verbindung wurde unterbrochen. Bereits geladene Bilder sind gespeichert, der Rest folgt über den Cronjob.'; finish(); });
      }
      next();
    }
    form.addEventListener('submit', function (ev) { ev.preventDefault(); run(); });
    if (form.dataset.auto === '1') run();
  });
  /* ---------- 10.9: Logo-Ebenen exakt auf die sichtbare Bildfläche legen ---------- */
  function fitMockupLayers() {
    document.querySelectorAll('.mockup-view').forEach(function (view) {
      var img = view.querySelector('[data-mockup-base]');
      var layers = view.querySelector('[data-mockup-layers]');
      if (!img || !layers) return;
      var nw = img.naturalWidth, nh = img.naturalHeight;
      var cw = view.clientWidth, ch = view.clientHeight;
      if (!nw || !nh || !cw || !ch) return;
      var scale = Math.min(cw / nw, ch / nh);
      var w = nw * scale, h = nh * scale;
      layers.style.left = ((cw - w) / 2) + 'px';
      layers.style.top = ((ch - h) / 2) + 'px';
      layers.style.width = w + 'px';
      layers.style.height = h + 'px';
    });
  }
  fitMockupLayers();
  window.addEventListener('resize', fitMockupLayers);
  window.addEventListener('load', fitMockupLayers);
  document.querySelectorAll('[data-mockup-base]').forEach(function (img) { img.addEventListener('load', fitMockupLayers); });
  document.querySelectorAll('[data-show-side]').forEach(function (b) { b.addEventListener('click', function () { setTimeout(fitMockupLayers, 0); }); });
  if (window.MutationObserver) { new MutationObserver(function () { fitMockupLayers(); }).observe(document.body, { attributes: true, subtree: true, attributeFilter: ['class', 'open'] }); }
  /* ---------- 10.6: Druckzonen-Editor (Admin → Produkte → Druckzonen) ---------- */
  document.querySelectorAll('[data-zone-editor]').forEach(function (form) {
    var input = form.querySelector('[data-zone-input]');
    var viewSel = form.querySelector('[data-zone-view]');
    var posSel = form.querySelector('[data-zone-position]');
    var width = form.querySelector('[data-zone-width]');
    var widthLabel = form.querySelector('[data-zone-width-label]');
    var list = form.querySelector('[data-zone-list]');
    var defaults = JSON.parse(form.querySelector('[data-zone-defaults]').innerHTML);
    var labels = JSON.parse(form.querySelector('[data-zone-labels]').innerHTML);
    var zones = {};
    try { zones = JSON.parse(input.value) || {}; } catch (e) { zones = {}; }
    if (Array.isArray(zones)) zones = {};

    function current() { return (zones[viewSel.value] || {})[posSel.value]; }
    function stageFor(view) { return form.querySelector('[data-zone-stage="' + view + '"]'); }

    function render() {
      form.querySelectorAll('[data-zone-stage]').forEach(function (stage) {
        stage.classList.toggle('active', stage.dataset.zoneStage === viewSel.value);
        stage.querySelectorAll('.zone-box').forEach(function (b) { b.remove(); });
        var map = zones[stage.dataset.zoneStage] || {};
        Object.keys(map).forEach(function (pos) {
          var z = map[pos];
          var box = document.createElement('div');
          box.className = 'zone-box' + (stage.dataset.zoneStage === viewSel.value && pos === posSel.value ? ' current' : '');
          box.style.left = z.x + '%'; box.style.top = z.y + '%'; box.style.width = z.w + '%';
          box.innerHTML = '<span class="zone-sample">LOGO</span><em>' + (labels[pos] || pos) + '</em>';
          stage.appendChild(box);
        });
      });
      var rows = [];
      Object.keys(zones).forEach(function (view) {
        Object.keys(zones[view]).forEach(function (pos) {
          rows.push('<div><strong>' + (labels[pos] || pos) + '</strong> <span class="muted">' + view + ' · ' + zones[view][pos].w + ' % breit</span></div>');
        });
      });
      list.innerHTML = rows.length ? rows.join('') : '<div class="muted">Noch keine eigene Zone. Klicke auf das Bild.</div>';
      var z = current();
      if (z) { width.value = z.w; widthLabel.textContent = z.w; }
      input.value = JSON.stringify(zones);
    }

    function setZone(x, y, w) {
      if (!zones[viewSel.value]) zones[viewSel.value] = {};
      zones[viewSel.value][posSel.value] = { x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10, w: Math.round(w * 10) / 10 };
      render();
    }

    form.querySelectorAll('[data-zone-stage]').forEach(function (stage) {
      stage.addEventListener('click', function (event) {
        var img = stage.querySelector('img');
        var rect = img.getBoundingClientRect();
        var x = ((event.clientX - rect.left) / rect.width) * 100;
        var y = ((event.clientY - rect.top) / rect.height) * 100;
        if (x < 1 || x > 99 || y < 1 || y > 99) return;
        var z = current();
        setZone(x, y, z ? z.w : parseFloat(width.value));
      });
    });
    width.addEventListener('input', function () {
      widthLabel.textContent = width.value;
      var z = current();
      if (z) setZone(z.x, z.y, parseFloat(width.value));
    });
    viewSel.addEventListener('change', function () {
      // Position auf eine sinnvolle Standardzone der Ansicht stellen
      var def = defaults[viewSel.value] || {};
      if (!current() && Object.keys(def).length && !def[posSel.value]) posSel.value = Object.keys(def)[0];
      render();
    });
    posSel.addEventListener('change', function () {
      if (!current()) {
        var def = (defaults[viewSel.value] || {})[posSel.value];
        if (def) { width.value = def.w; widthLabel.textContent = def.w; }
      }
      render();
    });
    form.querySelector('[data-zone-remove]').addEventListener('click', function () {
      if (zones[viewSel.value]) { delete zones[viewSel.value][posSel.value]; if (!Object.keys(zones[viewSel.value]).length) delete zones[viewSel.value]; }
      render();
    });
    render();
  });

  /* ---------- 10.6: Korrekturabzug als PNG (aus der Vorschau im Browser) ---------- */
  document.querySelectorAll('[data-proof-download]').forEach(function (button) {
    button.addEventListener('click', function () {
      var visual = button.closest('.collection-mockup') && button.closest('.collection-mockup').querySelector('[data-product-visual]');
      if (!visual) return;
      var views = visual.querySelectorAll('.mockup-view');
      var loads = [];
      function loadImg(src) {
        return new Promise(function (resolve, reject) {
          var img = new Image(); img.onload = function () { resolve(img); }; img.onerror = reject; img.src = src;
        });
      }
      button.disabled = true; button.textContent = 'Wird erstellt …';
      views.forEach(function (view) {
        var layerEls = view.querySelectorAll('.mockup-logo');
        loads.push(loadImg(view.dataset.image).then(function (base) {
          return Promise.all(Array.prototype.map.call(layerEls, function (el) { return loadImg(el.querySelector('img').getAttribute('src')); })).then(function (logos) {
            return { base: base, logos: logos, els: layerEls };
          });
        }));
      });
      Promise.all(loads).then(function (parts) {
        var W = 1200, gap = 40;
        var heights = parts.map(function (p) { return Math.round(W * p.base.naturalHeight / p.base.naturalWidth); });
        var canvas = document.createElement('canvas');
        canvas.width = parts.length * W + (parts.length - 1) * gap; canvas.height = Math.max.apply(null, heights) + 90;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        parts.forEach(function (p, i) {
          var ox = i * (W + gap), h = heights[i];
          ctx.drawImage(p.base, ox, 0, W, h);
          p.logos.forEach(function (logo, k) {
            var el = p.els[k];
            var lw = parseFloat(el.style.width) / 100 * W;
            var lh = lw * logo.naturalHeight / logo.naturalWidth;
            var cx = ox + parseFloat(el.style.left) / 100 * W, cy = parseFloat(el.style.top) / 100 * h;
            if (el.classList.contains('mockup-embroidery')) { ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 1.5; }
            ctx.drawImage(logo, cx - lw / 2, cy - lh / 2, lw, lh);
            ctx.shadowBlur = 0;
          });
        });
        ctx.fillStyle = '#17324d'; ctx.font = '600 26px Inter, Arial, sans-serif';
        ctx.fillText('Korrekturabzug · ' + button.dataset.proofDownload.replace(/-/g, ' ') + ' · ' + new Date().toLocaleDateString('de-DE') + ' · unverbindliche Darstellung, Größen ca.', 10, canvas.height - 30);
        var a = document.createElement('a');
        a.download = 'korrekturabzug-' + button.dataset.proofDownload.toLowerCase() + '.png';
        a.href = canvas.toDataURL('image/png'); a.click();
      }).catch(function () {
        alert('Der Korrekturabzug konnte nicht erstellt werden. Bitte Logo-Datei prüfen.');
      }).then(function () { button.disabled = false; button.textContent = 'Korrekturabzug als PNG herunterladen'; });
    });
  });
})();

/*
 * Nach einer Fehlermeldung die Eingaben wieder ins Formular eintragen (siehe yw_remember_input() in
 * app/functions.php). Gesucht wird das Formular mit derselben Aktion; bei mehreren gleichartigen
 * Formularen (z. B. je Produkt) muss auch die versteckte ID übereinstimmen.
 */
(function () {
  var node = document.getElementById('yw-old-input');
  if (!node) return;
  var old;
  try { old = JSON.parse(node.textContent || ''); } catch (e) { return; }
  if (!old || !old.action || !old.data) return;
  var data = old.data;

  function lookup(name) {
    var m = name.match(/^([^\[]+)((?:\[[^\]]*\])*)$/);
    if (!m) return undefined;
    var value = data[m[1]], parts = (m[2].match(/\[([^\]]*)\]/g) || []).map(function (p) { return p.slice(1, -1); });
    for (var i = 0; i < parts.length; i++) {
      if (parts[i] === '') return Array.isArray(value) ? value : (value && typeof value === 'object' ? Object.keys(value).map(function (k) { return value[k]; }) : []);
      if (value === undefined || value === null || typeof value !== 'object') return undefined;
      value = value[parts[i]];
    }
    return value;
  }

  var forms = Array.prototype.filter.call(document.querySelectorAll('form'), function (form) {
    var a = form.querySelector('input[type=hidden][name=action]');
    return a && a.value === old.action;
  });
  var form = forms.length === 1 ? forms[0] : forms.filter(function (f) {
    var ids = f.querySelectorAll('input[type=hidden][name$="id"]');
    return ids.length > 0 && Array.prototype.every.call(ids, function (i) { var v = lookup(i.name); return v === undefined || String(v) === i.value; });
  })[0];
  if (!form) return;

  Array.prototype.forEach.call(form.elements, function (el) {
    if (!el.name || el.disabled || /^(hidden|password|file|submit|button|reset)$/.test(el.type)) return;
    var value = lookup(el.name);
    if (el.type === 'checkbox') {
      if (/\[\]$/.test(el.name)) el.checked = Array.isArray(value) && value.map(String).indexOf(el.value) !== -1;
      else el.checked = value !== undefined && (el.value === 'on' || String(value) === el.value);
    } else if (el.type === 'radio') {
      if (value !== undefined) el.checked = String(value) === el.value;
    } else if (el.tagName === 'SELECT' && el.multiple) {
      var list = Array.isArray(value) ? value.map(String) : [];
      Array.prototype.forEach.call(el.options, function (o) { o.selected = list.indexOf(o.value) !== -1; });
    } else if (value !== undefined && typeof value !== 'object') {
      el.value = String(value);
    } else return;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  });
  var details = form.closest('details');
  if (details) details.open = true;
})();
