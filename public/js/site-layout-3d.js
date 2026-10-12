/* 3D virtual view of a site layout: orbit, walk-through, CCTV point of view and a camera wall. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('pl-3d')) return;
  var THREE_URL = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
  var ORBIT_URL = 'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js';
  var fog, ar = { stream: null, facing: 'environment', devs: null, have: false, q: null, raw: null, theta: 0, hfov: 65, stats: null, listening: false }, covers = [], domes = [], labels = [], P, T, renderer, scene, cam, controls, group, raf = 0, last = 0, ready = false, active = false;
  var mode = 'orbit', look = { yaw: 0, pitch: 0 }, walk = { x: 0, z: 0 }, keys = {}, tour = false, floorTex = null, camRefs = {}, objGroup = null, objMap = {}, objKey = '', polys = {};
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function loadScript(src) {
    return new Promise(function (res, rej) {
      var s = document.createElement('script'); s.src = src; s.crossOrigin = 'anonymous';
      s.onload = res; s.onerror = function () { rej(new Error(src)); }; document.head.appendChild(s);
    });
  }
  function loadLibs() {
    if (window.THREE && THREE.OrbitControls) return Promise.resolve();
    return (window.THREE ? Promise.resolve() : loadScript(THREE_URL)).then(function () { return THREE.OrbitControls ? 0 : loadScript(ORBIT_URL); });
  }
  function msg(t) { var m = $('pl3-msg'); m.hidden = !t; m.textContent = t || ''; }
  function rad(d) { return d * Math.PI / 180; }

  /* ---------- scene ---------- */
  function dims() { var s = P.state, W = s.width, H = P.H(); return { W: W, H: H, C: +s.s.ceiling || 3, F: P.floors(), f: s.floor }; }
  function polyOf(f) { return polys[f] || (polys[f] = P.poly(f)); }
  function showAll() { return mode !== 'ar' && $('pl3-floors').value === 'all'; }
  function canStand(f, x, z) { var d = dims(), px = x + d.W / 2, py = z + d.H / 2; return P.insideM(polyOf(f), px, py, 0.15) && !P.wallAt(f, px, py); }
  function moveTo(nx, nz) { var f = P.state.floor; if (canStand(f, nx, walk.z)) walk.x = nx; if (canStand(f, walk.x, nz)) walk.z = nz; }
  function startSpot() {
    var d = dims(), f = d.f, cabs = P.state.devices.filter(function (x) { return x.type === 'cab' && P.fl(x) === f; }), best = null, i, j;
    var cand = []; cabs.forEach(function (c) { var cx = c.x - d.W / 2, cz = c.y - d.H / 2; cand.push([cx, cz + 2.5], [cx + 2.5, cz], [cx - 2.5, cz], [cx, cz - 2.5]); });
    var bb = polyOf(f).reduce(function (a, p) { return [Math.min(a[0], p[0]), Math.min(a[1], p[1]), Math.max(a[2], p[0]), Math.max(a[3], p[1])]; }, [1e9, 1e9, -1e9, -1e9]);
    cand.push([(bb[0] + bb[2]) / 2 - d.W / 2, (bb[1] + bb[3]) / 2 - d.H / 2]);
    for (i = 0; i < cand.length; i++) if (canStand(f, cand[i][0], cand[i][1])) return cand[i];
    for (j = bb[1]; j < bb[3]; j += 1) for (i = bb[0]; i < bb[2]; i += 1) if (canStand(f, i - d.W / 2, j - d.H / 2)) return [i - d.W / 2, j - d.H / 2];
    return [0, 0];
  }

  function floorTexture(d) {
    var cv = document.createElement('canvas'), w = 2048, h = Math.round(w * d.H / d.W);
    cv.width = w; cv.height = h;
    var c = cv.getContext('2d'), src = $('pl-pdf');
    c.fillStyle = '#eef2f7'; c.fillRect(0, 0, w, h);
    if (P.state.pdfReady && src.width) c.drawImage(src, 0, 0, w, h);
    else {
      c.strokeStyle = '#c9d3df'; c.lineWidth = 2;
      var step = w / d.W * 5;
      for (var x = 0; x <= w; x += step) { c.beginPath(); c.moveTo(x, 0); c.lineTo(x, h); c.stroke(); }
      for (var y = 0; y <= h; y += step) { c.beginPath(); c.moveTo(0, y); c.lineTo(w, y); c.stroke(); }
    }
    var t = new THREE.CanvasTexture(cv);
    t.minFilter = THREE.LinearFilter; t.generateMipmaps = false; t.anisotropy = 4;
    return t;
  }

  function shapeOf(poly, ox, oz) { return new THREE.Shape(poly.map(function (q) { return new THREE.Vector2(q[0] - ox, q[1] - oz); })); }
  function planGeometry(poly, d) {
    var g = new THREE.ShapeGeometry(shapeOf(poly, d.W / 2, d.H / 2)), pos = g.attributes.position, uv = g.attributes.uv;
    for (var i = 0; i < pos.count; i++) uv.setXY(i, (pos.getX(i) + d.W / 2) / d.W, 1 - (pos.getY(i) + d.H / 2) / d.H);
    return g;
  }
  function sprite(text) {
    var cv = document.createElement('canvas'); cv.width = 256; cv.height = 64;
    var c = cv.getContext('2d'); c.font = '700 30px Segoe UI, Arial, sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle';
    c.lineWidth = 8; c.strokeStyle = 'rgba(8,12,20,.9)'; c.strokeText(text, 128, 34); c.fillStyle = '#fff'; c.fillText(text, 128, 34);
    var t = new THREE.CanvasTexture(cv), sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: t, depthTest: false, transparent: true }));
    sp.scale.set(2.4, 0.6, 1); sp.renderOrder = 10;
    return sp;
  }
  function mat(color, extra) { return new THREE.MeshStandardMaterial(Object.assign({ color: color, roughness: 0.55, metalness: 0.15 }, extra || {})); }
  function dir(a, pitch) { return new THREE.Vector3(Math.cos(rad(a)) * Math.cos(rad(pitch)), -Math.sin(rad(pitch)), Math.sin(rad(a)) * Math.cos(rad(pitch))); }
  var PITCH = 20;

  function build() {
    if (mode !== 'ar') { buildInner(); return; }
    if (!ar.devs) ar.devs = P.generateComplete();
    var restore = P.swap(ar.devs, Object.keys(P.config.allLayers));
    try { buildInner(); var r = P.computeRuns(), tot = 0, ok = P.computeStandards(r).every(function (x) { return x.ok; }); r.runs.forEach(function (x) { tot += x.len; }); ar.stats = { devices: ar.devs.length, runs: r.runs.length, cable: Math.ceil(tot), ok: ok, cabs: r.cabs.length }; }
    finally { restore(); }
  }
  function buildInner() {
    var d = dims(), s = P.state, ox = d.W / 2, oz = d.H / 2, showCover = $('pl3-cover').checked, all = showAll(), C = d.C;
    polys = {};
    if (group) { scene.remove(group); group.traverse(function (o) { if (o.geometry) o.geometry.dispose(); if (o.material) { if (o.material.map) o.material.map.dispose(); o.material.dispose(); } }); }
    group = new THREE.Group(); scene.add(group); covers = []; domes = []; labels = []; camRefs = {};
    var shown = function (f) { return mode === 'ar' ? f === d.f : (all || f === d.f); };
    var tex = mode !== 'ar' ? floorTexture(d) : null;

    for (var f = 0; f < d.F; f++) {
      var poly = polyOf(f), fy = f * C, fg = new THREE.Group(); fg.position.y = fy; group.add(fg);
      if (mode === 'ar') {
        if (f !== d.f) continue;
        var gs = Math.ceil(Math.max(d.W, d.H)), gh = new THREE.GridHelper(gs, gs, 0x3df5c0, 0x3df5c0);
        gh.material.transparent = true; gh.material.opacity = 0.22; gh.position.y = 0.01; fg.add(gh);
        var eg = new THREE.ExtrudeGeometry(shapeOf(poly, ox, oz), { depth: C, bevelEnabled: false });
        var fr = new THREE.LineSegments(new THREE.EdgesGeometry(eg), new THREE.LineBasicMaterial({ color: 0x3df5c0, transparent: true, opacity: 0.6 }));
        fr.rotation.x = Math.PI / 2; fr.position.y = C; fg.add(fr); eg.dispose();
        continue;
      }
      var slab = new THREE.Mesh(new THREE.ExtrudeGeometry(shapeOf(poly, ox, oz), { depth: 0.2, bevelEnabled: false }), mat(shown(f) ? 0x1c2533 : 0x2a3446));
      slab.rotation.x = Math.PI / 2; slab.position.y = -0.005; fg.add(slab);
      if (!shown(f)) {            // other floors: just the slab, so the height of the building is visible
        var ghost = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.ExtrudeGeometry(shapeOf(poly, ox, oz), { depth: C, bevelEnabled: false })), new THREE.LineBasicMaterial({ color: 0x5b6b82, transparent: true, opacity: 0.35 }));
        ghost.rotation.x = Math.PI / 2; ghost.position.y = C; fg.add(ghost);
        continue;
      }
      var floor = new THREE.Mesh(planGeometry(poly, d), new THREE.MeshStandardMaterial({ map: tex, roughness: 0.9, side: THREE.DoubleSide }));
      floor.rotation.x = Math.PI / 2; floor.position.y = 0.01; fg.add(floor);

      if ($('pl3-walls').checked) {
        var wm = new THREE.MeshStandardMaterial({ color: 0xcfd8e4, transparent: true, opacity: 0.16, roughness: 1, side: THREE.DoubleSide, depthWrite: false });
        for (var i = 0; i < poly.length; i++) {
          var a = poly[i], b = poly[(i + 1) % poly.length], dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy); if (L < 0.05) continue;
          var ang = -Math.atan2(dy, dx), mx = (a[0] + b[0]) / 2 - ox, mz = (a[1] + b[1]) / 2 - oz;
          var wall = new THREE.Mesh(new THREE.PlaneGeometry(L, C), wm); wall.position.set(mx, C / 2, mz); wall.rotation.y = ang; fg.add(wall);
          var edge = new THREE.Mesh(new THREE.BoxGeometry(L, 0.06, 0.12), mat(0x8fa0b5)); edge.position.set(mx, C, mz); edge.rotation.y = ang; fg.add(edge);
        }
        var wr = P.wallRes(f);
        if (wr && s.s.walls3d && wr.walls.length) {
          var n = Math.min(wr.walls.length, 9000), im = new THREE.InstancedMesh(new THREE.BoxGeometry(1, 1, 1), new THREE.MeshStandardMaterial({ color: 0xaebbd0, roughness: 0.9, transparent: true, opacity: 0.78 }), n), m4 = new THREE.Matrix4(), q4 = new THREE.Quaternion(), p3 = new THREE.Vector3(), s3 = new THREE.Vector3();
          for (var k = 0; k < n; k++) {
            var r = wr.walls[k], w = (r[2] - r[0]) / wr.grid.nx * d.W, h = (r[3] - r[1]) / wr.grid.ny * d.H;
            p3.set((r[0] + r[2]) / 2 / wr.grid.nx * d.W - ox, C / 2, (r[1] + r[3]) / 2 / wr.grid.ny * d.H - oz); s3.set(w, C, h);
            m4.compose(p3, q4, s3); im.setMatrixAt(k, m4);
          }
          fg.add(im);
        }
      }
    }

    var runs = P.computeRuns(), tray = d.C - 0.3, mounts = { cam: 2.7, ap: d.C - 0.05, spk: d.C - 0.05, outlet: 0.4, phone: 0.8 };
    var segs = {}, trayOff = { cam: 0, ap: 0.05, spk: 0.1, outlet: 0.15, phone: 0.2 };
    runs.runs.forEach(function (r) {
      if (r.n > 0) return;
      var t = r.dev.type, fd = P.fl(r.dev) * d.C, fc = P.fl(r.cab) * d.C;
      if (!shown(P.fl(r.dev)) && !shown(P.fl(r.cab))) return;
      var y = fd + tray - trayOff[t], a = [r.dev.x - ox, fd + mounts[t], r.dev.y - oz], b = [r.dev.x - ox, y, r.dev.y - oz],
        c = [r.cab.x - ox, y, r.dev.y - oz], e = [r.cab.x - ox, y, r.cab.y - oz], f = [r.cab.x - ox, fc + 1.9, r.cab.y - oz];
      (segs[t] = segs[t] || []).push([a, b], [b, c], [c, e], [e, f]);
    });
    if (P.state.show.runs) Object.keys(segs).forEach(function (t) {
      var pts = []; segs[t].forEach(function (p) { pts.push(p[0][0], p[0][1], p[0][2], p[1][0], p[1][1], p[1][2]); });
      var g = new THREE.BufferGeometry(); g.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
      group.add(new THREE.LineSegments(g, new THREE.LineBasicMaterial({ color: new THREE.Color(P.TYPES[t].color), transparent: true, opacity: 0.8 })));
    });

        // fiber riser: one amber shaft through the stacked telecom rooms, with a feed line to every IDF
    if (d.F > 1 && runs.main && all) {
      var shaft = new THREE.Mesh(new THREE.BoxGeometry(0.7, d.F * d.C, 0.7), new THREE.MeshBasicMaterial({ color: 0xc98500, transparent: true, opacity: 0.16, depthWrite: false }));
      shaft.position.set(runs.main.x - ox, d.F * d.C / 2, runs.main.y - oz); group.add(shaft);
      var rp = [];
      runs.riser.forEach(function (rr) { var yy = P.fl(rr.cab) * d.C + 2.3; rp.push(runs.main.x - ox, yy, runs.main.y - oz, rr.cab.x - ox, yy, rr.cab.y - oz); });
      var rg = new THREE.BufferGeometry(); rg.setAttribute('position', new THREE.Float32BufferAttribute(rp, 3));
      group.add(new THREE.LineSegments(rg, new THREE.LineBasicMaterial({ color: 0xffb454 })));
    }
    var devs = s.devices.filter(function (x) { return P.visible(x) && shown(P.fl(x)); }), labelAll = devs.length <= 90;
    devs.forEach(function (dv) {
      var x = dv.x - ox, z = dv.y - oz, fy = P.fl(dv) * d.C, g = new THREE.Group(), col = new THREE.Color(P.TYPES[dv.type].color), top = 0, cgRef = null, coneRef = null;
      if (dv.type === 'cab') {
        var body = new THREE.Mesh(new THREE.BoxGeometry(0.8, 2, 0.8), mat(0x232c3a)); body.position.y = 1; g.add(body);
        var glass = new THREE.Mesh(new THREE.BoxGeometry(0.7, 1.8, 0.02), new THREE.MeshStandardMaterial({ color: 0x6fa8ff, transparent: true, opacity: 0.25 })); glass.position.set(0, 1, 0.41); g.add(glass);
        for (var i = 0; i < 12; i++) { var led = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.03, 0.02), new THREE.MeshBasicMaterial({ color: i % 4 ? 0x3df5c0 : 0xffb454 })); led.position.set(-0.25 + (i % 6) * 0.1, 0.5 + Math.floor(i / 6) * 0.35, 0.39); g.add(led); }
        top = 2.4;
      } else if (dv.type === 'cam') {
        var cg = new THREE.Group();
        var cb = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.2, 0.42), mat(0xe9edf3)); cg.add(cb);
        var lens = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 0.06, 16), mat(0x0b1220)); lens.rotation.x = Math.PI / 2; lens.position.z = 0.23; cg.add(lens);
        var ring = new THREE.Mesh(new THREE.TorusGeometry(0.075, 0.012, 8, 20), new THREE.MeshBasicMaterial({ color: col })); ring.position.z = 0.26; cg.add(ring);
        cg.rotation.order = 'YXZ'; cg.rotation.y = Math.PI / 2 - rad(P.rotOf(dv)); cg.rotation.x = rad(PITCH); cgRef = cg;
        cg.position.y = mounts.cam; g.add(cg);
        var bracket = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.2, 0.06), mat(0x8fa0b5)); bracket.position.y = mounts.cam + 0.14; g.add(bracket);
        if (showCover) {
          var range = +s.s.camera_range || 12, r = range * Math.tan(rad((+s.s.camera_fov || 90) / 2)), len = range / Math.cos(rad(PITCH));
          var cone = new THREE.Mesh(new THREE.ConeGeometry(Math.min(r, range * 1.6), len, 28, 1, true), new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.07, side: THREE.DoubleSide, depthWrite: false }));
          cone.geometry.translate(0, -len / 2, 0);
          cone.quaternion.setFromUnitVectors(new THREE.Vector3(0, -1, 0), dir(P.rotOf(dv), PITCH)); coneRef = cone;
          cone.position.y = mounts.cam; g.add(cone); covers.push(cone);
        }
        top = 3;
      } else if (dv.type === 'ap') {
        var ap = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 0.05, 24), mat(0xffffff)); ap.position.y = mounts.ap; g.add(ap);
        var led2 = new THREE.Mesh(new THREE.TorusGeometry(0.17, 0.012, 8, 28), new THREE.MeshBasicMaterial({ color: col })); led2.rotation.x = Math.PI / 2; led2.position.y = mounts.ap - 0.03; g.add(led2);
        if (showCover) {
          var dome = new THREE.Mesh(new THREE.SphereGeometry(10, 24, 10, 0, Math.PI * 2, Math.PI / 2, Math.PI / 2), new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.05, side: THREE.DoubleSide, depthWrite: false }));
          dome.position.y = mounts.ap; g.add(dome); domes.push(dome);
        }
        top = d.C + 0.3;
      } else if (dv.type === 'spk') {
        var sp = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.22, 0.2, 20), mat(0xf2f2f2)); sp.position.y = mounts.spk - 0.1; g.add(sp);
        var cone2 = new THREE.Mesh(new THREE.CircleGeometry(0.18, 20), new THREE.MeshBasicMaterial({ color: col })); cone2.rotation.x = Math.PI / 2; cone2.position.y = mounts.spk - 0.205; g.add(cone2);
        top = d.C + 0.3;
      } else if (dv.type === 'outlet') {
        var o = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.12, 0.04), mat(0xffffff)); o.position.y = mounts.outlet; g.add(o);
        var jack = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.05, 0.05), new THREE.MeshBasicMaterial({ color: col })); jack.position.y = mounts.outlet; g.add(jack);
        top = 0.9;
      } else if (dv.type === 'phone') {
        var desk = new THREE.Mesh(new THREE.BoxGeometry(1.3, 0.05, 0.7), mat(0xb08968)); desk.position.y = 0.75; g.add(desk);
        [[-0.6, -0.3], [0.6, -0.3], [-0.6, 0.3], [0.6, 0.3]].forEach(function (l) { var leg = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.75, 0.05), mat(0x6b5a4a)); leg.position.set(l[0], 0.375, l[1]); g.add(leg); });
        var ph = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.07, 0.2), new THREE.MeshStandardMaterial({ color: col, roughness: 0.4 })); ph.position.set(0.3, 0.82, 0); g.add(ph);
        top = 1.3;
      }
      g.position.set(x, fy, z); g.userData = { id: dv.id };
      group.add(g);
      if (dv.type === 'cam') camRefs[dv.id] = { cg: cgRef, cone: coneRef, dv: dv, col: col, last: null };
      if ((labelAll || dv.type === 'cab' || dv.type === 'cam') && s.show.labels) { var sprt = sprite(dv.label || ''); sprt.position.set(x, fy + top, z); sprt.userData = { fl: P.fl(dv) }; group.add(sprt); labels.push(sprt); }
    });

    var cams = devs.filter(function (x) { return x.type === 'cam'; });
    var allCams = s.devices.filter(function (x) { return x.type === 'cam' && P.visible(x); });
    var sel = $('pl3-cam'), prev = sel.value;
    sel.innerHTML = cams.map(function (c) { return '<option value="' + c.id + '">' + c.label + '</option>'; }).join('');
    if (cams.some(function (c) { return c.id === prev; })) sel.value = prev;
    else if (cams.some(function (c) { return c.id === s.sel; })) sel.value = s.sel;
    $('pl3-modes').querySelector('[data-mode="cam"]').disabled = !cams.length;
    $('pl3-modes').querySelector('[data-mode="wall"]').disabled = !cams.length;
    if (!canStand(d.f, walk.x, walk.z)) { var sp = startSpot(); walk.x = sp[0]; walk.z = sp[1]; }
    syncFloorSel();
  }

  /* ---------- camera modes ---------- */
  function selectedCam() { return P.state.devices.filter(function (x) { return x.id === $('pl3-cam').value; })[0]; }
  function hud() {
    var h = $('pl3-hud'), c = selectedCam();
    if (mode === 'cam' && c) h.innerHTML = '<b class="rec">● REC</b> ' + c.label + '<span>' + new Date().toLocaleString([], { hour12: false }) + '</span>';
    else if (mode === 'ar' && ar.stream && ar.stats) h.innerHTML = '<span><b class="rec">●</b> AR · complete setup<br>' + ar.stats.devices + ' devices · ' + ar.stats.runs + ' cable runs · ' + ar.stats.cable + ' m<br>' + (ar.stats.ok ? '✔ Meets TIA / ISO limits' : '✕ Check standards') + '</span>';
    else h.innerHTML = '';
    h.style.display = (mode === 'cam' || (mode === 'ar' && ar.stream)) ? 'flex' : 'none';
  }
  function help() {
    $('pl3-help').textContent = {
      orbit: 'Drag to rotate, scroll to zoom, right-drag to pan. Cones show what each CCTV camera covers.',
      walk: 'Hold your phone up and turn to look around (or drag). Walk with W A S D, the arrow keys, or tick Step to move and walk on the spot.',
      cam: 'You are looking through the selected CCTV camera. Tilt and turn your phone, or drag, to look around within its range.',
      follow: 'Third-person view behind the selected moving object. Cameras on its floor turn to face it (red cones). Pick another object from the list.',
      wall: 'All CCTV cameras at once, like an NVR monitor. Click a tile to open that camera.',
      ar: 'Camera view: the layout is drawn over the live video. Face the top wall of the room and tap Calibrate, then walk with the arrow buttons or W A S D. Drag to look around if your device has no motion sensor.'
    }[mode];
  }
  function applyVis() {
    covers.forEach(function (c) { c.visible = mode === 'orbit' || mode === 'ar'; });
    domes.forEach(function (c) { c.visible = mode === 'orbit'; });
    var cur = P.state.floor; labels.forEach(function (l) { l.visible = mode === 'orbit' || ((mode === 'walk' || mode === 'ar' || mode === 'follow') && (!l.userData || l.userData.fl === cur)); });
  }
  function setMode(m) {
    var was = mode;
    mode = m; look.yaw = 0; look.pitch = 0;
    if (was === 'ar' && m !== 'ar') arStop();
    document.querySelectorAll('#pl3-modes [data-mode]').forEach(function (b) { b.classList.toggle('on', b.dataset.mode === m); });
    $('pl3-cam').hidden = m !== 'cam'; $('pl3-obj').hidden = m !== 'follow';
    controls.enabled = m === 'orbit';
    var d = dims();
    if (m === 'orbit') { var big = Math.max(d.W, d.H, d.F * d.C), mid = showAll() ? d.F * d.C / 2 : d.f * d.C; cam.fov = 50; cam.position.set(0, mid + big * 0.55, d.H * 0.95 + (showAll() ? d.F * d.C * 0.4 : 0)); controls.target.set(0, mid, 0); controls.update(); }
    if (m === 'walk') { cam.fov = 70; look.yaw = -90; var sp = startSpot(); walk.x = sp[0]; walk.z = sp[1]; }
    $('pl3-tiles').style.display = m === 'wall' ? 'grid' : 'none';
    renderer.setClearColor(0x0d121b, m === 'ar' ? 0 : 1); scene.fog = m === 'ar' ? null : fog;
    $('pl3-video').style.display = m === 'ar' ? 'block' : 'none'; $('pl3-ar').hidden = m !== 'ar';
    $('pl3-start').hidden = !(m === 'ar' && !ar.stream);
    if (m === 'ar') { look.yaw = -90; var sa = startSpot(); walk.x = sa[0]; walk.z = sa[1]; }
    if (m === 'ar' || was === 'ar') build();
    if (m === 'wall') buildTiles();
    if (m === 'follow') syncObjSel();
    if (m === 'walk' || m === 'cam') motionRequest(); else if (m === 'orbit' || m === 'wall' || m === 'follow') { listen(false); setSensor('', ''); }
    applyVis(); hud(); help();
  }

  function applyCamPov(camera, c, aspect, yaw, pitch) {
    var d = dims(), h = rad(+P.state.s.camera_fov || 90);
    camera.aspect = aspect; camera.fov = 2 * Math.atan(Math.tan(h / 2) / aspect) * 180 / Math.PI; camera.near = 0.1; camera.far = 400; camera.updateProjectionMatrix();
    var rot = P.rotOf(c), v0 = dir(rot, PITCH);
    camera.position.set(c.x - d.W / 2 + v0.x * 0.4, P.fl(c) * d.C + 2.7 + v0.y * 0.4, c.y - d.H / 2 + v0.z * 0.4);
    var v = dir(rot + yaw, PITCH + pitch);
    camera.lookAt(camera.position.x + v.x * 10, camera.position.y + v.y * 10, camera.position.z + v.z * 10);
  }

  var tiles = [];
  function buildTiles() {
    var cams = P.state.devices.filter(function (x) { return x.type === 'cam' && P.visible(x); }).slice(0, 16), box = $('pl3-tiles');
    tiles = cams;
    var cols = Math.ceil(Math.sqrt(cams.length || 1)), rows = Math.ceil((cams.length || 1) / cols);
    box.style.gridTemplateColumns = 'repeat(' + cols + ',1fr)'; box.style.gridTemplateRows = 'repeat(' + rows + ',1fr)';
    box.innerHTML = cams.map(function (c) { return '<button type="button" data-cam="' + c.id + '"><b class="rec">●</b> ' + c.label + '</button>'; }).join('');
  }
  $('pl3-tiles').addEventListener('click', function (e) {
    var b = e.target.closest('[data-cam]'); if (!b) return;
    $('pl3-cam').value = b.dataset.cam; setMode('cam');
  });

  /* ---------- floors, moving objects and cameras that turn to face them ---------- */
  function syncFloorSel() {
    var d = dims(), sel = $('pl3-floor'), want = d.F + ':' + d.f;
    if (sel.dataset.k !== want) {
      var h = ''; for (var f = 0; f < d.F; f++) h += '<option value="' + f + '">Floor ' + (f + 1) + '</option>';
      sel.innerHTML = h; sel.value = String(d.f); sel.dataset.k = want;
    }
    $('pl3-floor-wrap').hidden = d.F < 2;
  }
  function followObj() { var id = $('pl3-obj').value, o = P.sim.objs.filter(function (x) { return x.id === id; })[0]; return o || P.sim.objs[0]; }
  function syncObjSel() {
    var sel = $('pl3-obj'), prev = sel.value, h = P.sim.objs.map(function (o) { return '<option value="' + o.id + '">' + P.kinds[o.k].label + ' ' + o.label + '</option>'; }).join('');
    if (sel.dataset.k !== h) { sel.innerHTML = h; sel.dataset.k = h; if (P.sim.objs.some(function (o) { return o.id === prev; })) sel.value = prev; }
    $('pl3-modes').querySelector('[data-mode="follow"]').disabled = !P.sim.objs.length;
  }
  function makeObj(o) {
    var K = P.kinds[o.k], col = new THREE.Color(K.color), g = new THREE.Group(), body;
    if (o.k === 'vehicle') {
      body = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.7, 0.95), mat(K.color)); body.position.y = 0.55; g.add(body);
      var cab = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.55, 0.85), mat(0xffd43b)); cab.position.set(0.45, 1.1, 0); g.add(cab);
      [[-0.55, -0.5], [-0.55, 0.5], [0.55, -0.5], [0.55, 0.5]].forEach(function (w) { var wh = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, 0.14, 14), mat(0x111111)); wh.rotation.x = Math.PI / 2; wh.position.set(w[0], 0.22, w[1]); g.add(wh); });
      var tip = 1.6;
    } else {
      body = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.24, 1.15, 14), mat(K.color)); body.position.y = 0.65; g.add(body);
      var head = new THREE.Mesh(new THREE.SphereGeometry(0.16, 14, 10), mat(0xf1c9a5)); head.position.y = 1.42; g.add(head);
      var nose = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.05, 0.08), new THREE.MeshBasicMaterial({ color: 0xffffff })); nose.position.set(0.2, 1.42, 0); g.add(nose);
      tip = 1.95;
    }
    var sp = sprite(o.label); sp.position.y = tip; sp.scale.set(1.6, 0.4, 1); g.add(sp);
    return g;
  }
  /* Runs every frame: moves the object meshes and turns the CCTV cameras toward them. */
  function syncDynamic() {
    if (!P || !group) return;
    var d = dims(), objs = P.sim.objs, key = objs.map(function (o) { return o.id; }).join(',');
    if (!objGroup) { objGroup = new THREE.Group(); scene.add(objGroup); }
    if (key !== objKey) {
      objKey = key;
      Object.keys(objMap).forEach(function (id) { if (!objs.some(function (o) { return o.id === id; })) { objGroup.remove(objMap[id]); delete objMap[id]; } });
      objs.forEach(function (o) { if (!objMap[o.id]) { objMap[o.id] = makeObj(o); objGroup.add(objMap[o.id]); } });
      syncObjSel();
    }
    var all = showAll();
    objs.forEach(function (o) {
      var m = objMap[o.id]; if (!m) return;
      m.position.set(o.x - d.W / 2, o.fl * d.C, o.y - d.H / 2); m.rotation.y = -rad(o.h);
      m.visible = mode === 'ar' ? false : (all || o.fl === d.f);
    });
    Object.keys(camRefs).forEach(function (id) {
      var r = camRefs[id], rot = P.rotOf(r.dv), cs = P.sim.cam[id], lock = !!(cs && cs.tgt);
      if (r.last !== rot + '|' + lock) {
        r.last = rot + '|' + lock;
        if (r.cg) r.cg.rotation.y = Math.PI / 2 - rad(rot);
        if (r.cone) { r.cone.quaternion.setFromUnitVectors(new THREE.Vector3(0, -1, 0), dir(rot, PITCH)); r.cone.material.color.set(lock ? 0xe03131 : r.col); r.cone.material.opacity = lock ? 0.18 : 0.07; }
      }
    });
  }

  /* ---------- loop ---------- */
  function size() {
    var w = $('pl3-wrap'), cw = w.clientWidth, ch = w.clientHeight;
    if (renderer.domElement.width / renderer.getPixelRatio() !== cw || renderer.domElement.height / renderer.getPixelRatio() !== ch) { renderer.setSize(cw, ch, false); }
    return [cw, ch];
  }
  function frame(t) {
    if (!active) return;
    raf = requestAnimationFrame(frame);
    var dt = Math.min(0.1, (t - last) / 1000 || 0); last = t;
    var wh = size(), cw = wh[0], ch = wh[1], d = dims();
    if (ar.have) ar.qs.slerp(ar.q, Math.min(1, dt * 14));
    if (stepPending && (mode === 'walk' || mode === 'ar') && $('pl3-steps').checked) { var sf = useMotion() ? fwd(ar.qs) : new THREE.Vector3(Math.cos(rad(look.yaw)), 0, Math.sin(rad(look.yaw))), sn = Math.hypot(sf.x, sf.z) || 1; moveTo(walk.x + sf.x / sn * 0.65 * stepPending, walk.z + sf.z / sn * 0.65 * stepPending); }
    stepPending = 0;
    if (mode === 'orbit') {
      if (tour && !reduce) { var a = t / 6000, R = Math.max(d.W, d.H, d.F * d.C) * 0.75, my = showAll() ? d.F * d.C / 2 : d.f * d.C; cam.position.set(Math.sin(a) * R, my + R * 0.7, Math.cos(a) * R); controls.target.set(0, my, 0); }
      cam.aspect = cw / ch; cam.updateProjectionMatrix(); controls.update();
    } else if (mode === 'walk') {
      var sp = (keys.shift ? 6 : 3) * dt, fx = Math.cos(rad(look.yaw)), fz = Math.sin(rad(look.yaw));
      if (useMotion()) { var wf = fwd(ar.qs), wn = Math.hypot(wf.x, wf.z) || 1; fx = wf.x / wn; fz = wf.z / wn; }
      var mx = 0, mz = 0;
      if (keys.w || keys.arrowup) { mx += fx * sp; mz += fz * sp; }
      if (keys.s || keys.arrowdown) { mx -= fx * sp; mz -= fz * sp; }
      if (keys.a || keys.arrowleft) { mx += fz * sp; mz -= fx * sp; }
      if (keys.d || keys.arrowright) { mx -= fz * sp; mz += fx * sp; }
      if (mx || mz) moveTo(walk.x + mx, walk.z + mz);
      var eye = d.f * d.C + 1.7;
      cam.aspect = cw / ch; cam.fov = 70; cam.near = 0.05; cam.far = 600; cam.updateProjectionMatrix();
      cam.position.set(walk.x, eye, walk.z);
      if (useMotion()) cam.quaternion.copy(ar.qs); else cam.lookAt(walk.x + fx * 10, eye - Math.tan(rad(look.pitch)) * 10, walk.z + fz * 10);
    } else if (mode === 'ar') {
      var sp2 = 2.5 * dt, gx = Math.cos(rad(look.yaw)), gz = Math.sin(rad(look.yaw));
      if (useMotion()) { var fw = fwd(ar.qs); var n = Math.hypot(fw.x, fw.z) || 1; gx = fw.x / n; gz = fw.z / n; }
      var ax = 0, az = 0;
      if (keys.w) { ax += gx * sp2; az += gz * sp2; }
      if (keys.s) { ax -= gx * sp2; az -= gz * sp2; }
      if (keys.a) { ax += gz * sp2; az -= gx * sp2; }
      if (keys.d) { ax -= gz * sp2; az += gx * sp2; }
      if (ax || az) moveTo(walk.x + ax, walk.z + az);
      var asp = cw / ch; cam.aspect = asp; cam.fov = 2 * Math.atan(Math.tan(rad(ar.hfov / 2)) / Math.max(asp, 0.5)) * 180 / Math.PI; cam.near = 0.05; cam.far = 400; cam.updateProjectionMatrix();
      var aeye = d.f * d.C + 1.5;
      cam.position.set(walk.x, aeye, walk.z);
      if (useMotion()) cam.quaternion.copy(ar.qs); else cam.lookAt(walk.x + Math.cos(rad(look.yaw)) * 10, aeye - Math.tan(rad(look.pitch)) * 10, walk.z + Math.sin(rad(look.yaw)) * 10);
    } else if (mode === 'follow') {
      var fo = followObj();
      if (fo) {
        var hf = rad(fo.h), fyy = fo.fl * d.C;
        cam.aspect = cw / ch; cam.fov = 62; cam.near = 0.05; cam.far = 600; cam.updateProjectionMatrix();
        cam.position.set(fo.x - d.W / 2 - Math.cos(hf) * 3.2, fyy + 2.3, fo.y - d.H / 2 - Math.sin(hf) * 3.2);
        cam.lookAt(fo.x - d.W / 2 + Math.cos(hf) * 2, fyy + 1, fo.y - d.H / 2 + Math.sin(hf) * 2);
      }
    } else if (mode === 'cam') {
      var c = selectedCam();
      if (c && useMotion()) { var cf = fwd(ar.qs); look.yaw = Math.max(-70, Math.min(70, Math.atan2(cf.x, -cf.z) * 180 / Math.PI)); look.pitch = Math.max(-25, Math.min(35, -Math.asin(Math.max(-1, Math.min(1, cf.y))) * 180 / Math.PI)); }
      if (c) applyCamPov(cam, c, cw / ch, look.yaw, look.pitch);
    }
    syncDynamic();
    renderer.setScissorTest(false); renderer.setViewport(0, 0, cw, ch); renderer.clear();
    if (mode === 'wall') {
      var cols = Math.ceil(Math.sqrt(tiles.length || 1)), rows = Math.ceil((tiles.length || 1) / cols), tw = cw / cols, th = ch / rows, tc = new THREE.PerspectiveCamera();
      renderer.setScissorTest(true);
      tiles.forEach(function (c, i) {
        var x = (i % cols) * tw, y = ch - (Math.floor(i / cols) + 1) * th;
        renderer.setViewport(x + 1, y + 1, tw - 2, th - 2); renderer.setScissor(x + 1, y + 1, tw - 2, th - 2);
        applyCamPov(tc, c, (tw - 2) / (th - 2), 0, 0); renderer.render(scene, tc);
      });
      renderer.setScissorTest(false);
    } else renderer.render(scene, cam);
    if (mode === 'cam' && Math.floor(t / 1000) !== frame.sec) { frame.sec = Math.floor(t / 1000); hud(); }
  }

  /* ---------- input ---------- */
  var drag = null, cv;
  function bindInput() {
    cv = $('pl3-canvas');
    cv.addEventListener('pointerdown', function (e) { if (mode === 'walk' || mode === 'cam' || (mode === 'ar' && !useMotion())) { drag = { x: e.clientX, y: e.clientY }; cv.setPointerCapture(e.pointerId); } });
    cv.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y; drag.x = e.clientX; drag.y = e.clientY;
      if (mode === 'walk' || mode === 'ar') { look.yaw += dx * 0.25; look.pitch = Math.max(-60, Math.min(60, look.pitch + dy * 0.2)); }
      else { look.yaw = Math.max(-70, Math.min(70, look.yaw + dx * 0.15)); look.pitch = Math.max(-25, Math.min(35, look.pitch + dy * 0.12)); }
    });
    cv.addEventListener('pointerup', function () { drag = null; });
    document.addEventListener('keydown', function (e) { if (active && (mode === 'walk' || mode === 'ar') && !/input|select|textarea/i.test(e.target.tagName || '')) { var k = e.key.toLowerCase(); keys[k] = true; if (k.indexOf('arrow') === 0) e.preventDefault(); keys.shift = e.shiftKey; } });
    document.addEventListener('keyup', function (e) { keys[e.key.toLowerCase()] = false; keys.shift = e.shiftKey; });
    $('pl3-modes').addEventListener('click', function (e) { var b = e.target.closest('[data-mode]'); if (b && !b.disabled) setMode(b.dataset.mode); });
    $('pl3-cam').addEventListener('change', function () { look.yaw = 0; look.pitch = 0; hud(); });
    $('pl3-floor').addEventListener('change', function () { P.setFloor(parseInt(this.value, 10) || 0); });
    $('pl3-floors').addEventListener('change', function () { build(); if (mode === 'orbit') setMode('orbit'); });
    document.addEventListener('plfloor', function () { polys = {}; if (!active) return; build(); if (mode === 'walk' || mode === 'ar') { var sp = startSpot(); walk.x = sp[0]; walk.z = sp[1]; } if (mode === 'orbit') setMode('orbit'); });
    ['pl3-walls', 'pl3-cover'].forEach(function (id) { $(id).addEventListener('change', build); });
    $('pl3-motion').addEventListener('change', function () { if (mode === 'walk' || mode === 'cam' || mode === 'ar') motionRequest(); });
    $('pl3-motion-retry').addEventListener('click', function () { motionRequest(); });
    $('pl3-tour').addEventListener('change', function () { tour = this.checked; if (tour) setMode('orbit'); });
    $('pl3-snap').addEventListener('click', function () {
      frame.snap = true; var wh = size(); renderer.render(scene, cam);
      try { var a = document.createElement('a'); a.download = (P.state.name || 'layout').replace(/[^\w\-]+/g, '_') + '-3d.png'; a.href = cv.toDataURL('image/png'); a.click(); } catch (err) { msg('Could not save the snapshot.'); }
    });
    window.addEventListener('resize', function () { if (active && mode === 'wall') buildTiles(); });
  }


  /* ---------- live camera (AR) ---------- */
  var Q1, ZEE, EUL, Q0, YAX, tmpQ, stepLast = 0, stepPending = 0, lp = 9.8, watchT = 0;
  function motionOn() { return $('pl3-motion').checked; }
  function useMotion() { return ar.have && motionOn(); }
  function setSensor(t, cls) { var e = $('pl3-sensor'); e.textContent = t; e.className = 'pl3-sensor ' + (cls || ''); $('pl3-motion-retry').hidden = cls !== 'bad'; }
  var UA = navigator.userAgent || '', IOS = /iP(hone|ad|od)/.test(UA) || (/Macintosh/.test(UA) && navigator.maxTouchPoints > 1), ANDROID = /Android/.test(UA), BRAVE = !!navigator.brave;
  function deniedText() {
    if (IOS) return 'Motion access was denied. Close the browser completely (swipe it away), open this page again over HTTPS, tap Start camera and choose Allow. If it still says denied, clear this site\'s data in the browser settings and try again.';
    return 'Motion access was denied. Allow motion sensors for this site in the browser settings, then tap Enable motion.';
  }
  function missingText() {
    if (ANDROID && BRAVE) return 'Brave blocks motion sensors on Android and has no setting to turn them back on. Open this page in Chrome, Edge, Firefox or Samsung Internet for phone motion, or drag to look around.';
    if (ANDROID || IOS) return 'No motion data is coming from this browser. Check that the page uses https:// and that motion sensors are allowed in the browser settings. You can still drag to look around.';
    return 'This computer has no motion sensor. Drag to look around and use W A S D.';
  }
  function fwd(q) { return new THREE.Vector3(0, 0, -1).applyQuaternion(q); }
  function onOrient(e) {
    if (e.beta == null || (e.alpha == null && e.gamma == null)) return;
    if (!Q1) { Q1 = new THREE.Quaternion(-Math.sqrt(0.5), 0, 0, Math.sqrt(0.5)); ZEE = new THREE.Vector3(0, 0, 1); YAX = new THREE.Vector3(0, 1, 0); EUL = new THREE.Euler(); Q0 = new THREE.Quaternion(); tmpQ = new THREE.Quaternion(); }
    var orient = rad((window.screen.orientation && window.screen.orientation.angle) || window.orientation || 0);
    EUL.set(rad(e.beta), rad(e.alpha || 0), -rad(e.gamma || 0), 'YXZ');
    ar.raw.setFromEuler(EUL); ar.raw.multiply(Q1); ar.raw.multiply(Q0.setFromAxisAngle(ZEE, -orient));
    if (!ar.have) {                      // first reading: treat the way the phone points now as "toward the top wall"
      var f = fwd(ar.raw); ar.theta = Math.atan2(f.x, -f.z);
      applyTheta(); ar.qs.copy(ar.q); ar.have = true; setSensor('Motion sensor on', 'ok');
    } else applyTheta();
  }
  function applyTheta() { ar.q.setFromAxisAngle(YAX || new THREE.Vector3(0, 1, 0), ar.theta).multiply(ar.raw); }
  function onMotion(e) {
    if (!$('pl3-steps').checked) return;
    var a = e.accelerationIncludingGravity; if (!a) return;
    var m = Math.hypot(a.x || 0, a.y || 0, a.z || 0), now = performance.now();
    lp = lp * 0.85 + m * 0.15;
    if (m - lp > 2.4 && now - stepLast > 380) { stepLast = now; stepPending++; }
  }
  function listen(on) {
    var ev = 'ondeviceorientationabsolute' in window ? 'deviceorientationabsolute' : 'deviceorientation';
    if (on && !ar.listening) { window.addEventListener(ev, onOrient, true); window.addEventListener('devicemotion', onMotion, true); ar.ev = ev; ar.listening = true; }
    if (!on && ar.listening) { window.removeEventListener(ar.ev, onOrient, true); window.removeEventListener('devicemotion', onMotion, true); ar.listening = false; }
    if (!on) { ar.have = false; clearTimeout(watchT); }
  }
  /* Must be called from a tap or click: iPhones ask for motion permission only then. */
  function motionRequest() {
    if (!motionOn()) { listen(false); setSensor('Motion off', ''); return Promise.resolve('off'); }
    var calls = [];
    if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') calls.push(DeviceOrientationEvent.requestPermission().catch(function () { return 'denied'; }));
    if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') calls.push(DeviceMotionEvent.requestPermission().catch(function () { return 'denied'; }));
    return Promise.all(calls).then(function (rs) {
      if (rs.indexOf('denied') > -1) { listen(false); setSensor(deniedText(), 'bad'); return 'denied'; }
      listen(true);
      if (ar.have) { setSensor('Motion sensor on', 'ok'); return 'granted'; }
      setSensor('Looking for a motion sensor…', '');
      clearTimeout(watchT);
      watchT = setTimeout(function () { if (ar.listening && !ar.have) setSensor(missingText(), 'bad'); }, 2000);
      return 'granted';
    });
  }
  function arStop() {
    if (ar.stream) ar.stream.getTracks().forEach(function (t) { t.stop(); });
    ar.stream = null; ar.devs = null; ar.stats = null; if (mode !== 'walk' && mode !== 'cam') listen(false);
    var v = $('pl3-video'); v.srcObject = null;
    keys = {};
  }
  function arError(err) {
    var n = err && err.name;
    msg(n === 'NotAllowedError' || n === 'SecurityError' ? 'Camera permission was denied. Allow the camera for this site in your browser settings and try again.'
      : n === 'NotFoundError' || n === 'OverconstrainedError' ? 'No camera was found on this device.'
      : n === 'NotReadableError' ? 'The camera is busy in another app. Close it and try again.'
      : 'The camera could not be started.');
    $('pl3-start').hidden = false;
  }
  function arStart() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      msg('The camera needs a secure page (https:// or localhost). On a phone, open this site over HTTPS.'); return;
    }
    var perm = motionRequest();
    msg('Starting camera…');
    try { $('pl3-wrap').scrollIntoView({ block: 'center', behavior: reduce ? 'auto' : 'smooth' }); } catch (e) {}
    perm.then(function (r) {
      return navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: ar.facing }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
    }).then(function (stream) {
      if (ar.stream) ar.stream.getTracks().forEach(function (t) { t.stop(); });
      ar.stream = stream;
      var v = $('pl3-video'); v.srcObject = stream;
      return v.play().catch(function () {});
    }).then(function () {
      msg(''); $('pl3-start').hidden = true;
      ar.devs = P.generateComplete(); ar.theta = 0;      // a fresh complete setup every time the camera opens
      build(); hud(); help();
    }).catch(arError);
  }
  function bindAr() {
    $('ar-start').addEventListener('click', arStart);
    $('ar-switch').addEventListener('click', function () { ar.facing = ar.facing === 'environment' ? 'user' : 'environment'; arStart(); });
    $('ar-regen').addEventListener('click', function () { ar.devs = P.generateComplete(); build(); hud(); });
    $('ar-cal').addEventListener('click', function () {
      if (ar.have) { var f = fwd(ar.raw); ar.theta = Math.atan2(f.x, -f.z); applyTheta(); ar.qs.copy(ar.q); }
      else { look.yaw = -90; look.pitch = 0; }
    });
    $('ar-fov').addEventListener('input', function () { ar.hfov = parseFloat(this.value) || 65; });
    $('ar-save').addEventListener('click', function () {
      if (!ar.devs || !window.confirm('Replace this layout with the complete setup shown on the camera?')) return;
      P.state.layers = Object.keys(P.config.allLayers); P.state.devices = ar.devs.map(function (d) { return Object.assign({}, d); });
      P.syncLayers(); P.dirtyNow(); P.save();
    });
    document.querySelectorAll('.ar-pad [data-key]').forEach(function (b) {
      var k = b.dataset.key, up = function () { keys[k] = false; };
      b.addEventListener('pointerdown', function (e) { e.preventDefault(); keys[k] = true; });
      ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (ev) { b.addEventListener(ev, up); });
    });
  }

  function init() {
    P = window.sitePlanner; T = THREE;
    renderer = new THREE.WebGLRenderer({ canvas: $('pl3-canvas'), antialias: true, alpha: true, preserveDrawingBuffer: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2)); renderer.setClearColor(0x0d121b, 1); renderer.autoClear = false;
    fog = new THREE.Fog(0x0d121b, 120, 420); scene = new THREE.Scene(); scene.fog = fog; ar.q = new THREE.Quaternion(); ar.raw = new THREE.Quaternion(); ar.qs = new THREE.Quaternion();
    scene.add(new THREE.HemisphereLight(0xffffff, 0x445066, 0.95));
    var sun = new THREE.DirectionalLight(0xffffff, 0.7); sun.position.set(30, 60, 20); scene.add(sun);
    cam = new THREE.PerspectiveCamera(50, 1.6, 0.1, 800);
    controls = new THREE.OrbitControls(cam, $('pl3-canvas')); controls.enableDamping = true; controls.maxPolarAngle = Math.PI / 2 - 0.02;
    if (P.floors() > 4) $('pl3-floors').value = 'one';
    bindInput(); bindAr(); ready = true;
  }

  function start() {
    active = true; build(); setMode(mode === 'wall' ? 'wall' : mode); cancelAnimationFrame(raf); last = performance.now(); raf = requestAnimationFrame(frame);
  }
  function show(view) {
    document.querySelectorAll('.pl-tabs [data-view]').forEach(function (b) { var on = b.dataset.view === view; b.classList.toggle('on', on); b.setAttribute('aria-selected', on); });
    var is3d = view === '3d';
    $('pl-3d').hidden = !is3d; $('pl-stage').hidden = is3d; $('pl-legend').hidden = is3d;
    if (!is3d) { active = false; cancelAnimationFrame(raf); if (mode === 'ar') { arStop(); setMode('orbit'); } return; }
    P = window.sitePlanner;
    if (ready) { start(); return; }
    msg('Loading 3D…');
    loadLibs().then(function () { init(); msg(''); start(); })
      .catch(function () { msg('The 3D viewer could not be loaded. Check your internet connection and try again.'); });
  }
  if (window.sitePlanner && window.sitePlanner.config.camera) { setTimeout(function () { show('3d'); var m = $('pl3-modes').querySelector('[data-mode="ar"]'); if (m) m.click(); }, 1500); }
  document.querySelector('.pl-tabs').addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) show(b.dataset.view); });
  window.addEventListener('beforeprint', function () { if (active) show('2d'); });
})();
