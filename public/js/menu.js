/* Burger button: collapses the side menu on desktop, opens it as a drawer on phones. */
(function () {
  'use strict';
  var body = document.body, btns = document.querySelectorAll('[data-menu-toggle]');
  if (!btns.length) return;
  var mobile = function () { return window.matchMedia('(max-width: 860px)').matches; };
  function sync() {
    var open = mobile() ? body.classList.contains('menu-open') : !body.classList.contains('menu-collapsed');
    btns.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }
  try { if (!mobile() && localStorage.getItem('menu') === 'closed') body.classList.add('menu-collapsed'); } catch (e) {}
  btns.forEach(function (btn) { btn.addEventListener('click', function () {
    if (mobile()) body.classList.toggle('menu-open');
    else {
      body.classList.toggle('menu-collapsed');
      try { localStorage.setItem('menu', body.classList.contains('menu-collapsed') ? 'closed' : 'open'); } catch (e) {}
    }
    sync();
  }); });
  document.querySelectorAll('[data-menu-close]').forEach(function (el) {
    el.addEventListener('click', function () { body.classList.remove('menu-open'); sync(); });
  });
  document.querySelectorAll('.nav a').forEach(function (a) { a.addEventListener('click', function () { body.classList.remove('menu-open'); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { body.classList.remove('menu-open'); sync(); } });
  window.addEventListener('resize', function () { body.classList.remove('menu-open'); sync(); });
  sync();
})();

/* Dropdowns in the top bar (notifications, user menu) */
(function () {
  'use strict';
  var roots = document.querySelectorAll('[data-usermenu]');
  function set(root, open) {
    var btn = root.querySelector('[data-usermenu-toggle]'), pop = root.querySelector('.usermenu-pop');
    pop.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  roots.forEach(function (root) {
    root.querySelector('[data-usermenu-toggle]').addEventListener('click', function () {
      var wasOpen = !root.querySelector('.usermenu-pop').hidden;
      roots.forEach(function (r) { set(r, false); });
      set(root, !wasOpen);
    });
  });
  document.addEventListener('click', function (e) { roots.forEach(function (r) { if (!r.contains(e.target)) set(r, false); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') roots.forEach(function (r) { set(r, false); }); });
})();
