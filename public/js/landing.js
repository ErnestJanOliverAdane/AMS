(function () {
  var root = document.documentElement;
  root.classList.add('js');

  // Theme toggle
  var themeBtn = document.getElementById('theme');
  function isDark() {
    var t = root.dataset.theme;
    return t !== 'light';
  }
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var next = isDark() ? 'light' : 'dark';
      root.dataset.theme = next;
      try { localStorage.setItem('konektado-theme', next); } catch (e) {}
      readColor();
    });
  }

  // Mobile menu
  var menu = document.getElementById('menu'), links = document.getElementById('links');
  if (menu && links) {
    menu.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      menu.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    links.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') { links.classList.remove('open'); menu.setAttribute('aria-expanded', 'false'); }
    });
  }

  // Reveal on scroll
  var items = document.querySelectorAll('.card, .faq details, .prose, .book');
  items.forEach(function (el) { el.classList.add('reveal'); });
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.12 });
    items.forEach(function (el) { io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('in'); });
  }

  // Copy buttons
  document.querySelectorAll('.copy').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.dataset.copy, span = b.previousElementSibling && b.previousElementSibling.querySelector('span');
      function done(t) { b.textContent = t; setTimeout(function () { b.textContent = 'Copy'; }, 1600); }
      function select() {
        if (!span) return done('Copy');
        var r = document.createRange(); r.selectNodeContents(span); var s = getSelection(); s.removeAllRanges(); s.addRange(r); done('Selected');
      }
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(function () { done('Copied'); }, select);
      else select();
    });
  });

  // Request drafter: chips build the message; the same text can be sent to the team or copied.
  (function () {
    var box = document.getElementById('qchips'), msg = document.getElementById('qmsg'), hidden = document.getElementById('qservices');
    if (!box || !msg) return;
    var chosen = [];
    function draft() {
      hidden.innerHTML = '';
      chosen.forEach(function (n) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'services[]'; i.value = n; hidden.appendChild(i); });
      msg.value = 'Hello Konektado IT Solutions, I would like to ask about: ' + (chosen.length ? chosen.join(', ') : '(choose services above)') + '.\nBusiness name and location: \nPreferred contact number: ';
    }
    box.querySelectorAll('.qchip').forEach(function (b) {
      b.addEventListener('click', function () {
        var n = b.textContent.trim(), at = chosen.indexOf(n);
        if (at > -1) chosen.splice(at, 1); else chosen.push(n);
        b.setAttribute('aria-pressed', at > -1 ? 'false' : 'true');
        draft();
      });
    });
    var c = document.getElementById('qcopy');
    if (c) c.addEventListener('click', function () {
      function done(t) { c.textContent = t; setTimeout(function () { c.textContent = 'Copy message'; }, 1800); }
      function select() { msg.focus(); msg.select(); done('Selected, press copy'); }
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(msg.value).then(function () { done('Copied'); }, select);
      else select();
    });
  })();


  // Send via Gmail: opens a Gmail compose window with the recipient, subject and message filled in.
  (function () {
    var btn = document.getElementById('qgmail'), form = document.getElementById('request');
    if (!btn || !form) return;
    btn.addEventListener('click', function () {
      var name = form.elements['name'], phone = form.elements['phone'], email = form.elements['email'], msg = document.getElementById('qmsg');
      if (!name.value.trim()) { name.focus(); name.reportValidity && name.reportValidity(); return; }
      if (!phone.value.trim()) { phone.focus(); phone.reportValidity && phone.reportValidity(); return; }
      var services = Array.prototype.map.call(form.querySelectorAll('input[name="services[]"]'), function (i) { return i.value; });
      var subject = 'Inquiry from ' + name.value.trim() + (services.length ? ': ' + services.join(', ') : '');
      var body = msg.value.replace(/\s+$/, '') + '\n\nName: ' + name.value.trim() + '\nContact number: ' + phone.value.trim() + (email.value.trim() ? '\nEmail: ' + email.value.trim() : '');
      var url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(btn.dataset.to) + '&su=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
      // Gmail links have a length limit. If the message is very long, copy it too so nothing is lost.
      if (url.length > 7000 && navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(body).catch(function () {});
      if (url.length > 7000) url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(btn.dataset.to) + '&su=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent('(Paste your message here. It was copied to your clipboard.)');
      window.open(url, '_blank', 'noopener');
    });
  })();

  // Network background: drifting glowing dots joined by faint lines, pushed away by the pointer.
  var canvas = document.getElementById('net');
  if (!canvas || !canvas.getContext) return;
  var ctx = canvas.getContext('2d');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var w = 0, h = 0, dpr = 1, rgb = '61,245,192', pts = [], mouse = { x: -999, y: -999 }, raf = 0;

  function readColor() {
    var v = getComputedStyle(root).getPropertyValue('--dot').trim();
    if (v) rgb = v;
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = window.innerWidth; h = window.innerHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var count = Math.max(22, Math.min(70, Math.round(w * h / 24000)));
    pts = [];
    for (var i = 0; i < count; i++) {
      pts.push({
        x: Math.random() * w, y: Math.random() * h,
        vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35,
        r: 2 + Math.random() * 3.2, big: Math.random() < .18
      });
    }
  }

  function frame() {
    ctx.clearRect(0, 0, w, h);
    var i, j, p, q, dx, dy, d;
    for (i = 0; i < pts.length; i++) {
      p = pts[i];
      dx = p.x - mouse.x; dy = p.y - mouse.y; d = Math.sqrt(dx * dx + dy * dy);
      if (d < 140 && d > 0) { p.x += dx / d * 1.1; p.y += dy / d * 1.1; }
      if (!reduce) { p.x += p.vx; p.y += p.vy; }
      if (p.x < -10) p.x = w + 10; else if (p.x > w + 10) p.x = -10;
      if (p.y < -10) p.y = h + 10; else if (p.y > h + 10) p.y = -10;
    }
    for (i = 0; i < pts.length; i++) {
      p = pts[i];
      for (j = i + 1; j < pts.length; j++) {
        q = pts[j]; dx = p.x - q.x; dy = p.y - q.y; d = Math.sqrt(dx * dx + dy * dy);
        if (d < 150) {
          ctx.strokeStyle = 'rgba(' + rgb + ',' + (0.2 * (1 - d / 150)).toFixed(3) + ')';
          ctx.lineWidth = 1;
          ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
        }
      }
    }
    for (i = 0; i < pts.length; i++) {
      p = pts[i];
      var glow = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * (p.big ? 6 : 4));
      glow.addColorStop(0, 'rgba(' + rgb + ',' + (p.big ? .55 : .35) + ')');
      glow.addColorStop(1, 'rgba(' + rgb + ',0)');
      ctx.fillStyle = glow;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r * (p.big ? 6 : 4), 0, 6.2832); ctx.fill();
      ctx.fillStyle = 'rgba(' + rgb + ',' + (p.big ? .95 : .7) + ')';
      ctx.beginPath(); ctx.arc(p.x, p.y, p.big ? p.r : p.r * .6, 0, 6.2832); ctx.fill();
    }
    if (!reduce) raf = requestAnimationFrame(frame);
  }

  readColor(); resize(); frame();
  var timer;
  window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(function () { resize(); if (reduce) frame(); }, 150); });
  window.addEventListener('pointermove', function (e) { mouse.x = e.clientX; mouse.y = e.clientY; }, { passive: true });
  window.addEventListener('pointerleave', function () { mouse.x = mouse.y = -999; });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) cancelAnimationFrame(raf); else if (!reduce) { cancelAnimationFrame(raf); frame(); }
  });
})();
