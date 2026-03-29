// pwa/service-worker.js
const CACHE_NAME = 'dahira-akr-v1.0.0';
const STATIC_CACHE = 'dahira-static-v1';
const DYNAMIC_CACHE = 'dahira-dynamic-v1';

const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/pages/accueil.php',
  '/pages/bibliotheque.php',
  '/pages/tidiany-way.php',
  '/pwa/manifest.json',
  'https://cdn.tailwindcss.com',
  'https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Outfit:wght@300;400;500;600;700&display=swap',
];

// Installation : mise en cache des ressources statiques
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      console.log('[SW] Mise en cache des ressources statiques');
      return cache.addAll(STATIC_ASSETS.map(url => new Request(url, { mode: 'no-cors' })));
    }).catch(err => console.log('[SW] Erreur cache install:', err))
  );
  self.skipWaiting();
});

// Activation : nettoyage des anciens caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter(name => name !== STATIC_CACHE && name !== DYNAMIC_CACHE)
          .map(name => caches.delete(name))
      );
    })
  );
  self.clients.claim();
});

// Stratégie de fetch : Network First avec fallback Cache
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorer les requêtes non-GET et les APIs externes
  if (request.method !== 'GET') return;
  if (url.pathname.startsWith('/actions/')) return;
  if (url.pathname.startsWith('/admin/')) return;

  // Pages critiques : Cache First
  if (url.pathname === '/' || url.pathname.includes('accueil') || url.pathname.includes('bibliotheque')) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Autres : Network First
  event.respondWith(networkFirst(request));
});

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    const cache = await caches.open(STATIC_CACHE);
    cache.put(request, response.clone());
    return response;
  } catch {
    return offlinePage();
  }
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    const cache = await caches.open(DYNAMIC_CACHE);
    cache.put(request, response.clone());
    return response;
  } catch {
    const cached = await caches.match(request);
    return cached || offlinePage();
  }
}

function offlinePage() {
  return new Response(`
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Hors ligne - Dahira AKR</title>
      <style>
        body { font-family: 'Outfit', sans-serif; background: #1e3a8a; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; padding: 20px; }
        .container { max-width: 300px; }
        .emoji { font-size: 4rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { opacity: 0.8; font-size: 0.9rem; }
        button { margin-top: 1.5rem; background: white; color: #1e3a8a; border: none; padding: 0.75rem 2rem; border-radius: 2rem; font-weight: 600; cursor: pointer; }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="emoji">🕌</div>
        <h1>Mode hors ligne</h1>
        <p>Vous n'êtes pas connecté à Internet. Les pages mises en cache restent disponibles.</p>
        <button onclick="window.location.reload()">Réessayer</button>
      </div>
    </body>
    </html>
  `, { headers: { 'Content-Type': 'text/html' } });
}

// Notifications Push
self.addEventListener('push', (event) => {
  if (!event.data) return;
  const data = event.data.json();
  const options = {
    body: data.body || 'Rappel du jour de la Dahira AKR',
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: { url: data.url || '/' },
    actions: [
      { action: 'open', title: 'Voir', icon: '/assets/icons/icon-72x72.png' },
      { action: 'close', title: 'Fermer' }
    ]
  };
  event.waitUntil(
    self.registration.showNotification(data.title || 'Dahira AKR', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  if (event.action === 'open' || !event.action) {
    event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
  }
});
