<?php
// public/login.php
// Session & Auth logic BEFORE any output
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (isLoggedIn()) { 
    header('Location: /pages/accueil.php'); 
    exit; 
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token invalide.';
    } else {
        $loginInput = sanitize($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';

        $digits = preg_replace('/\D/', '', $loginInput);
        $cleanSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '+', ''), '.', ''), '/', '')";

        $stmt = db()->prepare("
            SELECT * FROM utilisateurs 
            WHERE email = ? 
               OR telephone = ? 
               OR (LENGTH(?) >= 7 AND ({$cleanSql} = ? OR {$cleanSql} LIKE ?))
            LIMIT 1
        ");
        $stmt->execute([
            $loginInput, 
            $loginInput, 
            $digits, 
            $digits, 
            '%' . (strlen($digits) >= 9 ? substr($digits, -9) : $digits)
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['statut_adhesion'] = $user['statut_adhesion'] ?? 'non_membre';

            $redirect = $_GET['redirect'] ?? ($user['role'] === 'admin' ? '/admin/dashboard.php' : '/pages/accueil.php');
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    }
}

// Now include header for HTML output
$pageTitle = 'Connexion';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="min-h-screen bg-gradient-to-b from-primary-900 via-primary-800 to-primary-700 flex flex-col">
    <!-- Logo -->
    <div class="flex flex-col items-center pt-12 pb-6 px-4">
        <div class="w-36 h-36 rounded-2xl flex items-center justify-center overflow-hidden mb-4 logo-container">
            <img src="/assets/uploads/20.png" alt="Logo" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
        </div>
        <h1 class="text-white text-lg font-bold">Dahira AKR</h1>
        <p class="text-white/60 text-xs mt-1">بسم الله الرحمن الرحيم</p>
    </div>

    <!-- Carte connexion -->
    <div class="flex-1 bg-white dark:bg-slate-900 rounded-t-3xl px-4 pt-6 pb-10">
        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-1">Connexion</h2>
        <p class="text-sm text-slate-400 mb-6">Bienvenue ! Connectez-vous à votre compte.</p>

        <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
        <div class="mb-4 p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-300 text-xs sm:text-sm font-medium flex items-center gap-2.5 shadow-xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span>Votre mot de passe a été réinitialisé avec succès ! Vous pouvez maintenant vous connecter.</span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Adresse email ou Téléphone</label>
                <input type="text" name="email" required autocomplete="username"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       placeholder="votre@email.com ou 77 123 45 67"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Mot de passe</label>
                    <a href="/public/forgot-password.php" class="text-xs text-primary-700 dark:text-blue-400 font-semibold hover:underline">
                        Mot de passe oublié ?
                    </a>
                </div>
                <div class="relative">
                    <input type="password" name="password" id="password-input" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500 pr-10">
                    <button type="button" onclick="togglePasswordVisibility('password-input', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-colors">
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

            <button type="submit" class="w-full bg-primary-900 text-white rounded-xl py-3.5 font-semibold text-sm mt-2 shadow-sm hover:shadow-md active:shadow-md transition-all">
                Se connecter
            </button>
        </form>

        <div class="text-center mt-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Pas encore de compte ?
                <a href="/public/register.php" class="text-primary-700 dark:text-blue-400 font-semibold ml-1">S'inscrire</a>
            </p>
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
                btn.setAttribute('aria-label', 'Masquer le mot de passe');
            } else {
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
                btn.setAttribute('title', 'Afficher le mot de passe');
                btn.setAttribute('aria-label', 'Afficher le mot de passe');
            }
        }
    }
}
function togglePassword() {
    togglePasswordVisibility('password-input', document.querySelector('#password-input + button'));
}
</script>
</body>
</html>
