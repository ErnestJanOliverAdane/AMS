/* Konektado site layout planner: floors, building shape from the PDF, auto-generated device layouts, moving objects. */
(function () {
  'use strict';
  var root = document.getElementById('planner');
  if (!root) return;
  var cfg = JSON.parse(root.dataset.config);
  var $ = function (id) { return document.getElementById(id); };
  var VB = 1000;

  /* ---------- catalog ---------- */
  var TYPES = {
    cab:    { layer: 'cabinet', label: 'Data cabinet',   short: 'CAB', color: '#c98500', glyph: 'R' },
    cam:    { layer: 'cctv',    label: 'CCTV camera',    short: 'CAM', color: '#2a78d6', glyph: 'C' },
    ap:     { layer: 'wifi',    label: 'WiFi access point', short: 'AP', color: '#0f8f66', glyph: 'W' },
    spk:    { layer: 'paging',  label: 'Paging speaker', short: 'SPK', color: '#d95926', glyph: 'S' },
    outlet: { layer: 'cabling', label: 'Data outlet',    short: 'DO',  color: '#5b6778', glyph: 'D' },
    phone:  { layer: 'pbx',     label: 'IP phone',       short: 'EXT', color: '#8a55c8', glyph: 'P' }
  };
  var ORDER = ['cam', 'ap', 'spk', 'outlet', 'phone'];
  var EDGE = { cam: 'CCTV', ap: 'WiFi', spk: 'Paging', outlet: 'Data', phone: 'IP PBX' };
  var KINDS = { person: { label: 'Person', short: 'P', color: '#e64980', speed: 1.3 }, vehicle: { label: 'Vehicle', short: 'V', color: '#212529', speed: 2.6 } };

  /* ---------- state ---------- */
  var st = {
    name: cfg.name,
    width: +cfg.width || 30,
    layers: (cfg.layers || []).slice(),
    devices: (cfg.devices || []).slice(),
    s: Object.assign({ density: 2, ratio: 0.66, camera_range: 12, camera_fov: 90, page: 1, ceiling: 3, floors: 1, sens: 50, walls3d: true }, cfg.settings || {}),
    sel: null, osel: null, tool: null, dirty: false, hasPdf: !!cfg.planUrl, pdf: null, floor: 0, draw: null,
    show: { runs: true, cover: true, labels: true, outline: true, walls: false }
  };
  (function normalise() {
    st.s.floors = clamp(parseInt(st.s.floors, 10) || 1, 1, 40);
    var o = st.s.outlines, fixed = {};
    if (o && typeof o === 'object') Object.keys(o).forEach(function (k) { if (validOutline(o[k])) fixed[k] = o[k]; });
    st.s.outlines = fixed;
    if (!validOutline(st.s.outline)) delete st.s.outline;
    if (!Array.isArray(st.s.floor_pages)) st.s.floor_pages = [];
    st.devices.forEach(function (d) { d.fl = clamp(parseInt(d.fl, 10) || 0, 0, st.s.floors - 1); });
  })();

  function on(layer) { return st.layers.indexOf(layer) > -1; }
  function H() { return st.width * st.s.ratio; }
  function ppm() { return VB / st.width; }
  function uid() { return 'd' + Math.random().toString(36).slice(2, 9); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function round1(n) { return Math.round(n * 10) / 10; }
  function visible(d) { return TYPES[d.type] && (d.type === 'cab' ? anyLayer() : on(TYPES[d.type].layer)); }
  function anyLayer() { return st.layers.length > 0; }
  function floors() { return st.s.floors; }
  function ceil() { return +st.s.ceiling || 3; }
  function fl(d) { return d.fl || 0; }
  function onFloor(d) { return fl(d) === st.floor; }
  function pageOf(f) { return (f > 0 && st.s.floor_pages[f]) || st.s.page || 1; }

  /* ---------- geometry: building outline (stored as fractions of the plan, used in meters) ---------- */
  function validOutline(o) { return Array.isArray(o) && o.length >= 3 && o.length <= 400 && o.every(function (p) { return Array.isArray(p) && p.length === 2 && isFinite(p[0]) && isFinite(p[1]); }); }
  function outlineN(f) { var o = st.s.outlines && st.s.outlines[f]; if (validOutline(o)) return o; return validOutline(st.s.outline) ? st.s.outline : null; }
  function polyM(f) {
    var o = outlineN(f), W = st.width, h = H();
    return o ? o.map(function (p) { return [p[0] * W, p[1] * h]; }) : [[0, 0], [W, 0], [W, h], [0, h]];
  }
  function pip(poly, x, y) {
    var c = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      var a = poly[i], b = poly[j];
      if ((a[1] > y) !== (b[1] > y) && x < (b[0] - a[0]) * (y - a[1]) / (b[1] - a[1]) + a[0]) c = !c;
    }
    return c;
  }
  function segDist(px, py, a, b) {
    var dx = b[0] - a[0], dy = b[1] - a[1], l2 = dx * dx + dy * dy, t = l2 ? clamp(((px - a[0]) * dx + (py - a[1]) * dy) / l2, 0, 1) : 0;
    return Math.hypot(px - (a[0] + t * dx), py - (a[1] + t * dy));
  }
  function edgeDist(poly, x, y) { var m = 1e9; for (var i = 0; i < poly.length; i++) m = Math.min(m, segDist(x, y, poly[i], poly[(i + 1) % poly.length])); return m; }
  function insideM(poly, x, y, m) { return pip(poly, x, y) && (!(m > 0) || edgeDist(poly, x, y) >= m); }
  function polyArea(poly) { var a = 0; for (var i = 0; i < poly.length; i++) { var p = poly[i], q = poly[(i + 1) % poly.length]; a += p[0] * q[1] - q[0] * p[1]; } return Math.abs(a) / 2; }
  function polyBox(poly) {
    var x0 = 1e9, y0 = 1e9, x1 = -1e9, y1 = -1e9;
    poly.forEach(function (p) { x0 = Math.min(x0, p[0]); y0 = Math.min(y0, p[1]); x1 = Math.max(x1, p[0]); y1 = Math.max(y1, p[1]); });
    return { x0: x0, y0: y0, w: x1 - x0, h: y1 - y0 };
  }
  function totalArea() { var a = 0; for (var f = 0; f < floors(); f++) a += polyArea(polyM(f)); return a; }

  /* ---------- PDF analysis: wall lines per page ---------- */
  var an = {};
  function anKey(page) { return page + '|' + st.s.sens; }
  function wallRes(f) { var r = an[anKey(pageOf(f))]; return r && r.ok ? r : null; }
  function wallAt(f, x, y) {
    if (!st.s.walls3d) return false;
    var r = wallRes(f); if (!r) return false;
    var u = x / st.width, v = y / H(); if (u < 0 || v < 0 || u >= 1 || v >= 1) return false;
    return r.grid.data[Math.floor(v * r.grid.ny) * r.grid.nx + Math.floor(u * r.grid.nx)] === 1;
  }
  var docP = null;
  function getDoc() {
    if (!window.pdfjsLib) return Promise.reject(new Error('pdf.js'));
    if (!docP) {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
      docP = window.pdfjsLib.getDocument({ url: cfg.planUrl, withCredentials: true }).promise.then(function (d) { st.pdf = d; return d; });
    }
    return docP;
  }
  function analyzePage(page, force) {
    var key = anKey(page);
    if (force) delete an[key];
    if (an[key]) return Promise.resolve(an[key]);
    if (!cfg.planUrl || !window.PlanShape) return Promise.resolve({ ok: false, reason: 'No PDF is attached to this layout.' });
    return getDoc().then(function (doc) { return doc.getPage(clamp(page, 1, doc.numPages)); }).then(function (pg) {
      var v1 = pg.getViewport({ scale: 1 }), v = pg.getViewport({ scale: Math.min(2.5, 1500 / v1.width) }), cv = document.createElement('canvas');
      cv.width = Math.round(v.width); cv.height = Math.round(v.height);
      return pg.render({ canvasContext: cv.getContext('2d'), viewport: v }).promise.then(function () {
        var r = window.PlanShape.analyze(cv, { widthM: st.width, sens: st.s.sens });
        an[key] = r; return r;
      });
    }).catch(function () { return { ok: false, reason: 'The PDF could not be read.' }; });
  }
  function ensureAnalysis() {
    var jobs = [], seen = {};
    for (var f = 0; f < floors(); f++) { var p = pageOf(f); if (!seen[p]) { seen[p] = 1; jobs.push(analyzePage(p)); } }
    return Promise.all(jobs);
  }

  /* ---------- auto-generation (TIA-568 / TIA-569 / ISO-IEC 11801 rules of thumb) ---------- */
  var STD = {
    maxLink: 90,        // m, permanent link (TIA-568 / ISO 11801)
    maxChannel: 100,    // m, with patch cords
    patch: 10,          // m of patch cords allowed in the channel
    roomArea: 1000,     // m2 per telecommunications room (TIA-569)
    workArea: 10,       // m2 per work area (TIA-569), 2 outlets each
    spare: 0.2,         // 20% spare capacity on patch panels
    panel: 24,
    slack: 1.1, rise: 3 // 10% routing slack + 3 m rise / service loop
  };
  var PORTS = { cam: 1, ap: 2, spk: 1, outlet: 2, phone: 2 };
  var CAT = { ap: 'Cat6A', cam: 'Cat6A', spk: 'Cat6', outlet: 'Cat6', phone: 'Cat6' };

  function cabLabel(f, i) { return f === 0 ? (i === 0 ? 'MDF' : 'IDF' + i) : 'IDF' + (f + 1) + (i ? '-' + (i + 1) : ''); }

  /* One floor's devices, placed only inside that floor's outline and out of the detected walls. */
  function genFloor(f, base) {
    var poly = polyM(f), bb = polyBox(poly), area = polyArea(poly), k = { 1: 1.35, 2: 1, 3: 0.75 }[st.s.density] || 1, out = [];
    var free = function (x, y, m) { return insideM(poly, x, y, m) && !wallAt(f, x, y); };
    var mk = function (type, p, rot, label) { return { id: uid(), type: type, x: round1(p[0]), y: round1(p[1]), rot: rot == null ? 0 : rot, label: label || '', fl: f }; };
    var cands = null;
    function candidates() {
      if (cands) return cands; cands = [];
      for (var y = bb.y0 + 0.75; y < bb.y0 + bb.h; y += 1.5) for (var x = bb.x0 + 0.75; x < bb.x0 + bb.w; x += 1.5) if (free(x, y, 1.2)) cands.push([x, y]);
      if (!cands.length) cands.push([bb.x0 + bb.w / 2, bb.y0 + bb.h / 2]);
      return cands;
    }
    function snap(x, y) { var best = null, bd = 1e9; candidates().forEach(function (c) { var d = Math.hypot(c[0] - x, c[1] - y); if (d < bd) { bd = d; best = c; } }); return best; }
    function lattice(step, margin, cap, hex) {
      var s = step, pts, m = Math.min(margin, 1);
      for (var g = 0; g < 12; g++) {
        pts = [];
        if (hex) {
          var rowH = s * 0.866, row = 0;
          for (var y = bb.y0 + margin + rowH / 2; y <= bb.y0 + bb.h - margin + 0.01; y += rowH, row++)
            for (var x = bb.x0 + margin + (row % 2 ? s : s / 2); x <= bb.x0 + bb.w - margin + 0.01; x += s) if (free(x, y, m)) pts.push([x, y]);
        } else {
          var nx = Math.max(1, Math.floor((bb.w - 2 * margin) / s)), ny = Math.max(1, Math.floor((bb.h - 2 * margin) / s)), gx = (bb.w - 2 * margin) / nx, gy = (bb.h - 2 * margin) / ny;
          for (var j = 0; j < ny; j++) for (var i = 0; i < nx; i++) { var px = bb.x0 + margin + gx * (i + 0.5), py = bb.y0 + margin + gy * (j + 0.5); if (free(px, py, m)) pts.push([px, py]); }
        }
        if (pts.length <= cap) break;
        s *= 1.25;
      }
      if (!pts.length) pts.push(snap(bb.x0 + bb.w / 2, bb.y0 + bb.h / 2));
      return pts;
    }
    /* Cameras along every outer wall (facing inward) plus one in each convex corner. */
    function perimeter(inset, step) {
      var res = [], n = poly.length, normals = [];
      for (var i = 0; i < n; i++) {
        var a = poly[i], b = poly[(i + 1) % n], dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy) || 1, nx = -dy / L, ny = dx / L;
        var mx = (a[0] + b[0]) / 2 + nx * 0.5, my = (a[1] + b[1]) / 2 + ny * 0.5;
        if (!pip(poly, mx, my)) { nx = -nx; ny = -ny; }
        normals.push([nx, ny]);
      }
      for (i = 0; i < n; i++) {
        a = poly[i]; b = poly[(i + 1) % n];
        var L2 = Math.hypot(b[0] - a[0], b[1] - a[1]), cnt = Math.max(0, Math.round(L2 / step) - 1), nn = normals[i];
        for (var q = 1; q <= cnt; q++) {
          var t = q / (cnt + 1), x = a[0] + (b[0] - a[0]) * t + nn[0] * inset, y = a[1] + (b[1] - a[1]) * t + nn[1] * inset;
          if (free(x, y, 0.3)) res.push([x, y, Math.round(Math.atan2(nn[1], nn[0]) * 180 / Math.PI)]);
        }
        var pn = normals[(i + n - 1) % n], bx = pn[0] + nn[0], by = pn[1] + nn[1], bl = Math.hypot(bx, by);
        if (bl > 0.5) {
          bx /= bl; by /= bl;
          var cx = a[0] + bx * inset * 1.4, cy = a[1] + by * inset * 1.4;
          if (free(cx, cy, 0.3) && pip(poly, a[0] + bx * 0.5, a[1] + by * 0.5)) res.push([cx, cy, Math.round(Math.atan2(by, bx) * 180 / Math.PI)]);
        }
      }
      return res;
    }
    function kmeans(kc) {
      var pts = candidates(), cs = [], i, j;
      var cx = 0, cy = 0; pts.forEach(function (p) { cx += p[0]; cy += p[1]; }); cx /= pts.length; cy /= pts.length;
      cs.push(snap(cx, cy));
      while (cs.length < kc) {
        var far = null, fd = -1;
        pts.forEach(function (p) { var m = 1e9; cs.forEach(function (c) { m = Math.min(m, Math.hypot(c[0] - p[0], c[1] - p[1])); }); if (m > fd) { fd = m; far = p; } });
        cs.push(far.slice());
      }
      for (var it = 0; it < 8; it++) {
        var sum = cs.map(function () { return [0, 0, 0]; });
        pts.forEach(function (p) { var bi = 0, bd = 1e9; cs.forEach(function (c, ci) { var d = Math.hypot(c[0] - p[0], c[1] - p[1]); if (d < bd) { bd = d; bi = ci; } }); sum[bi][0] += p[0]; sum[bi][1] += p[1]; sum[bi][2]++; });
        for (j = 0; j < cs.length; j++) if (sum[j][2]) cs[j] = [sum[j][0] / sum[j][2], sum[j][1] / sum[j][2]];
      }
      return cs.map(function (c) { return snap(c[0], c[1]); });
    }

    if (st.layers.length) {
      var nCab = Math.max(1, Math.min(8, Math.ceil(area / STD.roomArea))), spots = kmeans(nCab);
      if (base && free(base.x, base.y, 1.2)) spots[0] = [base.x, base.y]; else if (base) spots[0] = snap(base.x, base.y);
      spots.forEach(function (p, i) { out.push(mk('cab', p, 0, cabLabel(f, i))); });
    }
    if (on('cctv')) {
      perimeter(1.2, 9 * k).forEach(function (p) { out.push(mk('cam', p, p[2])); });
      if (bb.w > 24 && bb.h > 16 && area > 300) lattice(18 * k, 7, 40).forEach(function (p) { out.push(mk('cam', p, 90)); });
    }
    if (on('wifi')) lattice(12 * k, 3, 120, true).forEach(function (p) { out.push(mk('ap', p)); });
    if (on('paging')) lattice(9 * k, 2, 150).forEach(function (p) { out.push(mk('spk', p)); });
    if (on('cabling')) lattice(Math.sqrt(STD.workArea) * k, 0.6, 300).forEach(function (p) { out.push(mk('outlet', p)); });
    if (on('pbx')) lattice(6.5 * k, 3.5, 80).forEach(function (p) { var q = [p[0] + 1.2, p[1]]; out.push(mk('phone', free(q[0], q[1], 0.3) ? q : p)); });

    // Add telecommunications rooms until every horizontal run on this floor fits in 90 m (permanent link).
    if (st.layers.length) {
      for (var guard = 0; guard < 8; guard++) {
        var cabs = out.filter(function (d) { return d.type === 'cab'; }), worst = null, wl = 0;
        out.forEach(function (d) {
          if (d.type === 'cab') return;
          var m = 1e9; cabs.forEach(function (c) { m = Math.min(m, Math.abs(c.x - d.x) + Math.abs(c.y - d.y)); });
          var len = m * STD.slack + STD.rise;
          if (len > STD.maxLink && len > wl) { wl = len; worst = d; }
        });
        if (!worst || cabs.length >= 12) break;
        var sp = snap(worst.x, worst.y);
        out.push({ id: uid(), type: 'cab', x: round1(sp[0]), y: round1(sp[1]), rot: 0, label: cabLabel(f, cabs.length), fl: f });
      }
    }
    return out;
  }
  function number(list, start) {
    var c = Object.assign({}, start || {}), multi = floors() > 1;
    list.forEach(function (d) {
      if (d.label) return;
      var key = fl(d) + '|' + d.type;
      c[key] = (c[key] || 0) + 1;
      d.label = TYPES[d.type].short + (multi ? '-F' + (fl(d) + 1) : '') + '-' + (c[key] < 10 ? '0' : '') + c[key];
    });
  }
  function counts(list) { var c = {}; list.forEach(function (d) { var key = fl(d) + '|' + d.type; c[key] = (c[key] || 0) + 1; }); return c; }
  function generateRange(from, to) {
    var out = [], base = null;
    if (from > 0) { var c0 = st.devices.filter(function (d) { return d.type === 'cab' && fl(d) === 0; })[0]; if (c0) base = { x: c0.x, y: c0.y }; }
    for (var f = from; f <= to; f++) {
      var part = genFloor(f, base);
      if (!base) { var cb = part.filter(function (d) { return d.type === 'cab'; })[0]; if (cb) base = { x: cb.x, y: cb.y }; }
      out = out.concat(part);
    }
    number(out);
    return out;
  }
  function generate() { return generateRange(0, floors() - 1); }
  function swap(devs, layers) {
    var od = st.devices, ol = st.layers; st.devices = devs; st.layers = layers;
    return function () { st.devices = od; st.layers = ol; };
  }
  function generateComplete() {
    var restore = swap([], Object.keys(cfg.allLayers));
    try { return generate(); } finally { restore(); }
  }

  /* ---------- cable runs, terminations, standards ---------- */
  function two(n) { return (n < 10 ? '0' : '') + n; }
  function computeRuns() {
    var cabs = st.devices.filter(function (d) { return d.type === 'cab'; }), runs = [], used = {}, C = ceil();
    if (!cabs.length) return { runs: runs, cabs: cabs, ports: {}, riser: [] };
    ORDER.forEach(function (t) {
      st.devices.filter(function (d) { return d.type === t && visible(d); }).forEach(function (d) {
        var best = null, bd = 1e9, bm = 0;
        cabs.forEach(function (c) {
          var m = Math.abs(c.x - d.x) + Math.abs(c.y - d.y), dz = Math.abs(fl(c) - fl(d)), cost = m + dz * 10000;
          if (cost < bd) { bd = cost; best = c; bm = m + dz * C; }
        });
        for (var n = 0; n < (PORTS[t] || 1); n++) {
          used[best.id] = (used[best.id] || 0) + 1;
          var u = used[best.id], panel = Math.ceil(u / STD.panel), port = ((u - 1) % STD.panel) + 1;
          runs.push({ dev: d, cab: best, n: n, len: bm * STD.slack + STD.rise, cat: CAT[t] || 'Cat6', port: (best.label || 'CAB') + '-PP' + panel + '-' + two(port) });
        }
      });
    });
    var main = cabs.filter(function (c) { return c.label === 'MDF'; })[0] || cabs[0], riser = [];
    cabs.forEach(function (c) {
      if (c === main) return;
      var dz = Math.abs(fl(c) - fl(main));
      riser.push({ cab: c, floors: dz, len: (Math.abs(c.x - main.x) + Math.abs(c.y - main.y) + dz * C) * STD.slack + 10 });
    });
    return { runs: runs, cabs: cabs, ports: used, riser: riser, main: main };
  }
  function ceilTo(n, steps) { for (var i = 0; i < steps.length; i++) if (n <= steps[i]) return steps[i]; return steps[steps.length - 1]; }

  function computeStandards(r) {
    var res = [], longest = 0, over = 0, area = totalArea(), F = floors();
    r.runs.forEach(function (x) { longest = Math.max(longest, x.len); if (x.len > STD.maxLink) over++; });
    if (r.runs.length) {
      res.push({ label: 'Permanent link ≤ ' + STD.maxLink + ' m', value: 'Longest ' + Math.ceil(longest) + ' m', ok: over === 0, note: over ? over + ' run(s) too long: add a telecommunications room' : 'All horizontal runs comply' });
      res.push({ label: 'Channel ≤ ' + STD.maxChannel + ' m', value: Math.ceil(longest + STD.patch) + ' m with ' + STD.patch + ' m of patch cords', ok: longest + STD.patch <= STD.maxChannel + 0.5, note: 'TIA-568.2-D / ISO 11801' });
    }
    var roomsOk = true, need = 0;
    for (var f = 0; f < F; f++) {
      var af = polyArea(polyM(f)), n = Math.ceil(af / STD.roomArea); need += n;
      if (st.layers.length && r.cabs.filter(function (c) { return fl(c) === f; }).length < n) roomsOk = false;
    }
    res.push({ label: 'Telecom room on every floor, 1 per ' + STD.roomArea + ' m²', value: r.cabs.length + ' for ' + Math.round(area) + ' m² on ' + F + ' floor' + (F > 1 ? 's' : ''), ok: roomsOk && (!st.layers.length || r.cabs.length >= need), note: 'TIA-569-D' });
    if (on('cabling')) {
      var wa = Math.max(1, Math.round(area / STD.workArea)), outs = st.devices.filter(function (d) { return d.type === 'outlet' && visible(d); }).length;
      res.push({ label: 'Work areas (2 ports each)', value: outs + ' outlets for about ' + wa + ' work areas of ' + STD.workArea + ' m²', ok: outs >= Math.floor(wa * 0.45) || outs >= 200 * F, note: outs >= 200 * F && outs < Math.floor(wa * 0.45) ? 'Very large plan: outlets are capped, add the rest on site' : 'Each outlet is a duplex Cat6 box' });
    }
    if (on('wifi')) res.push({ label: 'WiFi access points', value: '2 × Cat6A each, about one per 100 to 150 m²', ok: true, note: 'TIA TSB-162-A: Cat6A to every access point' });
    res.push({ label: 'Patch panel spare capacity', value: Math.round(STD.spare * 100) + '% spare ports', ok: true, note: 'Panels are sized with growth included' });
    res.push({ label: 'Labelling and bonding', value: 'Port IDs like MDF-PP1-01, one ground bar per room', ok: true, note: 'TIA-606-C labelling, TIA-607-D grounding' });
    if (r.riser && r.riser.length) {
      var fib = r.riser.map(function (c) { return c.cab.label + ' ' + Math.ceil(c.len) + ' m'; });
      res.push({ label: 'Fiber backbone (OM4 / OS2)' + (F > 1 ? ' and floor risers' : ''), value: fib.slice(0, 8).join(', ') + (fib.length > 8 ? ' …' : ''), ok: true, note: 'MDF to each IDF, 6-core minimum' + (F > 1 ? ', one vertical riser shaft' : '') });
    }
    return res;
  }

  function renderSummary(r) {
    var cnt = {}, len = {}, byCat = {}, F = floors();
    st.devices.forEach(function (d) { if (visible(d)) cnt[d.type] = (cnt[d.type] || 0) + 1; });
    r.runs.forEach(function (x) { len[x.dev.type] = (len[x.dev.type] || 0) + x.len; byCat[x.cat] = (byCat[x.cat] || 0) + x.len; });
    var totalLen = r.runs.reduce(function (a, x) { return a + x.len; }, 0);
    var riserLen = (r.riser || []).reduce(function (a, x) { return a + x.len; }, 0);
    var h = '<h3>Bill of quantities' + (F > 1 ? ' (all ' + F + ' floors)' : '') + '</h3><div class="table-wrap"><table><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Cable (m)</th></tr></thead><tbody>';
    Object.keys(TYPES).forEach(function (t) {
      if (!cnt[t]) return;
      h += '<tr><td>' + esc(TYPES[t].label) + (PORTS[t] > 1 ? ' <span class="muted">(' + PORTS[t] + ' cables each)</span>' : '') + '</td><td class="num">' + cnt[t] + '</td><td class="num">' + (len[t] ? Math.ceil(len[t]) : '–') + '</td></tr>';
    });
    if (riserLen) h += '<tr><td>Fiber backbone and risers (OS2 6-core)</td><td class="num">' + r.riser.length + '</td><td class="num">' + Math.ceil(riserLen) + '</td></tr>';
    h += '<tr><th>Total copper, with slack</th><th class="num">' + r.runs.length + ' runs</th><th class="num">' + Math.ceil(totalLen) + '</th></tr></tbody></table></div>';
    h += '<p class="muted">' + Object.keys(byCat).map(function (c) { return c + ': ' + Math.ceil(byCat[c]) + ' m (' + Math.max(1, Math.ceil(byCat[c] / 305)) + ' box)'; }).join(' · ') + '. Lengths use the route to the nearest cabinet on the same floor plus 10% and 3 m for the rise and service loop.</p>';

    if (F > 1) {
      h += '<h3>By floor</h3><div class="table-wrap"><table><thead><tr><th>Floor</th><th class="num">Area (m²)</th><th class="num">Cabinets</th><th class="num">Devices</th><th class="num">Cable (m)</th></tr></thead><tbody>';
      for (var f = 0; f < F; f++) {
        var devs = st.devices.filter(function (d) { return fl(d) === f && d.type !== 'cab' && visible(d); }).length, cabsN = r.cabs.filter(function (c) { return fl(c) === f; }).length;
        var cl = r.runs.filter(function (x) { return fl(x.dev) === f; }).reduce(function (a, x) { return a + x.len; }, 0);
        h += '<tr><td>Floor ' + (f + 1) + '</td><td class="num">' + Math.round(polyArea(polyM(f))) + '</td><td class="num">' + cabsN + '</td><td class="num">' + devs + '</td><td class="num">' + Math.ceil(cl) + '</td></tr>';
      }
      h += '</tbody></table></div>';
    }

    var std = computeStandards(r);
    h += '<h3>Standards check</h3><div class="table-wrap"><table class="pl-std"><thead><tr><th></th><th>Requirement</th><th>This layout</th><th>Reference</th></tr></thead><tbody>';
    std.forEach(function (x) { h += '<tr><td class="' + (x.ok ? 'ok' : 'bad') + '">' + (x.ok ? '✔' : '✕') + '</td><td>' + esc(x.label) + '</td><td>' + esc(x.value) + '</td><td class="muted">' + esc(x.note) + '</td></tr>'; });
    h += '</tbody></table></div>';

    if (on('network') && r.cabs.length) {
      h += '<h3>Network topology</h3><p>' + (F > 1 ? 'Core router/firewall and core switch at the MDF (floor 1). Each of the ' + F + ' floors has its own telecom room with PoE+ access switches, joined to the core by a fiber riser (10G uplinks, dual path where the budget allows).' : 'Router/firewall and core switch at the MDF' + (r.cabs.length > 1 ? ', fiber uplinks to ' + (r.cabs.length - 1) + ' IDF' + (r.cabs.length > 2 ? 's' : '') : '') + ', PoE+ access switches feeding every device.') + ' Voice, CCTV and WiFi each get their own VLAN.</p>';
    }
    if (on('termination') && r.runs.length) {
      h += '<h3>End-to-end termination</h3><p>' + r.runs.length * 2 + ' terminations: ' + r.runs.length + ' on the patch panels and ' + r.runs.length + ' at the outlet or device end. Every run has the same ID at both ends and is tested and certified (TIA-1152-A).</p>';
    }
    if (r.cabs.length && (on('cabinet') || on('network') || r.runs.length)) {
      h += '<h3>Cabinet contents</h3><div class="table-wrap"><table><thead><tr><th>Cabinet</th><th>Floor</th><th class="num">Ports</th><th>Equipment</th><th class="num">Size</th></tr></thead><tbody>';
      r.cabs.forEach(function (c) {
        var mine = r.runs.filter(function (x) { return x.cab.id === c.id; }), n = mine.length;
        var q = function (t) { return mine.filter(function (x) { return x.dev.type === t && x.n === 0; }).length; };
        var items = [], u = 0, grown = Math.ceil(n * (1 + STD.spare));
        var pp = Math.ceil(grown / STD.panel); if (n) { items.push(pp + ' × 24-port Cat6' + (mine.some(function (x) { return x.cat === 'Cat6A'; }) ? '/6A' : '') + ' patch panel'); u += pp; }
        var sw = Math.ceil(grown / 48); if (on('network') && n) { items.push(sw + ' × 48-port PoE+ switch'); u += sw; }
        if (on('cctv') && q('cam')) { items.push('NVR ' + ceilTo(q('cam'), [4, 8, 16, 32, 64]) + ' ch'); u += 2; }
        if (on('paging') && q('spk')) { items.push('Paging amp ≥ ' + Math.ceil(q('spk') * 6 / 10) * 10 + ' W'); u += 2; }
        if (on('pbx') && q('phone')) { items.push('IP PBX (' + q('phone') + ' extensions)'); u += 1; }
        if (on('network') && c.label === 'MDF') { items.push('Router / firewall' + (F > 1 || r.cabs.length > 1 ? ', core switch (10G)' : '')); u += F > 1 ? 2 : 1; }
        if (n) { items.push('Ground bar, cable management, UPS'); u += 3; }
        u = Math.ceil(u * 1.3) + 2;
        h += '<tr><td>' + esc(c.label) + '</td><td>' + (fl(c) + 1) + '</td><td class="num">' + n + '</td><td>' + (items.length ? items.map(esc).join('<br>') : '–') + '</td><td class="num">' + ceilTo(u, [6, 9, 12, 22, 42]) + 'U</td></tr>';
      });
      h += '</tbody></table></div>';
    }
    if (on('termination') && r.runs.length) {
      h += '<details class="pl-sched"><summary>Termination schedule (' + r.runs.length + ')</summary><div class="table-wrap"><table><thead><tr><th>Port</th><th>Device</th><th>Type</th><th>Cable</th><th class="num">Length (m)</th></tr></thead><tbody>';
      r.runs.forEach(function (x) { h += '<tr><td class="mono">' + esc(x.port) + '</td><td>' + esc(x.dev.label) + (PORTS[x.dev.type] > 1 ? '.' + (x.n + 1) : '') + '</td><td>' + esc(EDGE[x.dev.type]) + '</td><td>' + x.cat + '</td><td class="num">' + Math.ceil(x.len) + '</td></tr>'; });
      h += '</tbody></table></div></details>';
    }
    $('pl-summary').innerHTML = h;
  }

  /* ---------- moving objects: patrol the building, cameras turn to face them ---------- */
  var sim = { objs: [], play: true, track: true, cam: {}, nav: {}, seq: 0, last: 0, raf: 0 };
  function turn(a, b, max) { var d = ((b - a + 540) % 360) - 180; return a + clamp(d, -max, max); }
  function deg(dx, dy) { return Math.atan2(dy, dx) * 180 / Math.PI; }
  function rotOf(d) { var c = d.type === 'cam' && sim.cam[d.id]; return c ? c.rot : (d.rot || 0); }

  function navFor(f) {
    var poly = polyM(f), key = f + '|' + st.width + '|' + Math.round(H() * 100) + '|' + JSON.stringify(poly) + '|' + (wallRes(f) ? 1 : 0) + '|' + st.s.walls3d, n = sim.nav[f];
    if (n && n.key === key) return n;
    var W = st.width, Hh = H(), cs = Math.max(0.5, Math.max(W, Hh) / 150), nx = Math.max(1, Math.ceil(W / cs)), ny = Math.max(1, Math.ceil(Hh / cs));
    function build(useWalls) {
      var free = new Uint8Array(nx * ny), total = 0;
      for (var j = 0; j < ny; j++) for (var i = 0; i < nx; i++) {
        var x = (i + 0.5) * cs, y = (j + 0.5) * cs;
        if (insideM(poly, x, y, 0.25)) { total++; if (!useWalls || !wallAt(f, x, y)) free[j * nx + i] = 1; }
      }
      // keep only the biggest connected walkable area
      var lab = new Int32Array(nx * ny), best = 0, bs = 0, id = 0, stack = [];
      for (var s = 0; s < nx * ny; s++) {
        if (!free[s] || lab[s]) continue;
        id++; var cnt = 0; stack = [s]; lab[s] = id;
        while (stack.length) {
          var p = stack.pop(), px = p % nx, py = (p - px) / nx; cnt++;
          for (var dy = -1; dy <= 1; dy++) for (var dx = -1; dx <= 1; dx++) {
            var qx = px + dx, qy = py + dy; if ((!dx && !dy) || qx < 0 || qy < 0 || qx >= nx || qy >= ny) continue;
            var q = qy * nx + qx; if (free[q] && !lab[q]) { lab[q] = id; stack.push(q); }
          }
        }
        if (cnt > bs) { bs = cnt; best = id; }
      }
      var out = new Uint8Array(nx * ny), cells = [];
      for (s = 0; s < nx * ny; s++) if (lab[s] === best) { out[s] = 1; cells.push(s); }
      return { free: out, cells: cells, total: total };
    }
    var b = build(true);
    if (b.cells.length < 0.3 * b.total) b = build(false);   // doors not found: ignore the walls rather than trap objects in one room
    n = { key: key, cs: cs, nx: nx, ny: ny, free: b.free, cells: b.cells };
    sim.nav[f] = n; return n;
  }
  function navCell(n, x, y) { return clamp(Math.floor(y / n.cs), 0, n.ny - 1) * n.nx + clamp(Math.floor(x / n.cs), 0, n.nx - 1); }
  function nearestFree(n, x, y) {
    var c = navCell(n, x, y); if (n.free[c]) return c;
    var best = -1, bd = 1e9;
    for (var k = 0; k < n.cells.length; k += 1) { var cc = n.cells[k], cx = (cc % n.nx + 0.5) * n.cs, cy = (Math.floor(cc / n.nx) + 0.5) * n.cs, d = Math.hypot(cx - x, cy - y); if (d < bd) { bd = d; best = cc; } }
    return best;
  }
  function los(n, a, b) {
    var d = Math.hypot(b[0] - a[0], b[1] - a[1]), steps = Math.ceil(d / (n.cs * 0.5));
    for (var i = 1; i < steps; i++) { var t = i / steps; if (!n.free[navCell(n, a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t)]) return false; }
    return true;
  }
  function findPath(n, from, to) {
    var s = nearestFree(n, from[0], from[1]), g = nearestFree(n, to[0], to[1]);
    if (s < 0 || g < 0) return [];
    var par = new Int32Array(n.nx * n.ny).fill(-2), q = [s], head = 0; par[s] = -1;
    while (head < q.length && par[g] === -2) {
      var p = q[head++], px = p % n.nx, py = (p - px) / n.nx;
      for (var dy = -1; dy <= 1; dy++) for (var dx = -1; dx <= 1; dx++) {
        var qx = px + dx, qy = py + dy; if ((!dx && !dy) || qx < 0 || qy < 0 || qx >= n.nx || qy >= n.ny) continue;
        var c = qy * n.nx + qx; if (par[c] !== -2 || !n.free[c]) continue;
        if (dx && dy && (!n.free[py * n.nx + qx] || !n.free[qy * n.nx + px])) continue;
        par[c] = p; q.push(c);
      }
    }
    if (par[g] === -2) return [];
    var cells = []; for (var c2 = g; c2 !== -1; c2 = par[c2]) cells.push(c2);
    cells.reverse();
    var pts = cells.map(function (c3) { return [(c3 % n.nx + 0.5) * n.cs, (Math.floor(c3 / n.nx) + 0.5) * n.cs]; });
    var out = [], i = 0;      // straighten the route
    while (i < pts.length - 1) { var j = pts.length - 1; while (j > i + 1 && !los(n, pts[i], pts[j])) j--; out.push(pts[j]); i = j; }
    return out;
  }
  function newTarget(o) {
    var n = navFor(o.fl); if (!n.cells.length) return;
    var c = n.cells[Math.floor(Math.random() * n.cells.length)];
    o.path = findPath(n, [o.x, o.y], [(c % n.nx + 0.5) * n.cs, (Math.floor(c / n.nx) + 0.5) * n.cs]);
    if (!o.path.length) o.wait = 1;
  }
  function addObject(kind) {
    if (sim.objs.length >= 12) { setStatus('Up to 12 moving objects', true); return; }
    var n = navFor(st.floor); if (!n.cells.length) { setStatus('No walkable area on this floor', true); return; }
    var c = n.cells[Math.floor(Math.random() * n.cells.length)], K = KINDS[kind];
    var count = sim.objs.filter(function (o) { return o.k === kind; }).length + 1;
    var o = { id: 'o' + (++sim.seq), k: kind, label: K.short + count, x: (c % n.nx + 0.5) * n.cs, y: (Math.floor(c / n.nx) + 0.5) * n.cs, fl: st.floor, h: Math.round(Math.random() * 360), sp: K.speed, path: [], wait: 0, pause: false };
    sim.objs.push(o); newTarget(o); sim.play = true; syncSim(); startSim(); drawDyn();
  }
  function selObj() { return sim.objs.filter(function (o) { return o.id === st.osel; })[0]; }
  function stepSim(dt) {
    sim.objs.forEach(function (o) {
      if (o.pause) return;
      if (o.wait > 0) { o.wait -= dt; if (o.wait <= 0 && !o.path.length) newTarget(o); return; }
      if (!o.path.length) { newTarget(o); return; }
      var t = o.path[0], dx = t[0] - o.x, dy = t[1] - o.y, d = Math.hypot(dx, dy), step = o.sp * dt;
      if (d > 0.01) o.h = turn(o.h, deg(dx, dy), 420 * dt);
      if (d <= step) { o.x = t[0]; o.y = t[1]; o.path.shift(); if (!o.path.length) o.wait = 0.5 + Math.random() * 1.5; }
      else { o.x += dx / d * step; o.y += dy / d * step; }
    });
  }
  /* A camera turns to the nearest object in its range (like a PTZ camera) and swings back when it leaves. */
  function trackCams(dt) {
    var range = +st.s.camera_range || 12, cams = st.devices.filter(function (d) { return d.type === 'cam' && visible(d); }), live = {};
    cams.forEach(function (c) {
      var cs = sim.cam[c.id] || (sim.cam[c.id] = { rot: c.rot || 0, tgt: null, idle: 9 }), best = null, bd = 1e9;
      live[c.id] = 1;
      if (sim.track) sim.objs.forEach(function (o) {
        if (o.fl !== fl(c)) return;
        var d = Math.hypot(o.x - c.x, o.y - c.y), lim = range * (cs.tgt === o.id ? 1.15 : 1);
        if (d <= lim && d < bd) { bd = d; best = o; }
      });
      var want;
      if (best) { cs.tgt = best.id; cs.idle = 0; want = deg(best.x - c.x, best.y - c.y); }
      else { cs.tgt = null; cs.idle += dt; want = cs.idle > 1.2 || !sim.track ? (c.rot || 0) : cs.rot; }
      cs.rot = turn(cs.rot, want, 150 * dt);
    });
    Object.keys(sim.cam).forEach(function (id) { if (!live[id]) delete sim.cam[id]; });
  }
  function simTick(t) {
    sim.raf = 0;
    if (!sim.objs.length) { sim.cam = {}; render(); return; }
    var dt = Math.min(0.1, (t - sim.last) / 1000 || 0); sim.last = t;
    if (sim.play) stepSim(dt);
    trackCams(dt);
    if (!$('pl-stage').hidden) drawDyn();
    syncObjInspector(true);
    sim.raf = requestAnimationFrame(simTick);
  }
  function startSim() { if (!sim.raf && sim.objs.length) { sim.last = performance.now(); sim.raf = requestAnimationFrame(simTick); } }
  function syncSim() {
    $('pl-obj-play').textContent = sim.play ? 'Pause' : 'Play';
    $('pl-obj-count').textContent = sim.objs.length ? sim.objs.length + ' moving' : 'none yet';
  }
  function syncObjInspector(light) {
    var o = selObj(); $('pl-oinspector').hidden = !o;
    if (!o) return;
    if (!light) { $('pl-ohead').value = Math.round(((o.h % 360) + 360) % 360); }
    else if (document.activeElement !== $('pl-ohead')) $('pl-ohead').value = Math.round(((o.h % 360) + 360) % 360);
    $('pl-opause').textContent = o.pause ? 'Resume walking' : 'Stand still';
    $('pl-oname').textContent = KINDS[o.k].label + ' ' + o.label + ' · floor ' + (o.fl + 1);
  }

  function dynMarkup() {
    var p = ppm(), cov = [], obj = [], range = +st.s.camera_range || 12, fov = +st.s.camera_fov || 90;
    if (st.show.cover) st.devices.forEach(function (d) {
      if (d.type !== 'cam' || !visible(d) || !onFloor(d)) return;
      var cs = sim.cam[d.id], rot = rotOf(d), t = TYPES.cam, tgt = cs && cs.tgt ? sim.objs.filter(function (o) { return o.id === cs.tgt; })[0] : null;
      var x = d.x * p, y = d.y * p, r = range * p, a1 = (rot - fov / 2) * Math.PI / 180, a2 = (rot + fov / 2) * Math.PI / 180, col = tgt ? '#e03131' : t.color;
      cov.push('<path d="M' + x + ' ' + y + ' L' + (x + r * Math.cos(a1)) + ' ' + (y + r * Math.sin(a1)) + ' A' + r + ' ' + r + ' 0 ' + (fov > 180 ? 1 : 0) + ' 1 ' + (x + r * Math.cos(a2)) + ' ' + (y + r * Math.sin(a2)) + ' Z" fill="' + col + '" fill-opacity="' + (tgt ? '.2' : '.09') + '" stroke="' + col + '" stroke-opacity="' + (tgt ? '.8' : '.4') + '" stroke-width="' + (tgt ? 1.6 : 1) + '"/>');
      if (tgt) cov.push('<line x1="' + x + '" y1="' + y + '" x2="' + tgt.x * p + '" y2="' + tgt.y * p + '" stroke="#e03131" stroke-width="1.4" stroke-dasharray="4 3"/>');
    });
    sim.objs.forEach(function (o) {
      if (o.fl !== st.floor) return;
      var K = KINDS[o.k], s = st.osel === o.id, body;
      if (o.k === 'vehicle') body = '<rect x="-15" y="-8" width="30" height="16" rx="4" fill="' + K.color + '" stroke="#fff" stroke-width="2"/><rect x="2" y="-6" width="9" height="12" rx="2" fill="#ffd43b"/>';
      else body = '<circle r="9" fill="' + K.color + '" stroke="#fff" stroke-width="2"/><polygon points="9,-5 18,0 9,5" fill="' + K.color + '" stroke="#fff" stroke-width="1.2"/>';
      obj.push('<g data-oid="' + o.id + '" transform="translate(' + o.x * p + ',' + o.y * p + ')" style="cursor:grab"><g transform="rotate(' + o.h.toFixed(1) + ')">' + (s ? '<circle r="22" fill="none" stroke="#0c1420" stroke-width="2" stroke-dasharray="3 3"/>' : '') + body + '</g>' +
        '<text y="-17" text-anchor="middle" font-size="10" font-weight="700" font-family="Segoe UI,Arial,sans-serif" fill="#0c1420" stroke="#fff" stroke-width="3" paint-order="stroke" pointer-events="none">' + esc(o.label) + '</text></g>');
    });
    return { cov: cov.join(''), obj: obj.join('') };
  }
  function drawDyn() {
    var a = $('pl-dyn-cov'), b = $('pl-dyn-obj'); if (!a || !b) return;
    var m = dynMarkup(); a.innerHTML = m.cov; b.innerHTML = m.obj;
  }

  /* ---------- drawing ---------- */
  function shape(d) {
    var t = TYPES[d.type], c = t.color, g = t.glyph, sel = st.sel === d.id;
    var ring = sel ? '<circle r="16" fill="none" stroke="#0c1420" stroke-width="2" stroke-dasharray="3 3"/>' : '';
    var body;
    if (d.type === 'cab') body = '<rect x="-11" y="-11" width="22" height="22" rx="4" fill="' + c + '" stroke="#fff" stroke-width="2"/>';
    else if (d.type === 'outlet') body = '<rect x="-7" y="-7" width="14" height="14" rx="2" fill="' + c + '" stroke="#fff" stroke-width="1.6"/>';
    else if (d.type === 'ap') body = '<circle r="9" fill="#fff" stroke="' + c + '" stroke-width="3"/>';
    else body = '<circle r="9" fill="' + c + '" stroke="#fff" stroke-width="2"/>';
    var fg = d.type === 'ap' ? c : '#fff', fs = d.type === 'cab' ? 13 : 10;
    return ring + body + '<text y="' + (fs / 3) + '" text-anchor="middle" font-size="' + fs + '" font-weight="700" font-family="Segoe UI,Arial,sans-serif" fill="' + fg + '">' + g + '</text>';
  }
  function pts(poly, p) { return poly.map(function (q) { return q[0] * p + ',' + q[1] * p; }).join(' '); }

  function svgMarkup(forExport) {
    var Hh = VB * st.s.ratio, p = ppm(), out = [];
    out.push('<defs><pattern id="g5" width="' + 5 * p + '" height="' + 5 * p + '" patternUnits="userSpaceOnUse"><path d="M ' + 5 * p + ' 0 L 0 0 0 ' + 5 * p + '" fill="none" stroke="#c9d3df" stroke-width="1"/></pattern></defs>');
    if (!st.pdfReady) out.push('<rect width="' + VB + '" height="' + Hh + '" fill="#f7f9fc"/><rect width="' + VB + '" height="' + Hh + '" fill="url(#g5)"/><rect x="1" y="1" width="' + (VB - 2) + '" height="' + (Hh - 2) + '" fill="none" stroke="#7d8a9a" stroke-width="2"/>');

    if (st.show.outline && outlineN(st.floor)) out.push('<polygon points="' + pts(polyM(st.floor), p) + '" fill="#2a78d6" fill-opacity=".06" stroke="#2a78d6" stroke-width="3" stroke-linejoin="round" stroke-dasharray="10 6"/>');
    var wr = wallRes(st.floor);
    if (st.show.walls && wr) {
      wr.walls.forEach(function (w) { out.push('<rect x="' + w[0] / wr.grid.nx * VB + '" y="' + w[1] / wr.grid.ny * Hh + '" width="' + (w[2] - w[0]) / wr.grid.nx * VB + '" height="' + (w[3] - w[1]) / wr.grid.ny * Hh + '" fill="#e03131" fill-opacity=".38"/>'); });
    }

    var devs = st.devices.filter(function (d) { return visible(d) && onFloor(d); });
    if (st.show.cover) {
      devs.forEach(function (d) {
        var x = d.x * p, y = d.y * p, t = TYPES[d.type];
        if (d.type === 'ap') out.push('<circle cx="' + x + '" cy="' + y + '" r="' + 10 * p + '" fill="' + t.color + '" fill-opacity=".04" stroke="' + t.color + '" stroke-opacity=".3" stroke-width="1" stroke-dasharray="5 4"/>');
        else if (d.type === 'spk') out.push('<circle cx="' + x + '" cy="' + y + '" r="' + 4.5 * p + '" fill="' + t.color + '" fill-opacity=".07" stroke="' + t.color + '" stroke-opacity=".3" stroke-width="1"/>');
      });
    }
    out.push('<g id="pl-dyn-cov">' + (forExport ? dynMarkup().cov : '') + '</g>');

    var r = computeRuns();
    if (st.show.runs) {
      r.runs.forEach(function (x) {
        if (x.n > 0 || !onFloor(x.dev)) return;
        var c = TYPES[x.dev.type].color, cx = x.cab.x, cy = x.cab.y;
        out.push('<polyline points="' + x.dev.x * p + ',' + x.dev.y * p + ' ' + cx * p + ',' + x.dev.y * p + ' ' + cx * p + ',' + cy * p + '" fill="none" stroke="' + c + '" stroke-opacity=".55" stroke-width="1.4" stroke-linejoin="round"' + (x.dev.type === 'outlet' ? ' stroke-dasharray="4 3"' : '') + '/>');
      });
    }

    devs.forEach(function (d) {
      out.push('<g data-id="' + esc(d.id) + '" transform="translate(' + d.x * p + ',' + d.y * p + ')" style="cursor:grab">' + shape(d) + '</g>');
    });
    out.push('<g id="pl-dyn-obj">' + (forExport ? dynMarkup().obj : '') + '</g>');

    if (st.show.labels) {
      var term = on('termination'), portOf = {};
      r.runs.forEach(function (x) { if (x.n === 0) portOf[x.dev.id] = x.port; });
      devs.forEach(function (d) {
        var txt = d.label + (term && portOf[d.id] ? ' · ' + portOf[d.id].replace(/^.*-PP/, 'T') : '');
        out.push('<text x="' + d.x * p + '" y="' + (d.y * p + (d.type === 'cab' ? 26 : 22)) + '" text-anchor="middle" font-size="10" font-family="Segoe UI,Arial,sans-serif" fill="#0c1420" stroke="#fff" stroke-width="3" paint-order="stroke" pointer-events="none">' + esc(txt) + '</text>');
      });
    }
    if (st.draw) {
      var dp = st.draw;
      if (dp.length) out.push('<polyline points="' + pts(dp, p) + '" fill="rgba(42,120,214,.12)" stroke="#2a78d6" stroke-width="3" stroke-linejoin="round"/>');
      dp.forEach(function (q, i) { out.push('<circle cx="' + q[0] * p + '" cy="' + q[1] * p + '" r="' + (i === 0 ? 9 : 5) + '" fill="' + (i === 0 ? '#fff' : '#2a78d6') + '" stroke="#2a78d6" stroke-width="3"/>'); });
    }
    return { markup: out.join(''), runs: r };
  }

  var raf = 0;
  function render() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(draw);
  }
  function draw() {
    var svg = $('pl-svg'), Hh = VB * st.s.ratio;
    svg.setAttribute('viewBox', '0 0 ' + VB + ' ' + Hh);
    $('pl-stage').style.aspectRatio = VB + ' / ' + Hh;
    var m = svgMarkup(false);
    svg.innerHTML = m.markup;
    drawDyn();
    renderSummary(m.runs);
    renderLegend();
    $('pl-height').value = round1(H());
  }
  function renderLegend() {
    var h = '';
    Object.keys(TYPES).forEach(function (t) {
      var used = st.devices.some(function (d) { return d.type === t && visible(d) && onFloor(d); });
      if (!used) return;
      h += '<li><i style="background:' + TYPES[t].color + '">' + TYPES[t].glyph + '</i>' + esc(TYPES[t].label) + '</li>';
    });
    $('pl-legend').innerHTML = h;
  }

  /* ---------- UI ---------- */
  function buildLayerList() {
    var h = '';
    Object.keys(cfg.allLayers).forEach(function (k) {
      h += '<label class="chk"><input type="checkbox" data-layer="' + k + '"' + (on(k) ? ' checked' : '') + '> ' + esc(cfg.allLayers[k]) + '</label>';
    });
    $('pl-layers').innerHTML = h;
    var t = '';
    Object.keys(TYPES).forEach(function (k) {
      t += '<button type="button" class="btn small" data-tool="' + k + '"><i style="background:' + TYPES[k].color + '">' + TYPES[k].glyph + '</i>' + esc(TYPES[k].label) + '</button>';
    });
    $('pl-tools').innerHTML = t;
  }
  function setStatus(msg, bad) {
    var el = $('pl-status');
    el.textContent = msg; el.className = 'pl-status' + (bad ? ' bad' : '');
  }
  function dirty() { st.dirty = true; setStatus('Unsaved changes'); render(); }
  function sMsg(text, bad) { var m = $('pl-shape-msg'); m.hidden = !text; m.textContent = text || ''; m.className = 'pl-hint' + (bad ? ' bad' : ''); }
  function syncInspector() {
    var d = st.devices.filter(function (x) { return x.id === st.sel; })[0];
    $('pl-inspector').hidden = !d;
    if (d) {
      $('pl-ilabel').value = d.label || '';
      $('pl-irot').value = Math.round(d.rot || 0);
      $('pl-irot-wrap').hidden = d.type !== 'cam';
    }
    syncObjInspector();
  }
  function buildFloorBar() {
    var h = '', F = floors();
    for (var f = 0; f < F; f++) h += '<option value="' + f + '">Floor ' + (f + 1) + (f === 0 ? ' (ground)' : '') + '</option>';
    $('pl-floor').innerHTML = h; $('pl-floor').value = String(st.floor);
    $('pl-floorbar').hidden = F < 2 && !st.hasPdf;
    $('pl-floor-prev').disabled = st.floor <= 0; $('pl-floor-next').disabled = st.floor >= F - 1;
    $('pl-floors').value = F;
    $('pl-same-wrap').hidden = F < 2;
  }
  function syncControls() {
    $('pl-width').value = st.width;
    $('pl-density').value = String(st.s.density);
    $('pl-range').value = st.s.camera_range;
    $('pl-fov').value = st.s.camera_fov;
    $('pl-height-wrap').hidden = st.hasPdf;
    $('pl-page-wrap').hidden = !st.hasPdf;
    $('pl-page').value = pageOf(st.floor);
    $('pl-ceiling').value = st.s.ceiling || 3;
    $('pl-sens').value = st.s.sens;
    $('pl-walls3d').checked = !!st.s.walls3d;
    $('pl-same').checked = !Object.keys(st.s.outlines).length;
    ['pl-detect', 'pl-sens-wrap', 'pl-showwalls-wrap'].forEach(function (id) { $(id).hidden = !st.hasPdf; });
    buildFloorBar(); syncSim();
  }
  function msg(text) { var m = $('pl-msg'); m.hidden = !text; m.textContent = text || ''; }

  function point(e) {
    var svg = $('pl-svg'), pt = svg.createSVGPoint();
    pt.x = e.clientX; pt.y = e.clientY;
    var q = pt.matrixTransform(svg.getScreenCTM().inverse());
    return { x: q.x / ppm(), y: q.y / ppm() };
  }

  function setFloor(f) {
    f = clamp(f, 0, floors() - 1);
    var changed = f !== st.floor;
    st.floor = f; st.sel = null; st.osel = null;
    buildFloorBar(); syncInspector(); $('pl-page').value = pageOf(f);
    if (changed) { if (st.hasPdf) loadPage(); document.dispatchEvent(new CustomEvent('plfloor')); }
    render();
  }

  /* ---------- building outline ---------- */
  function setOutline(norm) {
    if (st.s.floors > 1 && !$('pl-same').checked) st.s.outlines[st.floor] = norm;
    else { st.s.outline = norm; st.s.outlines = {}; }
    sim.nav = {}; dirty();
  }
  function detectShape() {
    if (!st.hasPdf) return;
    sMsg('Reading the plan…');
    var page = pageOf(st.floor);
    analyzePage(page, true).then(function (r) {
      if (!r.ok) { sMsg(r.reason || 'No building outline was found. Try a different sensitivity, or draw the outline by hand.', true); return; }
      setOutline(r.outline);
      st.show.walls = true; $('pl-showwalls').checked = true;
      sMsg('Found a ' + r.outline.length + '-sided building (about ' + Math.round(polyArea(polyM(st.floor))) + ' m²) and ' + r.walls.length + ' wall segments. Press Auto-generate to place the systems inside it.');
    });
  }
  function finishDraw() {
    if (!st.draw || st.draw.length < 3) { sMsg('Click at least 3 corners, then click the first point or press Enter.', true); return; }
    var W = st.width, h = H(), norm = st.draw.map(function (q) { return [Math.round(q[0] / W * 10000) / 10000, Math.round(q[1] / h * 10000) / 10000]; });
    st.draw = null; $('pl-draw').classList.remove('on'); $('pl-svg').style.cursor = '';
    setOutline(norm); sMsg('Outline saved. Press Auto-generate to place the systems inside it.');
  }
  function resetOutline() {
    if (st.s.floors > 1 && !$('pl-same').checked) delete st.s.outlines[st.floor]; else { delete st.s.outline; st.s.outlines = {}; }
    sim.nav = {}; sMsg('Using the full rectangle.'); dirty();
  }

  var drag = null;
  $('pl-svg').addEventListener('pointerdown', function (e) {
    if (st.draw) {
      var q = point(e), W = st.width, h = H(), first = st.draw[0];
      if (first && st.draw.length >= 3 && Math.hypot(q.x - first[0], q.y - first[1]) < W * 0.02) { finishDraw(); return; }
      st.draw.push([round1(clamp(q.x, 0, W)), round1(clamp(q.y, 0, h))]); render(); return;
    }
    var og = e.target.closest('[data-oid]');
    if (og) {
      st.osel = og.getAttribute('data-oid'); st.sel = null; st.tool = null; markTools(); syncInspector();
      drag = { oid: st.osel, moved: false }; $('pl-svg').setPointerCapture(e.pointerId); drawDyn(); return;
    }
    var g = e.target.closest('[data-id]');
    if (st.tool && !g) {
      var p = point(e), d = { id: uid(), type: st.tool, x: round1(clamp(p.x, 0, st.width)), y: round1(clamp(p.y, 0, H())), rot: st.tool === 'cam' ? 90 : 0, label: '', fl: st.floor };
      var n = st.devices.filter(function (x) { return x.type === d.type && fl(x) === st.floor; }).length + 1;
      d.label = TYPES[d.type].short + (floors() > 1 ? '-F' + (st.floor + 1) : '') + '-' + (n < 10 ? '0' : '') + n;
      st.devices.push(d); st.sel = d.id; st.osel = null; syncInspector(); dirty();
      return;
    }
    if (g) {
      st.sel = g.getAttribute('data-id'); st.osel = null; st.tool = null; markTools(); syncInspector();
      drag = { id: st.sel, moved: false }; $('pl-svg').setPointerCapture(e.pointerId); render();
    } else { st.sel = null; st.osel = null; syncInspector(); render(); }
  });
  $('pl-svg').addEventListener('pointermove', function (e) {
    if (!drag) return;
    var p = point(e);
    if (drag.oid) {
      var o = selObj(); if (!o) return;
      o.x = clamp(p.x, 0, st.width); o.y = clamp(p.y, 0, H()); o.path = []; o.wait = 0.4; drag.moved = true; drawDyn(); return;
    }
    var d = st.devices.filter(function (x) { return x.id === drag.id; })[0];
    if (!d) return;
    d.x = round1(clamp(p.x, 0, st.width)); d.y = round1(clamp(p.y, 0, H())); drag.moved = true; render();
  });
  $('pl-svg').addEventListener('pointerup', function () {
    if (drag && drag.oid) { var o = selObj(); if (o && drag.moved) { var n = navFor(o.fl), c = nearestFree(n, o.x, o.y); if (c >= 0 && !n.free[navCell(n, o.x, o.y)]) { o.x = (c % n.nx + 0.5) * n.cs; o.y = (Math.floor(c / n.nx) + 0.5) * n.cs; } o.wait = 0.4; drawDyn(); } }
    else if (drag && drag.moved) dirty();
    drag = null;
  });
  $('pl-svg').addEventListener('dblclick', function () { if (st.draw) { if (st.draw.length > 3) st.draw.pop(); finishDraw(); } });

  function markTools() {
    document.querySelectorAll('[data-tool]').forEach(function (b) { b.classList.toggle('on', b.dataset.tool === st.tool); });
    $('pl-svg').style.cursor = st.tool || st.draw ? 'crosshair' : '';
  }
  $('pl-tools').addEventListener('click', function (e) {
    var b = e.target.closest('[data-tool]'); if (!b) return;
    st.tool = st.tool === b.dataset.tool ? null : b.dataset.tool; markTools();
  });
  $('pl-layers').addEventListener('change', function (e) {
    var k = e.target.getAttribute('data-layer'); if (!k) return;
    var i = st.layers.indexOf(k);
    if (e.target.checked && i < 0) st.layers.push(k);
    if (!e.target.checked && i > -1) st.layers.splice(i, 1);
    if (e.target.checked && !st.devices.some(function (d) { return d.type !== 'cab' && TYPES[d.type].layer === k; }) && k !== 'cabinet') {
      // layer switched on with nothing placed yet: add just that layer's devices
      var keep = st.layers.slice();
      ensureAnalysis().then(function () { var only = generateOnly(k); st.layers = keep; only.forEach(function (d) { st.devices.push(d); }); dirty(); });
      return;
    }
    dirty();
  });
  function generateOnly(layer) {
    var all = st.layers.slice(); st.layers = [layer];
    var g = generateRange(0, floors() - 1).filter(function (d) { return d.type !== 'cab'; }); st.layers = all;
    g.forEach(function (d) { d.label = ''; });
    number(g, counts(st.devices));
    return g;
  }
  function bind(id, fn) { $(id).addEventListener('input', fn); }
  bind('pl-name', function () { st.name = this.value; dirty(); });
  bind('pl-width', function () { var v = parseFloat(this.value); if (v >= 5 && v <= 500) { st.width = v; sim.nav = {}; dirty(); } });
  bind('pl-height', function () { var v = parseFloat(this.value); if (v >= 3 && v <= 500 && !st.hasPdf) { st.s.ratio = v / st.width; sim.nav = {}; dirty(); } });
  bind('pl-density', function () { st.s.density = parseInt(this.value, 10); dirty(); });
  bind('pl-range', function () { var v = parseFloat(this.value); if (v >= 3) { st.s.camera_range = v; dirty(); } });
  bind('pl-fov', function () { var v = parseFloat(this.value); if (v >= 30 && v <= 180) { st.s.camera_fov = v; dirty(); } });
  bind('pl-ceiling', function () { var v = parseFloat(this.value); if (v >= 2 && v <= 10) { st.s.ceiling = v; dirty(); } });
  bind('pl-page', function () {
    var v = parseInt(this.value, 10); if (!(v >= 1 && v <= 50)) return;
    if (st.floor === 0 || floors() === 1) st.s.page = v; else st.s.floor_pages[st.floor] = v;
    loadPage(); st.dirty = true; setStatus('Unsaved changes');
  });
  bind('pl-sens', function () { st.s.sens = parseInt(this.value, 10) || 50; st.dirty = true; setStatus('Unsaved changes'); });
  $('pl-sens').addEventListener('change', function () { if (st.hasPdf) detectShape(); });
  ['runs', 'cover', 'labels'].forEach(function (k) { $('pl-' + k).addEventListener('change', function () { st.show[k] = this.checked; render(); }); });
  $('pl-showoutline').addEventListener('change', function () { st.show.outline = this.checked; render(); });
  $('pl-showwalls').addEventListener('change', function () { st.show.walls = this.checked; render(); });
  $('pl-walls3d').addEventListener('change', function () { st.s.walls3d = this.checked; sim.nav = {}; dirty(); });
  function sel() { return st.devices.filter(function (x) { return x.id === st.sel; })[0]; }
  $('pl-ilabel').addEventListener('input', function () { var d = sel(); if (d) { d.label = this.value; dirty(); } });
  $('pl-irot').addEventListener('input', function () { var d = sel(); if (d) { d.rot = parseFloat(this.value) || 0; dirty(); } });
  function removeSel() { if (!st.sel) return; st.devices = st.devices.filter(function (x) { return x.id !== st.sel; }); st.sel = null; syncInspector(); dirty(); }
  $('pl-idel').addEventListener('click', removeSel);
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase(), typing = tag === 'input' || tag === 'select' || tag === 'textarea';
    if (st.draw) {
      if (e.key === 'Enter') { e.preventDefault(); finishDraw(); return; }
      if (e.key === 'Escape') { st.draw = null; $('pl-draw').classList.remove('on'); markTools(); render(); return; }
      if (e.key === 'Backspace' && !typing) { e.preventDefault(); st.draw.pop(); render(); return; }
    }
    if (e.key === 'Escape') { st.tool = null; markTools(); }
    if ((e.key === 'Delete' || e.key === 'Backspace') && !typing) {
      e.preventDefault();
      if (st.osel) removeObj(); else removeSel();
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
  });

  /* floors */
  function setFloors(v) {
    v = clamp(v, 1, 40);
    var old = floors();
    if (v === old) return;
    if (v < old && st.devices.some(function (d) { return fl(d) >= v; }) && !window.confirm('Remove floors ' + (v + 1) + ' to ' + old + ' and their devices?')) { $('pl-floors').value = old; return; }
    st.s.floors = v;
    st.devices = st.devices.filter(function (d) { return fl(d) < v; });
    sim.objs = sim.objs.filter(function (o) { return o.fl < v; }); sim.nav = {};
    if (st.floor >= v) st.floor = v - 1;
    buildFloorBar(); syncSim();
    if (v > old && st.layers.length && st.devices.length) {
      setStatus('Placing the new floors…');
      ensureAnalysis().then(function () {
        st.devices = st.devices.concat(generateRange(old, v - 1)); dirty(); document.dispatchEvent(new CustomEvent('plfloor'));
      });
    } else { dirty(); document.dispatchEvent(new CustomEvent('plfloor')); }
  }
  $('pl-floors').addEventListener('change', function () { setFloors(parseInt(this.value, 10) || 1); });
  $('pl-floor').addEventListener('change', function () { setFloor(parseInt(this.value, 10) || 0); });
  $('pl-floor-prev').addEventListener('click', function () { setFloor(st.floor - 1); });
  $('pl-floor-next').addEventListener('click', function () { setFloor(st.floor + 1); });

  $('pl-detect').addEventListener('click', detectShape);
  $('pl-draw').addEventListener('click', function () {
    if (st.draw) { st.draw = null; this.classList.remove('on'); markTools(); render(); return; }
    st.draw = []; st.tool = null; st.sel = null; this.classList.add('on'); markTools(); render();
    sMsg('Click each corner of the building on the plan. Click the first point, double-click or press Enter to finish. Backspace undoes a corner.');
  });
  $('pl-rect').addEventListener('click', resetOutline);

  $('pl-gen').addEventListener('click', function () {
    if (st.devices.length && !window.confirm('Replace all devices on all floors with a new auto-generated layout? Hand-placed devices will be removed.')) return;
    setStatus('Generating…');
    ensureAnalysis().then(function () { st.devices = generate(); st.sel = null; syncInspector(); dirty(); });
  });

  /* moving objects UI */
  function removeObj() { sim.objs = sim.objs.filter(function (o) { return o.id !== st.osel; }); st.osel = null; syncSim(); syncInspector(); drawDyn(); }
  $('pl-obj-person').addEventListener('click', function () { addObject('person'); });
  $('pl-obj-vehicle').addEventListener('click', function () { addObject('vehicle'); });
  $('pl-obj-play').addEventListener('click', function () { sim.play = !sim.play; syncSim(); if (!sim.objs.length) addObject('person'); });
  $('pl-obj-clear').addEventListener('click', function () { sim.objs = []; st.osel = null; sim.cam = {}; syncSim(); syncInspector(); render(); });
  $('pl-track').addEventListener('change', function () { sim.track = this.checked; });
  $('pl-ohead').addEventListener('input', function () { var o = selObj(); if (o) { o.h = parseFloat(this.value) || 0; drawDyn(); } });
  $('pl-opause').addEventListener('click', function () { var o = selObj(); if (!o) return; o.pause = !o.pause; syncObjInspector(); });
  $('pl-oface').addEventListener('click', function () {
    var o = selObj(); if (!o) return;
    var cams = st.devices.filter(function (d) { return d.type === 'cam' && visible(d) && fl(d) === o.fl; }), best = null, bd = 1e9;
    cams.forEach(function (c) { var d = Math.hypot(c.x - o.x, c.y - o.y); if (d < bd) { bd = d; best = c; } });
    if (!best) { setStatus('No CCTV camera on this floor to face', true); return; }
    o.h = deg(best.x - o.x, best.y - o.y); o.pause = true; o.path = []; syncObjInspector(); drawDyn(); setStatus('Facing ' + best.label);
  });
  $('pl-odel').addEventListener('click', removeObj);

  /* ---------- save / export ---------- */
  function save() {
    if (!st.layers.length) { setStatus('Choose at least one service', true); return Promise.resolve(); }
    setStatus('Saving…');
    var settings = Object.assign({}, st.s, { outlines: st.s.outlines });
    return fetch(cfg.saveUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify({ name: st.name || 'Untitled layout', width_m: st.width, layers: st.layers, settings: settings, devices: st.devices })
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) { var m = j.errors ? j.errors[Object.keys(j.errors)[0]][0] : (j.message || 'Could not save'); throw new Error(m); }
        st.dirty = false; setStatus('Saved ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
      });
    }).catch(function (e) { setStatus(e.message || 'Could not save', true); });
  }
  $('pl-save').addEventListener('click', save);
  window.addEventListener('beforeunload', function (e) { if (st.dirty) { e.preventDefault(); e.returnValue = ''; } });
  $('pl-print').addEventListener('click', function () { window.print(); });

  $('pl-png').addEventListener('click', function () {
    var w = 2000, h = Math.round(w * st.s.ratio), cv = document.createElement('canvas'), ctx = cv.getContext('2d');
    cv.width = w; cv.height = h;
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h);
    if (st.pdfReady) ctx.drawImage($('pl-pdf'), 0, 0, w, h);
    var m = svgMarkup(true), s = '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="0 0 ' + VB + ' ' + VB * st.s.ratio + '">' + m.markup + '</svg>';
    var img = new Image();
    img.onload = function () {
      ctx.drawImage(img, 0, 0, w, h);
      var a = document.createElement('a');
      a.download = (st.name || 'layout').replace(/[^\w\-]+/g, '_') + (floors() > 1 ? '-floor' + (st.floor + 1) : '') + '.png';
      try { a.href = cv.toDataURL('image/png'); a.click(); } catch (err) { setStatus('Could not export the image', true); }
    };
    img.onerror = function () { setStatus('Could not export the image', true); };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(s);
  });

  /* ---------- PDF background ---------- */
  function loadPage() {
    if (!cfg.planUrl) return Promise.resolve();
    if (!window.pdfjsLib) { msg('The PDF viewer could not be loaded (check your internet connection). A blank grid is shown instead.'); st.pdfReady = false; render(); return Promise.resolve(); }
    var want = pageOf(st.floor), seq = (loadPage.seq = (loadPage.seq || 0) + 1);
    return getDoc().then(function (doc) {
      var n = clamp(parseInt(want, 10) || 1, 1, doc.numPages);
      $('pl-page').max = doc.numPages;
      return doc.getPage(n);
    }).then(function (page) {
      var v1 = page.getViewport({ scale: 1 }), sc = Math.min(2.5, 2000 / v1.width), v = page.getViewport({ scale: sc }), cv = $('pl-pdf');
      if (seq !== loadPage.seq) return null;
      cv.width = Math.round(v.width); cv.height = Math.round(v.height);
      if (!st.ratioSet || st.floor === 0) { st.s.ratio = v1.height / v1.width; st.ratioSet = true; }
      return page.render({ canvasContext: cv.getContext('2d'), viewport: v }).promise;
    }).then(function () { st.pdfReady = true; msg(''); render(); })
      .catch(function () { st.pdfReady = false; msg('This PDF could not be shown. A blank grid is used instead.'); render(); });
  }

  window.sitePlanner = {
    syncLayers: buildLayerList, state: st, computeRuns: computeRuns, computeStandards: computeStandards, generateComplete: generateComplete, swap: swap, save: save, render: render, dirtyNow: dirty,
    visible: visible, on: on, H: H, TYPES: TYPES, ORDER: ORDER, STD: STD, setStatus: setStatus, config: cfg,
    floors: floors, ceil: ceil, fl: fl, setFloor: setFloor, poly: polyM, wallRes: wallRes, wallAt: wallAt, insideM: insideM, polyArea: polyArea,
    sim: sim, rotOf: rotOf, ensureAnalysis: ensureAnalysis, kinds: KINDS, generateAsync: function () { return ensureAnalysis().then(generateComplete); }
  };

  /* ---------- start ---------- */
  buildLayerList(); syncControls(); markTools();
  var first = function () {
    ensureAnalysis().then(function () {
      var needShape = st.hasPdf && !outlineN(0) && !Object.keys(st.s.outlines).length;
      var go = function () {
        if (cfg.auto && !st.devices.length) { st.devices = generate(); st.dirty = true; render(); save(); }
        else render();
      };
      if (needShape && cfg.auto) {
        var r = an[anKey(pageOf(0))];
        if (r && r.ok) { st.s.outline = r.outline; sMsg('Building shape taken from the PDF (' + r.outline.length + ' corners). You can redraw it with “Draw outline”.'); }
      }
      go();
    });
  };
  if (st.hasPdf) {
    loadPage();
    // wait for the PDF so the plan ratio is known before devices are placed to scale
    var tries = 0, wait = setInterval(function () {
      if (st.pdfReady !== undefined || ++tries > 60 || !window.pdfjsLib) { clearInterval(wait); first(); }
    }, 100);
  } else first();
})();
