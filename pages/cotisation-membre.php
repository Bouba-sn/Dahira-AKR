<?php
// pages/cotisation-membre.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
requireMembre();

use App\Services\CotisationService;

$pdo = db();
$cotisationService = new CotisationService($pdo);

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$campagneId = isset($_GET['campagne_id']) ? (int)$_GET['campagne_id'] : 0;

// SÉCURITÉ STRICTE : Un membre régulier ne peut consulter que ses propres cotisations
if (!isAdmin()) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
} elseif ($userId <= 0) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
}

if ($campagneId <= 0) {
    $activeCampagne = $cotisationService->getActiveCampagne();
    $campagneId = $activeCampagne ? (int)$activeCampagne['id'] : 0;
}

$campagne = $cotisationService->getCampagneById($campagneId);
$detail = $cotisationService->getMemberDetail($userId, $campagneId);

if (!$detail || !$campagne) {
    header('Location: /pages/cotisations.php');
    exit;
}

$user = $detail['user'];
$sit = $detail['situation'];
$paiements = $detail['paiements'];
$nomComplet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$pageTitle = 'Fiche Cotisation — ' . ($nomComplet ?: $user['nom']);

// Calcul de la situation respectant strictement la limite de la catégorie
$cat = $user['categorie_membre'] ?? 'simple';
$objectif = (float)$sit['objectif'];
$totalPaye = (float)$sit['total_paye'];

$payeAffiche = min($totalPaye, $objectif);
$resteAffiche = max(0.0, $objectif - $totalPaye);
$isSolde = ($totalPaye >= $objectif && $objectif > 0);
$isAucune = ($totalPaye <= 0);
$isEnCours = (!$isSolde && !$isAucune);
$progressionAffichee = $objectif > 0 ? min(100.0, round(($totalPaye / $objectif) * 100, 1)) : 100.0;

$paiementConfig = file_exists(__DIR__ . '/../config/paiement.php') ? require __DIR__ . '/../config/paiement.php' : [];
$waveCfg = $paiementConfig['wave'] ?? [
    'nom'           => 'Caisse Dahira (Wave Business)',
    'telephone'     => '78 823 24 79',
    'raw_telephone' => '788232479',
    'lien_paiement' => 'https://pay.wave.com/m/M_dahira_akr',
    'logo'          => '/assets/wave-logo.png',
    'icon'          => '/assets/wave-icon.png'
];

$flashSuccess = '';
$flashError = '';

if (isset($_GET['success_wave'])) {
    $flashSuccess = "Votre versement via Wave a bien été enregistré avec succès.";
} elseif (isset($_GET['success_admin'])) {
    $flashSuccess = "Le versement a été enregistré avec succès.";
} elseif (isset($_GET['deleted'])) {
    $flashSuccess = "Versement supprimé avec succès.";
}

