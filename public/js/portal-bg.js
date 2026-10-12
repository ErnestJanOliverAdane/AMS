/* Network dots for the portal background: drifting points joined by faint lines, pushed away by the pointer. */
(function () {
  var canvas = document.getElementById('pbg-net');
  if (!canvas || !canvas.getContext) return;
  var ctx = canvas.getContext('2d');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var w = 0, h = 0, dpr = 1, rgb = '61,245,192', pts = [], mouse = { x: -999, y: -999 }, raf = 0;

  function readColor() {
    var v = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
    var m = /^#([0-9a-f]{6})$/i.exec(v);
    if (m) rgb = [parseInt(m[1].slice(0, 2), 16), parseInt(m[1].slice(2, 4), 16), parseInt(m[1].slice(4, 6), 16)].join(',');
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = window.innerWidth; h = window.innerHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var count = Math.max(18, Math.min(55, Math.round(w * h / 30000)));
    pts = [];
    for (var i = 0; i < count; i++) {
      pts.push({ x: Math.random() * w, y: Math.random() * h, vx: (Math.random() - .5) * .3, vy: (Math.random() - .5) * .3, r: 1.8 + Math.random() * 2.8, big: Math.random() < .15 });
    }
  }

  function frame() {
    ctx.clearRect(0, 0, w, h);
    var i, j, p, q, dx, dy, d;
    for (i = 0; i < pts.length; i++) {
      p = pts[i];
      dx = p.x - mouse.x; dy = p.y - mouse.y; d = Math.sqrt(dx * dx + dy * dy);
      if (d < 130 && d > 0) { p.x += dx / d; p.y += dy / d; }
      if (!reduce) { p.x += p.vx; p.y += p.vy; }
      if (p.x < -10) p.x = w + 10; else if (p.x > w + 10) p.x = -10;
      if (p.y < -10) p.y = h + 10; else if (p.y > h + 10) p.y = -10;
    }
    for (i = 0; i < pts.length; i++) {
      p = pts[i];
      for (j = i + 1; j < pts.length; j++) {
        q = pts[j]; dx = p.x - q.x; dy = p.y - q.y; d = Math.sqrt(dx * dx + dy * dy);
        if (d < 140) {
          ctx.strokeStyle = 'rgba(' + rgb + ',' + (0.2 * (1 - d / 140)).toFixed(3) + ')';
          ctx.lineWidth = 1;
          ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
        }
      }
    }
    for (i = 0; i < pts.length; i++) {
      p = pts[i];
      var rr = p.r * (p.big ? 5 : 3.5), g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, rr);
      g.addColorStop(0, 'rgba(' + rgb + ',' + (p.big ? .5 : .32) + ')'); g.addColorStop(1, 'rgba(' + rgb + ',0)');
      ctx.fillStyle = g; ctx.beginPath(); ctx.arc(p.x, p.y, rr, 0, 6.2832); ctx.fill();
      ctx.fillStyle = 'rgba(' + rgb + ',' + (p.big ? .9 : .65) + ')';
      ctx.beginPath(); ctx.arc(p.x, p.y, p.big ? p.r : p.r * .6, 0, 6.2832); ctx.fill();
    }
    if (!reduce) raf = requestAnimationFrame(frame);
  }

  readColor(); resize(); frame();
  var timer;
  window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(function () { resize(); if (reduce) frame(); }, 150); });
  window.addEventListener('pointermove', function (e) { mouse.x = e.clientX; mouse.y = e.clientY; }, { passive: true });
  window.addEventListener('pointerleave', function () { mouse.x = mouse.y = -999; });
  document.addEventListener('visibilitychange', function () { cancelAnimationFrame(raf); if (!document.hidden && !reduce) frame(); });
  if (window.matchMedia) window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', readColor);
})();
