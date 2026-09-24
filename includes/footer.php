<?php // includes/footer.php ?>

<?php require_once __DIR__ .'/navbar-bottom.php'; ?>

</div><!-- /.app-content -->
</div><!-- /#app -->

<script>

// ============================================
// UTILITAIRES GLOBAUX
// ============================================

// Toast notification rapide & fluide
let toastTimer = null;
function showToast(msg, duration = 1800) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    t.classList.remove('show');
  }, duration);
  t.onclick = () => {
    t.classList.remove('show');
    if (toastTimer) clearTimeout(toastTimer);
  };
}

// Disparition fluide des alertes de succès
function dismissFlash(el) {
  if (!el || el.classList.contains('dismissing')) return;
  el.classList.add('dismissing');
  setTimeout(() => {
    if (el.parentNode) el.remove();
  }, 380);
}

// Toggle visibilité mot de passe (icône œil)
function togglePasswordVisibility(targetId, btn) {
  const input = typeof targetId === 'string' ? document.getElementById(targetId) : targetId;
  if (!input) return;
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  if (btn) {
    const eyeOpen = btn.querySelector('.eye-open');
    const eyeClosed = btn.querySelector('.eye-closed');
    if (eyeOpen && eyeClosed) {
      if (isPassword) {
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
        btn.setAttribute('title', 'Masquer le mot de passe');
        btn.setAttribute('aria-label', 'Masquer le mot de passe');
      } else {
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
        btn.setAttribute('title', 'Afficher le mot de passe');
        btn.setAttribute('aria-label', 'Afficher le mot de passe');
      }
    }
  }
}

// Auto-disparition rapide des messages de succès ("repartis vite fait" ~2.2s)
document.addEventListener('DOMContentLoaded', () => {
  const successSelectors = [
    '.flash-success-box',
    '.bg-emerald-500\\/10',
    '.bg-green-50'
  ];
  
  const alerts = document.querySelectorAll(successSelectors.join(', '));
  alerts.forEach(alert => {
    if (alert.tagName === 'BUTTON' || alert.tagName === 'A' || alert.closest('table')) return;
    
    // Auto-dismiss après 2.2 secondes
    setTimeout(() => {
      dismissFlash(alert);
    }, 2200);
  });

  // Nettoyage des paramètres d'URL (?msg=... ou ?success=...)
  if (window.history && window.history.replaceState) {
    const url = new URL(window.location.href);
    if (url.searchParams.has('msg') || url.searchParams.has('success')) {
      url.searchParams.delete('msg');
      url.searchParams.delete('success');
      window.history.replaceState({}, document.title, url.toString());
    }
  }
});

// Dark mode toggle
function toggleDarkMode() {
  const html = document.documentElement;
  const isDark = html.classList.toggle('dark');
  document.cookie = `dark_mode=${isDark ?'1':'0'};path=/;max-age=31536000`;
  return isDark;
}

// Lazy loading images
if ('IntersectionObserver' in window) {
  const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        if (img.dataset.src) {
          img.src = img.dataset.src;
          img.onload = () => img.classList.add('loaded');
          img.onerror = () => {
            img.src = '/assets/placeholder.jpg';
            img.classList.add('loaded');
          };
          img.removeAttribute('data-src');
          imageObserver.unobserve(img);
        }
      }
    });
  }, { rootMargin: '100px' });

  document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));
} else {
  document.querySelectorAll('img[data-src]').forEach(img => {
    if (img.dataset.src) {
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
    }
  });
}

// Panier (localStorage)
const Cart = {
  get() { return JSON.parse(localStorage.getItem('dahira_cart') || '[]'); },
  save(items) { localStorage.setItem('dahira_cart', JSON.stringify(items)); },
  add(product) {
    let items = this.get();
    const idx = items.findIndex(i => i.id === product.id);
    if (idx > -1) {
      items[idx].qty++;
      if (product.details) items[idx].details = product.details;
    } else {
      items.push({ ...product, qty: 1 });
    }
    this.save(items);
    this.updateBadge();
  },
  remove(id) {
    const items = this.get().filter(i => i.id !== id);
    this.save(items);
    this.updateBadge();
  },
  total() { return this.get().reduce((s, i) => s + i.prix * i.qty, 0); },
  count() { return this.get().reduce((s, i) => s + i.qty, 0); },
  clear() { localStorage.removeItem('dahira_cart'); this.updateBadge(); },
  updateBadge() {
    const count = this.count();
    const badges = document.querySelectorAll('#cart-badge, #cart-badge-desktop, .cart-badge');
    badges.forEach(b => {
      b.textContent = count;
      b.style.display = count > 0 ? 'flex' : 'none';
    });
  }
};

