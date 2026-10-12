/* Reads a rendered floor plan (canvas) and finds the building outline and the long wall lines.
   Pure functions, no DOM except an off-screen canvas. Result coordinates are fractions (0..1) of the page width and height. */
(function () {
  'use strict';
  var DX = [-1, -1, 0, 1, 1, 1, 0, -1], DY = [0, -1, -1, -1, 0, 1, 1, 1];

  /* Box dilate (grow=true) or erode (grow=false) of a 0/1 mask by radius r, using running sums. */
  function box(src, w, h, r, grow) {
    var tmp = new Uint8Array(w * h), out = new Uint8Array(w * h), i, x, y, acc;
    var want = grow ? 1 : 0;
    function pass(a, b, horiz) {
      var len = horiz ? w : h, lines = horiz ? h : w, sum = new Int32Array(len + 1);
      for (var l = 0; l < lines; l++) {
        for (i = 0; i < len; i++) sum[i + 1] = sum[i] + (a[horiz ? l * w + i : i * w + l] === want ? 1 : 0);
        for (i = 0; i < len; i++) {
          var lo = Math.max(0, i - r), hi = Math.min(len - 1, i + r);
          acc = sum[hi + 1] - sum[lo];
          var v = grow ? (acc > 0 ? 1 : 0) : (acc > 0 ? 0 : 1);
          if (!grow && (i - r < 0 || i + r > len - 1)) v = 0;   // outside the page counts as empty when eroding
          b[horiz ? l * w + i : i * w + l] = v;
        }
      }
    }
    pass(src, tmp, true); pass(tmp, out, false);
    return out;
  }

  /* Connected components. Returns {label, count, bbox[], size[]}; only pixels where mask = 1. */
  function components(mask, w, h, eight) {
    var label = new Int32Array(w * h), n = 0, stack = new Int32Array(w * h), bbox = [null], size = [0];
    for (var s = 0; s < w * h; s++) {
      if (!mask[s] || label[s]) continue;
      n++; var sp = 0, minx = w, miny = h, maxx = 0, maxy = 0, cnt = 0;
      stack[sp++] = s; label[s] = n;
      while (sp) {
        var p = stack[--sp], x = p % w, y = (p - x) / w;
        cnt++; if (x < minx) minx = x; if (x > maxx) maxx = x; if (y < miny) miny = y; if (y > maxy) maxy = y;
        for (var d = 0; d < 8; d += eight ? 1 : 2) {
          var nx = x + DX[d], ny = y + DY[d];
          if (nx < 0 || ny < 0 || nx >= w || ny >= h) continue;
          var q = ny * w + nx;
          if (mask[q] && !label[q]) { label[q] = n; stack[sp++] = q; }
        }
      }
      bbox.push([minx, miny, maxx, maxy]); size.push(cnt);
    }
    return { label: label, count: n, bbox: bbox, size: size };
  }

  function trace(mask, w, h, sx, sy) {
    var pts = [], x = sx, y = sy, b = 0, guard = 0, limit = w * h * 2;
    do {
      pts.push([x, y]);
      var found = false;
      for (var k = 1; k <= 8; k++) {
        var d = (b + k) % 8, nx = x + DX[d], ny = y + DY[d];
        if (nx >= 0 && ny >= 0 && nx < w && ny < h && mask[ny * w + nx]) {
          var pd = (d + 7) % 8, bx = x + DX[pd] - nx, by = y + DY[pd] - ny, nb = 0;
          for (var m = 0; m < 8; m++) if (DX[m] === bx && DY[m] === by) nb = m;
          b = nb; x = nx; y = ny; found = true; break;
        }
      }
      if (!found) break;
    } while (!(x === sx && y === sy) && ++guard < limit);
    return pts;
  }

  function rdp(pts, eps) {
    if (pts.length < 4) return pts;
    var keep = new Uint8Array(pts.length); keep[0] = 1; keep[pts.length - 1] = 1;
    var stack = [[0, pts.length - 1]];
    while (stack.length) {
      var seg = stack.pop(), a = pts[seg[0]], c = pts[seg[1]], idx = -1, md = 0;
      var dx = c[0] - a[0], dy = c[1] - a[1], len = Math.hypot(dx, dy) || 1;
      for (var i = seg[0] + 1; i < seg[1]; i++) {
        var d = Math.abs(dy * (pts[i][0] - a[0]) - dx * (pts[i][1] - a[1])) / len;
        if (d > md) { md = d; idx = i; }
      }
      if (md > eps && idx > -1) { keep[idx] = 1; stack.push([seg[0], idx], [idx, seg[1]]); }
    }
    return pts.filter(function (_, i) { return keep[i]; });
  }

  /* Snap nearly horizontal / vertical edges to exact ones, then drop collinear and duplicate points. */
  function orthogonal(pts, tol) {
    var i, a, b;
    if (pts.length > 3 && Math.hypot(pts[0][0] - pts[pts.length - 1][0], pts[0][1] - pts[pts.length - 1][1]) <= tol) pts = pts.slice(0, -1);
    var n = pts.length;
    pts = pts.map(function (p) { return [p[0], p[1]]; });
    for (var pass = 0; pass < 2; pass++) {
      for (i = 0; i < n; i++) {
        a = pts[i]; b = pts[(i + 1) % n];
        if (Math.abs(a[1] - b[1]) <= tol && Math.abs(a[0] - b[0]) > tol) { var my = (a[1] + b[1]) / 2; a[1] = my; b[1] = my; }
        else if (Math.abs(a[0] - b[0]) <= tol && Math.abs(a[1] - b[1]) > tol) { var mx = (a[0] + b[0]) / 2; a[0] = mx; b[0] = mx; }
      }
    }
    var out = [];
    pts.forEach(function (p) { var l = out[out.length - 1]; if (!l || Math.hypot(l[0] - p[0], l[1] - p[1]) > 0.5) out.push(p); });
    var res = [];
    for (i = 0; i < out.length; i++) {
      var p0 = out[(i + out.length - 1) % out.length], p1 = out[i], p2 = out[(i + 1) % out.length];
      var cr = (p1[0] - p0[0]) * (p2[1] - p1[1]) - (p1[1] - p0[1]) * (p2[0] - p1[0]);
      if (Math.abs(cr) > 1e-6) res.push(p1);
    }
    res = res.length >= 3 ? res : out;
    if (res.length > 3 && Math.hypot(res[0][0] - res[res.length - 1][0], res[0][1] - res[res.length - 1][1]) <= 0.5) res.pop();
    return res;
  }

  /* canvas: a rendered page. opts: widthM (real width of the plan), sens 0..100 (higher = lighter lines count as walls). */
  function analyze(canvas, opts) {
    opts = opts || {};
    var scale = Math.min(1, 1100 / Math.max(canvas.width, canvas.height));
    var w = Math.max(40, Math.round(canvas.width * scale)), h = Math.max(40, Math.round(canvas.height * scale));
    var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
    var ctx = cv.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h); ctx.drawImage(canvas, 0, 0, w, h);
    var img = ctx.getImageData(0, 0, w, h).data, N = w * h, dark = new Uint8Array(N), i;
    var thr = 100 + (opts.sens == null ? 50 : opts.sens) * 1.3;
    for (i = 0; i < N; i++) { var lum = 0.299 * img[i * 4] + 0.587 * img[i * 4 + 1] + 0.114 * img[i * 4 + 2]; dark[i] = lum < thr ? 1 : 0; }

    var pxm = w / (opts.widthM || 30);                      // pixels per meter
    // 1. Remove the drawing frame: a single dark piece that spans nearly the whole page.
    var cc = components(dark, w, h, true), k;
    for (k = 1; k <= cc.count; k++) {
      var bb = cc.bbox[k];
      if ((bb[2] - bb[0]) >= 0.94 * w && (bb[3] - bb[1]) >= 0.94 * h) for (i = 0; i < N; i++) if (cc.label[i] === k) dark[i] = 0;
    }
    // 2. Seal door gaps, then flood the outside from the page border.
    var r = Math.max(1, Math.round(0.35 * pxm)), D = box(dark, w, h, r, true), out = new Uint8Array(N), stack = new Int32Array(N), sp = 0, x, y;
    function seed(p) { if (!D[p] && !out[p]) { out[p] = 1; stack[sp++] = p; } }
    for (x = 0; x < w; x++) { seed(x); seed((h - 1) * w + x); }
    for (y = 0; y < h; y++) { seed(y * w); seed(y * w + w - 1); }
    while (sp) {
      var p = stack[--sp]; x = p % w; y = (p - x) / w;
      if (x > 0) seed(p - 1); if (x < w - 1) seed(p + 1); if (y > 0) seed(p - w); if (y < h - 1) seed(p + w);
    }
    var inside = new Uint8Array(N);
    for (i = 0; i < N; i++) inside[i] = out[i] ? 0 : 1;
    // 3. Opening removes dimension lines, text and other thin attachments.
    var re = Math.max(2, Math.round(0.8 * pxm));
    inside = box(box(inside, w, h, re, false), w, h, re, true);
    // 4. Keep the biggest piece.
    var ic = components(inside, w, h, false), best = 0, bs = 0;
    for (k = 1; k <= ic.count; k++) if (ic.size[k] > bs) { bs = ic.size[k]; best = k; }
    var frac = bs / N;
    if (!best || frac < 0.01) return { ok: false, reason: 'No closed building outline was found in this page.' };
    var mask = new Uint8Array(N), sx = -1, sy = -1;
    for (i = 0; i < N; i++) if (ic.label[i] === best) { mask[i] = 1; if (sx < 0) { sx = i % w; sy = Math.floor(i / w); } }
    var poly = trace(mask, w, h, sx, sy);
    poly = rdp(poly, Math.max(1.5, 0.3 * pxm));
    poly = orthogonal(poly, Math.max(2, 0.4 * pxm));
    var outline = poly.map(function (q) { return [q[0] / w, q[1] / h]; });

    // 5. Wall lines: long dark pieces inside the building, drawn as a grid of wall cells.
    var keep = new Uint8Array(N), minLen = 2.5 * pxm, wc = components(dark, w, h, true);
    var dm = box(mask, w, h, Math.max(1, Math.round(0.3 * pxm)), true);
    for (k = 1; k <= wc.count; k++) {
      var b2 = wc.bbox[k];
      if (Math.max(b2[2] - b2[0], b2[3] - b2[1]) >= minLen) for (y = b2[1]; y <= b2[3]; y++) for (x = b2[0]; x <= b2[2]; x++) { var q = y * w + x; if (wc.label[q] === k && dm[q]) keep[q] = 1; }
    }
    var cs = Math.max(0.2, Math.min(0.5, (opts.widthM || 30) / 220)), cp = cs * pxm, nx = Math.max(1, Math.round(w / cp)), ny = Math.max(1, Math.round(h / cp)), grid = new Uint8Array(nx * ny);
    for (y = 0; y < h; y++) for (x = 0; x < w; x++) if (keep[y * w + x]) grid[Math.min(ny - 1, Math.floor(y / h * ny)) * nx + Math.min(nx - 1, Math.floor(x / w * nx))] = 1;
    // merge cells into rectangles: horizontal runs first, then stack identical runs
    var rects = [], open = {};
    for (var j = 0; j <= ny; j++) {
      var runs = {}, i0 = -1;
      if (j < ny) for (i = 0; i <= nx; i++) {
        var on = i < nx && grid[j * nx + i];
        if (on && i0 < 0) i0 = i;
        if (!on && i0 >= 0) { runs[i0 + ':' + i] = [i0, i]; i0 = -1; }
      }
      Object.keys(open).forEach(function (key) { if (!runs[key]) { rects.push(open[key]); delete open[key]; } });
      Object.keys(runs).forEach(function (key) { if (open[key]) open[key][3] = j + 1; else open[key] = [runs[key][0], j, runs[key][1], j + 1]; });
    }
    return { ok: true, outline: outline, frac: frac, walls: rects, grid: { nx: nx, ny: ny, data: grid }, size: { w: w, h: h } };
  }

  window.PlanShape = { analyze: analyze };
})();
