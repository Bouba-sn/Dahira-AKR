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
$phoneInput = trim($_POST['telephone'] ?? ($_SESSION['reset_phone'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $phone = trim($_POST['telephone'] ?? '');
        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits) || strlen($digits) < 7) {
            $error = 'Veuillez saisir un numéro de téléphone valide.';
        } else {
            try {
                $service = new PasswordResetService(db());
                $resetData = $service->createResetCodeForPhone($phone);

                if (!$resetData) {
                    $error = "Aucun compte n'est associé au numéro de téléphone " . htmlspecialchars($phone) . ".";
                } else {
                    $_SESSION['reset_phone'] = $phone;
                    $_SESSION['reset_phone_normalized'] = $resetData['clean_phone'];
                    $_SESSION['reset_wa_phone'] = $resetData['wa_phone'];
                    $_SESSION['reset_wa_url'] = $resetData['wa_url'];
                    $_SESSION['reset_user_name'] = trim(($resetData['user']['prenom'] ?? '') . ' ' . ($resetData['user']['nom'] ?? ''));
                    unset($_SESSION['reset_code_verified'], $_SESSION['verified_code']);
                    
                    header('Location: /public/reset-password.php?sent=1');
                    exit;
                }
            } catch (\Throwable $e) {
                $error = "Une erreur est survenue lors de la génération du code. Veuillez réessayer.";
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
                Indiquez le numéro de téléphone de votre compte. Un code de vérification à 6 chiffres sera généré et envoyé directement sur votre <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">WhatsApp</strong> pour réinitialiser votre mot de passe.
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
                <label class="text-xs font-semibold text-slate-700 dark:text-slate-200 block mb-1.5">Numéro de téléphone (WhatsApp)</label>
                <div class="relative">
                    <input type="tel" name="telephone" required autocomplete="tel" autofocus
                           value="<?= e($phoneInput) ?>"
                           placeholder="ex : 77 123 45 67 ou +221 77 473 14 93"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    <svg class="w-4 h-4 text-emerald-600 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5 flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                    Le code sera envoyé sur WhatsApp associé à ce numéro
                </p>
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-3.5 font-bold text-xs sm:text-sm shadow-md hover:shadow-lg active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                <!-- WhatsApp SVG icon -->
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.392-12.416c-5.514 0-10 4.486-10 10 0 1.764.462 3.42 1.267 4.861l-1.298 4.743 4.869-1.277c1.394.757 2.986 1.189 4.675 1.189 5.514 0 10-4.486 10-10s-4.486-10-10-10zm0 18.25c-1.547 0-3.048-.445-4.339-1.286l-.311-.202-3.238.85.864-3.155-.205-.327c-.899-1.433-1.373-3.1-1.373-4.83 0-4.549 3.701-8.25 8.252-8.25 4.548 0 8.248 3.701 8.248 8.25 0 4.551-3.7 8.25-8.25 8.25z"/></svg>
                <span>Envoyer le code sur WhatsApp</span>
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
