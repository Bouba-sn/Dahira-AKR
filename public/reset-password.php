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

$email = trim(strtolower($_SESSION['reset_email'] ?? ($_GET['email'] ?? ($_POST['email'] ?? ''))));

if (empty($email)) {
    header('Location: /public/forgot-password.php');
    exit;
}

$error = '';
$infoMsg = '';

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $infoMsg = "Un code de vérification à 6 chiffres a été envoyé à votre adresse email.";
}

$service = new PasswordResetService(db());

// Action : Renvoyer un nouveau code
if (isset($_POST['action']) && $_POST['action'] === 'resend_code') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        try {
            $newCode = $service->createResetCode($email);
            if ($newCode) {
                $infoMsg = "Un nouveau code de vérification vient d'être envoyé à {$email}.";
            } else {
                $error = "Impossible de renvoyer le code. Adresse email introuvable.";
            }
        } catch (\Throwable $e) {
            $error = "Erreur lors du renvoi du code.";
        }
    }
}

// Action : Valider le code et réinitialiser le mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'reset_password')) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $cleanCode = preg_replace('/\D/', '', $code);

        if (empty($cleanCode) || strlen($cleanCode) !== 6) {
            $error = 'Veuillez saisir le code de vérification à 6 chiffres reçu par email.';
        } elseif (strlen($password) < 6) {
            $error = 'Le nouveau mot de passe doit comporter au moins 6 caractères.';
        } elseif ($password !== $confirm) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            try {
                $success = $service->resetPassword($email, $cleanCode, $password);
                if ($success) {
                    unset($_SESSION['reset_email']);
                    header('Location: /public/login.php?reset=success');
                    exit;
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Réinitialisation du mot de passe';
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
        <div class="mb-5">
            <a href="/public/forgot-password.php" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors mb-2 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span>Changer d'email</span>
            </a>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Nouveau mot de passe</h2>
            
            <div class="mt-2 p-3 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-100 dark:border-slate-700/80 flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="text-xs text-slate-700 dark:text-slate-300 truncate font-semibold"><?= e($email) ?></span>
                </div>
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-md shrink-0">15 min</span>
            </div>
        </div>

        <?php if ($infoMsg): ?>
        <div class="mb-4 p-3.5 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 rounded-xl text-sky-800 dark:text-sky-300 text-xs sm:text-sm font-medium flex items-center gap-2.5">
            <svg class="w-4 h-4 text-sky-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?= e($infoMsg) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-4 p-3.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-xs sm:text-sm flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="email" value="<?= e($email) ?>">

            <!-- Code de vérification 6 chiffres -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-200">Code de vérification (6 chiffres) *</label>
                    <span class="text-[10px] text-slate-400">Reçu par email</span>
                </div>
                <input type="text" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus
                       value="<?= e($_POST['code'] ?? '') ?>"
                       placeholder="••••••"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-lg font-bold text-center tracking-[8px] text-slate-900 dark:text-white placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
            </div>

            <!-- Nouveau mot de passe -->
            <div>
                <label class="text-xs font-semibold text-slate-700 dark:text-slate-200 block mb-1.5">Nouveau mot de passe *</label>
                <div class="relative">
                    <input type="password" name="password" id="reset-password-input" required minlength="6"
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
                <span>Mettre à jour mon mot de passe</span>
            </button>
        </form>

        <!-- Renvoyer le code -->
        <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resend_code">
                <input type="hidden" name="email" value="<?= e($email) ?>">
                <button type="submit" class="text-primary-700 dark:text-blue-400 font-semibold hover:underline">
                    🔄 Renvoyer un nouveau code
                </button>
            </form>

            <a href="/public/login.php" class="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white font-medium">
                Retour à la connexion
            </a>
        </div>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
