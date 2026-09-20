/**
 * Dahira AKR v2 - Client WebSocket pour Notifications en Temps Réel
 * Conforme aux directives backend-patterns et security-reviewer (XSS-safe).
 */
(function () {
    'use strict';

    // 1. Récupération de la configuration
    const tokenMeta = document.querySelector('meta[name="ws-token"]');
    if (!tokenMeta || !tokenMeta.content) {
        // Utilisateur non connecté, aucun WebSocket à initialiser
        return;
    }

    const wsToken = tokenMeta.content;
    const metaWsUrl = document.querySelector('meta[name="ws-url"]');

    // Détection automatique de l'URL WebSocket
    let wsHost = window.location.hostname || 'localhost';
    let wsPort = 8085;
    let wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    let wsUrl = metaWsUrl && metaWsUrl.content
        ? metaWsUrl.content
        : `${wsProtocol}//${wsHost}:${wsPort}`;

    let socket = null;
    let reconnectAttempts = 0;
    const maxReconnectDelay = 15000;
    let pingInterval = null;

    /**
     * Génère un bip sonore discret et harmonieux via Web Audio API (sans fichier externe)
     */
    function playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();

            const now = ctx.currentTime;
            const osc1 = ctx.createOscillator();
            const gain = ctx.createGain();

            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now); // D5
            osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12); // A5

            gain.gain.setValueAtTime(0.12, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

            osc1.connect(gain);
            gain.connect(ctx.destination);

            osc1.start(now);
            osc1.stop(now + 0.35);
        } catch (e) {
            // Audio context bloqué par les politiques de lecture automatique du navigateur
        }
    }

    /**
     * Met à jour le badge rouge sur toutes les cloches de notification de la page
     */
    function incrementNotificationBadges() {
        const bellLinks = document.querySelectorAll('a[href*="notifications.php"]');

        bellLinks.forEach(link => {
            let badge = link.querySelector('.ws-notif-badge, span[class*="bg-red-500"]');

            if (badge) {
                // Si c'est un badge avec chiffre
                const currentCount = parseInt(badge.textContent.trim(), 10);
                if (!isNaN(currentCount)) {
                    badge.textContent = currentCount + 1;
                }
                badge.classList.remove('hidden');
                // Effet de pulsation
                badge.classList.remove('animate-pulse');
                void badge.offsetWidth; // Force reflow
                badge.classList.add('animate-pulse');
            } else {
                // Créer un point rouge indicateur s'il n'existait pas
                const dot = document.createElement('span');
                dot.className = 'ws-notif-badge absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-primary-900 animate-pulse';
                link.classList.add('relative');
                link.appendChild(dot);
            }
        });
    }

    /**
     * Affiche un Toast dynamique sécurisé (défense XSS garantie : textContent)
     */
    function showNotificationToast(notification) {
        let toastContainer = document.getElementById('ws-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'ws-toast-container';
            toastContainer.className = 'fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full px-4 pointer-events-none';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-slate-900/95 text-white p-4 rounded-2xl shadow-2xl border border-white/10 backdrop-blur-md flex items-start gap-3 transition-all duration-300 transform translate-y-[-20px] opacity-0 cursor-pointer';

        // Icône selon le type
        const iconContainer = document.createElement('div');
        iconContainer.className = 'w-10 h-10 rounded-full flex items-center justify-center shrink-0 ' +
            (notification.type === 'commande' ? 'bg-amber-500/20 text-amber-400' :
                notification.type === 'adhesion' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400');
        iconContainer.innerHTML = notification.type === 'commande' 
            ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'
            : (notification.type === 'adhesion' 
                ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'
                : '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>');

        const content = document.createElement('div');
        content.className = 'flex-1 min-w-0';

        const titleEl = document.createElement('h4');
        titleEl.className = 'font-semibold text-sm text-white truncate';
        titleEl.textContent = notification.titre || 'Nouvelle notification';

        const msgEl = document.createElement('p');
        msgEl.className = 'text-xs text-slate-300 mt-0.5 line-clamp-2 leading-relaxed';
        msgEl.textContent = notification.message || '';

        const timeEl = document.createElement('span');
        timeEl.className = 'text-[10px] text-slate-400 mt-1 block';
        timeEl.textContent = "À l'instant";

        content.appendChild(titleEl);
        content.appendChild(msgEl);
        content.appendChild(timeEl);

        const closeBtn = document.createElement('button');
        closeBtn.className = 'text-slate-400 hover:text-white p-1 text-xs';
        closeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
        closeBtn.onclick = (e) => {
            e.stopPropagation();
            removeToast(toast);
        };

        toast.appendChild(iconContainer);
        toast.appendChild(content);
        toast.appendChild(closeBtn);

        toast.onclick = () => {
            if (notification.lien && notification.lien !== '#') {
                window.location.href = notification.lien;
            } else {
                window.location.href = '/pages/notifications.php';
            }
        };

        toastContainer.appendChild(toast);

        // Animation d'entrée
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-[-20px]', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
        });

        // Suppression automatique rapide après 2.5 secondes
        const timer = setTimeout(() => removeToast(toast), 2500);

        function removeToast(el) {
            clearTimeout(timer);
            el.classList.add('opacity-0', 'translate-y-[-10px]');
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 300);
        }
    }

    /**
     * Si l'utilisateur est actuellement sur la page des notifications, injecte la nouvelle carte
     */
    function prependNotificationToView(notification) {
        const notifContainer = document.querySelector('.page-content .space-y-3');
        if (!notifContainer) return;

        // Si le placeholder "Aucune notification" est affiché, le retirer
        const emptyState = notifContainer.querySelector('.text-center');
        if (emptyState) {
            emptyState.remove();
        }

        const card = document.createElement('a');
        card.href = notification.lien || '#';
        card.className = 'group block bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-100 dark:border-slate-700 shadow-sm card-hover relative overflow-hidden transition-all duration-300 transform scale-95 opacity-0';

        const indicator = document.createElement('div');
        indicator.className = 'absolute top-0 left-0 w-1 h-full bg-red-500';
        card.appendChild(indicator);

        const flex = document.createElement('div');
        flex.className = 'flex gap-3 items-start';

        const icon = document.createElement('div');
        icon.className = 'w-10 h-10 rounded-full flex items-center justify-center shrink-0 ' +
            (notification.type === 'commande' ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400' :
                notification.type === 'adhesion' ? 'bg-green-100 text-green-600 dark:bg-green-950/40 dark:text-green-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400');
        icon.innerHTML = notification.type === 'commande' 
            ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'
            : (notification.type === 'adhesion' 
                ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'
                : '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>');

        const body = document.createElement('div');
        const h3 = document.createElement('h3');
        h3.className = 'font-bold text-sm text-slate-800 dark:text-slate-100 mb-0.5';
        h3.textContent = notification.titre;

        const p = document.createElement('p');
        p.className = 'text-xs text-slate-500 dark:text-slate-400 leading-relaxed';
        p.textContent = notification.message;

        const time = document.createElement('span');
        time.className = 'text-[10px] text-slate-400 mt-2 block';
        time.textContent = "À l'instant";

        body.appendChild(h3);
        body.appendChild(p);
        body.appendChild(time);

        flex.appendChild(icon);
        flex.appendChild(body);
        card.appendChild(flex);

        notifContainer.prepend(card);

        // Animation d'entrée fluide
        requestAnimationFrame(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        });
    }

    /**
     * Initialise la connexion WebSocket
     */
    function connect() {
        try {
            const urlWithAuth = `${wsUrl}?token=${encodeURIComponent(wsToken)}`;
            socket = new WebSocket(urlWithAuth);

            socket.onopen = function () {
                console.log('[WebSocket] Connecté au serveur de notifications');
                reconnectAttempts = 0;

                // Heartbeat ping toutes les 25 secondes
                clearInterval(pingInterval);
                pingInterval = setInterval(() => {
                    if (socket && socket.readyState === WebSocket.OPEN) {
                        socket.send(JSON.stringify({ type: 'ping' }));
                    }
                }, 25000);
            };

            socket.onmessage = function (event) {
                try {
                    const payload = JSON.parse(event.data);

                    if (payload.type === 'pong' || payload.type === 'connected') {
                        return;
                    }

                    // Réception d'une notification temps réel
                    const notif = payload.data || payload;
                    if (notif && (notif.titre || notif.message)) {
                        incrementNotificationBadges();
                        showNotificationToast(notif);
                        prependNotificationToView(notif);
                        playNotificationSound();
                    }
                } catch (e) {
                    console.error('[WebSocket] Erreur décodage message:', e);
                }
            };

            socket.onclose = function (event) {
                clearInterval(pingInterval);
                if (reconnectAttempts >= 3) {
                    console.info('[WebSocket] Mode dégradé gracieux : les notifications restent synchronisées via la base de données.');
                    return;
                }
                const delay = Math.min(1000 * Math.pow(1.5, reconnectAttempts), maxReconnectDelay);
                reconnectAttempts++;
                setTimeout(connect, delay);
            };

            socket.onerror = function () {
                // Erreur silencieuse sur environnement d'hébergement mutualisé sans démon actif
                if (socket && socket.readyState === WebSocket.OPEN) {
                    socket.close();
                }
            };
        } catch (err) {
            console.error('[WebSocket] Exception connexion:', err);
        }
    }

    // Démarrage de la connexion dès que le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', connect);
    } else {
        connect();
    }
})();
