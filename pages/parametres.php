<?php
// pages/parametres.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Paramètres';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/Services/CotisationService.php';

$pdo = db();
$userSession = getCurrentUser();
$user = null;
$memberSituation = null;
$cotisationService = null;
$activeCampagne = null;
$nbCommandes = 0;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Traitement POST si l'utilisateur est connecté
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $userId = (int)$_SESSION['user_id'];

    if ($action === 'update_profile') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');

        if (!empty($nom)) {
            // Upload photo si fournie
            $photoFileName = null;
            if (isset($_FILES['photo_membre']) && $_FILES['photo_membre']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['photo_membre']['tmp_name'];
                $name = basename($_FILES['photo_membre']['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $dir = __DIR__ . '/../assets/uploads/membres/';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $photoFileName = 'membre_' . $userId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($tmp, $dir . $photoFileName)) {
                        $pdo->prepare("UPDATE utilisateurs SET photo_membre = ? WHERE id = ?")->execute([$photoFileName, $userId]);
                    }
                }
            }

            $upd = $pdo->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, telephone = ?, adresse = ? WHERE id = ?");
            $upd->execute([$nom, $prenom ?: null, $telephone ?: null, $adresse ?: null, $userId]);

            $_SESSION['user_nom'] = $nom;
            $_SESSION['flash_success'] = 'Vos informations ont été mises à jour avec succès.';
            header('Location: /pages/parametres.php');
            exit;
        } else {
            $_SESSION['flash_error'] = 'Le nom de famille est obligatoire.';
            header('Location: /pages/parametres.php');
            exit;
        }
    }

    if ($action === 'change_password') {
        $ancienPassword = $_POST['ancien_password'] ?? '';
        $nouveauPassword = $_POST['nouveau_password'] ?? '';
        $confirmationPassword = $_POST['confirmation_password'] ?? '';

        $stmtPwd = $pdo->prepare("SELECT password FROM utilisateurs WHERE id = ?");
        $stmtPwd->execute([$userId]);
        $currentHash = $stmtPwd->fetchColumn();

        if (!password_verify($ancienPassword, $currentHash ?: '')) {
            $_SESSION['flash_error'] = 'Le mot de passe actuel est incorrect.';
            header('Location: /pages/parametres.php');
            exit;
        }

        if (strlen($nouveauPassword) < 6) {
            $_SESSION['flash_error'] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            header('Location: /pages/parametres.php');
            exit;
        }

        if ($nouveauPassword !== $confirmationPassword) {
            $_SESSION['flash_error'] = 'Les deux nouveaux mots de passe ne correspondent pas.';
            header('Location: /pages/parametres.php');
            exit;
        }

        $hash = password_hash($nouveauPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        $_SESSION['flash_success'] = 'Votre mot de passe a été modifié avec succès.';
        header('Location: /pages/parametres.php');
        exit;
    }
}

// Chargement complet du profil utilisateur
if ($userSession) {
    $stmtUser = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmtUser->execute([$userSession['id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (isMembre()) {
            $cotisationService = new \App\Services\CotisationService($pdo);
            $activeCampagne = $cotisationService->getActiveCampagne();
            if ($activeCampagne) {
                $detail = $cotisationService->getMemberDetail((int)$user['id'], (int)$activeCampagne['id']);
                $memberSituation = $detail['situation'] ?? null;
            }

            $cotisationObj = $memberSituation ? (float)$memberSituation['objectif'] : ($cotisationService ? $cotisationService->getObjectif($user['categorie_membre'] ?? 'simple') : 30000.0);
            $cotisationPaye = $memberSituation ? (float)$memberSituation['total_paye'] : 0.0;
            $cotisationAffichee = min($cotisationPaye, $cotisationObj);
            $cotisationIsSolde = ($cotisationPaye >= $cotisationObj && $cotisationObj > 0);
            $cotisationIsAucune = ($cotisationPaye <= 0);
            $cotisationProgression = $cotisationObj > 0 ? min(100.0, round(($cotisationPaye / $cotisationObj) * 100, 1)) : 100.0;
        }

        $stmtOrd = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE user_id = ?");
        $stmtOrd->execute([$user['id']]);
        $nbCommandes = (int)$stmtOrd->fetchColumn();
    }
}

$darkMode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';

// Formatage photo & identité
$userPhoto = null;
if (!empty($user['photo_membre']) && file_exists(__DIR__ . '/../assets/uploads/membres/' . $user['photo_membre'])) {
    $userPhoto = '/assets/uploads/membres/' . $user['photo_membre'];
} elseif (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . ltrim($user['avatar'], '/'))) {
    $userPhoto = '/' . ltrim($user['avatar'], '/');
}

$nomComplet = $user ? trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) : 'Visiteur';
if (empty($nomComplet) && $user) {
    $nomComplet = $user['nom'];
}
$initiale = strtoupper(substr($nomComplet ?: 'U', 0, 1));