// Init badge
document.addEventListener('DOMContentLoaded', () =>Cart.updateBadge());

// Add to cart buttons
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-add-cart');
  if (btn) {
    Cart.add({
      id: parseInt(btn.dataset.id),
      nom: btn.dataset.nom,
      prix: parseInt(btn.dataset.prix),
      image: btn.dataset.image || ''
    });
    e.preventDefault();
  }
});

// PWA Install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  const btn = document.getElementById('pwa-install-btn');
  if (btn) btn.style.display = 'flex';
});

function installPWA() {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  deferredPrompt.userChoice.then(choice => {
    if (choice.outcome === 'accepted') showToast('Application installée !');
    deferredPrompt = null;
  });
}

// Format price
function formatPrice(price) {
  return new Intl.NumberFormat('fr-SN', { style:'currency', currency:'XOF', minimumFractionDigits: 0 }).format(price);
}

// ============================================
// NOTIFICATIONS SYSTÈME (HEURES DE PRIÈRE + ADHAN)
// ============================================
<?php
$notifPrieres = db()->query("SELECT fajr, dhuhr, asr, maghrib, isha FROM heures_prieres WHERE actif=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
const prieresData = <?= json_encode($notifPrieres ?: []) ?>;

// Initialiser depuis cookie si présent
if (document.cookie.match(/notif_prieres_active=1/)) {
  localStorage.setItem('notif_prieres_active', '1');
}

// Déverrouillage audio lors d'une interaction utilisateur
let globalAudioCtx = null;
function unlockAudioContext() {
  if (!globalAudioCtx) {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (AudioCtx) globalAudioCtx = new AudioCtx();
  }
  if (globalAudioCtx && globalAudioCtx.state === 'suspended') {
    globalAudioCtx.resume();
  }
}
document.addEventListener('click', unlockAudioContext, { once: true });
document.addEventListener('touchstart', unlockAudioContext, { once: true });

// Fonction pour activer/désactiver les notifications prières
function togglePrieresNotif(btn) {
  unlockAudioContext();
  const isActive = localStorage.getItem('notif_prieres_active') === '1';
  const newState = !isActive;
  localStorage.setItem('notif_prieres_active', newState ? '1' : '0');
  document.cookie = `notif_prieres_active=${newState ? '1' : '0'};path=/;max-age=2592000`;
  
  if (btn) {
    btn.className = `relative w-11 h-6 rounded-full transition-colors duration-300 ${newState ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600'}`;
    const dot = btn.querySelector('span');
    if (dot) {
      dot.className = `absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-300 ${newState ? 'translate-x-5' : ''}`;
    }
  }
  
  // Demander permission notifications
  if (newState && 'Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().then(perm => {
      if (perm === 'granted') {
        showToast('Notifications autorisées');
      }
    });
  }
  
  // Synchroniser avec le Service Worker
  syncPrayTimesWithSW();
  showToast(newState ? 'Notifications prières activées' : 'Notifications prières désactivées');
}

function syncPrayTimesWithSW() {
  const active = localStorage.getItem('notif_prieres_active') === '1';
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then(reg => {
      if (reg.active) {
        reg.active.postMessage({
          type: 'PRAY_TIMES_UPDATE',
          times: prieresData,
          active: active
        });
      }
    }).catch(() => {});
  }
}

// Synchroniser au chargement si actif
if (localStorage.getItem('notif_prieres_active') === '1') {
  syncPrayTimesWithSW();
}

// Système Audio & Synthétiseur de secours
let currentAdhanAudio = null;
const adhanAudioUrl = 'https://praytimes.org/audio/sunni/Abdul-Basit.mp3';

function playAdhanChime() {
  try {
    unlockAudioContext();
    if (!globalAudioCtx) return;
    
    // Jouer une harmonie douce d'annonce
    const notes = [440, 554.37, 659.25, 880];
    notes.forEach((freq, idx) => {
      const osc = globalAudioCtx.createOscillator();
      const gain = globalAudioCtx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, globalAudioCtx.currentTime + idx * 0.25);
      gain.gain.setValueAtTime(0.001, globalAudioCtx.currentTime + idx * 0.25);
      gain.gain.exponentialRampToValueAtTime(0.3, globalAudioCtx.currentTime + idx * 0.25 + 0.05);
      gain.gain.exponentialRampToValueAtTime(0.001, globalAudioCtx.currentTime + idx * 0.25 + 1.2);
      osc.connect(gain);
      gain.connect(globalAudioCtx.destination);
      osc.start(globalAudioCtx.currentTime + idx * 0.25);
      osc.stop(globalAudioCtx.currentTime + idx * 0.25 + 1.3);
    });
  } catch(e) {
    console.warn('Synth error:', e);
  }
}

