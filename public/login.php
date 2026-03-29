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
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare("SELECT * FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['role']     = $user['role'];

            $redirect = $_GET['redirect'] ?? ($user['role'] === 'admin' ? '/admin/dashboard.php' : '/pages/accueil.php');
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
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
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center overflow-hidden mb-3">
            <img src="/assets/uploads/1.png" alt="Logo" class="w-full h-full object-contain mix-blend-screen" onerror="this.onerror=null; this.src='/assets/uploads/1.jpg'">
        </div>
        <h1 class="text-white text-lg font-bold">Dahira AKR</h1>
        <p class="text-white/60 text-xs mt-1">بسم الله الرحمن الرحيم</p>
    </div>

    <!-- Carte connexion -->
    <div class="flex-1 bg-white dark:bg-slate-900 rounded-t-3xl px-4 pt-6 pb-10">
        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-1">Connexion</h2>
        <p class="text-sm text-slate-400 mb-6">Bienvenue ! Connectez-vous à votre compte.</p>

        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-sm">
            ⚠️ <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Adresse email</label>
                <input type="email" name="email" required autocomplete="email"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       placeholder="votre@email.com"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Mot de passe</label>
                <div class="relative">
                    <input type="password" name="password" id="password-input" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500 pr-10">
                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-slate-400">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary-900 text-white rounded-xl py-3.5 font-semibold text-sm mt-2">
                Se connecter
            </button>
        </form>

        <div class="text-center mt-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Pas encore de compte ?
                <a href="/public/register.php" class="text-primary-700 dark:text-blue-400 font-semibold ml-1">S'inscrire</a>
            </p>
        </div>

        <div class="mt-5 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
            <p class="text-xs text-blue-700 dark:text-blue-300 font-semibold mb-1">💡 Compte démo</p>
            <p class="text-xs text-blue-600 dark:text-blue-400">Admin: admin@dahira.sn / password</p>
            <p class="text-xs text-blue-600 dark:text-blue-400">User: user@dahira.sn / password</p>
        </div>
    </div>
</main>

<script>
function togglePassword() {
    const input = document.getElementById('password-input');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
