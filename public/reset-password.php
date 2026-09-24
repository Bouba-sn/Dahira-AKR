<?php
// public/reset-password.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PasswordResetService;

if (isLoggedIn()) {
    header('Location: /pages/accueil.php');
    exit;
}

$phone = trim($_SESSION['reset_phone'] ?? ($_GET['phone'] ?? ($_POST['phone'] ?? '')));

if (empty($phone)) {
    header('Location: /public/forgot-password.php');
    exit;
}

$error = '';
$infoMsg = '';
$waUrl = $_SESSION['reset_wa_url'] ?? '';
$userName = $_SESSION['reset_user_name'] ?? '';

$service = new PasswordResetService(db());

// Si l'URL WhatsApp n'est pas en session, tenter de la régénérer si besoin
if (empty($waUrl)) {
    $user = $service->findUserByPhone($phone);
    if ($user) {
        $norm = PasswordResetService::normalizePhone($user['telephone'] ?: $phone);
        $waPhone = $norm['international'];
        $waUrl = "https://api.whatsapp.com/send?phone=" . $waPhone;
    }
}

// Action : Réinitialiser l'étape de vérification (recommencer)
if (isset($_GET['reverify']) && $_GET['reverify'] === '1') {
    unset($_SESSION['reset_code_verified'], $_SESSION['verified_code']);
    header('Location: /public/reset-password.php');
    exit;
}

// Action : Renvoyer un nouveau code sur WhatsApp
if (isset($_POST['action']) && $_POST['action'] === 'resend_code') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        try {
            unset($_SESSION['reset_code_verified'], $_SESSION['verified_code']);
            $resetData = $service->createResetCodeForPhone($phone);
            if ($resetData) {
                $_SESSION['reset_wa_url'] = $resetData['wa_url'];
                $waUrl = $resetData['wa_url'];
                $infoMsg = "Un nouveau code de vérification à 6 chiffres a été généré pour votre WhatsApp.";
            } else {
                $error = "Impossible de renvoyer le code. Numéro de téléphone introuvable.";
            }
        } catch (\Throwable $e) {
            $error = "Erreur lors de la génération du nouveau code.";
        }
    }
}

// Action 1 : Vérifier le code WhatsApp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $cleanCode = preg_replace('/\D/', '', $code);

        if (empty($cleanCode) || strlen($cleanCode) !== 6) {
            $error = 'Veuillez saisir le code à 6 chiffres reçu sur WhatsApp.';
        } elseif (!$service->verifyPhoneCode($phone, $cleanCode)) {
            $error = 'Code de vérification incorrect ou expiré. Veuillez vérifier votre WhatsApp ou demander un nouveau code.';
        } else {
            // Code validé avec succès !
            $_SESSION['reset_code_verified'] = true;
            $_SESSION['verified_code'] = $cleanCode;
            $infoMsg = 'Code vérifié avec succès ! Vous pouvez maintenant saisir votre nouveau mot de passe.';
        }
    }
}

