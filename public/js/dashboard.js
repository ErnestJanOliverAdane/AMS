/* Dashboard analytics: count-up numbers, hover tooltips, table toggle and the cipher animation. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Count-up
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var end = parseInt(el.dataset.count, 10);
    if (reduce || isNaN(end) || end < 1) return;
    var start = null;
    function tick(t) {
      if (start === null) start = t;
      var p = Math.min(1, (t - start) / 900);
      el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    }
    el.textContent = '0';
    requestAnimationFrame(tick);
  });

  // Tooltips: any [data-tip] inside a card with a .tip element
  document.querySelectorAll('.an-card').forEach(function (card) {
    var tip = card.querySelector('.tip');
    var focus = card.querySelector('.focus');
    if (!tip) return;
    var host = tip.parentElement;
    function show(el, e) {
      var box = host.getBoundingClientRect();
      tip.textContent = el.dataset.tip;
      tip.hidden = false;
      if (el.dataset.x && focus) {
        var svg = el.ownerSVGElement, r = svg.getBoundingClientRect(), s = r.width / svg.viewBox.baseVal.width;
        focus.setAttribute('cx', el.dataset.x); focus.setAttribute('cy', el.dataset.y);
        focus.classList.add('on');
        tip.style.left = (r.left - box.left + el.dataset.x * s) + 'px';
        tip.style.top = (r.top - box.top + el.dataset.y * s) + 'px';
      } else {
        tip.style.left = (e.clientX - box.left) + 'px';
        tip.style.top = (e.clientY - box.top) + 'px';
      }
    }
    function hide() { tip.hidden = true; if (focus) focus.classList.remove('on'); }
    card.querySelectorAll('[data-tip]').forEach(function (el) {
      el.addEventListener('mousemove', function (e) { show(el, e); });
      el.addEventListener('mouseleave', hide);
    });
  });

  // Table view toggle
  document.querySelectorAll('[data-table-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.an-card'), on = btn.getAttribute('aria-pressed') !== 'true';
      btn.setAttribute('aria-pressed', on);
      btn.textContent = on ? 'Chart view' : 'Table view';
      card.querySelector('[data-chart]').hidden = on;
      card.querySelector('.table-view').hidden = !on;
    });
  });

  // Cipher animation: random filler only, never real data.
  var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
  function rnd(n) { var s = ''; for (var i = 0; i < n; i++) s += chars[Math.floor(Math.random() * chars.length)]; return s; }
  document.querySelectorAll('[data-cipher]').forEach(function (el, i) {
    if (reduce) return;
    var len = el.textContent.length, locked = 0;
    function frame() {
      locked = (locked + 1) % (len * 2);
      var keep = Math.max(0, locked - len); // sweep: left part settles briefly, then re-scrambles
      el.textContent = 'ey' + rnd(Math.max(0, len - 2));
      if (keep > 0) el.textContent = el.textContent.slice(0, keep) + rnd(len - keep);
    }
    setTimeout(function () { setInterval(frame, 90); }, i * 260);
  });
})();
