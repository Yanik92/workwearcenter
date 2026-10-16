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

  onScroll();
})();
