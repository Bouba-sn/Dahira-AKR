<?php
// pages/parametres.php
$pageTitle = 'Paramètres';
require_once __DIR__ . '/../includes/header.php';
$user = getCurrentUser();
$darkMode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-4">
    <div class="flex items-center h-14">
        <h1 class="font-bold text-slate-900 dark:text-white">Paramètres ⚙️</h1>
    </div>
</div>

<main class="page-content">

    <!-- PROFIL -->
    <?php if ($user): ?>
    <div class="px-4 mt-4">
        <div class="bg-gradient-to-r from-primary-900 to-primary-700 rounded-2xl p-4 text-white flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-white/20 border border-white/30 flex items-center justify-center font-bold text-lg">
                <?= strtoupper(substr($user['nom'], 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold"><?= e($user['nom']) ?></p>
                <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full"><?= $user['role'] === 'admin' ? '👑 Administrateur' : '👤 Membre' ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTIONS -->
    <div class="px-4 mt-5 space-y-3">

        <!-- Mes Commandes -->
        <a href="/pages/mes_commandes.php" class="block bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden active:scale-[0.98] transition-transform">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📦</span>
                    <div class="text-left">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Mes Commandes</p>
                        <p class="text-[10px] text-slate-400">Suivi et historique</p>
                    </div>
                </div>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </div>
        </a>

        <!-- Apparence -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Apparence</p>
            </div>
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-lg">🌙</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Mode sombre</p>
                        <p class="text-xs text-slate-400">Interface nuit</p>
                    </div>
                </div>
                <button onclick="handleDarkToggle(this)" id="dark-toggle"
                        class="relative w-11 h-6 rounded-full transition-colors duration-300 <?= $darkMode ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600' ?>">
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-300 <?= $darkMode ? 'translate-x-5' : '' ?>"></span>
                </button>
            </div>
        </div>

        <!-- Notifications -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Notifications</p>
            </div>
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-lg">🔔</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Rappel du jour</p>
                        <p class="text-xs text-slate-400">Notifications quotidiennes</p>
                    </div>
                </div>
                <button onclick="requestNotif()" class="text-xs bg-primary-900 text-white px-3 py-1.5 rounded-lg font-medium">
                    Activer
                </button>
            </div>
        </div>

        <!-- Application -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Application</p>
            </div>
            <button id="pwa-install-btn" onclick="installPWA()" style="display:none;" class="w-full px-4 py-3 flex items-center gap-3 border-b border-slate-100 dark:border-slate-700">
                <span class="text-lg">📲</span>
                <div class="text-left">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Installer l'application</p>
                    <p class="text-xs text-slate-400">Ajouter à l'écran d'accueil</p>
                </div>
                <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-lg">📦</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Version</p>
                        <p class="text-xs text-slate-400">Dahira AKR v1.0.0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compte -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Compte</p>
            </div>

            <?php if ($user): ?>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="/admin/dashboard.php" class="w-full px-4 py-3 flex items-center gap-3 border-b border-slate-100 dark:border-slate-700">
                <span class="text-lg">👑</span>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Dashboard Admin</p>
                <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <?php endif; ?>
            <a href="/public/logout.php" class="w-full px-4 py-3 flex items-center gap-3 text-red-500">
                <span class="text-lg">🚪</span>
                <p class="text-sm font-medium">Se déconnecter</p>
            </a>
            <?php else: ?>
            <a href="/public/login.php" class="w-full px-4 py-3 flex items-center gap-3">
                <span class="text-lg">🔐</span>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Se connecter</p>
                <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="/public/register.php" class="w-full px-4 py-3 flex items-center gap-3">
                <span class="text-lg">✍️</span>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Créer un compte</p>
                <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <?php endif; ?>
        </div>

        <!-- À propos -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">À propos</p>
            </div>
            <div class="px-4 py-4 text-center">
                <p class="arabic text-2xl text-primary-900 dark:text-blue-400 mb-1">الطريقة التجانية</p>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Dahira A Khiba-i Rassouloulahi</p>
                <p class="text-xs text-slate-400 mt-1">Application officielle</p>
                <p class="text-xs text-slate-300 dark:text-slate-600 mt-3">© 2025 Dahira AKR — Tous droits réservés</p>
            </div>
        </div>

    </div>

</main>

<script>
function handleDarkToggle(btn) {
    const isDark = toggleDarkMode();
    btn.className = `relative w-11 h-6 rounded-full transition-colors duration-300 ${isDark ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600'}`;
    const dot = btn.querySelector('span');
    dot.className = `absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-300 ${isDark ? 'translate-x-5' : ''}`;
}

function requestNotif() {
    if ('Notification' in window) {
        Notification.requestPermission().then(p => {
            showToast(p === 'granted' ? '🔔 Notifications activées !' : '❌ Notifications refusées');
        });
    } else {
        showToast('Notifications non supportées');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
