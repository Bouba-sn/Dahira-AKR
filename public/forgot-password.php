<?php
// public/forgot-password.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PasswordResetService;

if (isLoggedIn()) {
    header('Location: /pages/accueil.php');
    exit;
}

$error = '';
$emailInput = trim($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez saisir une adresse email valide.';
        } else {
            try {
                $service = new PasswordResetService(db());
                $user = $service->findUserByEmail($email);

                if (!$user) {
                    $error = "Aucun compte n'est associé à l'adresse email {$email}.";
                } else {
                    $code = $service->createResetCode($email);
                    $_SESSION['reset_email'] = $email;
                    header('Location: /public/reset-password.php?sent=1');
                    exit;
                }
            } catch (\Throwable $e) {
                $error = "Une erreur est survenue lors de l'envoi du code. Veuillez réessayer.";
            }
        }
    }
}

$pageTitle = 'Mot de passe oublié';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="min-h-screen bg-gradient-to-b from-primary-900 via-primary-800 to-primary-700 flex flex-col">
    <!-- Header avec Logo -->
    <div class="flex flex-col items-center pt-10 pb-5 px-4">
        <div class="w-24 h-24 rounded-2xl flex items-center justify-center overflow-hidden mb-3 logo-container">
            <img src="/assets/uploads/20.png" alt="Logo" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
        </div>
        <h1 class="text-white text-base font-bold">Dahira AKR</h1>
        <p class="text-white/60 text-[11px] mt-0.5">بسم الله الرحمن الرحيم</p>
    </div>

    <!-- Carte formulaire -->
    <div class="flex-1 bg-white dark:bg-slate-900 rounded-t-3xl px-4 pt-6 pb-12 max-w-lg mx-auto w-full shadow-2xl">
        <div class="mb-5">
            <a href="/public/login.php" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors mb-2 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span>Retour à la connexion</span>
            </a>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Mot de passe oublié</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Indiquez l'adresse email de votre compte. Nous vous enverrons immédiatement un code de vérification à 6 chiffres pour définir un nouveau mot de passe.
            </p>
        </div>

        <?php if ($error): ?>
        <div class="mb-4 p-3.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-xs sm:text-sm flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="text-xs font-semibold text-slate-700 dark:text-slate-200 block mb-1.5">Adresse email de votre compte</label>
                <div class="relative">
                    <input type="email" name="email" required autocomplete="email" autofocus
                           value="<?= e($emailInput) ?>"
                           placeholder="votre.email@exemple.sn"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary-900 hover:bg-primary-800 text-white rounded-xl py-3.5 font-bold text-xs sm:text-sm shadow-md hover:shadow-lg active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                <span>Envoyer le code de vérification</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 text-center">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Vous vous souvenez de votre mot de passe ?
                <a href="/public/login.php" class="text-primary-700 dark:text-blue-400 font-bold hover:underline ml-1">Se connecter</a>
            </p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