// Variables pour carte officielle
$rawNom = trim(preg_replace('/\s*\((?:enfant|bureau|simple)\)/i', '', $user['nom'] ?? ''));
$rawPrenom = trim(preg_replace('/\s*\((?:enfant|bureau|simple)\)/i', '', $user['prenom'] ?? ''));

if (!empty($rawPrenom)) {
    $nomCarte = mb_strtoupper($rawNom, 'UTF-8');
    $prenomCarte = mb_strtoupper($rawPrenom, 'UTF-8');
} else {
    $parts = preg_split('/\s+/', $rawNom);
    if (count($parts) >= 2) {
        $nomPart = array_pop($parts);
        $prenomPart = implode(' ', $parts);
        $nomCarte = mb_strtoupper($nomPart, 'UTF-8');
        $prenomCarte = mb_strtoupper($prenomPart, 'UTF-8');
    } else {
        $nomCarte = mb_strtoupper($rawNom, 'UTF-8');
        $prenomCarte = '-';
    }
}

// Fonction gérée par l'administrateur (jamais enfant, bureau ou simple)
$fnc = trim($user['fonction'] ?? '');
if (empty($fnc) || in_array(strtolower($fnc), ['enfant', 'bureau', 'simple', 'membre simple', 'membre bureau'])) {
    $fonctionCarte = 'Membre';
} else {
    $fonctionCarte = $fnc;
}
$telephoneCarte = !empty($user['telephone']) ? $user['telephone'] : '-';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- TOP BAR MODERNE AVEC BLUR -->
<div class="sticky top-0 z-40 bg-white/85 dark:bg-slate-900/85 backdrop-blur-md border-b border-slate-100 dark:border-slate-800 px-4 transition-colors" style="height: calc(3.5rem + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px);">
    <div class="flex items-center justify-between h-14 max-w-2xl mx-auto">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-primary-900/10 dark:bg-primary-500/20 text-primary-900 dark:text-blue-400 flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white leading-tight">Paramètres</h1>
                <p class="text-[10px] text-slate-400 leading-none">Compte & Préférences</p>
            </div>
        </div>

        <?php if ($user && $user['role'] === 'admin'): ?>
        <a href="/admin/dashboard.php" class="inline-flex items-center gap-1 text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 px-2.5 py-1 rounded-full hover:bg-amber-500/20 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>Admin</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<main class="page-content pb-32 max-w-2xl mx-auto px-4 pt-3">

    <!-- ALERTES FLASH AUTO-DISMISSIBLES -->
    <?php if ($flashSuccess): ?>
    <div class="flash-success-box mb-4 p-3.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 rounded-2xl text-sm flex items-center justify-between gap-2.5 shadow-xs fade-in-up">
        <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="font-medium text-xs sm:text-sm"><?= e($flashSuccess) ?></span>
        </div>
        <button type="button" onclick="dismissFlash(this.closest('.flash-success-box'))" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 p-1 transition-colors" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
    <div class="flash-error-box mb-4 p-3.5 bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300 rounded-2xl text-sm flex items-center justify-between gap-2.5 shadow-xs fade-in-up">
        <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span class="font-medium text-xs sm:text-sm"><?= e($flashError) ?></span>
        </div>
        <button type="button" onclick="this.closest('.flash-error-box').remove()" class="text-red-500 hover:text-red-700 dark:hover:text-red-200 p-1 transition-colors" title="Fermer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endif; ?>

    <!-- HERO PROFIL SHOWCASE -->
    <?php if ($user): ?>
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-primary-950 to-primary-900 border border-white/10 shadow-xl p-5 sm:p-6 text-white mb-4">
        <!-- Effets d'ambiance lumineux d'arrière-plan -->
        <div class="pointer-events-none absolute -right-12 -top-12 w-48 h-48 bg-primary-500/20 rounded-full blur-3xl"></div>
        <div class="pointer-events-none absolute -left-12 -bottom-12 w-48 h-48 bg-amber-500/15 rounded-full blur-3xl"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <!-- Avatar & Infos Utilisateur -->
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="relative group shrink-0">
                    <?php if ($userPhoto): ?>
                        <img src="<?= e($userPhoto) ?>" alt="<?= e($nomComplet) ?>" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover ring-2 ring-amber-400/70 shadow-lg"
                             onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-amber-500 via-primary-700 to-primary-900 ring-2 ring-white/20 items-center justify-center font-bold text-2xl text-white shadow-lg" style="display:none;">
                            <?= $initiale ?>
                        </div>
                    <?php else: ?>
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-amber-500 via-primary-700 to-primary-900 ring-2 ring-white/20 flex items-center justify-center font-bold text-2xl text-white shadow-lg">
                            <?= $initiale ?>
                        </div>
                    <?php endif; ?>
                    <button type="button" onclick="openModal('modal-profil')" class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-amber-500 text-slate-950 hover:bg-amber-400 flex items-center justify-center shadow-md transition-transform active:scale-95" title="Modifier la photo">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                        <h2 class="text-base sm:text-lg font-bold text-white tracking-tight truncate"><?= e($nomComplet) ?></h2>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-amber-500/25 border border-amber-500/40 text-amber-300 px-2 py-0.5 rounded-full">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Admin
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="text-xs text-slate-300/90 flex items-center gap-1.5 truncate">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span class="truncate"><?= e($user['email']) ?></span>
                    </p>

                    <?php if (!empty($user['telephone'])): ?>
                    <p class="text-xs text-slate-300/90 flex items-center gap-1.5 mt-0.5 truncate">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <span><?= e($user['telephone']) ?></span>
                    </p>
                    <?php endif; ?>

                    <!-- Badges d'état & Catégorie -->
                    <div class="flex flex-wrap items-center gap-1.5 mt-2.5">
                        <!-- Statut Adhésion -->
                        <?php if (($user['statut_adhesion'] ?? '') === 'membre'): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 px-2.5 py-0.5 rounded-full">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Membre Officiel
                            </span>
                            <?php if (!empty($user['carte_physique'])): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 px-2 py-0.5 rounded-full">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="13" x="3" y="5" rx="2"/><path d="M7 15h4M7 11h2"/></svg>
                                Carte physique <?= !empty($user['numero_carte']) ? '#' . e($user['numero_carte']) : '' ?>
                            </span>
                            <?php endif; ?>
                        <?php elseif (($user['statut_adhesion'] ?? '') === 'en_attente'): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold bg-amber-500/20 border border-amber-500/30 text-amber-300 px-2 py-0.5 rounded-full">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/></svg>
                                Validation en cours
                            </span>
                        <?php else: ?>
                            <a href="/pages/accueil.php#adhesion" class="inline-flex items-center gap-1 text-[10px] font-semibold bg-primary-500/30 border border-primary-400/40 text-primary-200 hover:bg-primary-500/40 px-2 py-0.5 rounded-full transition-colors">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                Adhérer au Dahira
                            </a>
                        <?php endif; ?>

                        <!-- Catégorie -->
                        <span class="text-[10px] bg-white/10 text-slate-300 px-2 py-0.5 rounded-full font-medium">
                            <?= ucfirst($user['categorie_membre'] ?? 'simple') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions Rapides Profil -->
            <div class="flex items-center gap-2 pt-2 sm:pt-0 border-t sm:border-t-0 border-white/10">
                <button type="button" onclick="openModal('modal-profil')" class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white/15 hover:bg-white/20 active:scale-95 text-xs font-semibold text-white transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Modifier</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MINI DASHBOARD -->
    <div class="grid <?= isMembre() ? 'grid-cols-3' : 'grid-cols-2' ?> gap-2 sm:gap-3 mb-5">
        <?php if (isMembre()): ?>
        <!-- Card 1: Cotisations -->
        <a href="/pages/cotisations.php" class="bg-white dark:bg-slate-800 rounded-2xl p-3 border border-slate-100 dark:border-slate-700/80 shadow-xs hover:border-primary-400 dark:hover:border-primary-500 transition-all flex flex-col justify-between active:scale-[0.98]">
            <div class="flex items-center justify-between text-slate-400 dark:text-slate-400">
                <span class="text-[11px] font-semibold tracking-wide">Cotisation</span>
                <svg class="w-3.5 h-3.5 text-primary-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mt-2">
                <?php if ($cotisationIsAucune): ?>
                    <p class="text-xs sm:text-sm font-extrabold text-red-600 dark:text-red-400 leading-tight">Non cotisé</p>
                    <p class="text-[10px] text-red-500 font-semibold truncate">0 / <?= $cotisationService ? $cotisationService->formatFcfa($cotisationObj) : '30 000 FCFA' ?></p>
                <?php elseif ($cotisationIsSolde): ?>
                    <p class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white leading-tight truncate"><?= $cotisationService ? $cotisationService->formatFcfa($cotisationAffichee) : '' ?></p>
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold truncate">Cotisation complète (100%)</p>
                <?php else: ?>
                    <p class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white leading-tight truncate"><?= $cotisationService ? $cotisationService->formatFcfa($cotisationAffichee) : '' ?></p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold truncate"><?= $cotisationProgression ?>% atteint</p>
                <?php endif; ?>
            </div>
        </a>
        <?php endif; ?>

        <!-- Card 2: Commandes -->
        <a href="/pages/mes_commandes.php" class="bg-white dark:bg-slate-800 rounded-2xl p-3 border border-slate-100 dark:border-slate-700/80 shadow-xs hover:border-amber-400 dark:hover:border-amber-500 transition-all flex flex-col justify-between active:scale-[0.98]">
            <div class="flex items-center justify-between text-slate-400 dark:text-slate-400">
                <span class="text-[11px] font-semibold tracking-wide">Commandes</span>
                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <div class="mt-2">
                <p class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white leading-tight"><?= $nbCommandes ?> achat<?= $nbCommandes > 1 ? 's' : '' ?></p>
                <p class="text-[10px] text-slate-400">Boutique AKR</p>
            </div>
        </a>

        <!-- Card 3: Adhésion -->
        <a href="<?= ($user['statut_adhesion'] ?? '') === 'membre' ? '#carte' : '/pages/accueil.php#adhesion' ?>"
           <?= ($user['statut_adhesion'] ?? '') === 'membre' ? 'onclick="openCarteModal(); return false;"' : '' ?>
           class="bg-white dark:bg-slate-800 rounded-2xl p-3 border border-slate-100 dark:border-slate-700/80 shadow-xs hover:border-emerald-400 dark:hover:border-emerald-500 transition-all flex flex-col justify-between active:scale-[0.98]">
            <div class="flex items-center justify-between text-slate-400 dark:text-slate-400">
                <span class="text-[11px] font-semibold tracking-wide">Adhésion</span>
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mt-2">
                <?php if (($user['statut_adhesion'] ?? '') === 'membre'): ?>
                    <p class="text-xs sm:text-sm font-extrabold text-emerald-600 dark:text-emerald-400 leading-tight">Validée</p>
                    <p class="text-[10px] text-slate-400">Carte HD prête</p>
                <?php elseif (($user['statut_adhesion'] ?? '') === 'en_attente'): ?>
                    <p class="text-xs sm:text-sm font-extrabold text-amber-600 dark:text-amber-400 leading-tight">En attente</p>
                    <p class="text-[10px] text-slate-400">Validation admin</p>
                <?php else: ?>
                    <p class="text-xs sm:text-sm font-extrabold text-primary-600 dark:text-primary-400 leading-tight">Adhérer</p>
                    <p class="text-[10px] text-slate-400">Devenir membre</p>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php else: ?>
    <!-- HERO INVITÉ NON CONNECTÉ -->
    <div class="rounded-3xl bg-gradient-to-br from-slate-900 via-primary-950 to-primary-900 border border-white/10 p-5 sm:p-6 text-white text-center shadow-lg mb-5">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-white/10 flex items-center justify-center text-amber-400 mb-3">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <h2 class="text-lg font-bold">Bienvenue sur Dahira AKR</h2>
        <p class="text-xs text-slate-300 mt-1 max-w-sm mx-auto">Connectez-vous pour accéder à votre espace membre, vos cotisations et votre carte officielle.</p>
        <div class="flex items-center justify-center gap-2.5 mt-4">
            <a href="/public/login.php" class="px-4 py-2 rounded-xl bg-white text-primary-900 hover:bg-slate-100 font-bold text-xs shadow-sm transition-all active:scale-95">Se connecter</a>
            <a href="/public/register.php" class="px-4 py-2 rounded-xl bg-white/15 text-white hover:bg-white/20 font-bold text-xs border border-white/20 transition-all active:scale-95">Créer un compte</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTIONS DE PARAMÈTRES GROUPÉES -->
    <div class="space-y-4">

        <!-- GROUPE 1: MON DAHIRA & ACTIVITÉS -->
        <div>
            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider px-2 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Mon Dahira & Activités
            </p>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/80 shadow-xs divide-y divide-slate-100 dark:divide-slate-700/70 overflow-hidden">
                
                <!-- Carte de Membre ou Adhésion -->
                <?php if ($user && ($user['statut_adhesion'] ?? '') === 'membre'): ?>
                <button type="button" onclick="openCarteModal()" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="13" x="3" y="5" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 15h4M7 11h2M15 11h2m-2 4h2"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Ma Carte de Membre Officielle</p>
                            <p class="text-[11px] text-slate-400">Visualiser, imprimer ou télécharger (PDF HD)</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full border border-blue-200/60 dark:border-blue-800/40">Format HD</span>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </button>
                <?php else: ?>
                <a href="/pages/accueil.php#adhesion" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Adhésion au Dahira</p>
                            <p class="text-[11px] text-slate-400">Nouvelle carte ou carte physique existante</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full border border-emerald-200/70 dark:border-emerald-800/50">Carte physique</span>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Cotisations Annuelles -->
                <?php if (isMembre()): ?>
                <a href="/pages/cotisations.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Mes Cotisations Annuelles</p>
                            <p class="text-[11px] text-slate-400">Objectif : <?= $cotisationService ? $cotisationService->formatFcfa($cotisationService->getObjectif($user['categorie_membre'] ?? 'simple')) : '30 000 FCFA' ?> / an</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($cotisationIsAucune): ?>
                            <span class="text-[11px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 px-2.5 py-0.5 rounded-full border border-red-200 dark:border-red-900/50">Non cotisé</span>
                        <?php elseif ($cotisationIsSolde): ?>
                            <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 px-2.5 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">Cotisation complète</span>
                        <?php else: ?>
                            <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-2.5 py-0.5 rounded-full border border-slate-200 dark:border-slate-600">En cours (<?= $cotisationProgression ?>%)</span>
                        <?php endif; ?>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Mes Commandes -->
                <a href="/pages/mes_commandes.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Mes Commandes Boutique</p>
                            <p class="text-[11px] text-slate-400">Historique des achats et livraisons</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($nbCommandes > 0): ?>
                        <span class="text-[11px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-full border border-amber-200/60 dark:border-amber-800/40"><?= $nbCommandes ?></span>
                        <?php endif; ?>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </a>

            </div>
        </div>

        <!-- GROUPE 2: APPARENCE & INTERFACE -->
        <div>
            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider px-2 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                Apparence & Affichage
            </p>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/80 shadow-xs divide-y divide-slate-100 dark:divide-slate-700/70 overflow-hidden">
                
                <!-- Toggle Mode Sombre -->
                <div class="px-4 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Mode sombre</p>
                            <p class="text-[11px] text-slate-400">Thème sombre reposant pour la lecture nocturne</p>
                        </div>
                    </div>
                    <button type="button" onclick="handleDarkToggle(this)" id="dark-toggle"
                            class="relative inline-flex items-center w-12 h-7 rounded-full p-0.5 transition-colors duration-300 focus:outline-none <?= $darkMode ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600' ?>"
                            role="switch" aria-checked="<?= $darkMode ? 'true' : 'false' ?>">
                        <span class="pointer-events-none flex items-center justify-center w-6 h-6 rounded-full bg-white shadow-md transform transition-transform duration-300 <?= $darkMode ? 'translate-x-5 text-primary-900' : 'translate-x-0 text-amber-500' ?>">
                            <?php if ($darkMode): ?>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                            <?php else: ?>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <?php endif; ?>
                        </span>
                    </button>
                </div>

            </div>
        </div>

        <!-- GROUPE 3: PRIÈRES & APPLICATION -->
        <div>
            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider px-2 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                Prières & Application
            </p>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/80 shadow-xs divide-y divide-slate-100 dark:divide-slate-700/70 overflow-hidden">
                
                <!-- Toggle Notifications Prières -->
                <div class="px-4 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Notifications des Prières</p>
                            <p class="text-[11px] text-slate-400">Rappels aux heures de prière et Adhan sonore</p>
                        </div>
                    </div>
                    <button type="button" onclick="togglePrieresNotif(this)" id="prieres-toggle"
                            class="relative inline-flex items-center w-12 h-7 rounded-full p-0.5 transition-colors duration-300 focus:outline-none <?= isset($_COOKIE['notif_prieres_active']) && $_COOKIE['notif_prieres_active'] === '1' ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600' ?>"
                            role="switch" aria-checked="<?= isset($_COOKIE['notif_prieres_active']) && $_COOKIE['notif_prieres_active'] === '1' ? 'true' : 'false' ?>">
                        <span class="pointer-events-none block w-6 h-6 rounded-full bg-white shadow-md transform transition-transform duration-300 <?= isset($_COOKIE['notif_prieres_active']) && $_COOKIE['notif_prieres_active'] === '1' ? 'translate-x-5' : 'translate-x-0' ?>"></span>
                    </button>
                </div>

                <!-- Tester l'Adhan -->
                <button type="button" onclick="testAdhan()" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-primary-50 dark:bg-primary-950/50 text-primary-900 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Tester l'Adhan</p>
                            <p class="text-[11px] text-slate-400">Écouter la voix de l'Adhan et tester la notification</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-primary-50 dark:bg-primary-900/30 text-primary-900 dark:text-blue-400 text-xs font-bold hover:bg-primary-100 transition-colors">
                        <svg class="w-3 h-3 animate-pulse" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Écouter
                    </span>
                </button>

                <!-- Bouton Installation PWA -->
                <button id="pwa-install-btn" onclick="installPWA()" style="display:none;" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Installer l'Application</p>
                            <p class="text-[11px] text-slate-400">Ajouter à l'écran d'accueil pour un accès rapide</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <!-- Version -->
                <div class="px-4 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Version du Dahira</p>
                            <p class="text-[11px] text-slate-400">Dahira AKR v2.1.0 • PWA Ready</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">À jour</span>
                </div>

            </div>
        </div>

        <!-- GROUPE 4: ESPACE ADMINISTRATEUR (si Admin) -->
        <?php if ($user && $user['role'] === 'admin'): ?>
        <div>
            <p class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider px-2 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Administration Dahira
            </p>
            <div class="bg-gradient-to-r from-amber-500/5 via-amber-500/10 to-transparent dark:from-amber-950/20 rounded-2xl border border-amber-300/70 dark:border-amber-700/50 shadow-xs divide-y divide-amber-200/50 dark:divide-amber-800/40 overflow-hidden">
                
                <a href="/admin/dashboard.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-amber-500/10 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0 font-bold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Tableau de Bord Général</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Validations, adhésions en attente & finances</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>

                <a href="/admin/cotisations.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-amber-500/10 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Gestion des Cotisations</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Enregistrer les versements et bilans financiers</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>

                <a href="/admin/utilisateurs.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-amber-500/10 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Membres & Cartes Physiques</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Valider les adhésions et attribuer les rôles</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>

            </div>
        </div>
        <?php endif; ?>

        <!-- GROUPE 5: SÉCURITÉ & COMPTE -->
        <div>
            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider px-2 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Compte & Sécurité
            </p>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/80 shadow-xs divide-y divide-slate-100 dark:divide-slate-700/70 overflow-hidden">
                
                <?php if ($user): ?>
                <button type="button" onclick="openModal('modal-profil')" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Modifier mes Coordonnées</p>
                            <p class="text-[11px] text-slate-400">Nom, prénom, téléphone, adresse postale et photo</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <button type="button" onclick="openModal('modal-password')" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Changer de Mot de Passe</p>
                            <p class="text-[11px] text-slate-400">Mettre à jour vos identifiants de connexion</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <a href="/public/logout.php" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?');" class="w-full px-4 py-3.5 flex items-center justify-between text-left text-red-600 dark:text-red-400 hover:bg-red-50/60 dark:hover:bg-red-950/20 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold">Se déconnecter</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <?php else: ?>
                <a href="/public/login.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Se connecter</p>
                            <p class="text-[11px] text-slate-400">Accéder à votre compte existant</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>

                <a href="/public/register.php" class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition-colors active:scale-[0.99]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Créer un nouveau compte</p>
                            <p class="text-[11px] text-slate-400">Rejoindre la communauté Dahira AKR</p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- GROUPE 6: À PROPOS & ASSISTANCE -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700/80 p-5 text-center shadow-xs">
            <p class="arabic text-3xl text-primary-900 dark:text-blue-400 font-bold mb-1 tracking-wide">الطريقة التجانية</p>
            <p class="text-xs font-bold text-slate-800 dark:text-slate-200 tracking-tight">Dahira A Khiba-i Rassouloulahi (AKR)</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Application officielle • Foi, Solidarité & Spiritualité</p>

            <!-- Boutons Support & Contact -->
            <div class="flex items-center justify-center gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                <a href="https://wa.me/221780196650?text=Assalamou%20Aleykoum%2C%20je%20contacte%20le%20Dahira%20AKR" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold hover:bg-emerald-100 transition-colors">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.971.531 1.771.82 2.796.82 3.18 0 5.767-2.587 5.767-5.766.001-3.182-2.585-5.806-5.767-5.806zm0 10.375c-.933 0-1.748-.277-2.483-.715l-.178-.106-1.597.419.426-1.558-.117-.186c-.482-.767-.738-1.528-.737-2.463 0-2.532 2.059-4.593 4.593-4.593 2.534 0 4.594 2.061 4.594 4.593 0 2.534-2.06 4.609-4.498 4.609zm2.519-3.454c-.138-.07-8.172-4.041-.231-4.123-.058-.083-.1-.125-.198-.125-.098 0-.256.037-.39.183-.134.146-.513.501-.513 1.222s.527 1.417.6 1.514c.074.098 1.036 1.583 2.51 2.221.351.152.625.243.839.311.353.112.674.096.928.058.283-.042.871-.356.994-.7s.123-.64.086-.702c-.037-.063-.138-.1-.276-.17z"/></svg>
                    <span>WhatsApp Dahira</span>
                </a>
                <a href="tel:+221780196650" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-200 transition-colors" title="Appeler le 78 019 66 50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>Appeler</span>
                </a>
            </div>

            <p class="text-[10px] text-slate-400 mt-4">&copy; <?= date('Y') ?> Dahira AKR — Tous droits réservés</p>
        </div>

    </div>

