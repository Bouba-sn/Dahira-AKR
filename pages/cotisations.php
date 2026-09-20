<?php
// pages/cotisations.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireMembre();

$pageTitle = 'Cotisations Annuelles';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CotisationService;

$pdo = db();
$cotisationService = new CotisationService($pdo);

// 1. Récupération des campagnes
$campagnes = $cotisationService->getAllCampagnes();
$selectedCampagneId = isset($_GET['campagne_id']) ? (int)$_GET['campagne_id'] : 0;

if ($selectedCampagneId > 0) {
    $currentCampagne = $cotisationService->getCampagneById($selectedCampagneId);
} else {
    $currentCampagne = $cotisationService->getActiveCampagne();
}

if (!$currentCampagne && !empty($campagnes)) {
    $currentCampagne = $campagnes[0];
}

$campagneId = $currentCampagne ? (int)$currentCampagne['id'] : 0;

$paiementConfig = file_exists(__DIR__ . '/../config/paiement.php') ? require __DIR__ . '/../config/paiement.php' : [];
$waveCfg = $paiementConfig['wave'] ?? [
    'nom'           => 'Caisse Dahira (Wave Business)',
    'telephone'     => '78 823 24 79',
    'raw_telephone' => '788232479',
    'lien_paiement' => 'https://pay.wave.com/m/M_dahira_akr',
    'logo'          => '/assets/wave-logo.png',
    'icon'          => '/assets/wave-icon.png'
];

// 2. Traitement des versements (Membre via Wave OU Enregistrement par Admin)
$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'membre_wave_cotisation') {
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

                    $pId = $cotisationService->addPaiement(
                        $campagneId,
                        $targetUserId,
                        $montant,
                        $datePaiement,
                        null,
                        'wave',
                        $noteComplete
                    );

                    // Situation mise à jour
                    $memberInfo = $cotisationService->getMemberDetail($targetUserId, $campagneId);
                    $nouveauTotal = $memberInfo ? $cotisationService->formatFcfa($memberInfo['situation']['total_paye']) : '';
                    $campName = $currentCampagne['nom'] ?? 'Cotisations Annuelles';

                    // 1. Notification temps réel au membre
                    addNotification(
                        $targetUserId,
                        'Cotisation Wave enregistrée',
                        "Votre versement de {$cotisationService->formatFcfa($montant)} via Wave a été validé ({$campName}). Total cotisé : {$nouveauTotal}.",
                        "/pages/cotisation-membre.php?id={$targetUserId}&campagne_id={$campagneId}",
                        'cotisation'
                    );

                    // 2. Notification temps réel à tous les administrateurs
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

                    $flashSuccess = "Votre cotisation de {$cotisationService->formatFcfa($montant)} via Wave a été enregistrée avec succès !";
                } catch (\Throwable $e) {
                    $flashError = 'Erreur : ' . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'add_paiement') {
        requireAdmin();
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $flashError = 'Session expirée, veuillez réessayer.';
        } else {
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            $montant = (float)($_POST['montant'] ?? 0);
            $datePaiement = trim($_POST['date_paiement'] ?? date('Y-m-d'));
            $mode = trim($_POST['mode_paiement'] ?? 'especes');
            $note = trim($_POST['commentaire'] ?? '');

            if ($targetUserId <= 0) {
                $flashError = 'Veuillez sélectionner un membre.';
            } elseif ($montant <= 0) {
                $flashError = 'Le montant doit être supérieur à 0 FCFA.';
            } else {
                try {
                    $pId = $cotisationService->addPaiement(
                        $campagneId,
                        $targetUserId,
                        $montant,
                        $datePaiement,
                        $_SESSION['user_id'] ?? null,
                        $mode,
                        $note
                    );

                    // Notification temps réel au membre via WebSocket
                    $memberInfo = $cotisationService->getMemberDetail($targetUserId, $campagneId);
                    $nouveauTotal = $memberInfo ? $cotisationService->formatFcfa($memberInfo['situation']['total_paye']) : '';
                    $adminNom = $_SESSION['user_nom'] ?? 'Un administrateur';
                    
                    addNotification(
                        $targetUserId,
                        'Reçu de Cotisation enregistré',
                        "L'administrateur {$adminNom} a enregistré votre versement de {$cotisationService->formatFcfa($montant)} ({$mode}) pour {$currentCampagne['nom']}. Total cotisé : {$nouveauTotal}.",
                        "/pages/cotisation-membre.php?id={$targetUserId}&campagne_id={$campagneId}",
                        'cotisation'
                    );

                    $flashSuccess = "Versement de {$cotisationService->formatFcfa($montant)} enregistré avec succès.";
                } catch (Throwable $e) {
                    $flashError = 'Erreur : ' . $e->getMessage();
                }
            }
        }
    }
}

