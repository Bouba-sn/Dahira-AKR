<?php
// admin/dashboard.php
$pageTitle = 'Tableau de Bord - Administration';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CotisationService;

$pdo = db();
$currentUser = getCurrentUser();

// Service Cotisation & Campagne Active
$cotisationService = new CotisationService($pdo);
$campagneActive = $cotisationService->getActiveCampagne();
$campagneStats = null;
if ($campagneActive) {
    $campagneStats = $cotisationService->getGlobalStats((int)$campagneActive['id']);
}
require_once __DIR__ . '/../includes/header.php';

// 1. Infos de l'administrateur connecté
$adminInfo = null;
if ($currentUser) {
    $stmtAdmin = $pdo->prepare("SELECT nom, prenom, email, avatar, role FROM utilisateurs WHERE id = ?");
    $stmtAdmin->execute([$currentUser['id']]);
    $adminInfo = $stmtAdmin->fetch() ?: null;
}
$adminNomComplet = trim(($adminInfo['prenom'] ?? '') . ' ' . ($adminInfo['nom'] ?? ''));
if (empty($adminNomComplet)) {
    $adminNomComplet = $currentUser['nom'] ?? 'Administrateur';
}

$initiales = strtoupper(
    mb_substr($adminInfo['prenom'] ?? 'A', 0, 1) . 
    mb_substr($adminInfo['nom'] ?? 'D', 0, 1)
);
if (empty(trim($initiales))) {
    $initiales = 'AD';
}

// 2. Date en français
$joursFr = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$moisFr  = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$dateAujourdhui = ucfirst($joursFr[(int)date('w')]) . ' ' . date('j') . ' ' . $moisFr[(int)date('n')] . ' ' . date('Y');

$heureActuelle = (int)date('H');
$salutation = ($heureActuelle >= 5 && $heureActuelle < 18) ? 'Bonjour' : 'Bonsoir';