</main>

<!-- ========================================================= -->
<!-- MODALES INTERACTIVES                                      -->
<!-- ========================================================= -->

<!-- 1. MODAL MODIFICATION DU PROFIL -->
<?php if ($user): ?>
<div id="modal-profil" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
    <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 max-w-md w-full shadow-2xl my-auto animate-fade-in border border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-primary-50 dark:bg-primary-950/50 text-primary-900 dark:text-blue-400 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white text-base">Modifier mon Profil</h3>
            </div>
            <button onclick="closeModal('modal-profil')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="/pages/parametres.php" method="POST" enctype="multipart/form-data" class="space-y-3.5">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <!-- Photo Preview & File Input -->
            <div class="flex items-center gap-3.5 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700">
                <div class="shrink-0 relative">
                    <?php if ($userPhoto): ?>
                        <img id="profil-preview-img" src="<?= e($userPhoto) ?>" class="w-14 h-14 rounded-xl object-cover ring-2 ring-primary-500">
                    <?php else: ?>
                        <div id="profil-preview-img" class="w-14 h-14 rounded-xl bg-primary-700 text-white flex items-center justify-center font-bold text-xl ring-2 ring-primary-500">
                            <?= $initiale ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">Photo de profil</label>
                    <input type="file" name="photo_membre" accept="image/png, image/jpeg, image/webp" onchange="previewProfilePhoto(event)"
                           class="block w-full text-[11px] text-slate-500 dark:text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-900 file:text-white hover:file:bg-primary-800 cursor-pointer">
                    <p class="text-[10px] text-slate-400 mt-0.5">JPG, PNG ou WebP (max 4 Mo)</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Prénom</label>
                    <input type="text" name="prenom" value="<?= e($user['prenom'] ?? '') ?>" placeholder="Ex: Aminata"
                           class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" required value="<?= e($user['nom'] ?? '') ?>" placeholder="Ex: Mané"
                           class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Numéro de Téléphone</label>
                <input type="tel" name="telephone" value="<?= e($user['telephone'] ?? '') ?>" placeholder="Ex: 77 445 99 22"
                       class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Adresse de résidence</label>
                <input type="text" name="adresse" value="<?= e($user['adresse'] ?? '') ?>" placeholder="Ex: Médina Rue 6, Dakar"
                       class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="button" onclick="closeModal('modal-profil')" class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-primary-900 hover:bg-primary-800 active:scale-95 text-xs font-bold text-white shadow-md transition-all">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL SÉCURITÉ / MOT DE PASSE -->