$isCurrentUserAdmin = isAdmin();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$search = sanitize($_GET['q'] ?? '');
$statutFilter = sanitize($_GET['statut'] ?? 'all');
if (!in_array($statutFilter, ['all', 'solde', 'en_cours', 'non_cotise'], true)) {
    $statutFilter = 'all';
}

// 3. Récupération de tous les membres pour la liste unifiée des cotisations
$rawMembers = $campagneId > 0 ? $cotisationService->getMembersWithSituation($campagneId, $search) : [];

// 4. Calcul des situations en respectant strictement la limite de chaque catégorie
$members = [];
$nbSolde = 0;
$nbEnCours = 0;
$nbAucune = 0;

foreach ($rawMembers as $m) {
    $sit = $m['situation'];
    $cat = $m['categorie_membre'] ?: 'simple';
    $objectif = (float)$sit['objectif'];
    $totalPaye = (float)$sit['total_paye'];

    // Respect de la limite de cotisation : plafonné à l'objectif de la catégorie
    $payeAffiche = min($totalPaye, $objectif);
    $reste = max(0.0, $objectif - $totalPaye);
    $isSolde = ($totalPaye >= $objectif && $objectif > 0);
    $isAucune = ($totalPaye <= 0);
    $isEnCours = (!$isSolde && !$isAucune);
    $progression = $objectif > 0 ? min(100.0, round(($totalPaye / $objectif) * 100, 1)) : 100.0;

    if ($isSolde) {
        $nbSolde++;
        $m['prio_tri'] = 1;
    } elseif ($isEnCours) {
        $nbEnCours++;
        $m['prio_tri'] = 2;
    } else {
        $nbAucune++;
        $m['prio_tri'] = 3;
    }

    $m['paye_affiche'] = $payeAffiche;
    $m['reste_affiche'] = $reste;
    $m['is_solde'] = $isSolde;
    $m['is_en_cours'] = $isEnCours;
    $m['is_aucune'] = $isAucune;
    $m['progression_affichee'] = $progression;

    $members[] = $m;
}

// Classement strict : ceux qui sont le plus près de leur but annuel en haut avec leurs badges
// 1. Soldé (100% de leur objectif atteint) tout en haut
// 2. En cours (classés par progression décroissante : 90% -> 80% -> 50%, et plus petit reste)
// 3. Non cotisé en bas
usort($members, function($a, $b) {
    if ($a['prio_tri'] !== $b['prio_tri']) {
        return $a['prio_tri'] <=> $b['prio_tri'];
    }
    if ($a['progression_affichee'] !== $b['progression_affichee']) {
        return $b['progression_affichee'] <=> $a['progression_affichee'];
    }
    if ($a['reste_affiche'] !== $b['reste_affiche']) {
        return $a['reste_affiche'] <=> $b['reste_affiche'];
    }
    $nomA = trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? ''));
    $nomB = trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? ''));
    return strcasecmp($nomA, $nomB);
});

// Liste des utilisateurs pour la modale d'ajout admin
$allUsersForSelect = $isCurrentUserAdmin 
    ? $pdo->query("SELECT id, nom, prenom, telephone, categorie_membre FROM utilisateurs ORDER BY nom ASC")->fetchAll() 
    : [];

