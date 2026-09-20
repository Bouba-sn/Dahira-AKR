const CACHE_NAME = 'dahira-akr-v1.0.7';
const STATIC_CACHE = 'dahira-static-v1';
const DYNAMIC_CACHE = 'dahira-dynamic-v1';

// Heures de prière à vérifier (mise à jour via postMessage)
let prayTimes = null;
let isNotificationActive = false;
let lastNotifiedPrayers = {};

// Listen for messages from the main app
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'PRAY_TIMES_UPDATE') {
    prayTimes = event.data.times;
    isNotificationActive = event.data.active;
    
    // Stocker dans le cache pour persistance
    caches.open(DYNAMIC_CACHE).then(cache => {
      cache.put(new Request('/pwa/praytimes.json'), 
        new Response(JSON.stringify({ times: prayTimes, active: isNotificationActive }))
      );
    });
  }
});

const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/pages/accueil.php',
  '/pages/bibliotheque.php',
  '/pages/tidiany-way.php',
  '/pages/parametres.php',
  '/pages/cotisations.php',
  '/pages/notifications.php',
  '/pages/panier.php',
  '/pwa/manifest.json',
  '/assets/uploads/1.jpg',
  '/assets/uploads/1.png',
  '/assets/uploads/2.png',
  '/assets/uploads/3.png',
  '/assets/uploads/6.png',
  '/assets/uploads/8.png',
];

const EXTERNAL_ASSETS = [
  'https://cdn.tailwindcss.com',
  'https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Outfit:wght@300;400;500;600;700&display=swap',
  'https://praytimes.org/audio/sunni/Abdul-Basit.mp3',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(async (cache) => {
      for (const url of STATIC_ASSETS) {
        try {
          await cache.add(new Request(url));
        } catch (e) { }
      }
      for (const url of EXTERNAL_ASSETS) {
        try {
          await cache.add(new Request(url, { mode: 'no-cors' }));
        } catch (e) { }
      }
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== STATIC_CACHE && k !== DYNAMIC_CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Background sync pour vérifier les prières
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'prayer-check') {
    event.waitUntil(checkPrayers());
  }
});

async function checkPrayers() {
  if (!isNotificationActive || !prayTimes) return;
  
  const now = new Date();
  const currentTime = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
  const todayStr = now.toDateString();
  
  const prayerNames = {
    fajr: 'Fajr',
    dhuhr: 'Dhuhr',
    asr: 'Asr',
    maghrib: 'Maghrib',
    isha: 'Isha'
  };

  for (const [name, time] of Object.entries(prayTimes)) {
    if (!time) continue;
    const prayTime = time.substring(0, 5);
    
    if (prayTime === currentTime) {
      const key = name + '_' + todayStr;
      if (!lastNotifiedPrayers[key]) {
        lastNotifiedPrayers[key] = true;
        
        // Informer les clients ouverts pour jouer le son de l'Adhan
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
          clients.forEach(client => {
            client.postMessage({
              type: 'PLAY_ADHAN',
              prayer: name,
              prayerLabel: prayerNames[name] || name
            });
          });
        });
        
        // Afficher la notification système
        self.registration.showNotification('Dahira AKR', {
          body: "C'est l'heure de la prière : " + (prayerNames[name] || name),
          icon: '/assets/icons/icon-192x192.png',
          badge: '/assets/icons/icon-72x72.png',
          tag: 'adhan-' + name,
          renotify: true,
          vibrate: [200, 100, 200]
        });
      }
    }
  }
}

// Enregistrer le periodic sync (si supporté)
self.addEventListener('install', () => {
  if ('periodicSync' in self.registration) {
    self.registration.periodicSync.register('prayer-check', {
      minInterval: 60000 // 1 minute
    }).catch(err => console.log('Periodic sync failed:', err));
  }
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;
  if (url.origin !== self.location.origin) return;

  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request).then(response => {
        if (!response || response.status !== 200) return response;
        const clone = response.clone();
        caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, clone));
        return response;
      }).catch(() => offlinePage());
    }).catch(() => offlinePage())
  );
});

// Push notifications (nécessite VAPID keys pour fonctionner)
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'Dahira AKR';
  const body = data.body || "C'est l'heure de la prière";
  
  event.waitUntil(
    self.registration.showNotification(title, {
      body: body,
      icon: '/assets/icons/icon-192x192.png',
      badge: '/assets/icons/icon-72x72.png',
      tag: 'adhan-notification',
      renotify: true,
      vibrate: [200, 100, 200]
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data?.url || '/pages/accueil.php')
  );
});

function offlinePage() {
  return new Response(
    '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Hors ligne - Dahira AKR</title></head><body style="font-family:Outfit,sans-serif;background:#1e3a8a;color:white;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px;"><div style="max-width:320px;"><svg style="width:48px;height:48px;margin:0 auto 16px;color:#fbbf24;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg><h1 style="font-size:1.5rem;margin-bottom:0.5rem;font-weight:700;">Hors ligne</h1><p style="opacity:0.8;font-size:0.9rem;">Vous n\'êtes pas connecté à Internet.</p></div></body></html>',
    { headers: { 'Content-Type': 'text/html' } }
  );
}