<div id="modal-password" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
    <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 max-w-md w-full shadow-2xl my-auto animate-fade-in border border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white text-base">Changer mon Mot de Passe</h3>
            </div>
            <button onclick="closeModal('modal-password')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="/pages/parametres.php" method="POST" class="space-y-3.5">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mot de passe actuel <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="password" name="ancien_password" id="param-ancien-password" required placeholder="••••••••"
                           class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none pr-9">
                    <button type="button" onclick="togglePasswordVisibility('param-ancien-password', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg focus:outline-none transition-colors">
                        <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nouveau mot de passe (min 6 caractères) <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="password" name="nouveau_password" id="param-nouveau-password" minlength="6" required placeholder="••••••••"
                           class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none pr-9">
                    <button type="button" onclick="togglePasswordVisibility('param-nouveau-password', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg focus:outline-none transition-colors">
                        <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirmer le nouveau mot de passe <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="password" name="confirmation_password" id="param-confirm-password" minlength="6" required placeholder="••••••••"
                           class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none pr-9">
                    <button type="button" onclick="togglePasswordVisibility('param-confirm-password', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg focus:outline-none transition-colors">
                        <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="button" onclick="closeModal('modal-password')" class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-primary-900 hover:bg-primary-800 active:scale-95 text-xs font-bold text-white shadow-md transition-all">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL CARTE DE MEMBRE OFFICIELLE EXACTE (523x745px) -->