// Pour un membre connecté non-admin : chargement de sa situation personnelle pour son bandeau récapitulatif
$mySituation = null;
if (!$isCurrentUserAdmin && $currentUserId > 0) {
    $myDetail = $campagneId > 0 ? $cotisationService->getMemberDetail($currentUserId, $campagneId) : null;
    if ($myDetail) {
        $mySit = $myDetail['situation'] ?? [];
        $myCat = $myDetail['user']['categorie_membre'] ?? 'simple';
        $myObj = (float)($mySit['objectif'] ?? $cotisationService->getObjectif($myCat));
        $myTotal = (float)($mySit['total_paye'] ?? 0);
        $myPaye = min($myTotal, $myObj);
        $myReste = max(0.0, $myObj - $myTotal);
        $mySolde = ($myTotal >= $myObj && $myObj > 0);
        $myAucune = ($myTotal <= 0);
        $myProg = $myObj > 0 ? min(100.0, round(($myTotal / $myObj) * 100, 1)) : 100.0;
        $mySituation = [
            'objectif' => $myObj,
            'paye' => $myPaye,
            'reste' => $myReste,
            'is_solde' => $mySolde,
            'is_aucune' => $myAucune,
            'progression' => $myProg,
            'cat_label' => match($myCat) {
                'bureau' => 'Membre du bureau (35 000 F)',
                'enfant' => 'Enfant (15 000 F)',
                default  => 'Membre simple (30 000 F)'
            },
            'nb_versements' => count($myDetail['paiements'] ?? [])
        ];
    }
}
?>

