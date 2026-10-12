/* Carnet Sport Santé / Sport pour tous : service worker.
   - L'application (page, police, générateur PDF, icônes) est gardée en cache :
     démarrage rapide et utilisation hors connexion.
   - Les appels à l'API (/api/...) ne sont JAMAIS mis en cache : les données
     restent sur le serveur, rien de sensible n'est stocké par le service worker. */
const VERSION = "sportsante-v1";
const SHELL = [
  "./", "./manifest.webmanifest", "./vendor/jspdf.umd.min.js", "./fonts/garamond.css",
  "./fonts/eb-garamond-latin-400-normal.woff2", "./fonts/eb-garamond-latin-500-normal.woff2",
  "./fonts/eb-garamond-latin-600-normal.woff2", "./fonts/eb-garamond-latin-700-normal.woff2",
  "./fonts/eb-garamond-latin-800-normal.woff2", "./fonts/eb-garamond-latin-500-italic.woff2",
  "./fonts/eb-garamond-latin-600-italic.woff2",
  "./img/msje.jpg", "./img/spt.jpg",
  "./icons/icon-192.png", "./icons/icon-512.png", "./icons/apple-touch-icon.png"
];
const SCOPE = new URL("./", self.location).pathname;

self.addEventListener("install", event => {
  event.waitUntil((async () => {
    const cache = await caches.open(VERSION);
    await Promise.all(SHELL.map(u => cache.add(u).catch(() => {})));
    await self.skipWaiting();
  })());
});

self.addEventListener("activate", event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener("fetch", event => {
  const req = event.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith(SCOPE + "api/") || url.pathname.startsWith(SCOPE + "up")) return; // données : toujours en direct

  // Page : réseau d'abord (nouvelle version immédiate), cache si hors ligne
  if (req.mode === "navigate") {
    event.respondWith((async () => {
      try {
        const res = await fetch(req);
        if (res.ok) { const cache = await caches.open(VERSION); cache.put("./", res.clone()); }
        return res;
      } catch {
        return (await caches.match("./")) || Response.error();
      }
    })());
    return;
  }

  // Fichiers de l'application : cache d'abord, mise à jour en arrière-plan
  event.respondWith((async () => {
    const cache = await caches.open(VERSION);
    const hit = await cache.match(req);
    const net = fetch(req).then(res => { if (res && res.ok) cache.put(req, res.clone()); return res; }).catch(() => null);
    return hit || (await net) || Response.error();
  })());
});