<?php if (($user['statut_adhesion'] ?? '') === 'membre'): ?>
<div id="carte-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 overflow-y-auto">
    <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-6 max-w-lg w-full flex flex-col items-center shadow-2xl my-auto animate-fade-in border border-slate-100 dark:border-slate-800">
        <!-- Header Modal -->
        <div class="w-full flex items-center justify-between pb-3 mb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm md:text-base">Carte de Membre Officielle</h3>
            </div>
            <button onclick="closeCarteModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Conteneur Carte Responsive -->
        <div class="w-full flex justify-center py-2 overflow-hidden" id="carte-scaler-wrapper">
            <div id="carte-scaler" style="transform-origin: top center; transition: transform 0.2s ease;">
                <!-- RENDU EXACT DE LA CARTE DE MEMBRE (523px x 745px) -->
                <div id="carte-membre-render" class="relative rounded-2xl shadow-xl overflow-hidden" 
                     style="width: 523px; height: 745px; min-width: 523px; min-height: 745px; background: url('/assets/carte_membre_template.png') no-repeat center center; background-size: 523px 745px;">
                  
                    <!-- Champs de données du membre -->
                    <div style="position: absolute; left: 41px; top: 371px; display: flex; align-items: baseline; gap: 12px; line-height: 1;">
                        <span style="font-family: 'Montserrat', 'Arial Black', sans-serif; font-size: 21px; font-weight: 900; color: #111827; letter-spacing: -0.2px;">NOM:</span>
                        <span style="font-family: 'Outfit', 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;"><?= e($nomCarte) ?></span>
                    </div>

                    <div style="position: absolute; left: 41px; top: 423px; display: flex; align-items: baseline; gap: 12px; line-height: 1;">
                        <span style="font-family: 'Montserrat', 'Arial Black', sans-serif; font-size: 21px; font-weight: 900; color: #111827; letter-spacing: -0.2px;">PRENOM:</span>
                        <span style="font-family: 'Outfit', 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;"><?= e($prenomCarte) ?></span>
                    </div>

                    <div style="position: absolute; left: 41px; top: 476px; display: flex; align-items: baseline; gap: 12px; line-height: 1;">
                        <span style="font-family: 'Montserrat', 'Arial Black', sans-serif; font-size: 21px; font-weight: 900; color: #111827; letter-spacing: -0.2px;">FONCTION:</span>
                        <span style="font-family: 'Outfit', 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;"><?= e($fonctionCarte) ?></span>
                    </div>

                    <div style="position: absolute; left: 41px; top: 528px; display: flex; align-items: baseline; gap: 12px; line-height: 1;">
                        <span style="font-family: 'Montserrat', 'Arial Black', sans-serif; font-size: 21px; font-weight: 900; color: #111827; letter-spacing: -0.2px;">TELEPHONE:</span>
                        <span style="font-family: 'Outfit', 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a; font-feature-settings: 'tnum';"><?= e($telephoneCarte) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bouton d'Action Téléchargement PDF -->
        <div class="w-full mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button id="btn-download-pdf" onclick="downloadCartePdf()" class="w-full py-3 px-4 rounded-xl bg-primary-900 hover:bg-primary-800 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition-all active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Télécharger la Carte (PDF HD)</span>
            </button>
        </div>
    </div>
