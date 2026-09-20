<?php
// admin/cotisations.php
$pageTitle = 'Gestion des Cotisations';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CotisationService;

$pdo = db();
$cotisationService = new CotisationService($pdo);

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

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $campagneId > 0) {
    $members = $cotisationService->getMembersWithSituation($campagneId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cotisations_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $currentCampagne['nom']) . '.csv');
    
    $output = fopen('php://output', 'w');
    // BOM UTF-8 for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Nom', 'Catégorie', 'Téléphone', 'Objectif FCFA', 'Total Payé FCFA', 'Reste à Payer FCFA', 'Avance FCFA', 'Progression %', 'Statut'], ';');

    foreach ($members as $m) {
        $sit = $m['situation'];
        fputcsv($output, [
            $m['nom'],
            ucfirst($m['categorie_membre'] ?? 'simple'),
            $m['telephone'] ?? '',
            $sit['objectif'],
            $sit['total_paye'],
            $sit['reste'],
            $sit['avance'],
            $sit['progression'] . '%',
            strtoupper(str_replace('_', ' ', $sit['statut']))
        ], ';');
    }
    fclose($output);
    exit;
}

$msg = '';
$err = '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // 1. Ajouter un versement
    if ($action === 'add_paiement') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $montant = (float)($_POST['montant'] ?? 0);
        $date = trim($_POST['date_paiement'] ?? date('Y-m-d'));
        $mode = trim($_POST['mode_paiement'] ?? 'especes');
        $note = trim($_POST['commentaire'] ?? '');

        if ($userId <= 0 || $montant <= 0) {
            $err = 'Veuillez sélectionner un membre et indiquer un montant supérieur à 0.';
        } else {
            try {
                $cotisationService->addPaiement($campagneId, $userId, $montant, $date, (int)$_SESSION['user_id'], $mode, $note);
                
                // Notification WebSocket
                addNotification(
                    $userId,
                    'Cotisation enregistrée',
                    "Un versement de {$cotisationService->formatFcfa($montant)} a été validé ({$currentCampagne['nom']}).",
                    '/pages/cotisations.php',
                    'commande'
                );

                $msg = 'Versement enregistré avec succès.';
            } catch (Throwable $e) {
                $err = 'Erreur : ' . $e->getMessage();
            }
        }
    }

    // 2. Changer la catégorie d'un membre
    if ($action === 'update_categorie') {
        $userId = (int)($_POST['member_user_id'] ?? 0);
        $newCat = trim($_POST['new_category'] ?? 'simple');
        if ($userId > 0) {
            $cotisationService->updateMemberCategory($userId, $newCat);
            $msg = 'Catégorie du membre mise à jour avec succès.';
        }
    }

    // 3. Créer une nouvelle campagne
    if ($action === 'create_campagne') {
        $nom = trim($_POST['campagne_nom'] ?? '');
        $debut = trim($_POST['campagne_debut'] ?? '');
        $fin = trim($_POST['campagne_fin'] ?? '');
        $desc = trim($_POST['campagne_desc'] ?? '');
        $active = isset($_POST['campagne_active']);

        if (empty($nom) || empty($debut) || empty($fin)) {
            $err = 'Le nom, la date de début et la date de fin sont obligatoires.';
        } else {
            try {
                $newId = $cotisationService->createCampagne($nom, $debut, $fin, $desc, $active);
                header("Location: /admin/cotisations.php?campagne_id={$newId}&msg=creee");
                exit;
            } catch (Throwable $e) {
                $err = 'Erreur : ' . $e->getMessage();
            }
        }
    }

    // 4. Supprimer un versement
    if ($action === 'delete_paiement') {
        $pId = (int)($_POST['paiement_id'] ?? 0);
        if ($pId > 0) {
            $cotisationService->deletePaiement($pId);
            $msg = 'Paiement supprimé.';
        }
    }
}

// Données
$stats = $campagneId > 0 ? $cotisationService->getGlobalStats($campagneId) : null;
$members = $campagneId > 0 ? $cotisationService->getMembersWithSituation($campagneId) : [];
$allUsers = $pdo->query("SELECT id, nom, telephone, email, categorie_membre FROM utilisateurs WHERE statut_adhesion = 'membre' OR role = 'admin' ORDER BY nom ASC")->fetchAll();

