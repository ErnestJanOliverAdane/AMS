/* Konektado portal service worker. Static files are cached; pages are never stored,
   so private (encrypted) data is not kept on the phone's cache. */
const CACHE = 'konektado-static-v1';
const OFFLINE = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#07090d;color:#e8edf4;font:16px system-ui;text-align:center;padding:24px"><div><h1 style="margin:0 0 8px">You are offline</h1><p style="color:#8a96a6">Check your connection, then try again.</p><button onclick="location.reload()" style="margin-top:14px;padding:10px 18px;border-radius:10px;border:0;background:#3df5c0;font-weight:700">Retry</button></div>';

self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
    return;
  }
  if (/^\/(css|js|images)\//.test(url.pathname)) {
    e.respondWith(caches.open(CACHE).then(c => c.match(req).then(hit => {
      const net = fetch(req).then(r => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => hit);
      return hit || net;
    })));
  }
});
