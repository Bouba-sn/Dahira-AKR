<?php
// admin/utilisateurs.php
$pageTitle = 'Membres';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = db();
$msg = '';

// Changer le rôle
if (isset($_GET['toggle_role']) && is_numeric($_GET['toggle_role'])) {
    $uid = (int)$_GET['toggle_role'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $cur = $pdo->prepare("SELECT role FROM utilisateurs WHERE id=?");
        $cur->execute([$uid]);
        $curRole = $cur->fetchColumn();
        $newRole = $curRole === 'admin' ? 'user' : 'admin';
        $pdo->prepare("UPDATE utilisateurs SET role=? WHERE id=?")->execute([$newRole, $uid]);
        $msg = "✅ Rôle mis à jour en $newRole.";
    }
}

// Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$uid]);
        $msg = '✅ Membre supprimé.';
    }
}

$search = sanitize($_GET['q'] ?? '');
$where  = $search ? "WHERE nom LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];
$stmt   = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM commandes c WHERE c.user_id=u.id) as nb_commandes FROM utilisateurs u $where ORDER BY u.created_at DESC");
$stmt->execute($params);
$membres = $stmt->fetchAll();
?>

<!-- TOP BAR -->
<div class="sticky top-0 z-40 bg-primary-900 text-white px-4">
    <div class="flex items-center h-14 gap-3">
        <a href="/admin/dashboard.php" class="w-8 h-8 flex items-center justify-center text-white/70">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1 class="font-bold text-sm flex-1">Membres 👥</h1>
        <span class="text-xs opacity-70"><?= count($membres) ?></span>
    </div>
</div>

<main class="pb-6 bg-slate-50 dark:bg-slate-950 min-h-screen px-4">

    <?php if ($msg): ?>
    <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-xl text-green-700 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- RECHERCHE -->
    <div class="mt-4">
        <form method="GET" class="relative">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher un membre..."
                   class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl pl-9 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none">
            <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </form>
    </div>

    <!-- LISTE -->
    <div class="mt-3 space-y-2">
    <?php foreach ($membres as $m): ?>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full <?= $m['role'] === 'admin' ? 'bg-gold-400 dark:bg-yellow-600' : 'bg-primary-900' ?> flex items-center justify-center text-white font-bold flex-shrink-0">
            <?= strtoupper(substr($m['nom'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($m['nom']) ?></p>
                <?php if ($m['role'] === 'admin'): ?>
                <span class="text-[9px] bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-full font-bold">ADMIN</span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-400 truncate"><?= e($m['email']) ?></p>
            <p class="text-xs text-slate-400">Inscrit le <?= date('d/m/Y', strtotime($m['created_at'])) ?> · <?= $m['nb_commandes'] ?> commande(s)</p>
        </div>
        <div class="flex gap-1 flex-shrink-0">
            <?php if ($m['id'] != $_SESSION['user_id']): ?>
            <a href="?toggle_role=<?= $m['id'] ?>" title="Changer le rôle"
               class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center text-xs">👑</a>
            <a href="?delete=<?= $m['id'] ?>" onclick="return confirm('Supprimer ce membre ?')"
               class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center text-xs">🗑️</a>
            <?php else: ?>
            <span class="text-[10px] text-slate-300 px-2">Vous</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($membres)): ?>
    <div class="text-center py-10 text-slate-400">
        <span class="text-3xl block mb-2">👤</span>
        <p class="text-sm">Aucun membre trouvé</p>
    </div>
    <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