// Dernières transactions
$stmtRecent = $pdo->prepare("
    SELECT p.*, u.nom AS membre_nom, u.categorie_membre, a.nom AS admin_nom
    FROM cotisation_paiements p
    JOIN utilisateurs u ON u.id = p.user_id
    LEFT JOIN utilisateurs a ON a.id = p.admin_id
    WHERE p.campagne_id = ?
    ORDER BY p.date_paiement DESC, p.id DESC
    LIMIT 15
");
$stmtRecent->execute([$campagneId]);
$recentPaiements = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- TOP BAR ADMIN -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4 shadow-md" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 active:scale-95 transition-transform">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="font-bold text-sm">Gestion des Cotisations</h1>
            <p class="text-[11px] opacity-75 truncate"><?= e($currentCampagne['nom'] ?? 'Campagnes') ?></p>
        </div>
        
        <!-- Sélecteur de campagne -->
        <?php if (!empty($campagnes)): ?>
        <form method="GET" class="shrink-0">
            <select name="campagne_id" onchange="this.form.submit()" 
                    class="bg-white/15 border border-white/20 text-white text-xs font-semibold rounded-xl px-2.5 py-1.5 focus:outline-none cursor-pointer">
                <?php foreach ($campagnes as $c): ?>
                <option value="<?= $c['id'] ?>" class="text-slate-900" <?= $c['id'] == $campagneId ? 'selected' : '' ?>>
                    <?= e($c['nom']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <a href="?export=csv&campagne_id=<?= $campagneId ?>" 
           class="w-8 h-8 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center text-xs font-bold shadow transition-colors" 
           title="Exporter en CSV">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        </a>
    </div>
</div>

<main class="page-content px-4 py-5 max-w-4xl mx-auto pb-32">

    <?php if ($msg || isset($_GET['msg'])): ?>
    <div class="flash-success-box mb-4 p-3.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 rounded-2xl text-sm font-medium flex items-center justify-between gap-2.5">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span><?= e($msg ?: 'Opération effectuée avec succès.') ?></span>
        </div>
        <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 p-1 transition-colors" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($err): ?>
    <div class="mb-4 p-3.5 bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300 rounded-2xl text-sm font-medium flex items-center gap-2.5">
        <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
        <span><?= e($err) ?></span>
    </div>
    <?php endif; ?>

    <!-- STATS SYNTHÉTIQUES -->
    <?php if ($stats): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm">
            <span class="text-xs text-slate-400 block mb-1">Total Attendu</span>
            <p class="text-xl font-bold text-slate-900 dark:text-white"><?= number_format($stats['montant_attendu'], 0, ',', ' ') ?> <span class="text-xs font-normal">FCFA</span></p>
        </div>
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm">
            <span class="text-xs text-emerald-600 dark:text-emerald-400 block mb-1">Total Encaissé</span>
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($stats['montant_collecte'], 0, ',', ' ') ?> <span class="text-xs font-normal">FCFA</span></p>
        </div>
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm">
            <span class="text-xs text-amber-600 dark:text-amber-400 block mb-1">Solde Restant</span>
            <p class="text-xl font-bold text-amber-600 dark:text-amber-400"><?= number_format($stats['montant_restant'], 0, ',', ' ') ?> <span class="text-xs font-normal">FCFA</span></p>
        </div>
        <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm">
            <span class="text-xs text-primary-900 dark:text-blue-400 block mb-1">Taux Global</span>
            <p class="text-xl font-bold text-primary-900 dark:text-blue-400"><?= $stats['taux_global'] ?>%</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ONGLETS DE GESTION -->
    <div class="grid md:grid-cols-2 gap-4 mb-6">

        <!-- 1. FORMULAIRE D'AJOUT D'UN VERSEMENT -->
        <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm">
            <h2 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-gold-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Enregistrer une Cotisation</span>
            </h2>
            <form method="POST" class="space-y-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_paiement">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Membre concerné *</label>
                    <select name="user_id" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100">
                        <option value="">-- Choisir un membre --</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= e($u['nom']) ?> (<?= ucfirst($u['categorie_membre']) ?>) <?= !empty($u['telephone']) ? '· ' . e($u['telephone']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Montant versé (FCFA) *</label>
                    <input type="number" name="montant" step="500" min="500" required placeholder="Ex: 5000"
                           class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Date *</label>
                        <input type="date" name="date_paiement" value="<?= date('Y-m-d') ?>" required
                               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mode *</label>
                        <select name="mode_paiement" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100">
                            <option value="especes">Espèces</option>
                            <option value="wave">Wave</option>
                            <option value="orange_money">Orange Money</option>
                            <option value="virement">Virement bancaire</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Note / Commentaire</label>
                    <input type="text" name="commentaire" placeholder="Ex: Acompte, remise directe..."
                           class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100">
                </div>

                <button type="submit" class="w-full bg-primary-900 hover:bg-primary-800 text-white font-bold text-xs py-2.5 rounded-xl shadow transition-all">
                    Enregistrer le paiement
                </button>
            </form>
        </div>

        <!-- 2. GESTION DES CATÉGORIES ET CAMPAGNES -->
        <div class="space-y-4">
            <!-- Modifier la catégorie d'un membre -->
            <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm">
                <h2 class="font-bold text-sm text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Modifier la Catégorie d'un Membre</span>
                </h2>
                <p class="text-[11px] text-slate-400 mb-3">Fixe l'objectif annuel (Bureau: 35 000 FCFA, Simple: 30 000 FCFA, Enfant: 15 000 FCFA).</p>

                <form method="POST" class="space-y-2.5">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_categorie">

                    <select name="member_user_id" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100">
                        <option value="">-- Choisir un membre --</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= e($u['nom']) ?> (Actuel : <?= ucfirst($u['categorie_membre']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="flex gap-2">
                        <select name="new_category" class="flex-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 dark:text-slate-100">
                            <option value="bureau">Membre du Bureau</option>
                            <option value="simple" selected>Membre Simple</option>
                            <option value="enfant">Enfant</option>
                        </select>
                        <button type="submit" class="bg-gold-500 hover:bg-gold-400 text-primary-950 font-bold text-xs px-4 py-2 rounded-xl shadow">
                            Modifier
                        </button>
                    </div>
                </form>
            </div>

            <!-- Créer une nouvelle campagne annuelle -->
            <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm">
                <h2 class="font-bold text-sm text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Créer une Nouvelle Campagne Annuelle</span>
                </h2>
                <form method="POST" class="space-y-2.5">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_campagne">

                    <div>
                        <input type="text" name="campagne_nom" required placeholder="Ex: Cotisation 2027–2028"
                               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-100">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="campagne_debut" required value="<?= date('Y-m-d') ?>"
                               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-800 dark:text-slate-100">
                        <input type="date" name="campagne_fin" required value="<?= date('Y-m-d', strtotime('+1 year')) ?>"
                               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-800 dark:text-slate-100">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="campagne_active" id="c-active" checked class="rounded text-primary-900">
                        <label for="c-active" class="text-xs text-slate-600 dark:text-slate-300">Activer comme campagne courante</label>
                    </div>

                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs py-2 rounded-xl shadow">
                        Créer la campagne
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- DERNIÈRES TRANSACTIONS -->
    <div class="bg-white dark:bg-slate-800 p-5 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Dernières Transactions Enregistrées</span>
                <span class="text-xs font-normal text-slate-400">(<?= count($recentPaiements) ?>)</span>
            </h2>
            <a href="/pages/cotisations.php?campagne_id=<?= $campagneId ?>" class="text-xs text-primary-900 dark:text-blue-400 font-semibold hover:underline">
                Voir vue membre →
            </a>
        </div>

        <?php if (empty($recentPaiements)): ?>
        <p class="text-xs text-slate-400 text-center py-6">Aucun versement enregistré pour cette campagne.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700 text-slate-400 uppercase text-[10px]">
                        <th class="py-2 px-2">Date</th>
                        <th class="py-2 px-2">Membre</th>
                        <th class="py-2 px-2">Montant</th>
                        <th class="py-2 px-2">Mode</th>
                        <th class="py-2 px-2">Note / Référence</th>
                        <th class="py-2 px-2">Enregistré par</th>
                        <th class="py-2 px-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <?php foreach ($recentPaiements as $p): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                        <td class="py-2.5 px-2 font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            <?= date('d/m/Y', strtotime($p['date_paiement'])) ?>
                        </td>
                        <td class="py-2.5 px-2 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                            <a href="/pages/cotisation-membre.php?id=<?= $p['user_id'] ?>&campagne_id=<?= $campagneId ?>" class="hover:underline">
                                <?= e($p['membre_nom']) ?>
                            </a>
                            <span class="text-[10px] text-slate-400 block font-normal capitalize"><?= e($p['categorie_membre']) ?></span>
                        </td>
                        <td class="py-2.5 px-2 font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                            <?= $cotisationService->formatFcfa($p['montant']) ?>
                        </td>
                        <td class="py-2.5 px-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
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
                        <td class="py-2.5 px-2 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                            <?= !empty($p['commentaire']) ? e($p['commentaire']) : '<span class="text-slate-400 italic">Aucune note</span>' ?>
                        </td>
                        <td class="py-2.5 px-2 whitespace-nowrap">
                            <?php if (!empty($p['admin_id'])): ?>
                                <span class="text-slate-600 dark:text-slate-300"><?= e($p['admin_nom'] ?: 'Admin') ?></span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/30 px-2 py-0.5 rounded border border-sky-200/50 dark:border-sky-800/50">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                    Wave (Membre)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2.5 px-2 text-right whitespace-nowrap">
                            <form method="POST" onsubmit="return confirm('Supprimer ce versement ?')" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_paiement">
                                <input type="hidden" name="paiement_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 p-1" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