</div>
<!-- Librairie html2pdf pour export vectoriel HD -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<?php endif; ?>
<?php endif; ?>

<!-- ========================================================= -->
<!-- JAVASCRIPT & LOGIQUE D'INTERACTION                        -->
<!-- ========================================================= -->
<script>
// Toggle Mode Sombre avec animation
function handleDarkToggle(btn) {
    const isDark = toggleDarkMode();
    if (btn) {
        btn.className = `relative inline-flex items-center w-12 h-7 rounded-full p-0.5 transition-colors duration-300 focus:outline-none ${isDark ? 'bg-primary-900' : 'bg-slate-200 dark:bg-slate-600'}`;
        btn.setAttribute('aria-checked', isDark ? 'true' : 'false');
        const dot = btn.querySelector('span');
        if (dot) {
            dot.className = `pointer-events-none flex items-center justify-center w-6 h-6 rounded-full bg-white shadow-md transform transition-transform duration-300 ${isDark ? 'translate-x-5 text-primary-900' : 'translate-x-0 text-amber-500'}`;
            dot.innerHTML = isDark 
                ? '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>'
                : '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>';
        }
    }
}

// Gestion des modales
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

// Prévisualisation de la photo sélectionnée
function previewProfilePhoto(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('profil-preview-img');
        if (preview) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                const newImg = document.createElement('img');
                newImg.id = 'profil-preview-img';
                newImg.src = e.target.result;
                newImg.className = 'w-14 h-14 rounded-xl object-cover ring-2 ring-primary-500';
                preview.parentNode.replaceChild(newImg, preview);
            }
        }
    };
    reader.readAsDataURL(file);
}

