<?php
// includes/navbar-bottom.php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminArea = str_contains($requestUri, '/admin/');
$isUserAdmin = function_exists('isAdmin') && isAdmin();
$isUserLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$isUserMembre = function_exists('isMembre') && isMembre();

$userInitials = 'AK';
$userName = 'Membre';
if ($isUserLoggedIn && isset($_SESSION['user_nom'])) {
    $userName = $_SESSION['user_nom'];
    $parts = preg_split('/\s+/', trim($userName));
    if (count($parts) >= 2) {
        $userInitials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    } else {
        $userInitials = strtoupper(mb_substr($userName, 0, 2));
    }
}

$unreadNotifsCount = 0;
if ($isUserLoggedIn && function_exists('db')) {
    try {
        $stmtNotif = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
        $stmtNotif->execute([(int)$_SESSION['user_id']]);
        $unreadNotifsCount = (int)$stmtNotif->fetchColumn();
    } catch (\Throwable $e) {
        $unreadNotifsCount = 0;
    }
}
?>

<nav id="bottom-nav" class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-slate-900/95 border-t md:border-t-0 border-slate-200/80 dark:border-slate-800 transition-colors"
     style="padding-bottom: env(safe-area-inset-bottom); padding-left: env(safe-area-inset-left, 0); padding-right: env(safe-area-inset-right, 0);">
    <div class="max-w-6xl mx-auto px-3 sm:px-4 w-full h-full flex items-center justify-between">

        <!-- 1. LOGO & BRANDING (VISIBLE SUR DESKTOP) -->
        <a href="/pages/accueil.php" class="hidden md:flex items-center gap-3 group shrink-0" title="Dahira A Khiba-i Rassouloulahi">
            <img src="/assets/uploads/20.png" alt="Dahira AKR" class="w-9 h-9 rounded-xl object-contain shadow-xs group-hover:scale-105 transition-transform" onerror="this.style.display='none'">
            <div class="leading-tight">
                <span class="font-black text-slate-900 dark:text-white text-base tracking-tight block group-hover:text-primary-700 dark:group-hover:text-blue-400 transition-colors">Dahira AKR</span>
                <span class="text-[10px] text-slate-400 font-medium tracking-wide">A Khiba-i Rassouloulahi</span>
            </div>
        </a>

        <!-- 2. NAVIGATION (MOBILE & DESKTOP OPTIMISÉS) -->
        <div class="flex items-center justify-around md:justify-center gap-1 sm:gap-2 md:gap-1.5 lg:gap-2 w-full md:w-auto py-1 md:py-0">
            
            <!-- Accueil -->
            <?php $isAccueil = in_array($currentPage, ['accueil', 'index']) && !$isAdminArea; ?>
            <a href="/pages/accueil.php" class="flex flex-col md:flex-row items-center gap-0.5 md:gap-2 py-1.5 px-2.5 sm:px-3 md:px-3.5 md:py-2 rounded-xl transition-all duration-200 flex-1 md:flex-none justify-center
                <?= $isAccueil 
                    ? 'text-primary-900 dark:text-blue-400 font-bold md:bg-primary-50 md:dark:bg-blue-950/60 md:text-primary-900 md:dark:text-blue-300 md:border md:border-primary-200/80 md:dark:border-blue-800/80 md:shadow-xs' 
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white md:hover:bg-slate-100 md:dark:hover:bg-slate-800/60 font-medium' ?>">
                <svg class="w-5 h-5 shrink-0" fill="<?= $isAccueil ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span class="text-[10px] md:text-xs font-semibold">Accueil</span>
            </a>

            <!-- Boutique -->
            <?php $isBoutique = in_array($currentPage, ['tidiany-way','produit','panier','commande']) && !$isAdminArea; ?>
            <a href="/pages/tidiany-way.php" class="flex flex-col md:flex-row items-center gap-0.5 md:gap-2 py-1.5 px-2.5 sm:px-3 md:px-3.5 md:py-2 rounded-xl transition-all duration-200 flex-1 md:flex-none justify-center
                <?= $isBoutique 
                    ? 'text-primary-900 dark:text-blue-400 font-bold md:bg-primary-50 md:dark:bg-blue-950/60 md:text-primary-900 md:dark:text-blue-300 md:border md:border-primary-200/80 md:dark:border-blue-800/80 md:shadow-xs' 
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white md:hover:bg-slate-100 md:dark:hover:bg-slate-800/60 font-medium' ?>">
                <svg class="w-5 h-5 shrink-0" fill="<?= $isBoutique ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" x2="21" y1="6" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <span class="text-[10px] md:text-xs font-semibold">Boutique</span>
            </a>

            <!-- Cotisations (Membres uniquement) -->
            <?php if ($isUserMembre): ?>
            <?php $isCotisations = in_array($currentPage, ['cotisations', 'cotisation-membre']) && !$isAdminArea; ?>
            <a href="/pages/cotisations.php" class="flex flex-col md:flex-row items-center gap-0.5 md:gap-2 py-1.5 px-2.5 sm:px-3 md:px-3.5 md:py-2 rounded-xl transition-all duration-200 flex-1 md:flex-none justify-center
                <?= $isCotisations 
                    ? 'text-primary-900 dark:text-blue-400 font-bold md:bg-primary-50 md:dark:bg-blue-950/60 md:text-primary-900 md:dark:text-blue-300 md:border md:border-primary-200/80 md:dark:border-blue-800/80 md:shadow-xs' 
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white md:hover:bg-slate-100 md:dark:hover:bg-slate-800/60 font-medium' ?>">
                <svg class="w-5 h-5 shrink-0" fill="<?= $isCotisations ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect width="20" height="14" x="2" y="5" rx="2"/>
                    <line x1="2" x2="22" y1="10" y2="10"/>
                </svg>
                <span class="text-[10px] md:text-xs font-semibold">Cotisations</span>
            </a>
            <?php endif; ?>

            <!-- Paramètres -->
            <?php $isParametres = ($currentPage === 'parametres') && !$isAdminArea; ?>
            <a href="/pages/parametres.php" class="flex flex-col md:flex-row items-center gap-0.5 md:gap-2 py-1.5 px-2.5 sm:px-3 md:px-3.5 md:py-2 rounded-xl transition-all duration-200 flex-1 md:flex-none justify-center
                <?= $isParametres 
                    ? 'text-primary-900 dark:text-blue-400 font-bold md:bg-primary-50 md:dark:bg-blue-950/60 md:text-primary-900 md:dark:text-blue-300 md:border md:border-primary-200/80 md:dark:border-blue-800/80 md:shadow-xs' 
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white md:hover:bg-slate-100 md:dark:hover:bg-slate-800/60 font-medium' ?>">
                <svg class="w-5 h-5 shrink-0" fill="<?= $isParametres ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m7.08 7.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m7.08-7.08l4.24-4.24"/>
                </svg>
                <span class="text-[10px] md:text-xs font-semibold">Paramètres</span>
            </a>

            <!-- Onglet Administration (VISIBLE SUR DESKTOP SI ADMIN) -->
            <?php if ($isUserAdmin): ?>
            <a href="/admin/dashboard.php" class="hidden md:flex flex-row items-center gap-2 py-2 px-3.5 rounded-xl transition-all duration-200
                <?= $isAdminArea 
                    ? 'bg-amber-500/15 text-amber-800 dark:text-gold-300 font-bold border border-amber-500/30 shadow-xs' 
                    : 'text-amber-600 hover:text-amber-800 dark:text-gold-400 dark:hover:text-gold-200 hover:bg-amber-500/10 font-semibold' ?>">
                <svg class="w-4 h-4 shrink-0 text-amber-600 dark:text-gold-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="text-xs">Administration</span>
            </a>
            <?php endif; ?>

        </div>

        <!-- 3. ACTIONS PROFIL & SESSION (VISIBLE SUR DESKTOP) -->
        <div class="hidden md:flex items-center gap-2 shrink-0">
            <!-- Bouton Mode Sombre -->
            <button type="button" onclick="toggleDarkMode()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 flex items-center justify-center transition-colors cursor-pointer" title="Changer le thème">
                <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="w-4 h-4 block dark:hidden text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>

            <!-- Bouton Panier Global -->
            <a href="/pages/panier.php" class="relative w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 flex items-center justify-center transition-colors" title="Mon Panier">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" x2="21" y1="6" y2="6"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <span id="cart-badge-desktop" class="cart-badge absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full items-center justify-center hidden flex">0</span>
            </a>

            <?php if ($isUserLoggedIn): ?>
            <!-- Cloche Notifications -->
            <a href="/pages/notifications.php" class="relative w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 flex items-center justify-center transition-colors" title="Notifications">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($unreadNotifsCount > 0): ?>
                <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-slate-900 animate-pulse"></span>
                <?php endif; ?>
            </a>

            <!-- Profil connecté -->
            <a href="<?= $isUserAdmin ? '/admin/dashboard.php' : '/pages/parametres.php' ?>" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1 transition-colors" title="Mon compte">
                <div class="w-7 h-7 rounded-lg <?= $isUserAdmin ? 'bg-gradient-to-br from-gold-500 to-amber-600 text-slate-950' : 'bg-gradient-to-br from-primary-700 to-blue-900 text-white' ?> font-black text-xs flex items-center justify-center shadow-xs">
                    <?= e($userInitials) ?>
                </div>
                <div class="text-left leading-tight hidden lg:block">
                    <p class="text-xs font-semibold text-slate-900 dark:text-white max-w-[110px] truncate"><?= e($userName) ?></p>
                    <p class="text-[10px] <?= $isUserAdmin ? 'text-gold-600 dark:text-gold-400 font-bold' : 'text-slate-400' ?>"><?= $isUserAdmin ? 'Administrateur' : 'Membre' ?></p>
                </div>
            </a>
            <a href="/public/logout.php" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-500 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-950/40 dark:text-slate-400 dark:hover:text-rose-300 border border-slate-200 dark:border-slate-700 flex items-center justify-center transition-colors" title="Se déconnecter">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
            <?php else: ?>
            <a href="/public/login.php" class="px-3.5 py-1.5 rounded-xl bg-primary-900 hover:bg-primary-800 text-white text-xs font-bold shadow-xs transition-colors">
                Connexion
            </a>
            <?php endif; ?>
        </div>

    </div>
</nav>