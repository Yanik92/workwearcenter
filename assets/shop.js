/*
 * WorkwearCenter – Firmenshop (Mitarbeitende und Verantwortliche).
 * Wird nur auf den Shopseiten geladen. Keine Inline-Skripte (CSP script-src 'self').
 * Alle Preise kommen vom Server (data-Attribute); die Bestellung prüft der Server erneut.
 */
(function () {
  'use strict';
  document.documentElement.classList.add('js-shop');

  /* 10.12: Texte und Zahlenformat der Shopsprache aus dem Seitenkopf (data-yw-i18n, kein Inline-Skript). */
  var I18N = { lang: 'de', dec: ',', thou: '.', money: '{n} €', t: {} };
  try { var raw = document.documentElement.getAttribute('data-yw-i18n'); if (raw) I18N = JSON.parse(raw); } catch (e) { /* Deutsch */ }
  var DEFAULTS = {
    item: 'Artikel', size: 'Größe {size}', each_net: 'je {amount} netto', line_total: 'zusammen {amount} brutto',
    your_share: 'Dein Anteil {amount}', change: 'Ändern', change_label: '{name} ändern', remove: 'Entfernen',
    remove_label: '{name} entfernen', removed: '{name} wurde entfernt.', limit_reached: 'Für diesen Artikel ist das Stücklimit erreicht.',
    added: '{qty}× {name} ist in deiner Auswahl.', added_size: '{qty}× {name} (Größe {size}) ist in deiner Auswahl.',
    selected: '{n} ausgewählt', sending: 'Wird gesendet …'
  };
  var pluralIndex = function (lang, n) {
    n = Math.abs(n); var m10 = n % 10, m100 = n % 100;
    if (lang === 'pl') return n === 1 ? 0 : (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) ? 1 : 2;
    if (lang === 'ru') return (m10 === 1 && m100 !== 11) ? 0 : (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) ? 1 : 2;
    if (lang === 'ro') return n === 1 ? 0 : (n === 0 || (m100 >= 1 && m100 <= 19)) ? 1 : 2;
    return n === 1 ? 0 : 1;
  };
  var tr = function (key, params) {
    params = params || {};
    var text = (I18N.t && I18N.t[key] !== undefined) ? I18N.t[key] : DEFAULTS[key];
    if (Array.isArray(text)) text = text[Math.min(pluralIndex(I18N.lang, parseInt(params.n || 0, 10)), text.length - 1)];
    return String(text === undefined ? key : text).replace(/\{(\w+)\}/g, function (m, name) { return params[name] !== undefined ? String(params[name]) : m; });
  };
  // Gleiches Format wie yw_money() in app/i18n.php (ganze Cent, Beträge immer in Euro)
  var euro = function (cents) {
    var negative = cents < 0, abs = Math.abs(Math.round(cents));
    var whole = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, I18N.thou);
    var amount = whole + I18N.dec + ('0' + (abs % 100)).slice(-2);
    return (negative ? '-' : '') + String(I18N.money || '{n} €').replace('{n}', amount);
  };
  var int = function (v) { return parseInt(v || '0', 10) || 0; };

  /* ---------- 10.16: Konto- und Sprachmenü in der Kopfzeile schließen (Klick daneben, Escape) ---------- */
  var popups = Array.prototype.slice.call(document.querySelectorAll('details.cp-account, details.yw-lang'));
  if (popups.length) {
    document.addEventListener('click', function (e) {
      popups.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      popups.forEach(function (d) {
        if (!d.open) return;
        d.open = false;
        var s = d.querySelector('summary'); if (s) s.focus();
      });
    });
    popups.forEach(function (d) {
      d.addEventListener('toggle', function () { if (d.open) popups.forEach(function (o) { if (o !== d) o.open = false; }); });
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

  /* ---------- Gebündelte Team-Bestellung im Verantwortlichen-Portal ---------- */
  document.querySelectorAll('form[data-team-order]').forEach(function (teamForm) {
    var peopleBox = teamForm.querySelector('[data-team-people]');
    var personTemplate = teamForm.querySelector('template[data-team-person-template]') || document.querySelector('template[data-team-person-template]');
    var feedback = teamForm.querySelector('[data-team-feedback]');
    var catalogData = {};
    try {
      var bytes = Uint8Array.from(atob(teamForm.dataset.catalog || ''), function (c) { return c.charCodeAt(0); });
      catalogData = JSON.parse(new TextDecoder('utf-8').decode(bytes));
    } catch (e) { catalogData = {}; }
    var catalogs = catalogData.profiles || {};
    var accountCatalogs = catalogData.accounts || {};
    var productsFor = function (account) { return catalogs[accountCatalogs[String(account)]] || []; };
    var limit = int(teamForm.dataset.maxPeople || '20');
    var message = function (key) { return teamForm.dataset[key] || ''; };
    var tell = function (text) { if (feedback) feedback.textContent = text; };
    var cards = function () { return Array.prototype.slice.call(peopleBox.querySelectorAll('[data-team-person]')); };
    var refreshNames = function () {
      cards().forEach(function (person, personIndex) {
        person.dataset.teamIndex = String(personIndex);
        var number = person.querySelector('[data-team-number]');
        if (number) number.textContent = String(personIndex + 1).padStart(2, '0');
        var account = person.querySelector('[data-team-account]');
        if (account) account.name = 'team[' + personIndex + '][employee_id]';
        person.querySelectorAll('[data-team-item]').forEach(function (row, itemIndex) {
          var base = 'team[' + personIndex + '][items][' + itemIndex + ']';
          var product = row.querySelector('[data-team-product]');
          var size = row.querySelector('[data-team-size]');
          var quantity = row.querySelector('[data-team-quantity]');
          if (product) product.name = base + '[product_id]';
          if (size) size.name = base + '[size]';
          if (quantity) quantity.name = base + '[quantity]';
        });
      });
      refreshAccounts();
    };
    var refreshAccounts = function () {
      var selected = cards().map(function (person) { return person.querySelector('[data-team-account]').value; }).filter(Boolean);
      cards().forEach(function (person) {
        var current = person.querySelector('[data-team-account]');
        Array.prototype.forEach.call(current.options, function (option) {
          option.disabled = !!option.value && option.value !== current.value && selected.indexOf(option.value) !== -1;
        });
      });
    };
    var updateSizes = function (row) {
      var product = row.querySelector('[data-team-product]');
      var size = row.querySelector('[data-team-size]');
      var account = row.closest('[data-team-person]').querySelector('[data-team-account]').value;
      var selected = product.value;
      var item = productsFor(account).find(function (entry) { return String(entry.id) === String(selected); });
      size.innerHTML = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = size.dataset.placeholder || '';
      size.appendChild(placeholder);
      if (!item) { size.disabled = true; return; }
      (item.sizes || []).forEach(function (label) {
        var option = document.createElement('option');
        option.value = label; option.textContent = label; size.appendChild(option);
      });
      size.disabled = false;
    };
    var updateProducts = function (person) {
      var account = person.querySelector('[data-team-account]').value;
      person.querySelectorAll('[data-team-item]').forEach(function (row) {
        var product = row.querySelector('[data-team-product]');
        var size = row.querySelector('[data-team-size]');
        var placeholder = product.options[0] ? product.options[0].textContent : '';
        product.innerHTML = '';
        var first = document.createElement('option'); first.value = ''; first.textContent = placeholder; product.appendChild(first);
        productsFor(account).forEach(function (item) {
          var option = document.createElement('option');
          option.value = String(item.id); option.textContent = item.name + ' · ' + item.price + ' (' + (teamForm.dataset.priceUnit || 'brutto je Stück') + ')'; product.appendChild(option);
        });
        product.disabled = !account;
        size.innerHTML = '';
        var sizeFirst = document.createElement('option'); sizeFirst.value = ''; sizeFirst.textContent = size.dataset.placeholder || ''; size.appendChild(sizeFirst);
        size.disabled = true;
      });
    };
    var populateProductOptions = function (row, account) {
      var product = row.querySelector('[data-team-product]');
      var size = row.querySelector('[data-team-size]');
      var placeholder = product.options[0] ? product.options[0].textContent : '';
      product.innerHTML = '';
      var first = document.createElement('option'); first.value = ''; first.textContent = placeholder; product.appendChild(first);
      productsFor(account).forEach(function (item) {
        var option = document.createElement('option');
        option.value = String(item.id); option.textContent = item.name + ' · ' + item.price + ' (' + (teamForm.dataset.priceUnit || 'brutto je Stück') + ')'; product.appendChild(option);
      });
      product.disabled = !account;
      size.innerHTML = '';
      var sizeFirst = document.createElement('option'); sizeFirst.value = ''; sizeFirst.textContent = size.dataset.placeholder || ''; size.appendChild(sizeFirst);
      size.disabled = true;
    };
    var addItem = function (person) {
      var host = person.querySelector('[data-team-items]');
      var template = person.querySelector('template[data-team-item-template]');
      if (!template || host.children.length >= 10) { tell(message('limitMessage')); return; }
      var row = template.content.firstElementChild.cloneNode(true);
      var size = row.querySelector('[data-team-size]');
      size.dataset.placeholder = size.options[0] ? size.options[0].textContent : '';
      host.appendChild(row);
      populateProductOptions(row, person.querySelector('[data-team-account]').value);
      refreshNames();
    };
    var addPerson = function (accountId) {
      if (cards().length >= limit) { tell(message('limitMessage')); return null; }
      var person = personTemplate.content.firstElementChild.cloneNode(true);
      peopleBox.appendChild(person);
      var account = person.querySelector('[data-team-account]');
      if (accountId) account.value = String(accountId);
      var template = person.querySelector('template[data-team-item-template]');
      var firstRow = template.content.firstElementChild.cloneNode(true);
      var size = firstRow.querySelector('[data-team-size]');
      size.dataset.placeholder = size.options[0] ? size.options[0].textContent : '';
      person.querySelector('[data-team-items]').appendChild(firstRow);
      populateProductOptions(firstRow, account.value);
      refreshNames();
      return person;
    };
    teamForm.querySelector('[data-team-add-person]').addEventListener('click', function () {
      var person = addPerson('');
      if (person) { tell(''); person.querySelector('[data-team-account]').focus(); }
    });
    teamForm.querySelector('[data-team-add-all]').addEventListener('click', function () {
      var optionNodes = personTemplate.content.querySelector('[data-team-account]').options;
      var ids = Array.prototype.map.call(optionNodes, function (option) { return option.value; }).filter(Boolean);
      if (ids.length > limit) { tell(message('limitMessage')); return; }
      cards().forEach(function (person) {
        var hasProduct = Array.prototype.some.call(person.querySelectorAll('[data-team-product]'), function (product) { return !!product.value; });
        if (!person.querySelector('[data-team-account]').value && !hasProduct) person.remove();
      });
      var selectedIds = cards().map(function (person) { return person.querySelector('[data-team-account]').value; }).filter(Boolean);
      var missing = ids.filter(function (id) { return selectedIds.indexOf(id) === -1; });
      if (cards().length + missing.length > limit) { tell(message('limitMessage')); return; }
      missing.forEach(function (id) { addPerson(id); });
      tell('');
    });
    peopleBox.addEventListener('change', function (event) {
      if (event.target.matches('[data-team-account]')) {
        updateProducts(event.target.closest('[data-team-person]'));
        refreshAccounts();
      }
      if (event.target.matches('[data-team-product]')) updateSizes(event.target.closest('[data-team-item]'));
    });
    peopleBox.addEventListener('click', function (event) {
      var removeItem = event.target.closest('[data-team-remove-item]');
      var removePerson = event.target.closest('[data-team-remove]');
      if (removeItem) { removeItem.closest('[data-team-item]').remove(); refreshNames(); return; }
      if (removePerson) { removePerson.closest('[data-team-person]').remove(); refreshNames(); return; }
      var addLine = event.target.closest('[data-team-add-item]');
      if (addLine) addItem(addLine.closest('[data-team-person]'));
    });
    teamForm.addEventListener('submit', function (event) {
      var selectedPeople = {}, positions = 0, invalid = '';
      cards().forEach(function (person) {
        var account = person.querySelector('[data-team-account]').value;
        person.querySelectorAll('[data-team-item]').forEach(function (row) {
          var product = row.querySelector('[data-team-product]').value;
          var size = row.querySelector('[data-team-size]').value;
          var qty = int(row.querySelector('[data-team-quantity]').value);
          if (product) {
            positions++;
            if (!account) invalid = message('duplicateMessage');
            else if (!size) invalid = message('sizeMessage');
            else if (qty < 1 || qty > 10) invalid = message('limitMessage');
          }
        });
        if (account) {
          if (selectedPeople[account]) invalid = message('duplicateMessage');
          selectedPeople[account] = true;
        }
      });
      if (!positions && !invalid) invalid = message('emptyMessage');
      if (invalid) { event.preventDefault(); tell(invalid); if (feedback) feedback.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });
    addPerson('');
  });

  // 10.21: Erfolgsbild nach dem Absenden als modaler Dialog (Fokus bleibt darin, Esc schließt).
  var done = document.querySelector('dialog[data-done][open]');
  if (done && typeof done.showModal === 'function') {
    try { done.close(); done.showModal(); } catch (e) { done.setAttribute('open', ''); }
  }

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

  /* 10.11: gleiche Rechnung wie app/modules/pricing.php (yw_calc_items) – Netto × Menge je Position,
     Umsatzsteuer je Steuersatz auf die Nettosumme (wie Lexware Office), Verteilung der Steuer auf die
     Positionen nach größten Resten, Eigenanteil je Position. Ganze Cent, keine Gleitkommarundung.
     Gleichstand bei den Resten: größerer Positionsbetrag zuerst – unabhängig von der Artikelreihenfolge. */
  var bpOf = function (rate) { var v = parseFloat(rate); return isNaN(v) ? 0 : Math.max(0, Math.min(10000, Math.round(v * 100))); };
  var taxFor = function (net, bp) { return Math.floor((Math.max(0, net) * bp + 5000) / 10000); };
  var lineValues = {};
  var calcLines = function () {
    var lines = [], groups = {}, netByRate = {};
    qtyInputs.forEach(function (input) {
      var qty = Math.max(0, int(input.value));
      if (qty <= 0) return;
      lines.push({ input: input, qty: qty, net: int(input.dataset.net) * qty, bp: bpOf(input.dataset.vat === undefined ? '19' : input.dataset.vat), share: bpOf(input.dataset.share || '0'), tax: 0, rem: 0 });
    });
    lines.forEach(function (line, i) { (groups[line.bp] = groups[line.bp] || []).push(i); });
    Object.keys(groups).forEach(function (key) {
      var bp = parseInt(key, 10), idx = groups[key], sum = 0, floors = 0;
      idx.forEach(function (i) { var exact = lines[i].net * bp; lines[i].tax = Math.floor(exact / 10000); lines[i].rem = exact % 10000; floors += lines[i].tax; sum += lines[i].net; });
      var extra = taxFor(sum, bp) - floors;
      idx.slice().sort(function (a, b) { return (lines[b].rem - lines[a].rem) || (lines[b].net - lines[a].net) || (a - b); }).forEach(function (i) { if (extra > 0) { lines[i].tax++; extra--; } });
      netByRate[bp] = sum;
    });
    var totals = { net: 0, gross: 0, employee: 0, company: 0, netByRate: netByRate, count: lines.length };
    lineValues = {};
    lines.forEach(function (line) {
      line.gross = line.net + line.tax;
      line.employee = Math.floor((line.gross * line.share + 5000) / 10000);
      line.company = line.gross - line.employee;
      totals.net += line.net; totals.gross += line.gross; totals.employee += line.employee; totals.company += line.company;
      lineValues[line.input.id] = line;
    });
    return totals;
  };

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
      var itemName = input.dataset.lineName || tr('item');
      strong.textContent = qty + '× ' + itemName;
      var small = document.createElement('small');
      var lv = lineValues[input.id] || { gross: int(input.dataset.gross) * qty, employee: int(input.dataset.employee) * qty };
      // 10.12: Netto je Stück + Positionsbetrag (brutto wird je Steuersatz gerundet, ein Stück-Brutto wäre irreführend)
      var parts = [tr('size', { size: sizeSel ? sizeSel.value : '–' }), tr('each_net', { amount: euro(int(input.dataset.net)) })];
      if (shareActive) parts.push(tr('line_total', { amount: euro(lv.gross) }));
      small.textContent = parts.join(' · ');
      text.appendChild(strong); text.appendChild(small);
      var price = document.createElement('b');
      price.textContent = shareActive ? tr('your_share', { amount: euro(lv.employee) }) : euro(lv.gross);
      var actions = document.createElement('div');
      actions.className = 'sp-cart-line-actions';
      var edit = document.createElement('button');
      edit.type = 'button'; edit.textContent = tr('change');
      edit.setAttribute('aria-label', tr('change_label', { name: itemName }));
      edit.addEventListener('click', function () {
        var dialog = document.getElementById(input.dataset.group || '');
        if (!dialog) return;
        selectVariant(dialog, input.id.replace('qty-', ''), false);
        openDetail(dialog, edit);
      });
      var remove = document.createElement('button');
      remove.type = 'button'; remove.textContent = tr('remove');
      remove.setAttribute('aria-label', tr('remove_label', { name: itemName }));
      remove.addEventListener('click', function () {
        input.value = '0'; update();
        toast(tr('removed', { name: itemName }));
        if (panel) panel.focus();
      });
      actions.appendChild(edit); actions.appendChild(remove);
      li.appendChild(text); li.appendChild(price); li.appendChild(actions);
      linesEl.appendChild(li);
    });
  };

  var update = function () {
    var count = 0, groups = {};
    qtyInputs.forEach(function (input) {
      var max = int(input.max || '10');
      var qty = Math.max(0, int(input.value));
      if (qty > max) { qty = max; input.value = String(max); }
      count += qty;
      if (input.dataset.group) groups[input.dataset.group] = (groups[input.dataset.group] || 0) + qty;
    });
    var t = calcLines();
    var net = t.net, gross = t.gross, employee = t.employee, company = t.company;

    // Versand wie shipping_breakdown(): eigene Position, Steuer = Steuer des Satzes mit minus ohne Versand.
    var shippingGross = 0, shippingNet = 0;
    if (panel && panel.dataset.shippingEnabled === '1' && count > 0) {
      var freeFrom = int(panel.dataset.freeShipping);
      if (!(freeFrom > 0 && gross >= freeFrom)) {
        shippingNet = Math.max(0, int(panel.dataset.shippingNet));
        var shippingVat = parseFloat(panel.dataset.shippingVat === undefined ? '19' : panel.dataset.shippingVat);
        if (isNaN(shippingVat) || shippingVat < 0 || shippingVat > 100) shippingVat = 19;
        var shippingBp = bpOf(shippingVat), itemsNet = t.netByRate[shippingBp] || 0;
        shippingGross = t.count ? shippingNet + taxFor(itemsNet + shippingNet, shippingBp) - taxFor(itemsNet, shippingBp) : grossFromNet(shippingNet, shippingVat);
        if (panel.dataset.shippingPayer === 'employee') employee += shippingGross; else company += shippingGross;
      }
    }

    var map = {
      '[data-selection-count]': String(count),
      '[data-summary-net]': euro(net), // Warenwert ohne Versand (wie „Warenwert brutto“)
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
      if (badge) { badge.hidden = n === 0; badge.textContent = tr('selected', { n: n }); }
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
  var sizeMissing = function (dialog, size, input) {
    var msg = tr('size_missing', { name: input.dataset.lineName || tr('item') });
    var status = dialog ? dialog.querySelector('[data-detail-status]') : null;
    if (status) status.textContent = msg;
    size.setAttribute('aria-invalid', 'true');
    if (size.ywChips) size.ywChips.sync();
    window.setTimeout(function () { if (size.ywChips) size.ywChips.focus(); else size.focus(); }, 60);
    toast(msg);
  };
  form.querySelectorAll('select[data-size-select]').forEach(function (s) {
    s.addEventListener('change', function () {
      s.removeAttribute('aria-invalid');
      var dialog = s.closest('[data-detail]');
      var status = dialog ? dialog.querySelector('[data-detail-status]') : null;
      if (status) status.textContent = '';
    });
  });
  // 10.21: Größen als Knöpfe. Das Auswahlfeld bleibt die Quelle für Formular und Prüfung (ohne JavaScript sichtbar).
  form.querySelectorAll('select[data-size-select]').forEach(function (s) {
    var options = Array.prototype.filter.call(s.options, function (o) { return o.value !== ''; });
    var label = s.closest('label');
    if (!label || options.length < 2 || options.length > 18) return;
    var title = label.querySelector('span');
    var box = document.createElement('div');
    box.className = 'sp-size-chips';
    box.setAttribute('role', 'radiogroup');
    if (title) { title.id = s.id + '-label'; box.setAttribute('aria-labelledby', title.id); }
    var buttons = options.map(function (o) {
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = o.textContent; b.dataset.value = o.value;
      b.setAttribute('role', 'radio'); b.disabled = s.disabled;
      box.appendChild(b);
      return b;
    });
    var sync = function () {
      var any = buttons.some(function (b) { return b.dataset.value === s.value; });
      buttons.forEach(function (b, i) {
        var on = b.dataset.value === s.value;
        b.setAttribute('aria-checked', on ? 'true' : 'false');
        b.tabIndex = on || (!any && i === 0) ? 0 : -1;
      });
      box.classList.toggle('is-invalid', s.getAttribute('aria-invalid') === 'true');
    };
    var pick = function (b) {
      if (!b || b.disabled) return;
      s.value = b.dataset.value;
      s.dispatchEvent(new Event('change', { bubbles: true }));
      sync(); b.focus();
    };
    box.addEventListener('click', function (e) { pick(e.target.closest('button')); });
    box.addEventListener('keydown', function (e) {
      var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
      if (!step) return;
      e.preventDefault();
      var i = buttons.indexOf(document.activeElement);
      pick(buttons[(i + step + buttons.length) % buttons.length]);
    });
    s.addEventListener('change', sync);
    s.ywChips = { sync: sync, focus: function () { var b = box.querySelector('[tabindex="0"]'); if (b) b.focus(); } };
    s.classList.add('sp-native-size');
    s.tabIndex = -1;
    s.setAttribute('aria-hidden', 'true');
    s.insertAdjacentElement('afterend', box);
    var fields = label.closest('.sp-fields');
    if (fields) fields.classList.add('has-chips');
    sync();
  });
  document.querySelectorAll('[data-add-detail]').forEach(function (button) {
    button.addEventListener('click', function () {
      var dialog = button.closest('[data-detail]');
      var id = activeVariant(dialog);
      var input = document.getElementById('qty-' + id);
      if (!input || input.disabled) { toast(tr('limit_reached')); return; }
      var size = document.getElementById(input.dataset.sizeTarget || '');
      // 10.19: Ohne gewählte Größe nichts übernehmen (kein stilles „erste Größe“ mehr).
      if (size && size.value === '') { sizeMissing(dialog, size, input); return; }
      if (int(input.value) === 0) input.value = '1';
      update();
      var msg = tr(size ? 'added_size' : 'added', { qty: input.value, name: input.dataset.lineName || tr('item'), size: size ? size.value : '' });
      // Karte zeigt danach die zuletzt gewählte Farbe
      var card = document.querySelector('.sp-card[data-group="' + dialog.id + '"] .sp-card-visual .mockup-view');
      var src = dialog.querySelector('[data-variant-visual="' + id + '"] .mockup-view');
      if (card && src) { var copy = src.cloneNode(true); copy.classList.add('active'); card.parentNode.replaceChild(copy, card); }
      closeDetail(dialog);
      toast(msg);
    });
  });
  document.querySelectorAll('[data-scroll-checkout]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); panel.focus({ preventScroll: true }); }
    });
  });
  form.addEventListener('submit', function (event) {
    var missing = null;
    qtyInputs.forEach(function (input) {
      var size = document.getElementById(input.dataset.sizeTarget || '');
      if (!missing && int(input.value) > 0 && size && size.value === '') missing = { input: input, size: size };
    });
    if (missing) {
      event.preventDefault();
      var dialog = document.getElementById(missing.input.dataset.group || '');
      if (dialog) { selectVariant(dialog, missing.input.id.replace('qty-', ''), false); openDetail(dialog, null); }
      sizeMissing(dialog, missing.size, missing.input);
      return;
    }
    var submit = form.querySelector('.shop-submit');
    if (submit) { window.setTimeout(function () { submit.disabled = true; submit.textContent = tr('sending'); }, 0); }
  });
  update();
})();
