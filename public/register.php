<?php
// public/register.php
// Session & Auth logic BEFORE any output
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (isLoggedIn()) { 
    header('Location: /pages/accueil.php'); 
    exit; 
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token invalide.';
    } else {
        $nom      = sanitize($_POST['nom'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (strlen($nom) < 2) {
            $error = 'Le nom doit faire au moins 2 caractères.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif (strlen($password) < 6) {
            $error = 'Le mot de passe doit faire au moins 6 caractères.';
        } elseif ($password !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            $pdo = db();
            $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$nom, $email, $hash]);
                $success = true;
            }
        }
    }
}

// Now include header for HTML output
$pageTitle = 'Inscription';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="min-h-screen bg-gradient-to-b from-primary-900 via-primary-800 to-primary-700 flex flex-col">
    <!-- Logo -->
    <div class="flex flex-col items-center pt-10 pb-5 px-4">
        <div class="w-32 h-32 rounded-2xl flex items-center justify-center overflow-hidden mb-4 logo-container">
            <img src="/assets/uploads/20.png" alt="Logo" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='/assets/uploads/20.png'">
        </div>
        <h1 class="text-white text-base font-bold">Dahira AKR</h1>
    </div>

    <!-- Carte -->
    <div class="flex-1 bg-white dark:bg-slate-900 rounded-t-3xl px-4 pt-6 pb-10">
        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-1">Créer un compte</h2>
        <p class="text-sm text-slate-400 mb-5">Rejoignez la communauté Dahira AKR</p>

        <?php if ($success): ?>
        <div class="text-center py-8 fade-in-up">
            <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2">Compte créé avec succès !</h3>
            <p class="text-sm text-slate-400 mb-5">Vous pouvez maintenant vous connecter.</p>
            <a href="/public/login.php" class="inline-block bg-primary-900 text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm hover:shadow-md active:shadow-md transition-all">
                Se connecter
            </a>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Nom complet</label>
                <input type="text" name="nom" required value="<?= e($_POST['nom'] ?? '') ?>"
                       placeholder="Votre nom complet"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Adresse email</label>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"
                       placeholder="votre@email.com"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Mot de passe</label>
                <div class="relative">
                    <input type="password" name="password" id="register-password" required placeholder="Minimum 6 caractères"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500 pr-10">
                    <button type="button" onclick="togglePasswordVisibility('register-password', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-colors">
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

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Confirmer le mot de passe</label>
                <div class="relative">
                    <input type="password" name="confirm" id="register-confirm" required placeholder="Répétez le mot de passe"
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500 pr-10">
                    <button type="button" onclick="togglePasswordVisibility('register-confirm', this)" aria-label="Afficher le mot de passe" title="Afficher le mot de passe" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-colors">
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
                Créer mon compte
            </button>
        </form>

        <div class="text-center mt-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Déjà inscrit ?
                <a href="/public/login.php" class="text-primary-700 dark:text-blue-400 font-semibold ml-1">Se connecter</a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