// 3. Stats Globales
$stats = [
    'utilisateurs'     => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
    'adherents'        => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut_adhesion='membre'")->fetchColumn(),
    'adh_attente'      => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut_adhesion='en_attente'")->fetchColumn(),
    'commandes'        => (int)$pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
    'en_attente'       => (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE statut='en_attente'")->fetchColumn(),
    'commandes_guichet'=> (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE nom_client IS NOT NULL AND nom_client != ''")->fetchColumn(),
    'revenue'          => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE statut != 'annulee'")->fetchColumn(),
    'produits'         => (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn(),
    'stock_faible'     => (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE actif=1 AND stock <= 5")->fetchColumn(),
    'evenements'       => (int)$pdo->query("SELECT COUNT(*) FROM evenements")->fetchColumn(),
];

// 5. Adhésions en attente
$adhesions = $pdo->query("SELECT * FROM utilisateurs WHERE statut_adhesion = 'en_attente' ORDER BY id DESC")->fetchAll();

// 6. Dernières commandes (Boutique & Guichet)
$commandes = $pdo->query("
    SELECT c.*, COALESCE(NULLIF(c.nom_client, ''), u.nom) as client, u.telephone as client_tel 
    FROM commandes c 
    LEFT JOIN utilisateurs u ON c.user_id = u.id 
    ORDER BY c.date_commande DESC 
    LIMIT 5
")->fetchAll();

// 7. Derniers paiements de cotisations pour l'activité financière
$derniersPaiements = [];
try {
    $derniersPaiements = $pdo->query("
        SELECT p.*, u.nom as membre_nom, u.prenom as membre_prenom, u.categorie_membre 
        FROM cotisation_paiements p 
        JOIN utilisateurs u ON u.id = p.user_id 
        ORDER BY p.date_paiement DESC, p.id DESC 
        LIMIT 4
    ")->fetchAll();
} catch (\Throwable $e) {
    $derniersPaiements = [];
}
?>

<!-- TOP BAR ADMIN (MOBILE UNIQUEMENT, SUR DESKTOP LA NAVBAR PRINCIPALE EN TÊTE PREND LE RELAIS) -->
<div class="sticky top-0 z-40 bg-slate-900/95 dark:bg-slate-950/95 backdrop-blur-md text-white border-b border-slate-800/80 px-4 transition-colors md:hidden" style="height: calc(3.75rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center justify-between h-14 max-w-6xl mx-auto gap-3">
        <!-- Retour portail public & Logo titre -->
        <div class="flex items-center gap-3 min-w-0">
            <a href="/pages/accueil.php" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 flex items-center justify-center text-white/80 hover:text-white transition-all shrink-0" title="Retour au portail Dahira AKR">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0" title="Plateforme active"></span>
                    <h1 class="font-bold text-sm text-white tracking-wide truncate">Administration AKR</h1>
                </div>
                <p class="text-[11px] text-slate-400 truncate">Dahira A Khiba-i Rassouloulahi</p>
            </div>
        </div>

        <!-- Profil Admin & Actions Rapides -->
        <div class="flex items-center gap-2 shrink-0">

            <div class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-xl px-2.5 py-1">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-gold-500 to-amber-600 text-slate-950 font-black text-xs flex items-center justify-center shadow-xs">
                    <?= e($initiales) ?>
                </div>
                <div class="hidden md:block text-left leading-tight">
                    <p class="text-xs font-semibold text-white max-w-[120px] truncate"><?= e($adminNomComplet) ?></p>
                    <p class="text-[10px] text-gold-400 uppercase tracking-wider font-bold">Admin</p>
                </div>
            </div>

            <a href="/public/logout.php" class="w-8 h-8 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-300 hover:text-red-200 border border-red-500/30 flex items-center justify-center transition-colors" title="Se déconnecter">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
        </div>
    </div>
</div>

<main class="page-content bg-slate-50 dark:bg-slate-950 min-h-screen pb-32">
    <div class="max-w-6xl mx-auto px-4 pt-4 space-y-5">

        <!-- MESSAGES FLASH -->
        <?php if (isset($_GET['msg'])): ?>
        <div class="flash-success-box p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-2xl text-sm font-medium flex items-center justify-between gap-3 shadow-xs fade-in-up">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <span class="truncate"><?= e($_GET['msg']) ?></span>
            </div>
            <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-200 p-1.5 rounded-lg hover:bg-emerald-500/20 transition-colors" title="Fermer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="flash-error-box p-4 bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-2xl text-sm font-medium flex items-center justify-between gap-3 shadow-xs fade-in-up">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-rose-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <span class="truncate"><?= e($_GET['error']) ?></span>
            </div>
            <button type="button" onclick="dismissFlash(this.closest('.flash-error-box'))" class="text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-200 p-1.5 rounded-lg hover:bg-rose-500/20 transition-colors" title="Fermer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <?php endif; ?>

        <!-- ACTIONS REQUISES / ATTENTION BAR (SI BESOIN) -->
        <?php if ($stats['adh_attente'] > 0 || $stats['en_attente'] > 0 || $stats['stock_faible'] > 0): ?>
        <div class="bg-amber-500/10 dark:bg-amber-950/40 border border-amber-500/30 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-amber-900 dark:text-amber-200">Actions en attente de traitement</h3>
                    <p class="text-[11px] text-amber-700/90 dark:text-amber-300">Certaines opérations nécessitent votre validation directe :</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($stats['adh_attente'] > 0): ?>
                <a href="#adhesions-attente" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <span><?= $stats['adh_attente'] ?> adhésion<?= $stats['adh_attente'] > 1 ? 's' : '' ?></span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <?php endif; ?>
                <?php if ($stats['en_attente'] > 0): ?>
                <a href="/admin/commandes.php?statut=en_attente" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <span><?= $stats['en_attente'] ?> commande<?= $stats['en_attente'] > 1 ? 's' : '' ?></span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
                <?php if ($stats['stock_faible'] > 0): ?>
                <a href="/admin/boutique.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition-colors">
                    <span><?= $stats['stock_faible'] ?> stock bas</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>



        <!-- GRILLE DE STATISTIQUES & INDICATEURS CLÉS (KPI) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- 1. Chiffre d'affaires Boutique & Guichet -->
            <div class="card-hover bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Recettes Boutique & Ventes</span>
                    <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    <?= number_format($stats['revenue'], 0, ',', ' ') ?> <span class="text-xs font-bold text-slate-400">FCFA</span>
                </p>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                    <span><?= $stats['commandes'] ?> commandes</span>
                    <span class="font-medium text-emerald-600 dark:text-emerald-400"><?= $stats['commandes_guichet'] ?> au guichet</span>
                </div>
            </div>

            <!-- 2. Cotisations Collectées (Campagne Active) -->
            <div class="card-hover bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Cotisations Recouvrées</span>
                    <div class="w-8 h-8 rounded-xl bg-gold-50 dark:bg-amber-950/60 text-gold-600 dark:text-gold-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    <?= number_format($campagneStats['montant_collecte'] ?? 0, 0, ',', ' ') ?> <span class="text-xs font-bold text-slate-400">FCFA</span>
                </p>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="text-slate-500 dark:text-slate-400"><?= e($campagneActive['nom'] ?? 'Campagne en cours') ?></span>
                        <span class="font-bold text-gold-600 dark:text-gold-400"><?= $campagneStats['taux_global'] ?? 0 ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-gradient-to-r from-gold-500 to-amber-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, (float)($campagneStats['taux_global'] ?? 0)) ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- 3. Adhérents & Membres validés -->
            <div class="card-hover bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Membres Officiels</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    <?= $stats['adherents'] ?> <span class="text-xs font-bold text-slate-400">/ <?= $stats['utilisateurs'] ?> comptes</span>
                </p>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-[11px]">
                    <span class="text-slate-500 dark:text-slate-400">Cartes délivrées</span>
                    <?php if ($stats['adh_attente'] > 0): ?>
                    <span class="font-bold text-blue-600 dark:text-blue-400"><?= $stats['adh_attente'] ?> en attente</span>
                    <?php else: ?>
                    <span class="font-medium text-emerald-600 dark:text-emerald-400">À jour</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. Produits & Disponibilité Catalogue -->
            <div class="card-hover bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Articles & Inventaire</span>
                    <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    <?= $stats['produits'] ?> <span class="text-xs font-bold text-slate-400">actifs</span>
                </p>
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-[11px]">
                    <span class="text-slate-500 dark:text-slate-400"><?= $stats['evenements'] ?> événements</span>
                    <?php if ($stats['stock_faible'] > 0): ?>
                    <span class="font-bold text-rose-600 dark:text-rose-400"><?= $stats['stock_faible'] ?> alerte stock</span>
                    <?php else: ?>
                    <span class="font-medium text-emerald-600 dark:text-emerald-400">Stocks OK</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MODULES DE GESTION ADMINISTRATIVE -->
        <div>
            <div class="flex items-center justify-between mb-3.5">
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white tracking-tight">Modules de Gestion</h2>
                    <p class="text-xs text-slate-400">Accès direct aux différents pôles de gestion du Dahira</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <!-- 1. Cotisations -->
                <a href="/admin/cotisations.php" class="card-hover group bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-start gap-3.5 transition-all">
                    <div class="w-11 h-11 rounded-2xl bg-gold-500/10 text-gold-600 dark:text-gold-400 border border-gold-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 group-hover:bg-gold-500 group-hover:text-slate-950 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-gold-600 dark:group-hover:text-gold-400 transition-colors">Cotisations</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gold-500/10 text-gold-600 dark:text-gold-400">Finances</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">Suivi des campagnes, enregistrement des versements et reçus officiels.</p>
                    </div>
                </a>

                <!-- 2. Commandes & Ventes Directes -->
                <a href="/admin/commandes.php" class="card-hover group bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-start gap-3.5 transition-all">
                    <div class="w-11 h-11 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 group-hover:bg-blue-600 group-hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">Commandes & Guichet</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400"><?= $stats['commandes'] ?> cmds</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">Suivi des livraisons en ligne et saisie de ventes directes au comptoir.</p>
                    </div>
                </a>

                <!-- 3. Membres & Adhésions -->
                <a href="/admin/utilisateurs.php" class="card-hover group bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-start gap-3.5 transition-all">
                    <div class="w-11 h-11 rounded-2xl bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 group-hover:bg-purple-600 group-hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">Membres & Rôles</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400"><?= $stats['adherents'] ?> validés</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">Attribution des catégories (Simple, Bureau, Enfant) et profils membres.</p>
                    </div>
                </a>

                <!-- 4. Boutique & Articles -->
                <a href="/admin/boutique.php" class="card-hover group bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-start gap-3.5 transition-all">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">Catalogue Boutique</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><?= $stats['produits'] ?> articles</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">Gestion des livres, parfums, tenues, tarifs et réapprovisionnements.</p>
                    </div>
                </a>

                <!-- 5. Événements & Dahiras -->
                <a href="/admin/evenements.php" class="card-hover group bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-start gap-3.5 transition-all">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Événements</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"><?= $stats['evenements'] ?> séances</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">Planification des Dahiras, Gamou, veillées spirituelles et daaras.</p>
                    </div>
                </a>

                <!-- 6. Heures de Prière -->
                <a href="/admin/heures_prieres.php" class="card-hover group bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex items-start gap-3.5 transition-all">
                    <div class="w-11 h-11 rounded-2xl bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 group-hover:bg-teal-600 group-hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">Heures de Prière</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-teal-500/10 text-teal-600 dark:text-teal-400">Actif</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">Ajustement précis des horaires Fajr, Tisbar, Takusan, Timis et Gué.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- SECTION: ADHÉSIONS EN ATTENTE -->
        <div id="adhesions-attente" class="scroll-mt-20">
            <div class="flex items-center justify-between mb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white tracking-tight">Demandes d'adhésion en attente</h2>
                    <span class="px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 font-bold text-[10px]">
                        <?= count($adhesions) ?>
                    </span>
                </div>
                <a href="/admin/utilisateurs.php" class="text-xs font-semibold text-primary-600 dark:text-blue-400 hover:underline">
                    Gérer tous les membres →
                </a>
            </div>

            <?php if (!empty($adhesions)): ?>
            <div class="space-y-3">
                <?php foreach ($adhesions as $adh): 
                    $isPhysique = !empty($adh['carte_physique']) || ($adh['type_adhesion'] ?? '') === 'carte_existante';
                    $nomAdh = trim(($adh['prenom'] ?? '') . ' ' . ($adh['nom'] ?? ''));
                    if (empty($nomAdh)) $nomAdh = $adh['nom'] ?? 'Adhérent inconnu';
                    $initAdh = strtoupper(mb_substr($adh['prenom'] ?? 'M', 0, 1) . mb_substr($adh['nom'] ?? 'M', 0, 1));
                ?>
                <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs card-hover fade-in-up">
                    <div class="flex items-start gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-primary-700 to-blue-900 text-white font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                            <?= e($initAdh) ?>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <p class="font-bold text-sm text-slate-900 dark:text-white truncate"><?= e($nomAdh) ?></p>
                                <?php if ($isPhysique): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300/80 dark:border-emerald-800/80 shrink-0">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>Carte physique existante</span>
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-cyan-50 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border border-cyan-300/80 dark:border-cyan-800/80 shrink-0">
                                    <svg class="w-3 h-3 text-cyan-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    <span>Nouvelle adhésion (Wave)</span>
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <span><?= e($adh['telephone']) ?></span>
                                </span>

                                <?php if (!empty($adh['adresse'])): ?>
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="truncate max-w-[200px]"><?= e($adh['adresse']) ?></span>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire de validation d'adhésion -->
                    <form action="/admin/valider_adhesion.php" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-slate-700/60">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $adh['id'] ?>">
                        <input type="hidden" name="redirect_to" value="/admin/dashboard.php#adhesions-attente">

                        <div class="flex items-center gap-2">
                            <label class="text-[11px] font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">Catégorie :</label>
                            <select name="categorie_membre" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 text-xs font-semibold rounded-xl px-2.5 py-1.5 focus:ring-2 focus:ring-primary-500 outline-none">
                                <option value="simple" <?= ($adh['categorie_membre'] ?? 'simple') === 'simple' ? 'selected' : '' ?>>Simple (30 000 F / an)</option>
                                <option value="bureau" <?= ($adh['categorie_membre'] ?? '') === 'bureau' ? 'selected' : '' ?>>Bureau (35 000 F / an)</option>
                                <option value="enfant" <?= ($adh['categorie_membre'] ?? '') === 'enfant' ? 'selected' : '' ?>>Enfant (15 000 F / an)</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit" name="action" value="valider" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs shadow-xs transition-all" title="Valider l'adhésion avec cette catégorie">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>Valider</span>
                            </button>
                            <button type="submit" name="action" value="refuser" onclick="return confirm('Êtes-vous sûr de vouloir refuser cette adhésion ?');" class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-500 hover:text-rose-600 dark:bg-slate-700/80 dark:hover:bg-rose-950/50 dark:text-slate-300 dark:hover:text-rose-400 transition-colors" title="Refuser cette adhésion">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="bg-white dark:bg-slate-800/60 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-700/80 text-center shadow-xs">
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Toutes les adhésions sont à jour</p>
                <p class="text-xs text-slate-400 mt-0.5">Aucune demande en attente d'approbation actuellement.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- DOUBLE PANNEAU: DERNIÈRES COMMANDES & DERNIÈRES COTISATIONS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Panneau 1: Dernières commandes & Ventes directes -->
            <div class="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Dernières Commandes</h2>
                        </div>
                        <a href="/admin/commandes.php" class="text-xs font-semibold text-primary-600 dark:text-blue-400 hover:underline">
                            Tout voir →
                        </a>
                    </div>

                    <?php if (!empty($commandes)): ?>
                    <div class="space-y-2.5">
                        <?php
                        $statutColors = [
                            'en_attente' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800/60',
                            'confirmee'  => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-400 dark:border-blue-800/60',
                            'expediee'   => 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-400 dark:border-purple-800/60',
                            'livree'     => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800/60',
                            'annulee'    => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-800/60',
                        ];
                        $statutLabels = [
                            'en_attente' => 'En attente',
                            'confirmee'  => 'Confirmée',
                            'expediee'   => 'Expédiée',
                            'livree'     => 'Livrée',
                            'annulee'    => 'Annulée'
                        ];

                        foreach ($commandes as $c):
                            $isDirect = !empty($c['nom_client']);
                        ?>
                        <div class="bg-slate-50 dark:bg-slate-900/60 rounded-2xl p-3 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl <?= $isDirect ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-primary-500/10 text-primary-600 dark:text-blue-400' ?> flex items-center justify-center shrink-0">
                                    <?php if ($isDirect): ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <?php else: ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= e($c['client'] ?? 'Client') ?></p>
                                        <?php if ($isDirect): ?>
                                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">Guichet</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-[10px] text-slate-400">
                                        #CMD-<?= $c['id'] ?> • <?= date('d/m/Y à H:i', strtotime($c['date_commande'])) ?>
                                    </p>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <p class="text-xs font-black text-slate-900 dark:text-white">
                                    <?= number_format($c['total'], 0, ',', ' ') ?> F
                                </p>
                                <span class="inline-block text-[10px] px-2 py-0.5 rounded-md font-bold border <?= $statutColors[$c['statut']] ?? 'bg-slate-100 text-slate-600' ?>">
                                    <?= $statutLabels[$c['statut']] ?? $c['statut'] ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-slate-400 text-center py-6">Aucune commande enregistrée.</p>
                    <?php endif; ?>
                </div>

                <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-700/60 text-center">
                    <a href="/admin/commandes.php" class="text-xs font-bold text-primary-600 dark:text-blue-400 hover:underline">
                        Accéder à la gestion des commandes & ventes directes →
                    </a>
                </div>
            </div>

            <!-- Panneau 2: Derniers Versements Cotisations -->
            <div class="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-gold-500"></div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Derniers Encaissements Cotisations</h2>
                        </div>
                        <a href="/admin/cotisations.php" class="text-xs font-semibold text-gold-600 dark:text-gold-400 hover:underline">
                            Tout voir →
                        </a>
                    </div>

                    <?php if (!empty($derniersPaiements)): ?>
                    <div class="space-y-2.5">
                        <?php foreach ($derniersPaiements as $p): 
                            $nomP = trim(($p['membre_prenom'] ?? '') . ' ' . ($p['membre_nom'] ?? ''));
                            if (empty($nomP)) $nomP = 'Membre Dahira';
                        ?>
                        <div class="bg-slate-50 dark:bg-slate-900/60 rounded-2xl p-3 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl bg-gold-500/10 text-gold-600 dark:text-gold-400 border border-gold-500/20 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= e($nomP) ?></p>
                                    <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                                        <span class="capitalize font-medium"><?= e($p['methode'] ?? 'Espèces') ?></span>
                                        <span>•</span>
                                        <span><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <p class="text-xs font-black text-emerald-600 dark:text-emerald-400">
                                    +<?= number_format($p['montant'], 0, ',', ' ') ?> F
                                </p>
                                <span class="inline-block text-[9px] px-1.5 py-0.2 rounded font-semibold bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    <?= ucfirst($p['categorie_membre'] ?? 'Simple') ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-slate-400 text-center py-6">Aucun versement de cotisation récent.</p>
                    <?php endif; ?>
                </div>

                <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-700/60 text-center">
                    <a href="/admin/cotisations.php" class="text-xs font-bold text-gold-600 dark:text-gold-400 hover:underline">
                        Gérer les campagnes et exporter les états financiers →
                    </a>
                </div>
            </div>
        </div>

    </div>
</main>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