// Action 2 : Modifier le mot de passe (accessible uniquement après vérification du code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_new_password') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } elseif (empty($_SESSION['reset_code_verified']) || empty($_SESSION['verified_code'])) {
        $error = 'Veuillez d\'abord valider votre code de vérification.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $verifiedCode = $_SESSION['verified_code'];

        if (strlen($password) < 6) {
            $error = 'Le nouveau mot de passe doit comporter au moins 6 caractères.';
        } elseif ($password !== $confirm) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            try {
                $success = $service->resetPasswordByPhone($phone, $verifiedCode, $password);
                if ($success) {
                    unset(
                        $_SESSION['reset_phone'],
                        $_SESSION['reset_phone_normalized'],
                        $_SESSION['reset_wa_phone'],
                        $_SESSION['reset_wa_url'],
                        $_SESSION['reset_user_name'],
                        $_SESSION['reset_code_verified'],
                        $_SESSION['verified_code']
                    );
                    header('Location: /public/login.php?reset=success');
                    exit;
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// État actuel : Étape 1 (vérification code) ou Étape 2 (nouveau mot de passe)
$isCodeVerified = !empty($_SESSION['reset_code_verified']) && !empty($_SESSION['verified_code']);

if (!$isCodeVerified && isset($_GET['sent']) && $_GET['sent'] === '1' && empty($infoMsg)) {
    $infoMsg = "Un code de vérification à 6 chiffres a été généré pour votre compte WhatsApp.";
}

$pageTitle = $isCodeVerified ? 'Définir un nouveau mot de passe' : 'Vérification du code WhatsApp';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="min-h-screen bg-gradient-to-b from-primary-900 via-primary-800 to-primary-700 flex flex-col">
    <!-- Header avec Logo -->
    <div class="flex flex-col items-center pt-8 pb-4 px-4">
        <div class="w-20 h-20 rounded-2xl flex items-center justify-center overflow-hidden mb-2 logo-container">
            <img src="/assets/uploads/20.png" alt="Logo" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
        </div>
        <h1 class="text-white text-base font-bold">Dahira AKR</h1>
        <p class="text-white/60 text-[11px] mt-0.5">بسم الله الرحمن الرحيم</p>
    </div>

    <!-- Carte formulaire -->
    <div class="flex-1 bg-white dark:bg-slate-900 rounded-t-3xl px-4 pt-6 pb-12 max-w-lg mx-auto w-full shadow-2xl">
        
        <!-- Navigation haute & Statut -->
        <div class="mb-5">
            <div class="flex items-center justify-between mb-2">
                <a href="/public/forgot-password.php" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span>Changer de numéro</span>
                </a>

                <!-- Indicateur d'étape -->
                <div class="flex items-center gap-1.5 text-[11px] font-bold">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full <?= $isCodeVerified ? 'bg-emerald-600 text-white' : 'bg-primary-900 text-white' ?>">
                        <?= $isCodeVerified ? '✓' : '1' ?>
                    </span>
                    <span class="<?= $isCodeVerified ? 'text-emerald-600 font-bold' : 'text-slate-700 dark:text-slate-200' ?>">Code</span>
                    <span class="text-slate-300 dark:text-slate-600">→</span>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full <?= $isCodeVerified ? 'bg-primary-900 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400' ?>">
                        2
                    </span>
                    <span class="<?= $isCodeVerified ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400' ?>">Passe</span>
                </div>
            </div>

            <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                <?= $isCodeVerified ? 'Nouveau mot de passe' : 'Vérification du code' ?>
            </h2>
            
            <div class="mt-2 p-3 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-100 dark:border-slate-700/80 flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span class="text-xs text-slate-700 dark:text-slate-300 truncate font-semibold"><?= e($phone) ?></span>
                    <?php if (!empty($userName)): ?>
                        <span class="text-[11px] text-slate-400 truncate hidden sm:inline">(<?= e($userName) ?>)</span>
                    <?php endif; ?>
                </div>
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-md shrink-0">15 min</span>
            </div>
        </div>

        <?php if ($infoMsg): ?>
        <div class="mb-4 p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-300 text-xs sm:text-sm font-medium flex items-center gap-2.5">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?= e($infoMsg) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-4 p-3.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-xs sm:text-sm flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!$isCodeVerified): ?>
            <!-- ============================================================ -->
            <!-- ÉTAPE 1 : VÉRIFICATION DU CODE SEULEMENT                     -->
            <!-- ============================================================ -->
            
            <?php if (!empty($waUrl)): ?>
            <!-- Bannière WhatsApp pour voir le code -->
            <div class="mb-5 p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700/60 rounded-2xl shadow-xs">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.392-12.416c-5.514 0-10 4.486-10 10 0 1.764.462 3.42 1.267 4.861l-1.298 4.743 4.869-1.277c1.394.757 2.986 1.189 4.675 1.189 5.514 0 10-4.486 10-10s-4.486-10-10-10zm0 18.25c-1.547 0-3.048-.445-4.339-1.286l-.311-.202-3.238.85.864-3.155-.205-.327c-.899-1.433-1.373-3.1-1.373-4.83 0-4.549 3.701-8.25 8.252-8.25 4.548 0 8.248 3.701 8.248 8.25 0 4.551-3.7 8.25-8.25 8.25z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-xs sm:text-sm font-bold text-emerald-950 dark:text-emerald-100">Votre code WhatsApp est prêt</h3>
                        <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-0.5 leading-relaxed">
                            Le code à 6 chiffres vous attend sur WhatsApp. Cliquez sur le bouton pour l'ouvrir :
                        </p>
                        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" id="whatsapp-link" class="inline-flex items-center gap-2 mt-2.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow hover:shadow-md transition-all active:scale-[0.98]">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.392-12.416c-5.514 0-10 4.486-10 10 0 1.764.462 3.42 1.267 4.861l-1.298 4.743 4.869-1.277c1.394.757 2.986 1.189 4.675 1.189 5.514 0 10-4.486 10-10s-4.486-10-10-10zm0 18.25c-1.547 0-3.048-.445-4.339-1.286l-.311-.202-3.238.85.864-3.155-.205-.327c-.899-1.433-1.373-3.1-1.373-4.83 0-4.549 3.701-8.25 8.252-8.25 4.548 0 8.248 3.701 8.248 8.25 0 4.551-3.7 8.25-8.25 8.25z"/></svg>
                            <span>Ouvrir WhatsApp</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="verify_code">
                <input type="hidden" name="phone" value="<?= e($phone) ?>">

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-slate-700 dark:text-slate-200">Saisissez le code de vérification (6 chiffres) *</label>
                        <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Reçu sur WhatsApp</span>
                    </div>
                    <input type="text" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus
                           value="<?= e($_POST['code'] ?? '') ?>"
                           placeholder="••••••"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3.5 text-xl font-bold text-center tracking-[10px] text-slate-900 dark:text-white placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-3.5 font-bold text-xs sm:text-sm shadow-md hover:shadow-lg active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>Vérifier le code</span>
                </button>
            </form>

            <!-- Renvoyer le code sur WhatsApp -->
            <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <form method="POST" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend_code">
                    <input type="hidden" name="phone" value="<?= e($phone) ?>">
                    <button type="submit" class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline inline-flex items-center gap-1.5">
                        <span>🔄 Renvoyer un nouveau code sur WhatsApp</span>
                    </button>
                </form>

                <a href="/public/login.php" class="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white font-medium">
                    Retour à la connexion
                </a>
            </div>

        <?php else: ?>
            <!-- ============================================================ -->
            <!-- ÉTAPE 2 : CODE VÉRIFIÉ -> CHAMPS DE NOUVEAU MOT DE PASSE      -->
            <!-- ============================================================ -->
            
            <div class="mb-5 p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700/60 rounded-xl flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                    <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0">✓</div>
                    <span>Code vérifié avec succès pour ce numéro</span>
                </div>
                <a href="/public/reset-password.php?reverify=1" class="text-[11px] text-slate-500 hover:text-slate-700 dark:text-slate-400 underline shrink-0">
                    Changer de code
                </a>
            </div>

            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_new_password">
                <input type="hidden" name="phone" value="<?= e($phone) ?>">

                <!-- Nouveau mot de passe -->
                <div>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-200 block mb-1.5">Nouveau mot de passe *</label>
                    <div class="relative">
                        <input type="password" name="password" id="reset-password-input" required minlength="6" autofocus
                               placeholder="Minimum 6 caractères"
                               class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 pr-10">
                        <button type="button" onclick="togglePasswordVisibility('reset-password-input', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-colors">
                            <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Confirmer mot de passe -->
                <div>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-200 block mb-1.5">Confirmer le nouveau mot de passe *</label>
                    <div class="relative">
                        <input type="password" name="confirm" id="reset-confirm-input" required minlength="6"
                               placeholder="Répétez le mot de passe"
                               class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 pr-10">
                        <button type="button" onclick="togglePasswordVisibility('reset-confirm-input', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-colors">
                            <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary-900 hover:bg-primary-800 text-white rounded-xl py-3.5 font-bold text-xs sm:text-sm shadow-md hover:shadow-lg active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>Enregistrer mon nouveau mot de passe</span>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 text-center">
                <a href="/public/login.php" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white font-medium">
                    Retour à la connexion
                </a>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
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
            } else {
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
                btn.setAttribute('title', 'Afficher le mot de passe');
            }
        }
    }
}

// Déclenchement automatique de WhatsApp au premier chargement si sent=1
<?php if (!$isCodeVerified && isset($_GET['sent']) && $_GET['sent'] === '1' && !empty($waUrl)): ?>
window.addEventListener('DOMContentLoaded', () => {
    try {
        const waLink = document.getElementById('whatsapp-link');
        if (waLink && !sessionStorage.getItem('wa_opened_<?= md5($waUrl) ?>')) {
            sessionStorage.setItem('wa_opened_<?= md5($waUrl) ?>', '1');
            window.open(<?= json_encode($waUrl) ?>, '_blank');
        }
    } catch (e) {}
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