// Gestion de la Carte de Membre Officielle
function openCarteModal() {
    const modal = document.getElementById('carte-modal');
    if (!modal) {
        window.location.href = '/pages/accueil.php#carte';
        return;
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    scaleCarteRender();
}

function closeCarteModal() {
    closeModal('carte-modal');
}

function scaleCarteRender() {
    const wrapper = document.getElementById('carte-scaler-wrapper');
    const scaler = document.getElementById('carte-scaler');
    if (!wrapper || !scaler) return;

    const availableWidth = wrapper.clientWidth - 16;
    const naturalWidth = 523;

    if (availableWidth < naturalWidth) {
        const scale = Math.max(0.48, availableWidth / naturalWidth);
        scaler.style.transform = `scale(${scale})`;
        scaler.style.transformOrigin = 'top center';
        wrapper.style.height = `${745 * scale}px`;
    } else {
        scaler.style.transform = 'none';
        wrapper.style.height = 'auto';
    }
}

window.addEventListener('resize', scaleCarteRender);

function downloadCartePdf() {
    const element = document.getElementById('carte-membre-render');
    const btn = document.getElementById('btn-download-pdf');
    const originalHtml = btn ? btn.innerHTML : '';
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span> <span>Génération...</span>';
    }

    const clone = element.cloneNode(true);
    clone.style.position = 'fixed';
    clone.style.left = '-9999px';
    clone.style.top = '0';
    clone.style.transform = 'none';
    document.body.appendChild(clone);

    const opt = {
        margin:       0,
        filename:     'Carte_Membre_<?= e($nomCarte) ?>_<?= e($prenomCarte) ?>.pdf',
        image:        { type: 'jpeg', quality: 1.0 },
        html2canvas:  { scale: 2.5, useCORS: true, logging: false },
        jsPDF:        { unit: 'px', format: [523, 745], orientation: 'portrait' }
    };

    if (typeof html2pdf !== 'undefined') {
        html2pdf().set(opt).from(clone).save().then(() => {
            clone.remove();
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            if (typeof showToast === 'function') {
                showToast('Carte téléchargée avec succès !');
            }
        }).catch(err => {
            clone.remove();
            console.error('Erreur PDF:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            if (typeof showToast === 'function') {
                showToast('Erreur lors du téléchargement du PDF.');
            }
        });
    } else {
        clone.remove();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
        alert('Module PDF en cours de chargement. Veuillez réessayer dans un instant.');
    }
}



// Détection PWA Install
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPrompt = e;
    const btn = document.getElementById('pwa-install-btn');
    if (btn) btn.style.display = 'flex';
});

// Écouter hash URL pour ouverture directe de modale
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#carte') {
        openCarteModal();
    } else if (window.location.hash === '#profil') {
        openModal('modal-profil');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
