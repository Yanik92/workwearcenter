/*
 * WorkwearCenter – Firmenshop (Mitarbeitende und Verantwortliche).
 * Wird nur auf den Shopseiten geladen. Keine Inline-Skripte (CSP script-src 'self').
 * Alle Preise kommen vom Server (data-Attribute); die Bestellung prüft der Server erneut.
 */
(function () {
  'use strict';
  document.documentElement.classList.add('js-shop');

  var euro = function (cents) {
    return (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  };
  var int = function (v) { return parseInt(v || '0', 10) || 0; };

  /* ---------- Menü am Handy ---------- */
  var side = document.querySelector('[data-cp-side]');
  var toggle = side && side.querySelector('.cp-menu-toggle');
  if (side && toggle) {
    side.classList.add('js-menu');
    toggle.hidden = false;
    var setMenu = function (open) {
      side.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    toggle.addEventListener('click', function () { setMenu(!side.classList.contains('open')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && side.classList.contains('open')) { setMenu(false); toggle.focus(); }
    });
    document.addEventListener('click', function (e) {
      if (side.classList.contains('open') && !side.contains(e.target)) setMenu(false);
    });
  }

  /* ---------- Ansicht vorne/hinten/Ärmel: Zustand für Screenreader ---------- */
  document.querySelectorAll('[data-show-side]').forEach(function (b) {
    b.setAttribute('aria-pressed', b.classList.contains('active') ? 'true' : 'false');
    b.addEventListener('click', function () {
      var bar = b.parentElement;
      if (bar) bar.querySelectorAll('[data-show-side]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    });
  });

  /* ---------- Kategorie-Filter ---------- */
  document.querySelectorAll('.sp-filters').forEach(function (filter) {
    var buttons = filter.querySelectorAll('button[data-filter]');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.classList.toggle('active', b === btn); b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
        var wanted = btn.dataset.filter;
        document.querySelectorAll('.sp-card[data-category]').forEach(function (card) {
          card.hidden = !(wanted === 'all' || card.dataset.category === wanted);
        });
      });
    });
  });

  var form = document.querySelector('form[data-order-form]');
  if (!form) return;

  var qtyInputs = Array.prototype.slice.call(form.querySelectorAll('.shop-qty'));
  var panel = form.querySelector('.shop-order-panel');
  var toastEl = form.querySelector('[data-shop-toast]');
  var toastTimer = null;
  var toast = function (text) {
    if (!toastEl) return;
    toastEl.textContent = text;
    toastEl.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
  };

  /* ---------- Adresse ?artikel=… pflegen (eigene URL je Artikel) ---------- */
  var setArticleParam = function (id) {
    if (!window.history || !window.URL) return;
    var url = new URL(window.location.href);
    if (id) url.searchParams.set('artikel', id); else url.searchParams.delete('artikel');
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
  };

  /* ---------- Farbauswahl in der Detailansicht ---------- */
  var activeVariant = function (dialog) {
    var panelEl = dialog.querySelector('[data-variant-panel].is-active') || dialog.querySelector('[data-variant-panel]');
    return panelEl ? panelEl.dataset.variantPanel : '';
  };
  var selectVariant = function (dialog, id, focus) {
    dialog.querySelectorAll('[data-variant-panel]').forEach(function (p) { p.classList.toggle('is-active', p.dataset.variantPanel === id); });
    dialog.querySelectorAll('[data-variant-visual]').forEach(function (v) { v.classList.toggle('is-active', v.dataset.variantVisual === id); });
    dialog.querySelectorAll('[data-variant]').forEach(function (b) {
      var on = b.dataset.variant === id;
      b.setAttribute('aria-checked', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
      if (on) {
        var name = dialog.querySelector('[data-color-name]');
        if (name) name.textContent = b.dataset.color || '';
        if (focus) b.focus();
      }
    });
    if (dialog.open) setArticleParam(id);
  };
  document.querySelectorAll('[data-detail]').forEach(function (dialog) {
    var radios = Array.prototype.slice.call(dialog.querySelectorAll('[data-variant]'));
    radios.forEach(function (b, i) {
      b.addEventListener('click', function () { selectVariant(dialog, b.dataset.variant, false); });
      b.addEventListener('keydown', function (e) {
        var step = (e.key === 'ArrowRight' || e.key === 'ArrowDown') ? 1 : (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ? -1 : 0;
        if (!step) return;
        e.preventDefault();
        var next = radios[(i + step + radios.length) % radios.length];
        selectVariant(dialog, next.dataset.variant, true);
      });
    });
  });

  /* ---------- Detailansicht öffnen/schließen ---------- */
  var lastTrigger = null;
  var supportsModal = typeof HTMLDialogElement === 'function' && typeof HTMLDialogElement.prototype.showModal === 'function';
  var openDetail = function (dialog, trigger) {
    if (!dialog) return;
    lastTrigger = trigger || null;
    if (supportsModal) { if (dialog.open) dialog.close(); dialog.showModal(); } else dialog.setAttribute('open', '');
    document.documentElement.classList.add('sp-modal-open');
    setArticleParam(activeVariant(dialog));
    var first = dialog.querySelector('[data-variant][aria-checked="true"]') || dialog.querySelector('[data-variant-panel].is-active select') || dialog.querySelector('select');
    if (first) window.setTimeout(function () { first.focus(); }, 30);
  };
  var closeDetail = function (dialog) {
    if (supportsModal && dialog.open) dialog.close(); else { dialog.removeAttribute('open'); afterClose(dialog); }
  };
  var afterClose = function () {
    document.documentElement.classList.remove('sp-modal-open');
    setArticleParam('');
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
  };
  document.querySelectorAll('[data-detail]').forEach(function (dialog) {
    dialog.addEventListener('close', function () { afterClose(dialog); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDetail(dialog); });
    dialog.querySelectorAll('[data-close-detail]').forEach(function (b) { b.addEventListener('click', function () { closeDetail(dialog); }); });
  });
  document.querySelectorAll('[data-open-product]').forEach(function (trigger) {
    trigger.addEventListener('click', function (e) {
      var dialog = document.getElementById(trigger.dataset.openProduct);
      if (!dialog) return;
      e.preventDefault();
      var card = trigger.closest('.sp-card');
      openDetail(dialog, card ? card.querySelector('.sp-add') : trigger);
    });
  });
  // Direktaufruf ?artikel=…: Server liefert den Dialog geöffnet aus, hier wird er modal.
  var preOpen = document.querySelector('[data-detail][open]');
  if (preOpen && supportsModal) { preOpen.close(); openDetail(preOpen, null); }

  /* ---------- Mengen, Summen, Auswahl ---------- */
  var shareActive = qtyInputs.some(function (i) { return int(i.dataset.employee) > 0; });
  var linesEl = form.querySelector('[data-cart-lines]');
  var emptyEl = form.querySelector('[data-cart-empty]');
  var grossFromNet = function (net, vat) { return Math.round(net * (1 + (vat / 100))); };

  var renderLines = function () {
    if (!linesEl) return;
    linesEl.textContent = '';
    qtyInputs.forEach(function (input) {
      var qty = int(input.value);
      if (qty <= 0) return;
      var sizeSel = document.getElementById(input.dataset.sizeTarget || '');
      var li = document.createElement('li');
      li.className = 'sp-cart-line';
      var text = document.createElement('div');
      var strong = document.createElement('strong');
      strong.textContent = qty + '× ' + (input.dataset.lineName || 'Artikel');
      var small = document.createElement('small');
      small.textContent = 'Größe ' + (sizeSel ? sizeSel.value : '–') + ' · je ' + euro(int(input.dataset.gross)) + ' brutto (' + euro(int(input.dataset.net)) + ' netto)';
      text.appendChild(strong); text.appendChild(small);
      var price = document.createElement('b');
      price.textContent = shareActive ? 'Dein Anteil ' + euro(int(input.dataset.employee) * qty) : euro(int(input.dataset.gross) * qty);
      var actions = document.createElement('div');
      actions.className = 'sp-cart-line-actions';
      var edit = document.createElement('button');
      edit.type = 'button'; edit.textContent = 'Ändern';
      edit.setAttribute('aria-label', (input.dataset.lineName || 'Artikel') + ' ändern');
      edit.addEventListener('click', function () {
        var dialog = document.getElementById(input.dataset.group || '');
        if (!dialog) return;
        selectVariant(dialog, input.id.replace('qty-', ''), false);
        openDetail(dialog, edit);
      });
      var remove = document.createElement('button');
      remove.type = 'button'; remove.textContent = 'Entfernen';
      remove.setAttribute('aria-label', (input.dataset.lineName || 'Artikel') + ' entfernen');
      remove.addEventListener('click', function () {
        input.value = '0'; update();
        toast((input.dataset.lineName || 'Artikel') + ' wurde entfernt.');
        if (panel) panel.focus();
      });
      actions.appendChild(edit); actions.appendChild(remove);
      li.appendChild(text); li.appendChild(price); li.appendChild(actions);
      linesEl.appendChild(li);
    });
  };

  var update = function () {
    var count = 0, net = 0, gross = 0, employee = 0, company = 0, groups = {};
    qtyInputs.forEach(function (input) {
      var max = int(input.max || '10');
      var qty = Math.max(0, int(input.value));
      if (qty > max) { qty = max; input.value = String(max); }
      count += qty;
      net += int(input.dataset.net) * qty;
      gross += int(input.dataset.gross) * qty;
      employee += int(input.dataset.employee) * qty;
      company += int(input.dataset.company) * qty;
      if (input.dataset.group) groups[input.dataset.group] = (groups[input.dataset.group] || 0) + qty;
    });

    var shippingGross = 0, shippingNet = 0;
    if (panel && panel.dataset.shippingEnabled === '1' && count > 0) {
      var freeFrom = int(panel.dataset.freeShipping);
      if (!(freeFrom > 0 && gross >= freeFrom)) {
        shippingNet = int(panel.dataset.shippingNet);
        shippingGross = grossFromNet(shippingNet, parseFloat(panel.dataset.shippingVat || '19') || 19);
        if (panel.dataset.shippingPayer === 'employee') employee += shippingGross; else company += shippingGross;
      }
    }

    var map = {
      '[data-selection-count]': String(count),
      '[data-summary-net]': euro(net + shippingNet),
      '[data-summary-gross]': euro(gross),
      '[data-summary-shipping]': euro(shippingGross),
      '[data-summary-employee]': euro(employee),
      '[data-summary-company]': euro(company),
      '[data-summary-total]': euro(gross + shippingGross)
    };
    Object.keys(map).forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) { el.textContent = map[sel]; });
    });

    document.querySelectorAll('.sp-card[data-group]').forEach(function (card) {
      var n = groups[card.dataset.group] || 0;
      card.classList.toggle('is-selected', n > 0);
      var badge = card.querySelector('[data-group-count]');
      if (badge) { badge.hidden = n === 0; badge.textContent = n + ' ausgewählt'; }
    });

    var budgetBox = document.querySelector('[data-budget-remaining]');
    var warning = form.querySelector('[data-budget-warning]');
    var submit = form.querySelector('.shop-submit');
    var over = false;
    if (budgetBox) {
      over = company > int(budgetBox.dataset.budgetRemaining);
      if (warning) warning.hidden = !over;
    }
    if (submit) submit.disabled = count === 0 || over;
    if (emptyEl) emptyEl.hidden = count > 0;

    // Bestellleiste am Handy erst zeigen, wenn mindestens ein Artikel gewählt ist.
    document.querySelectorAll('[data-mobile-cart]').forEach(function (bar) {
      bar.classList.toggle('show', count > 0);
      bar.setAttribute('aria-hidden', count > 0 ? 'false' : 'true');
      bar.querySelectorAll('button').forEach(function (b) { b.tabIndex = count > 0 ? 0 : -1; });
    });
    renderLines();
  };

  qtyInputs.forEach(function (input) {
    input.addEventListener('input', update);
    input.addEventListener('change', update);
  });
  form.querySelectorAll('select[name^="size["]').forEach(function (s) { s.addEventListener('change', renderLines); });
  form.querySelectorAll('[data-qty-step]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.qtyTarget || '');
      if (!input || input.disabled) return;
      var max = int(input.max || '10');
      input.value = String(Math.min(max, Math.max(0, int(input.value) + int(button.dataset.qtyStep))));
      update();
    });
  });
  document.querySelectorAll('[data-add-detail]').forEach(function (button) {
    button.addEventListener('click', function () {
      var dialog = button.closest('[data-detail]');
      var id = activeVariant(dialog);
      var input = document.getElementById('qty-' + id);
      if (!input || input.disabled) { toast('Für diesen Artikel ist das Stücklimit erreicht.'); return; }
      if (int(input.value) === 0) input.value = '1';
      update();
      var size = document.getElementById(input.dataset.sizeTarget || '');
      var msg = input.value + '× ' + (input.dataset.lineName || 'Artikel') + (size ? ' (Größe ' + size.value + ')' : '') + ' ist in deiner Auswahl.';
      closeDetail(dialog);
      toast(msg);
    });
  });
  document.querySelectorAll('[data-scroll-checkout]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); panel.focus({ preventScroll: true }); }
    });
  });
  form.addEventListener('submit', function () {
    var submit = form.querySelector('.shop-submit');
    if (submit) { window.setTimeout(function () { submit.disabled = true; submit.textContent = 'Wird gesendet …'; }, 0); }
  });
  update();
})();
