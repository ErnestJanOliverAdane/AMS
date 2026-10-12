/* Shows three animated dots while the next page loads (links and form submits). */
(function () {
  'use strict';
  var box = document.createElement('div');
  box.className = 'ld';
  box.setAttribute('role', 'status');
  box.setAttribute('aria-live', 'polite');
  box.innerHTML = '<div class="ld-box"><span class="dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="ld-text">Loading</span></div>';
  var text = box.querySelector('.ld-text'), timer;
  function add() { if (document.body && !box.parentNode) document.body.appendChild(box); }
  if (document.body) add(); else document.addEventListener('DOMContentLoaded', add);

  function show(label) {
    add();
    text.textContent = label || 'Loading';
    box.classList.add('on');
    clearTimeout(timer);
    timer = setTimeout(hide, 15000); // safety net for downloads or failed requests
  }
  function hide() {
    box.classList.remove('on');
    document.querySelectorAll('.is-loading').forEach(function (b) {
      b.classList.remove('is-loading');
      if (b.dataset.label) { b.innerHTML = b.dataset.label; delete b.dataset.label; }
    });
  }
  window.addEventListener('pageshow', hide); // back button / cached pages

  function labelFor(url) {
    var p = (url || '').toLowerCase();
    if (/\/logout/.test(p)) return 'Signing out';
    if (/\/login/.test(p)) return 'Signing in';
    if (/\/register/.test(p)) return 'Creating your account';
    return 'Loading';
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (a.target && a.target !== '_self') return;
    if (a.hasAttribute('download') || a.hasAttribute('data-no-loader')) return;
    var u;
    try { u = new URL(a.href, location.href); } catch (err) { return; }
    if (u.origin !== location.origin || !/^https?:$/.test(u.protocol)) return;
    if (u.pathname === location.pathname && u.search === location.search && u.hash) return;
    if (/\/export\/csv|\/slip/.test(u.pathname)) return; // file downloads do not leave the page
    show(labelFor(u.pathname));
  });

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f || f.hasAttribute('data-no-loader') || f.target === '_blank') return;
    var btn = f.querySelector('button[type=submit],button:not([type]),input[type=submit]');
    if (btn && btn.tagName === 'BUTTON' && btn.classList.contains('btn')) {
      btn.dataset.label = btn.innerHTML;
      btn.style.minWidth = btn.offsetWidth + 'px';
      btn.innerHTML = '<span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>';
      btn.classList.add('is-loading');
    }
    // Let the browser start the request before the form's own state changes.
    show(labelFor(f.getAttribute('action')));
  });
})();
