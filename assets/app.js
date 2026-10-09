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
  var openHashTarget = function () {
    if (!location.hash) return;
    var el = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (el instanceof HTMLDetailsElement) { el.open = true; el.scrollIntoView({ block: 'start' }); }
  };
  openHashTarget();
  window.addEventListener('hashchange', openHashTarget);

  document.querySelectorAll('[data-select-all]').forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      document.querySelectorAll('input[name="order_ids[]"]').forEach(function (box) { box.checked = toggle.checked; });
    });
  });

  /* ---------- Firmenshop: Kategorie-Filter ---------- */
  document.querySelectorAll('.shop-category-filter').forEach(function (filter) {
    var buttons = filter.querySelectorAll('button[data-filter]');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        var wanted = btn.dataset.filter;
        document.querySelectorAll('.product-card[data-category]').forEach(function (card) {
          card.style.display = (wanted === 'all' || card.dataset.category === wanted) ? '' : 'none';
        });
      });
    });
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

  /* ---------- Firmenshop: Mengen-Stepper und Live-Summe ---------- */
  var qtyInputs = document.querySelectorAll('.shop-qty');
  if (qtyInputs.length) {
    var panel = document.querySelector('.shop-order-panel[data-shipping-enabled]');
    var grossFromNet = function (net, vat) { return Math.round(net * (1 + (vat / 100))); };

    var updateSelection = function () {
      var count = 0, gross = 0, employee = 0, company = 0;
      qtyInputs.forEach(function (input) {
        var max = parseInt(input.max || '10', 10);
        var qty = Math.max(0, parseInt(input.value || '0', 10) || 0);
        if (qty > max) { qty = max; input.value = String(max); }
        count += qty;
        gross += (parseInt(input.dataset.gross || '0', 10) || 0) * qty;
        employee += (parseInt(input.dataset.employee || '0', 10) || 0) * qty;
        company += (parseInt(input.dataset.company || '0', 10) || 0) * qty;
        var card = input.closest('.company-product-card');
        if (card) card.classList.toggle('is-selected', qty > 0);
      });

      var shippingGross = 0;
      if (panel && panel.dataset.shippingEnabled === '1' && count > 0) {
        var freeFrom = parseInt(panel.dataset.freeShipping || '0', 10) || 0;
        if (!(freeFrom > 0 && gross >= freeFrom)) {
          shippingGross = grossFromNet(parseInt(panel.dataset.shippingNet || '0', 10) || 0, parseFloat(panel.dataset.shippingVat || '19') || 19);
          if (panel.dataset.shippingPayer === 'employee') employee += shippingGross; else company += shippingGross;
        }
      }

      var map = {
        '[data-selection-count]': String(count),
        '[data-summary-gross]': euro(gross),
        '[data-summary-shipping]': euro(shippingGross),
        '[data-summary-employee]': euro(employee),
        '[data-summary-company]': euro(company),
        '[data-summary-total]': euro(gross + shippingGross)
      };
      Object.keys(map).forEach(function (sel) {
        document.querySelectorAll(sel).forEach(function (el) { el.textContent = map[sel]; });
      });

      var budgetBox = document.querySelector('[data-budget-remaining]');
      var budgetWarning = document.querySelector('[data-budget-warning]');
      var submit = document.querySelector('.shop-submit');
      var over = false;
      if (budgetBox) {
        var remaining = parseInt(budgetBox.dataset.budgetRemaining || '0', 10) || 0;
        over = company > remaining;
        if (budgetWarning) budgetWarning.hidden = !over;
      }
      if (submit) submit.disabled = count === 0 || over;
      document.querySelectorAll('[data-mobile-cart]').forEach(function (bar) { bar.classList.toggle('show', count > 0); });
    };

    qtyInputs.forEach(function (input) {
      input.addEventListener('input', updateSelection);
      input.addEventListener('change', updateSelection);
    });
    document.querySelectorAll('[data-qty-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.qtyTarget || '');
        if (!input || input.disabled) return;
        var max = parseInt(input.max || '10', 10), step = parseInt(button.dataset.qtyStep, 10) || 0;
        input.value = String(Math.min(max, Math.max(0, (parseInt(input.value, 10) || 0) + step)));
        updateSelection();
      });
    });
    document.querySelectorAll('[data-scroll-checkout]').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = document.querySelector('.shop-order-panel');
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    var orderForm = document.querySelector('form[data-order-form]');
    if (orderForm) {
      orderForm.addEventListener('submit', function () {
        var submit = orderForm.querySelector('.shop-submit');
        if (submit) { submit.disabled = true; submit.textContent = 'Wird gesendet …'; }
      });
    }
    updateSelection();
  }

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
      var ek = chosen.length ? Math.max.apply(null, chosen.map(function (c) { return parseFloat(c.dataset.ek) || 0; })) : parseFloat(form.dataset.ek) || 0;
      var mode = form.querySelector('input[name="price_mode"]:checked').value;
      if (factor) factor.disabled = mode !== 'factor';
      if (fixed) fixed.disabled = mode !== 'fixed';
      var vk = mode === 'fixed' ? num(fixed) : Math.ceil(ek * num(factor) * 10 - 1e-9) / 10;
      out.ek && (out.ek.textContent = eur(ek));
      out.vk && (out.vk.textContent = vk > 0 ? eur(vk) : '–');
      out.brutto && (out.brutto.textContent = vk > 0 ? eur(vk * 1.19) : '–');
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