// 1. Traitement cotisation Wave par le membre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'membre_wave_cotisation') {
    requireMembre();
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flashError = 'Session expirée, veuillez réessayer.';
    } else {
        $targetUserId = (int)($_SESSION['user_id'] ?? 0);
        $montant = (float)($_POST['montant'] ?? 0);
        $waveTel = sanitize($_POST['wave_telephone'] ?? '');
        $datePaiement = date('Y-m-d');

        if ($montant <= 0) {
            $flashError = 'Veuillez saisir un montant supérieur à 0 FCFA.';
        } else {
            try {
                $noteComplete = "Cotisation Wave";
                if (!empty($waveTel)) $noteComplete .= " ({$waveTel})";

                $cotisationService->addPaiement(
                    $campagneId,
                    $targetUserId,
                    $montant,
                    $datePaiement,
                    null,
                    'wave',
                    $noteComplete
                );

                $campName = $campagne['nom'] ?? 'Cotisations';
                $memberInfo = $cotisationService->getMemberDetail($targetUserId, $campagneId);
                $nouveauTotal = $memberInfo ? $cotisationService->formatFcfa($memberInfo['situation']['total_paye']) : '';

                addNotification(
                    $targetUserId,
                    'Cotisation Wave enregistrée',
                    "Votre versement de {$cotisationService->formatFcfa($montant)} via Wave a été enregistré ({$campName}). Total cotisé : {$nouveauTotal}.",
                    "/pages/cotisation-membre.php?id={$targetUserId}&campagne_id={$campagneId}",
                    'cotisation'
                );

                $adminIds = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                $membreNom = $_SESSION['user_nom'] ?? 'Un membre';
                foreach ($adminIds as $admId) {
                    addNotification(
                        (int)$admId,
                        'Nouveau versement Wave reçu',
                        "Le membre {$membreNom} a envoyé {$cotisationService->formatFcfa($montant)} via Wave pour {$campName}.",
                        "/pages/cotisation-membre.php?id={$targetUserId}&campagne_id={$campagneId}",
                        'cotisation'
                    );
                }

                header("Location: /pages/cotisation-membre.php?id={$targetUserId}&campagne_id={$campagneId}&success_wave=1");
                exit;
            } catch (\Throwable $e) {
                $flashError = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

// 2. Traitement ajout versement par un admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_add_paiement') {
    requireAdmin();
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flashError = 'Session expirée, veuillez réessayer.';
    } else {
        $montant = (float)($_POST['montant'] ?? 0);
        $datePaiement = trim($_POST['date_paiement'] ?? date('Y-m-d'));
        $mode = trim($_POST['mode_paiement'] ?? 'especes');
        $note = trim($_POST['commentaire'] ?? '');

        if ($montant <= 0) {
            $flashError = 'Le montant doit être supérieur à 0 FCFA.';
        } else {
            try {
                $cotisationService->addPaiement(
                    $campagneId,
                    $userId,
                    $montant,
                    $datePaiement,
                    $_SESSION['user_id'] ?? null,
                    $mode,
                    $note
                );

                $memberInfo = $cotisationService->getMemberDetail($userId, $campagneId);
                $nouveauTotal = $memberInfo ? $cotisationService->formatFcfa($memberInfo['situation']['total_paye']) : '';
                $adminNom = $_SESSION['user_nom'] ?? 'Un administrateur';

                addNotification(
                    $userId,
                    'Versement enregistré',
                    "L'administrateur {$adminNom} a enregistré votre versement de {$cotisationService->formatFcfa($montant)} ({$mode}) pour {$campagne['nom']}. Total cotisé : {$nouveauTotal}.",
                    "/pages/cotisation-membre.php?id={$userId}&campagne_id={$campagneId}",
                    'cotisation'
                );

                header("Location: /pages/cotisation-membre.php?id={$userId}&campagne_id={$campagneId}&success_admin=1");
                exit;
            } catch (\Throwable $e) {
                $flashError = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

// Traitement suppression versement (Admin seulement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_paiement_id'])) {
    requireAdmin();
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $cotisationService->deletePaiement((int)$_POST['delete_paiement_id']);
        header("Location: /pages/cotisation-membre.php?id={$userId}&campagne_id={$campagneId}&deleted=1");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

$catName = match($cat) {
    'bureau' => 'Membre du bureau (35 000 F)',
    'enfant' => 'Enfant (15 000 F)',
    default  => 'Membre simple (30 000 F)'
};

$catBadgeStyle = 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300 border-slate-200 dark:border-slate-600';
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4 shadow-md print:hidden" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center h-14 gap-3">
        <a href="/pages/cotisations.php?campagne_id=<?= $campagneId ?>" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 active:scale-95 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="font-bold text-sm truncate">Fiche Membre</h1>
            <p class="text-[11px] opacity-75 truncate"><?= e($campagne['nom']) ?></p>
        </div>
        <button onclick="window.print()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-white/20 transition-colors" title="Imprimer l'historique">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        </button>
    </div>
</div>

<main class="page-content px-4 py-5 max-w-3xl mx-auto pb-32">

    <!-- Messages Flash -->
    <?php if ($flashSuccess): ?>
    <div class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 rounded-xl text-xs sm:text-sm flex items-center justify-between gap-2 shadow-xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="font-medium"><?= e($flashSuccess) ?></span>
        </div>
        <button type="button" onclick="this.closest('.mb-4').remove()" class="text-emerald-500 hover:text-emerald-700 p-1">×</button>
    </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
    <div class="mb-4 p-3 bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300 rounded-xl text-xs sm:text-sm flex items-center justify-between gap-2 shadow-xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span class="font-medium"><?= e($flashError) ?></span>
        </div>
        <button type="button" onclick="this.closest('.mb-4').remove()" class="text-red-500 hover:text-red-700 p-1">×</button>
    </div>
    <?php endif; ?>

    <!-- En-tête profil du membre -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-xs mb-5 fade-in-up">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-slate-800 text-white font-bold text-xl flex items-center justify-center shrink-0 shadow-xs">
                <?= strtoupper(substr($nomComplet ?: $user['nom'], 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate"><?= e($nomComplet ?: $user['nom']) ?></h2>
                    
                    <!-- Statut -->
                    <?php if ($isSolde): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-bold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800 shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Cotisation complète</span>
                    </span>
                    <?php elseif ($isAucune): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-bold px-3 py-1 rounded-full bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-900 shrink-0">
                        <svg class="w-3.5 h-3.5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
                        <span>Non cotisé</span>
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center text-xs font-medium px-3 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 shrink-0">
                        En cours (<?= $progressionAffichee ?>%)
                    </span>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center border px-2.5 py-0.5 rounded-md font-semibold text-[11px] <?= $catBadgeStyle ?>">
                        <?= $catName ?>
                    </span>
                    <?php if (!empty($user['telephone'])): ?>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <span><?= e($user['telephone']) ?></span>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($user['email']) && !str_ends_with($user['email'], '.local')): ?>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span><?= e($user['email']) ?></span>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Synthèse financière -->
        <div class="grid grid-cols-3 gap-2 mt-5 p-3.5 bg-slate-50 dark:bg-slate-900/50 rounded-2xl text-center border border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Objectif</span>
                <span class="text-sm md:text-base font-bold text-slate-800 dark:text-white"><?= $cotisationService->formatFcfa($objectif) ?></span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Total Payé</span>
                <span class="text-sm md:text-base font-bold <?= $isSolde ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-100' ?>"><?= $cotisationService->formatFcfa($payeAffiche) ?></span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Reste à payer</span>
                <span class="text-sm md:text-base font-bold <?= $isSolde ? 'text-emerald-600 dark:text-emerald-400' : ($isAucune ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200') ?>">
                    <?= $isSolde ? 'Cotisation complète' : $cotisationService->formatFcfa($resteAffiche) ?>
                </span>
            </div>
        </div>

        <!-- Jauge de progression -->
        <div class="mt-4 space-y-1.5">
            <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-slate-300">
                <span>Progression : <?= $progressionAffichee ?>%</span>
                <?php if ($isSolde): ?>
                <span class="text-emerald-600 dark:text-emerald-400 font-bold">Cotisation complète (100%)</span>
                <?php elseif ($isAucune): ?>
                <span class="text-red-600 dark:text-red-400 font-medium">Aucun versement</span>
                <?php else: ?>
                <span class="text-slate-500 font-medium">Reste : <?= $cotisationService->formatFcfa($resteAffiche) ?></span>
                <?php endif; ?>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 <?= $isSolde ? 'bg-emerald-500' : ($isAucune ? 'bg-transparent' : 'bg-slate-700 dark:bg-slate-300') ?>" 
                     style="width: <?= $progressionAffichee ?>%"></div>
            </div>
        </div>

        <!-- Bouton Cotiser Wave pour le membre connecté -->
        <?php if ($userId === (int)($_SESSION['user_id'] ?? 0)): ?>
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center justify-between gap-3">
            <div>
                <?php if ($isSolde): ?>
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Cotisation annuelle complète !</span>
                    </span>
                <?php else: ?>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Reste à payer : <strong class="text-slate-900 dark:text-white font-bold"><?= $cotisationService->formatFcfa($resteAffiche) ?></strong></span>
                <?php endif; ?>
            </div>
            <a href="<?= e($waveCfg['lien_paiement']) ?>" target="_blank" rel="noopener noreferrer" 
               class="bg-[#1da1f2] hover:bg-[#1a94df] text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-xs active:scale-95 transition-all flex items-center gap-2 group">
                <img src="/assets/wave-icon.png" alt="Wave" class="w-4 h-4 rounded-full object-contain" onerror="this.src='/assets/wave-logo.png'">
                <span><?= $isSolde ? 'Faire une avance Wave' : 'Cotiser via Wave' ?></span>
                <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historique des versements -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-xs fade-in-up">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Historique des Versements</span>
                <span class="text-xs font-normal text-slate-400">(<?= count($paiements) ?>)</span>
            </h3>

            <?php if (isAdmin()): ?>
            <button type="button" onclick="openAdminPaiementModal()" 
                    class="text-xs bg-primary-900 hover:bg-primary-800 text-white px-3.5 py-1.5 rounded-xl font-bold shadow-xs print:hidden transition-all flex items-center gap-1 active:scale-95">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>Enregistrer un versement</span>
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($paiements)): ?>
        <div class="text-center py-8">
            <div class="w-12 h-12 mx-auto mb-2 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            </div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Aucun versement enregistré pour cette campagne.</p>
            <p class="text-xs text-slate-400 mt-1">Les versements s'afficheront ici au fur et à mesure.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700 text-slate-400 uppercase font-semibold text-[10px]">
                        <th class="py-2.5 px-2">Date</th>
                        <th class="py-2.5 px-2">Montant</th>
                        <th class="py-2.5 px-2">Mode</th>
                        <th class="py-2.5 px-2">Total Cumulé</th>
                        <th class="py-2.5 px-2">Enregistré par</th>
                        <th class="py-2.5 px-2">Note / Référence</th>
                        <?php if (isAdmin()): ?><th class="py-2.5 px-2 text-right print:hidden">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($paiements as $p): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-3 px-2 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                            <?= date('d/m/Y', strtotime($p['date_paiement'])) ?>
                        </td>
                        <td class="py-3 px-2 font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                            <?= $cotisationService->formatFcfa($p['montant']) ?>
                        </td>
                        <td class="py-3 px-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            <?php if ($p['mode_paiement'] === 'wave'): ?>
                            <span class="inline-flex items-center gap-1.5 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 text-[11px] font-semibold px-2 py-0.5 rounded-md">
                                <img src="/assets/wave-icon.png" alt="Wave" class="w-3.5 h-3.5 rounded-full object-contain">
                                Wave
                            </span>
                            <?php elseif ($p['mode_paiement'] === 'orange_money'): ?>
                            <span class="inline-flex items-center gap-1.5 bg-orange-50 dark:bg-orange-950/40 border border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-300 text-[11px] font-semibold px-2 py-0.5 rounded-md">
                                <img src="/assets/orange-money-icon.svg" alt="Orange Money" class="w-3.5 h-3.5 object-contain">
                                Orange Money
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[11px] font-medium px-2 py-0.5 rounded-md capitalize">
                                <?= e(str_replace('_', ' ', $p['mode_paiement'])) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 font-extrabold text-primary-900 dark:text-blue-300 whitespace-nowrap">
                            <?= $cotisationService->formatFcfa($p['cumul_progressif']) ?>
                        </td>
                        <td class="py-3 px-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            <?= !empty($p['admin_id']) ? e($p['admin_nom'] ?: 'Admin') : '<span class="text-sky-600 dark:text-sky-400 font-semibold">Wave (Membre)</span>' ?>
                        </td>
                        <td class="py-3 px-2 text-slate-500 dark:text-slate-400">
                            <?php if (!empty($p['commentaire'])): ?>
                            <span class="text-slate-600 dark:text-slate-300"><?= e($p['commentaire']) ?></span>
                            <?php else: ?>
                            <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <?php if (isAdmin()): ?>
                        <td class="py-3 px-2 text-right print:hidden whitespace-nowrap">
                            <form method="POST" onsubmit="return confirm('Supprimer définitivement ce versement de <?= $cotisationService->formatFcfa($p['montant']) ?> ?')" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete_paiement_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 p-1 text-xs" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>



<!-- MODALE D'ENREGISTREMENT VERSEMENT (ADMIN) -->
<?php if (isAdmin()): ?>
<div id="modal-admin-paiement" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl p-5 sm:p-6 shadow-xl border border-slate-100 dark:border-slate-800 animate-in fade-in duration-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-4">
            <h3 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                <span>Enregistrer un Versement (Admin)</span>
            </h3>
            <button onclick="closeAdminPaiementModal()" class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="admin_add_paiement">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Membre</label>
                <div class="w-full bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">
                    <?= e($nomComplet ?: $user['nom']) ?> (<?= ucfirst($cat) ?>)
                </div>
            </div>

            <!-- Montant -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Montant Versé (FCFA) *</label>
                <input type="number" name="montant" id="modal-admin-montant" step="500" min="500" required placeholder="Ex : 5000"
                       value="<?= $resteAffiche > 0 ? (int)$resteAffiche : 5000 ?>"
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <?php if ($resteAffiche > 0): ?>
                    <button type="button" onclick="setAdminMontant(<?= (int)$resteAffiche ?>)" class="px-2 py-0.5 text-xs bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-100">Solde (<?= $cotisationService->formatFcfa($resteAffiche) ?>)</button>
                    <?php endif; ?>
                    <button type="button" onclick="addAdminMontant(2000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+2k</button>
                    <button type="button" onclick="addAdminMontant(5000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+5k</button>
                    <button type="button" onclick="addAdminMontant(10000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+10k</button>
                </div>
            </div>

            <!-- Date & Mode -->
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Date *</label>
                    <input type="date" name="date_paiement" value="<?= date('Y-m-d') ?>" required
                           class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mode *</label>
                    <select name="mode_paiement" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:outline-none">
                        <option value="especes">Espèces</option>
                        <option value="wave">Wave</option>
                        <option value="orange_money">Orange Money</option>
                        <option value="virement">Virement</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
            </div>

            <!-- Note -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Note (Optionnel)</label>
                <input type="text" name="commentaire" placeholder="Ex: Versement partiel, en main propre..."
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeAdminPaiementModal()" class="flex-1 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">Annuler</button>
                <button type="submit" class="flex-1 bg-primary-900 text-white font-bold text-xs py-2.5 rounded-xl shadow-xs hover:bg-primary-800 active:scale-95 transition-all">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openAdminPaiementModal() {
    const modal = document.getElementById('modal-admin-paiement');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeAdminPaiementModal() {
    const modal = document.getElementById('modal-admin-paiement');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function setAdminMontant(val) {
    const inp = document.getElementById('modal-admin-montant');
    if (inp) inp.value = val;
}

function addAdminMontant(val) {
    const inp = document.getElementById('modal-admin-montant');
    if (inp) {
        const cur = parseFloat(inp.value) || 0;
        inp.value = cur + val;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