function playAdhanSound() {
  unlockAudioContext();
  try {
    if (!currentAdhanAudio) {
      currentAdhanAudio = new Audio(adhanAudioUrl);
      currentAdhanAudio.preload = 'auto';
    }
    currentAdhanAudio.currentTime = 0;
    currentAdhanAudio.volume = 0.8;
    const playPromise = currentAdhanAudio.play();
    if (playPromise !== undefined) {
      playPromise.catch(err => {
        console.warn('Audio MP3 play blocked/failed, using chime synthesizer:', err);
        playAdhanChime();
      });
    }
  } catch (e) {
    playAdhanChime();
  }
}

function stopAdhanSound() {
  if (currentAdhanAudio) {
    try {
      currentAdhanAudio.pause();
      currentAdhanAudio.currentTime = 0;
    } catch(e) {}
  }
}

// Affichage bannière d'alerte in-app
function showInAppPrayerAlert(prayerName) {
  let alertEl = document.getElementById('in-app-prayer-alert');
  if (!alertEl) {
    alertEl = document.createElement('div');
    alertEl.id = 'in-app-prayer-alert';
    alertEl.className = 'fixed top-4 left-4 right-4 max-w-md mx-auto z-50 bg-primary-900 text-white p-4 rounded-2xl shadow-2xl border border-gold-500/40 flex items-center justify-between gap-3 animate-bounce-short';
    document.body.appendChild(alertEl);
  }

  alertEl.innerHTML = `
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-gold-500/20 text-gold-400 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      </div>
      <div>
        <div class="text-[11px] text-gold-400 font-bold uppercase tracking-wider">Appel à la prière</div>
        <div class="text-sm font-bold text-white">C'est l'heure de : ${prayerName}</div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button onclick="stopAdhanSound(); this.closest('#in-app-prayer-alert').remove();" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition-colors">
        Arrêter
      </button>
    </div>
  `;

  setTimeout(() => {
    if (alertEl && alertEl.parentNode) alertEl.remove();
  }, 45000);
}

// Dispatch de notification système compatible Android PWA et Desktop
function dispatchPrayerNotification(title, body) {
  const options = {
    body: body,
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    tag: 'prayer-' + Date.now(),
    renotify: true,
    vibrate: [200, 100, 200, 100, 200]
  };

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then(reg => {
      if (reg && reg.showNotification) {
        reg.showNotification(title, options);
      } else if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, options);
      }
    }).catch(() => {
      try {
        if ('Notification' in window && Notification.permission === 'granted') {
          new Notification(title, options);
        }
      } catch(e) {}
    });
  } else {
    try {
      if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, options);
      }
    } catch(e) {}
  }
}

// Fonction globale d'alerte prière
function triggerPrayerAlert(prayerName) {
  playAdhanSound();
  showInAppPrayerAlert(prayerName);
  dispatchPrayerNotification('Dahira AKR', "C'est l'heure de la prière : " + prayerName);
}

// Écoute des messages venant du Service Worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'PLAY_ADHAN') {
      triggerPrayerAlert(event.data.prayerLabel || event.data.prayer);
    }
  });
}

// Test Adhan & Notification (appelable depuis n'importe où)
window.testAdhan = function() {
  unlockAudioContext();
  triggerPrayerAlert('Fajr (Test)');
};
window.testPrayerNotification = window.testAdhan;

// Vérification locale chaque 30 secondes
setInterval(() => {
  if (localStorage.getItem('notif_prieres_active') !== '1') return;
  
  const now = new Date();
  const currentHM = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
  const todayStr = now.toDateString();

  const labels = {
    fajr: 'Fajr',
    dhuhr: 'Dhuhr',
    asr: 'Asr',
    maghrib: 'Maghrib',
    isha: 'Isha'
  };

  for (const [name, time] of Object.entries(prieresData)) {
    if (!time) continue;
    const shortTime = time.substring(0, 5);
    const storageKey = 'last_notif_' + name;
    const lastFired = localStorage.getItem(storageKey);

    if (shortTime === currentHM && lastFired !== todayStr) {
      localStorage.setItem(storageKey, todayStr);
      triggerPrayerAlert(labels[name] || name.toUpperCase());
    }
  }
}, 30000);

</script>
<?php if (isLoggedIn()): ?>
<!-- Notifications Temps Réel WebSockets -->
<script src="/assets/js/notifications-ws.js" defer></script>
<?php endif; ?>
</body>
</html>