<!-- TOP BAR SOBRE -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4 shadow-sm" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center justify-between h-14 max-w-3xl mx-auto gap-2.5">
        <div class="flex items-center gap-2.5 min-w-0">
            <a href="/pages/accueil.php" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 transition-all shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="font-bold text-sm sm:text-base truncate leading-tight">Cotisations Annuelles</h1>
                <p class="text-[10px] text-white/70 truncate leading-none">Dahira A Khiba-i Rassouloulahi</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <!-- Sélecteur de Campagne -->
            <?php if (!empty($campagnes)): ?>
            <form method="GET" class="shrink-0">
                <select name="campagne_id" onchange="this.form.submit()" 
                        class="bg-white/15 border border-white/20 text-white text-xs font-semibold rounded-xl px-2.5 py-1.5 focus:outline-none focus:bg-primary-800 cursor-pointer">
                    <?php foreach ($campagnes as $c): ?>
                    <option value="<?= $c['id'] ?>" class="text-slate-900" <?= $c['id'] == $campagneId ? 'selected' : '' ?>>
                        <?= e($c['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            </form>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <button onclick="openPaiementModal()" 
                    class="bg-gold-500 hover:bg-gold-400 text-primary-950 font-bold text-xs px-3 py-1.5 rounded-xl shadow-xs flex items-center gap-1 active:scale-95 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>Verser</span>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<main class="page-content px-4 py-4 max-w-3xl mx-auto pb-32">

    <!-- Messages Flash auto-dismiss (2.2s) -->
    <?php if ($flashSuccess): ?>
    <div class="flash-success-box mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 rounded-xl text-xs sm:text-sm flex items-center justify-between gap-2 shadow-xs fade-in-up">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="font-medium"><?= e($flashSuccess) ?></span>
        </div>
        <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-emerald-500 hover:text-emerald-700 p-1" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
    <div class="mb-4 p-3 bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300 rounded-xl text-xs sm:text-sm flex items-center justify-between gap-2 shadow-xs fade-in-up">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span class="font-medium"><?= e($flashError) ?></span>
        </div>
        <button type="button" onclick="this.closest('.mb-4').remove()" class="text-red-500 hover:text-red-700 p-1" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <?php if (!$isCurrentUserAdmin && $mySituation): ?>
    <!-- MA SITUATION PERSONNELLE RAPIDE -->
    <div class="mb-4 bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs fade-in-up">
        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary-600"></span>
                <h2 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Ma Situation Personnelle</h2>
                <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-full"><?= $mySituation['cat_label'] ?></span>
            </div>
            <?php if ($mySituation['is_solde']): ?>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                    <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>Cotisation complète</span>
                </span>
            <?php elseif ($mySituation['is_aucune']): ?>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-900">
                    <svg class="w-3 h-3 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
                    <span>Non cotisé</span>
                </span>
            <?php else: ?>
                <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                    En cours (<?= $mySituation['progression'] ?>%)
                </span>
            <?php endif; ?>
        </div>

        <!-- Mini jauges et montants -->
        <div class="grid grid-cols-3 gap-2 p-2.5 bg-slate-50 dark:bg-slate-900/40 rounded-xl text-center border border-slate-100 dark:border-slate-800 mb-3">
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Objectif</span>
                <span class="text-xs sm:text-sm font-bold text-slate-800 dark:text-white"><?= $cotisationService->formatFcfa($mySituation['objectif']) ?></span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Versé</span>
                <span class="text-xs sm:text-sm font-bold <?= $mySituation['is_solde'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white' ?>"><?= $cotisationService->formatFcfa($mySituation['paye']) ?></span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Reste</span>
                <span class="text-xs sm:text-sm font-bold <?= $mySituation['is_solde'] ? 'text-emerald-600 dark:text-emerald-400' : ($mySituation['is_aucune'] ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200') ?>">
                    <?= $mySituation['is_solde'] ? 'Cotisation complète' : $cotisationService->formatFcfa($mySituation['reste']) ?>
                </span>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2">
            <div class="flex-1 mr-2">
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300 <?= $mySituation['is_solde'] ? 'bg-emerald-500' : ($mySituation['is_aucune'] ? 'bg-transparent' : 'bg-slate-700 dark:bg-slate-300') ?>" 
                         style="width: <?= $mySituation['progression'] ?>%"></div>
                </div>
            </div>
            <a href="/pages/cotisation-membre.php?id=<?= $currentUserId ?>&campagne_id=<?= $campagneId ?>" 
               class="shrink-0 text-xs font-semibold text-primary-900 dark:text-blue-400 hover:underline flex items-center gap-1">
                <span>Historique des paiements (<?= $mySituation['nb_versements'] ?>)</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

        <!-- ACTION PAIEMENT WAVE -->
        <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center justify-between gap-2.5">
            <div>
                <?php if ($mySituation['is_solde']): ?>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Cotisation annuelle complète !</span>
                    </p>
                <?php else: ?>
                    <p class="text-xs text-slate-600 dark:text-slate-300 font-medium">
                        Reste à verser : <strong class="text-slate-900 dark:text-white font-bold"><?= $cotisationService->formatFcfa($mySituation['reste']) ?></strong>
                    </p>
                <?php endif; ?>
            </div>
            <a href="<?= e($waveCfg['lien_paiement']) ?>" target="_blank" rel="noopener noreferrer" 
               class="bg-[#1da1f2] hover:bg-[#1a94df] text-white text-xs sm:text-sm font-bold px-4 py-2 rounded-xl shadow-xs active:scale-95 transition-all flex items-center gap-2 group">
                <img src="/assets/wave-icon.png" alt="Wave" class="w-4 h-4 rounded-full object-contain" onerror="this.src='/assets/wave-logo.png'">
                <span><?= $mySituation['is_solde'] ? 'Faire une avance Wave' : 'Cotiser via Wave' ?></span>
                <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- RECHERCHE & FILTRES PAR STATUT -->
    <section class="mb-3.5">
        <form method="GET" class="relative" id="cotisation-search-form">
            <input type="hidden" name="campagne_id" value="<?= $campagneId ?>">
            <input type="hidden" name="statut" id="filter-statut-input" value="<?= e($statutFilter) ?>">
            <input type="text" id="search-input" name="q" value="<?= e($search) ?>" 
                   placeholder="Rechercher par nom, prénom ou téléphone..."
                   class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-10 pr-9 py-2.5 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-primary-500 shadow-xs">
            <svg class="absolute left-3.5 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <?php if ($search): ?>
            <a href="?campagne_id=<?= $campagneId ?><?= $statutFilter !== 'all' ? '&statut=' . urlencode($statutFilter) : '' ?>" 
               class="absolute right-3 top-2.5 w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors" title="Effacer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </a>
            <?php endif; ?>
        </form>

        <!-- ONGLETS DE TRI ET FILTRE INTERACTIFS PAR STATUT -->
        <div class="mt-2.5 flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-[11px]" role="tablist" aria-label="Filtrer les cotisations">
            <!-- TOUS -->
            <button type="button" 
                    onclick="setStatutFilter('all')" 
                    id="btn-filter-all" 
                    role="tab"
                    aria-selected="<?= $statutFilter === 'all' ? 'true' : 'false' ?>"
                    class="statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all <?= $statutFilter === 'all' ? 'bg-primary-900 text-white border-primary-900 shadow-xs dark:bg-primary-800 dark:border-primary-700' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600' ?>">
                <span>Tous</span>
                <span id="badge-count-all" class="text-[10px] px-1.5 py-0.2 rounded-full font-bold <?= $statutFilter === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300' ?>">
                    <?= count($members) ?>
                </span>
            </button>

            <!-- COTISATION COMPLÈTE -->
            <button type="button" 
                    onclick="setStatutFilter('solde')" 
                    id="btn-filter-solde" 
                    role="tab"
                    aria-selected="<?= $statutFilter === 'solde' ? 'true' : 'false' ?>"
                    class="statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all <?= $statutFilter === 'solde' ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white dark:bg-slate-800 border-emerald-300 dark:border-emerald-800/60 text-slate-700 dark:text-slate-300 hover:border-emerald-400' ?>">
                <span class="w-2 h-2 rounded-full bg-emerald-500 <?= $statutFilter === 'solde' ? 'ring-2 ring-white/50' : '' ?>"></span>
                <span>Cotisation complète</span>
                <span id="badge-count-solde" class="text-[10px] px-1.5 py-0.2 rounded-full font-bold <?= $statutFilter === 'solde' ? 'bg-white/20 text-white' : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' ?>">
                    <?= $nbSolde ?>
                </span>
            </button>

            <!-- EN COURS -->
            <button type="button" 
                    onclick="setStatutFilter('en_cours')" 
                    id="btn-filter-en_cours" 
                    role="tab"
                    aria-selected="<?= $statutFilter === 'en_cours' ? 'true' : 'false' ?>"
                    class="statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all <?= $statutFilter === 'en_cours' ? 'bg-slate-800 text-white border-slate-800 shadow-xs dark:bg-slate-700 dark:border-slate-600' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600' ?>">
                <span class="w-2 h-2 rounded-full bg-slate-400 <?= $statutFilter === 'en_cours' ? 'ring-2 ring-white/50' : '' ?>"></span>
                <span>En cours</span>
                <span id="badge-count-en_cours" class="text-[10px] px-1.5 py-0.2 rounded-full font-bold <?= $statutFilter === 'en_cours' ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300' ?>">
                    <?= $nbEnCours ?>
                </span>
            </button>

            <!-- NON COTISÉ -->
            <button type="button" 
                    onclick="setStatutFilter('non_cotise')" 
                    id="btn-filter-non_cotise" 
                    role="tab"
                    aria-selected="<?= $statutFilter === 'non_cotise' ? 'true' : 'false' ?>"
                    class="statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all <?= $statutFilter === 'non_cotise' ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-white dark:bg-slate-800 border-red-200 dark:border-red-900/60 text-slate-700 dark:text-slate-300 hover:border-red-400' ?>">
                <span class="w-2 h-2 rounded-full bg-red-500 <?= $statutFilter === 'non_cotise' ? 'ring-2 ring-white/50' : '' ?>"></span>
                <span>Non cotisé</span>
                <span id="badge-count-non_cotise" class="text-[10px] px-1.5 py-0.2 rounded-full font-bold <?= $statutFilter === 'non_cotise' ? 'bg-white/20 text-white' : 'bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400' ?>">
                    <?= $nbAucune ?>
                </span>
            </button>
        </div>
    </section>

    <!-- LISTE DES COTISATIONS PAR ORDRE (LES PLUS PROCHES DU BUT EN HAUT) -->
    <section id="members-container" class="space-y-2.5">

        <!-- Message affiché si aucun membre ne correspond au filtre actif -->
        <div id="filter-empty-message" class="hidden bg-white dark:bg-slate-800 rounded-2xl p-8 text-center border border-slate-200 dark:border-slate-700 shadow-xs">
            <p class="text-xs text-slate-500 dark:text-slate-400">Aucun membre avec le statut <strong id="filter-empty-label" class="font-semibold text-slate-800 dark:text-white"></strong>.</p>
            <button type="button" onclick="setStatutFilter('all')" class="inline-block mt-2 text-xs text-primary-700 dark:text-blue-400 font-semibold hover:underline cursor-pointer">Afficher tous les membres</button>
        </div>

        <?php if (empty($members)): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 text-center border border-slate-200 dark:border-slate-700 shadow-xs">
            <p class="text-xs text-slate-500 dark:text-slate-400">Aucun membre trouvé pour "<?= e($search) ?>"</p>
            <a href="?campagne_id=<?= $campagneId ?>" class="inline-block mt-2 text-xs text-primary-700 dark:text-blue-400 font-semibold hover:underline">Réinitialiser</a>
        </div>
        <?php else: ?>

            <?php foreach ($members as $m): 
                $sit = $m['situation'];
                $cat = $m['categorie_membre'] ?: 'simple';
                $objectif = (float)$sit['objectif'];
                $isSolde = $m['is_solde'];
                $isAucune = $m['is_aucune'];
                $payeAffiche = $m['paye_affiche'];
                $resteAffiche = $m['reste_affiche'];
                $progression = $m['progression_affichee'];
                $nomAffiche = trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? ''));
                $isMe = ($m['id'] === $currentUserId);
                $cardStatut = $isSolde ? 'solde' : ($isAucune ? 'non_cotise' : 'en_cours');
                $isCardVisible = ($statutFilter === 'all' || $statutFilter === $cardStatut);

                $catLabel = match($cat) {
                    'bureau' => 'Bureau (35 000 F)',
                    'enfant' => 'Enfant (15 000 F)',
                    default  => 'Simple (30 000 F)'
                };
            ?>

            <!-- CARTE DU MEMBRE (CLASSEMENT PAR PROXIMITÉ DU BUT ANNUEL AVEC BADGES) -->
            <div class="member-card bg-white dark:bg-slate-800 rounded-xl p-3.5 sm:p-4 border <?= $isMe ? 'border-primary-400/80 ring-1 ring-primary-500/20' : 'border-slate-200/80 dark:border-slate-700' ?> shadow-xs hover:border-slate-300 dark:hover:border-slate-600 transition-colors"
                 data-statut="<?= $cardStatut ?>"
                 style="<?= $isCardVisible ? '' : 'display: none;' ?>">
                <div class="flex items-start gap-3">
                    
                    <!-- Initiale neutre ou mise en valeur si connecté -->
                    <div class="w-10 h-10 rounded-lg shrink-0 flex items-center justify-center font-bold text-xs <?= $isAucune ? 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-300' : ($isMe ? 'bg-primary-100 text-primary-900 dark:bg-primary-900/50 dark:text-primary-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200') ?>">
                        <?= strtoupper(substr($m['nom'], 0, 1)) ?>
                    </div>

                    <!-- Détails -->
                    <div class="flex-1 min-w-0">
                        
                        <!-- Ligne 1 : Nom, Badge Vous & Badge d'état -->
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2 flex-wrap">
                                    <?php if ($isCurrentUserAdmin || $isMe): ?>
                                    <a href="/pages/cotisation-membre.php?id=<?= $m['id'] ?>&campagne_id=<?= $campagneId ?>" 
                                       class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white truncate hover:underline">
                                        <?= e($nomAffiche ?: $m['nom']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white truncate">
                                        <?= e($nomAffiche ?: $m['nom']) ?>
                                    </span>
                                    <?php endif; ?>

                                    <?php if ($isMe): ?>
                                    <span class="text-[10px] bg-primary-50 text-primary-800 dark:bg-primary-950/50 dark:text-blue-300 px-1.5 py-0.2 rounded font-bold border border-primary-200 dark:border-primary-800">
                                        Vous
                                    </span>
                                    <?php endif; ?>

                                    <span class="text-[10px] text-slate-400 font-normal truncate">
                                        <?= $catLabel ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Badges stricts : Vert pour Cotisation complète, Rouge pour Non cotisé, Neutre pour En cours -->
                            <div class="shrink-0">
                                <?php if ($isSolde): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                                        <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        <span>Cotisation complète</span>
                                    </span>
                                <?php elseif ($isAucune): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-900">
                                        <svg class="w-3 h-3 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
                                        <span>Non cotisé</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                        En cours (<?= $progression ?>%)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Téléphone si renseigné -->
                        <?php if (!empty($m['telephone'])): ?>
                        <div class="mb-2">
                            <a href="tel:<?= e($m['telephone']) ?>" class="inline-flex items-center gap-1 text-[11px] text-slate-400 hover:text-slate-600">
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span><?= e($m['telephone']) ?></span>
                            </a>
                        </div>
                        <?php endif; ?>

                        <!-- Barre de progression sobre -->
                        <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 mb-2 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300 <?= $isSolde ? 'bg-emerald-500' : ($isAucune ? 'bg-transparent' : 'bg-slate-700 dark:bg-slate-300') ?>" 
                                 style="width: <?= $progression ?>%"></div>
                        </div>

                        <!-- Ligne financière claire & sobre -->
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100 dark:border-slate-700/60">
                            
                            <!-- Montant versé plafonné à l'objectif -->
                            <div class="text-slate-700 dark:text-slate-300 flex items-baseline gap-1">
                                <span class="text-slate-400 text-[11px]">Versé :</span>
                                <strong class="font-bold text-slate-900 dark:text-white"><?= $cotisationService->formatFcfa($payeAffiche) ?></strong>
                                <span class="text-[10px] text-slate-400">/ <?= $cotisationService->formatFcfa($objectif) ?></span>
                            </div>

                            <!-- Reste ou confirmation cotisation complète -->
                            <div>
                                <?php if ($isSolde): ?>
                                    <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                        Cotisation complète
                                    </span>
                                <?php elseif ($isAucune): ?>
                                    <span class="text-[11px] text-red-600 dark:text-red-400 font-semibold">
                                        Reste : <strong class="font-bold"><?= $cotisationService->formatFcfa($resteAffiche) ?></strong>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Reste : <strong class="font-semibold text-slate-800 dark:text-slate-200"><?= $cotisationService->formatFcfa($resteAffiche) ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- Liens d'action discrets (Respect strict de la confidentialité) -->
                        <?php if ($isCurrentUserAdmin || $isMe): ?>
                        <div class="flex items-center justify-between mt-2 pt-1.5 text-[11px]">
                            <a href="/pages/cotisation-membre.php?id=<?= $m['id'] ?>&campagne_id=<?= $campagneId ?>" 
                               class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-1">
                                <span><?= $isMe ? 'Voir mes reçus de versement' : 'Historique des versements' ?></span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            <?php if ($isCurrentUserAdmin): ?>
                            <button type="button" onclick="openPaiementModal(<?= $m['id'] ?>)" 
                                    class="text-primary-700 dark:text-blue-400 font-semibold hover:underline">
                                + Verser
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>



<!-- MODALE D'AJOUT DE VERSEMENT (ADMIN) -->
<?php if (isAdmin()): ?>
<div id="modal-paiement" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl p-5 sm:p-6 shadow-xl border border-slate-100 dark:border-slate-800 animate-in fade-in duration-200">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-4">
            <h3 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                <span>Enregistrer un Versement</span>
            </h3>
            <button onclick="closePaiementModal()" class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_paiement">

            <!-- Sélection du membre -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Membre *</label>
                <select name="user_id" id="modal-user-id" required 
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Choisir un membre --</option>
                    <?php foreach ($allUsersForSelect as $u): ?>
                    <option value="<?= $u['id'] ?>">
                        <?= e($u['nom']) ?> (<?= ucfirst($u['categorie_membre']) ?>) <?= !empty($u['telephone']) ? '· ' . e($u['telephone']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Montant -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Montant Versé (FCFA) *</label>
                <input type="number" name="montant" id="modal-montant" step="500" min="500" required placeholder="Ex : 5000"
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                
                <div class="flex gap-1.5 mt-2">
                    <button type="button" onclick="setMontant(1000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+1k</button>
                    <button type="button" onclick="setMontant(2000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+2k</button>
                    <button type="button" onclick="setMontant(5000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+5k</button>
                    <button type="button" onclick="setMontant(10000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+10k</button>
                    <button type="button" onclick="setMontant(30000)" class="px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200">+30k</button>
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
                        <option value="virement">Virement</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
            </div>

            <!-- Note -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Note (Optionnel)</label>
                <input type="text" name="commentaire" placeholder="Ex: Versement partiel, solde..."
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closePaiementModal()" class="flex-1 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">Annuler</button>
                <button type="submit" class="flex-1 bg-primary-900 text-white font-bold text-xs py-2.5 rounded-xl shadow-xs hover:bg-primary-800 active:scale-95 transition-all">
                    Confirmer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaiementModal(userId = null) {
    const modal = document.getElementById('modal-paiement');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        if (userId) {
            document.getElementById('modal-user-id').value = userId;
        }
    }
}

function closePaiementModal() {
    const modal = document.getElementById('modal-paiement');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function setMontant(val) {
    const inp = document.getElementById('modal-montant');
    if (inp) {
        const current = parseFloat(inp.value) || 0;
        inp.value = current + val;
    }
}
</script>
<?php endif; ?>

<!-- SCRIPT DE TRI ET FILTRE INTERACTIF PAR STATUT (ACCESSIBLE À CHACUN) -->
<script>
function setStatutFilter(statut) {
    const validStatuts = ['all', 'solde', 'en_cours', 'non_cotise'];
    if (!validStatuts.includes(statut)) {
        statut = 'all';
    }

    // 1. Mettre à jour l'input caché dans le formulaire de recherche
    const hiddenInput = document.getElementById('filter-statut-input');
    if (hiddenInput) {
        hiddenInput.value = statut;
    }

    // 2. Mettre à jour les styles des boutons d'onglets
    const tabs = {
        all: document.getElementById('btn-filter-all'),
        solde: document.getElementById('btn-filter-solde'),
        en_cours: document.getElementById('btn-filter-en_cours'),
        non_cotise: document.getElementById('btn-filter-non_cotise')
    };

    const badges = {
        all: document.getElementById('badge-count-all'),
        solde: document.getElementById('badge-count-solde'),
        en_cours: document.getElementById('badge-count-en_cours'),
        non_cotise: document.getElementById('badge-count-non_cotise')
    };

    // Réinitialiser les états inactifs
    if (tabs.all) {
        tabs.all.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600";
        tabs.all.setAttribute('aria-selected', 'false');
    }
    if (badges.all) {
        badges.all.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300";
    }

    if (tabs.solde) {
        tabs.solde.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-white dark:bg-slate-800 border-emerald-300 dark:border-emerald-800/60 text-slate-700 dark:text-slate-300 hover:border-emerald-400";
        tabs.solde.setAttribute('aria-selected', 'false');
    }
    if (badges.solde) {
        badges.solde.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300";
    }

    if (tabs.en_cours) {
        tabs.en_cours.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600";
        tabs.en_cours.setAttribute('aria-selected', 'false');
    }
    if (badges.en_cours) {
        badges.en_cours.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300";
    }

    if (tabs.non_cotise) {
        tabs.non_cotise.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-white dark:bg-slate-800 border-red-200 dark:border-red-900/60 text-slate-700 dark:text-slate-300 hover:border-red-400";
        tabs.non_cotise.setAttribute('aria-selected', 'false');
    }
    if (badges.non_cotise) {
        badges.non_cotise.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400";
    }

    // Appliquer le style actif sur le bouton sélectionné
    const activeTab = tabs[statut];
    const activeBadge = badges[statut];
    if (activeTab) {
        activeTab.setAttribute('aria-selected', 'true');
        if (statut === 'solde') {
            activeTab.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-emerald-600 text-white border-emerald-600 shadow-xs";
            if (activeBadge) activeBadge.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-white/20 text-white";
        } else if (statut === 'non_cotise') {
            activeTab.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-red-600 text-white border-red-600 shadow-xs";
            if (activeBadge) activeBadge.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-white/20 text-white";
        } else if (statut === 'en_cours') {
            activeTab.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-slate-800 text-white border-slate-800 shadow-xs dark:bg-slate-700 dark:border-slate-600";
            if (activeBadge) activeBadge.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-white/20 text-white";
        } else {
            activeTab.className = "statut-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold shrink-0 cursor-pointer transition-all bg-primary-900 text-white border-primary-900 shadow-xs dark:bg-primary-800 dark:border-primary-700";
            if (activeBadge) activeBadge.className = "text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-white/20 text-white";
        }
    }

    // 3. Filtrer les cartes dans la liste
    const cards = document.querySelectorAll('.member-card');
    let visibleCount = 0;
    cards.forEach(card => {
        const cardStatut = card.getAttribute('data-statut');
        if (statut === 'all' || cardStatut === statut) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // 4. Gestion de l'état vide
    const emptyMsg = document.getElementById('filter-empty-message');
    const labelSpan = document.getElementById('filter-empty-label');
    if (emptyMsg) {
        if (visibleCount === 0 && cards.length > 0) {
            emptyMsg.classList.remove('hidden');
            if (labelSpan) {
                const labels = {
                    solde: '"Cotisation complète"',
                    en_cours: '"En cours"',
                    non_cotise: '"Non cotisé"'
                };
                labelSpan.textContent = labels[statut] || '';
            }
        } else {
            emptyMsg.classList.add('hidden');
        }
    }

    // 5. Mise à jour de l'URL sans rechargement
    const url = new URL(window.location.href);
    if (statut === 'all') {
        url.searchParams.delete('statut');
    } else {
        url.searchParams.set('statut', statut);
    }
    window.history.replaceState({}, '', url.toString());
}

// Initialisation au chargement de la page selon l'URL
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const initialStatut = params.get('statut');
    if (initialStatut && ['solde', 'en_cours', 'non_cotise'].includes(initialStatut)) {
        setStatutFilter(initialStatut);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
