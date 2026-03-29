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
        <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center mb-3">
            <span class="text-2xl">🕌</span>
        </div>
        <h1 class="text-white text-base font-bold">Dahira AKR</h1>
    </div>

    <!-- Carte -->
    <div class="flex-1 bg-white dark:bg-slate-900 rounded-t-3xl px-4 pt-6 pb-10">
        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-1">Créer un compte</h2>
        <p class="text-sm text-slate-400 mb-5">Rejoignez la communauté Dahira AKR</p>

        <?php if ($success): ?>
        <div class="text-center py-8 fade-in-up">
            <span class="text-4xl block mb-3">✅</span>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2">Compte créé avec succès !</h3>
            <p class="text-sm text-slate-400 mb-5">Vous pouvez maintenant vous connecter.</p>
            <a href="/public/login.php" class="inline-block bg-primary-900 text-white px-6 py-2.5 rounded-xl text-sm font-medium">
                Se connecter
            </a>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl text-red-600 dark:text-red-400 text-sm">
            ⚠️ <?= e($error) ?>
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
                <input type="password" name="password" required placeholder="Minimum 6 caractères"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-1.5">Confirmer le mot de passe</label>
                <input type="password" name="confirm" required placeholder="Répétez le mot de passe"
                       class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-primary-500">
            </div>

            <button type="submit" class="w-full bg-primary-900 text-white rounded-xl py-3.5 font-semibold text-sm mt-2">
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
